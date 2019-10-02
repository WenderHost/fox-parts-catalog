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
 * Displays the Part Search results table
 *
 * @param      array  $atts {
 *  @type string $s The search string
 * }
 *
 * @return     string  An html Part Search results table
 */
function partsearchresults( $atts ){
  $args = shortcode_atts( [
    's' => null
  ], $atts );

  $original_search_string = $args['s'];
  $args['s'] = \FoxParts\utilities\standardize_search_string( $args['s'] );
  if( ! $args['s'] ){
    $part_types = [];
    foreach( FOXPC_PART_TYPES as $part_code => $part_name ){
      $part_types[] = $part_code . ' (' . $part_name . ')';
    }
    return '<div class="elementor-alert elementor-alert-warning"><span class="elementor-alert-title">No results for `<code>' . $original_search_string . '</code>`</span><span class="elementor-alert-description">No results returned. Please ensure your part search string begins with one of the following characters:<ul><li>' . implode( '</li><li>', $part_types ) . '</li></ul></span></div>';
  }

  // Filter the search query to left align the search string
  add_filter( 'posts_where', function( $where, $query ){
    global $wpdb;
    $s = $query->get('s');
    $where.= " AND $wpdb->posts.post_title LIKE '$s%'";
    return $where;
  }, 10 , 2 );

  // Query `foxpart` CPTs according to our search string:
  $query_args = [
    's'               => $args['s'],
    'post_type'       => 'foxpart',
    'orderby'         => 'title',
    'order'           => 'ASC',
    'posts_per_page'  => -1,

  ];
  $query = new \WP_Query( $query_args );

  if( $query->have_posts() ){
    $x = 0;
    $rows = [];
    while( $query->have_posts() ): $query->the_post();
      $post_id = get_the_ID();
      $post_title = get_the_title();
      $rows[$x]['name'] = '<a href="' . get_permalink( $post_id ) . '">' . $post_title . '</a>';

      $part_types = wp_get_post_terms( $post_id, 'part_type' );
      $part_type = $part_types[0]->name;
      $package_type = ( 'crystal' == $part_type || 'crystal-khz' == $part_type )? get_post_meta( $post_id, 'package_option', true ) : null ;
      $size = get_post_meta( $post_id, 'size', true );

      $attributes = \FoxParts\utilities\get_part_attributes( $part_type );

      // Configure the header row by:
      // - Removing rows we don't want to display (i.e. `part_type` and `load`)
      // - Formatting the text (e.g. capitalizing first letter of the header's name)
      if( 0 === $x ){
        $part_type_key = array_search( 'part_type', $attributes );
        $load_key = array_search( 'load', $attributes );
        unset( $attributes[$part_type_key], $attributes[$load_key] );

        $header_attributes = array_map('ucfirst', $attributes );
        $headers = array_merge( ['Part Number'], $header_attributes );
      }

      foreach( $attributes as $key => $attr ){
        // Don't display `part_type` and `load` columns
        if( 'part_type' == $attr || 'load' == $attr ){
          unset($attributes[$key]);
          continue;
        }

        $value = get_post_meta( $post_id, $attr, true );
        $mapped_value = \FoxParts\utilities\map_part_attribute([
          'part_type'     => $part_type,
          'package_type'  => $package_type,
          'attribute'     => $attr,
          'value'         => $value,
          'size'          => $size,
        ]);
        $rows[$x][$attr] = $mapped_value;
      }
      $x++;
    endwhile;

    foreach( $rows as $row ){
      $tbody[] = '<tr><td>' . implode( '</td><td>', $row ) . '</td></tr>';
    }

    wp_enqueue_script( 'datatables-init' );
    wp_enqueue_style( 'datatables-custom' );

    return '<table id="partsearchresults"><thead><tr><th>' . implode( '</th><th>', $headers ) . '</th></tr></thead><tbody>' . implode( '', $tbody ) . '</tbody></table>';
  } else {
    return '<div class="elementor-alert elementor-alert-info"><span class="elementor-alert-title">No Results for `<code>' . $original_search_string . '</code>`</span><span class="elementor-alert-description">Please try searching again.</span></div>';
  }
}
add_shortcode( 'partsearchresults', __NAMESPACE__ . '\\partsearchresults' );

/**
 * Displays the details for a Part Series
 *
 * @param      <type>  $atts   The atts
 *
 * @return     string  ( description_of_the_return_value )
 */
function part_series_results( $atts ){
  $args = shortcode_atts( [
    's' => null
  ], $atts );

  wp_enqueue_script( 'datatables-init' );
  wp_enqueue_style( 'datatables-custom' );
  wp_localize_script( 'datatables-init', 'wpvars', ['partSeriesSearchUrl' => get_site_url( '', '/wp-json/foxparts/v1/get_part_series?partnum=' . $args['s'] ) ] );
  return '<table id="partseries"><thead><tr><th></th><th>Part Series</th><th>Part Type</th><th>Package</th><th>Datasheet</th></tr></thead><tbody></tbody></table>';
}
add_shortcode( 'partseriesresults', __NAMESPACE__ . '\\part_series_results' );

/**
 * For Product Family pages, displays a listing of FOX parts.
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

