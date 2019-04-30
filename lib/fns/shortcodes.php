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
  $args = shortcode_atts([
    'part_number' => null
  ], $atts );

  wp_enqueue_style( 'foxselect-theme' );

  // Enqueue FOXSelect React App JS
  $scripts = glob( plugin_dir_path( __FILE__ ) . '../foxselect/static/js/main.*' );
  $x = 0;
  foreach( $scripts as $script ){
    if( '.js' == substr( $script, -3 ) ){
      wp_enqueue_script( 'foxselect-' . $x );
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
      $part_options_json = \FoxParts\restapi\get_options( $data, true );

      $valid_external_part_options = ['part_type','size','frequency','tolerance','stability','load','optemp'];
      $part_types_array = [ 'C' => 'Crystal', 'K' => 'Crystal', 'O' => 'Oscillator', 'T' => 'TCXO', 'Y' => 'VC-TCXO/VCXO', 'S' => 'SSO' ];
      $configuredPart = [];

      foreach( $part_options_json->configuredPart as $option => $value ){
        if( in_array( $option, $valid_external_part_options ) &&  ! in_array( $value, ['_','__','0.0'] ) ){
          $key = ( 'part_type' == $option )? 'product_type' : $option ;
          $configuredPartValue = $part_options_json->partOptions[$option][0];
          if( 'part_type' == $option ){
            $configuredPartValue = ['value' => $value, 'label' => $part_types_array[$value] ];
          }
          $configuredPart[$key] = $configuredPartValue;
        }
      }
      $configuredPartJs = '<script type="text/javascript">var configuredPart = ' . wp_json_encode( $configuredPart ) . ';</script>';

    }
    $html = str_replace( '{configuredPart}', $configuredPartJs, $html );
    return $html;
  }
}
add_shortcode( 'foxselect', __NAMESPACE__ . '\\foxselect' );

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
  //$ids[] = $count;

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
    /*'dataurl' => $args['dataurl'],*/
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

