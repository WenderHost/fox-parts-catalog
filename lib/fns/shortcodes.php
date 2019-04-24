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

      $html = str_replace( ['{configuredPart}'], [ 'var configuredPart = ' . wp_json_encode( $configuredPart ) . ';' ], $html );
    }

    return $html;
  }
}
add_shortcode( 'foxselect', __NAMESPACE__ . '\\foxselect' );