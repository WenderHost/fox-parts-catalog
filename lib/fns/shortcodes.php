<?php

namespace FoxParts\shortcodes;

/**
 * Handles display of FOXSelect on a page.
 *
 * @param      array  $atts{
 *    @type string $part_number Fox part number
 * }
 *
 * @return     string  HTML DOM object to which FOXSelect attaches.
 */
function foxselect( $atts ){
  static $called = false;
  if( $called ){
    error_log('foxselect shortcode has been called. returning...');
    return;
  }

  if( ! $called )
    $called = true;

  $args = shortcode_atts([
    'part_number' => null,
    'autoload' => false,
  ], $atts );

  wp_enqueue_style( 'foxselect-theme' );

  // Enqueue FOXSelect React App JS
  $scripts = glob( plugin_dir_path( __FILE__ ) . '../foxselect/static/js/main.*' );
  $x = 0;
  $foxselect_last_script = 0;
  foreach( $scripts as $script ){
    if( '.js' == substr( $script, -3 ) ){
      wp_enqueue_script( 'foxselect-' . $x );
      $foxselect_last_script = $x;
      $x++;
    }
  }
  // Enqueue FOXSelect React App CSS
  $styles = glob( plugin_dir_path( __FILE__ ) . '../foxselect/static/css/main.*' );
  $y = 0;
  foreach( $styles as $style ){
    if( '.css' == substr( $style, -4 ) ){
      wp_enqueue_style( 'foxselect-' . $y );
      $y++;
    }
  }

  if( is_admin() ){
    return '<img src="' . plugin_dir_url( __FILE__ ) . '../../foxselect-sample.jpg" alt="FOXSelect" />';
  } else {
    $html = file_get_contents( plugin_dir_path( __FILE__ ) . '../html/foxselect.html' );

    $configuredPartJs = '';
    if( $args['part_number'] ){
      $part_number = explode('/', $args['part_number'] );
      $data = [
        'part' => $part_number[0],
        'package_type' => $part_number[1],
        'frequency_unit' => $part_number[2]
      ];
      error_log('$args = ' . print_r( $args, true ) . "\n\$data = " . print_r($data, true) );
      $part_options_json = \FoxParts\restapi\get_options( $data, true );

      $valid_external_part_options = ['part_type','size','frequency','tolerance','stability','load','optemp'];
      $part_types_array = [ 'C' => 'Crystal', 'K' => 'Crystal', 'O' => 'Oscillator', 'T' => 'TCXO', 'Y' => 'VC-TCXO/VCXO', 'S' => 'SSO' ];
      $configuredPart = [];

      foreach( $part_options_json->configuredPart as $option => $value ){
        if( in_array( $option, $valid_external_part_options ) &&  ! in_array( $value, ['_','__','0.0'] ) ){
          $key = ( 'part_type' == $option )? 'product_type' : $option ;
          $configuredPartValue = $part_options_json->partOptions[$option][0];
          if( 'part_type' == $option ){
            $value = $value['value'];
            $configuredPartValue = ['value' => $value, 'label' => $part_types_array[$value] ];
          }
          $configuredPart[$key] = $configuredPartValue;
        }
      }
      $configuredPartJs = '<script type="text/javascript">var configuredPart = ' . wp_json_encode( $configuredPart ) . ';</script>';

    }
    $html = str_replace( '{configuredPart}', $configuredPartJs, $html );

    if( $args['autoload'] ){
      $script = ( isset( $_GET['partnum'] ) && ! empty( $_GET['partnum'] ) )? "loadPreConfiguredFoxSelect('" . urldecode( $_GET['partnum'] ) . "');" : "window.FoxSelect.init( document.getElementById('rootfoxselect') );";
      wp_add_inline_script( 'foxselect-' . $foxselect_last_script, $script, 'after' );
    }

    return $html;
  }
}
add_shortcode( 'foxselect', __NAMESPACE__ . '\\foxselect' );

/**
 * Displays part details for a FoxPart CPT.
 *
 * @param      array  $atts   The atts
 */
function part_details( $atts ){
  $args = shortcode_atts([
    'id' => null,
  ], $atts );

  if( is_null( $args['id'] ) || ! is_numeric( $args['id'] ) )
    return '<p>ERROR: Missing `id` attribute. Please add a FoxPart ID to your shortcode.</p>';

  $details_table = \FoxParts\utilities\get_part_details_table( $args['id'] );

  return $details_table;
}
add_shortcode( 'partdetails', __NAMESPACE__ . '\\part_details' );

/**
 * Returns the Part Num for a given FoxPart Post ID.
 */
function part_number( $atts ){
  $args = shortcode_atts([
    'id' => null,
  ], $atts );

  if( is_null( $args['id'] ) )
    return '<p><code>Missing ID.</code></p>';

  $part_number = get_the_title( $args['id'] );
  if( $frequency = get_query_var( 'frequency' ) )
    $part_number.= $frequency;

  return $part_number;
}
add_shortcode( 'partnumber', __NAMESPACE__ . '\\part_number' );

/**
 * Gets the part data sheet.
 */
function part_datasheet( $atts ){
  $args = shortcode_atts([
    'id' => null,
  ], $atts );

  if( is_null( $args['id'] ) )
    return '<p><code>Missing ID.</code></p>';

  $frequency = get_query_var( 'frequency' );

  $data_sheet = false;
  $post = get_post( $args['id'] );
  $partnum = $post->post_title;
  if( $frequency ){
    $data_sheet_url = \FoxParts\utilities\get_part_series_details( $partnum, $frequency, 'Data Sheet' );
  } else {
    $data_sheet_url = \FoxParts\utilities\get_product_family_details( $partnum, 'data_sheet_url' );
  }
  if( $data_sheet_url )
    $data_sheet = '<a href="' . $data_sheet_url . '" target="_blank">Download Datasheet</a>';

  return $data_sheet;
}
add_shortcode( 'part_datasheet', __NAMESPACE__ . '\\part_datasheet' );

/**
 * Gets the Part Photo.
 */
function part_photo( $atts ){
  $args = shortcode_atts([
    'id' => null,
  ], $atts );

  if( is_null( $args['id'] ) )
    return '<p><code>Missing ID.</code></p>';

  $part_photo = false;
  if( ! has_post_thumbnail( $args['id'] ) ){
    $post = get_post( $args['id'] );
    $partnum = $post->post_title;
    $product_photo_part_image = \FoxParts\utilities\get_product_family_details( $partnum, 'product_photo_part_image' );
    if( ! empty( $product_photo_part_image ) ){
      require_once( plugin_dir_path( __FILE__ ) . '../classes/download-remote-image.php' );

      $download_remote_image = new \KM_Download_Remote_Image( $product_photo_part_image );
      $attachment_id = $download_remote_image->download();

      if( $attachment_id )
        $meta_id = set_post_thumbnail( $args['id'], $attachment_id );

      if( $meta_id )
        $part_photo = get_the_post_thumbnail( $args['id'], 'large', ['style' => 'width: 260px;'] );
    }
  } else {
    $part_photo = get_the_post_thumbnail( $args['id'], 'large', ['style' => 'width: 260px;'] );
  }

  return $part_photo;
}
add_shortcode( 'part_photo', __NAMESPACE__ . '\\part_photo' );

/**
 * Displays a listing of FOX parts.
 *
 * @param      <type>  $atts   The atts
 *
 * @return     string  ( description_of_the_return_value )
 */
function product_list( $atts ){
  static $count = 0;
  static $lists = [];
  $count++;

  $args = shortcode_atts( [
    'dataurl' => null,
    'package_type' => 'SMD',
    'frequency_unit' => 'MHz'
  ], $atts );

  if( is_null( $args['dataurl'] ) )
    return '<p>ERROR: Empty/Missing required attribute <code>dataurl</code>.</p>';

  $lists[$count] = [
    'dataurl' => $args['dataurl'],
    'id' => $count,
    'package_type' => $args['package_type'],
    'frequency_unit' => $args['frequency_unit'],
  ];

  wp_enqueue_script( 'productlist' );
  $upload_dir = wp_get_upload_dir();
  wp_localize_script( 'productlist', 'productListVars', [
    'modalurl' => '#elementor-action%3Aaction%3Dpopup%3Aopen%20settings%3DeyJpZCI6IjMxMDQyIiwidG9nZ2xlIjpmYWxzZX0%3D',
    'imageurl' => $upload_dir['baseurl'],
    'lists' => $lists,
  ]);

  add_action( 'wp_footer', __NAMESPACE__ . '\\load_product_list_template' );

  return '<div id="product-list-' . $count . '">Loading product list...</div>';
}
add_shortcode( 'productlist', __NAMESPACE__ . '\\product_list' );

function load_product_list_template(){
  echo file_get_contents( plugin_dir_path( __FILE__ ) . '../handlebars/product-list.html' );
}

