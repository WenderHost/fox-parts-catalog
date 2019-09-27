<?php

namespace FoxParts\utilities;

/**
 * Available functions:
 *
 * get_part_attributes()
 * get_part_details_table()
 * get_product_family_details()
 * get_product_family_from_partnum()
 * get_key_label()
 * map_part_attribute()
 *
 */

/**
 * Returns array of part attributes for a specified Fox part_type
 *
 * @param      <type>         $part_type  The part type
 *
 * @return     array|boolean  The part attributes.
 */
function get_part_attributes( $part_type = null ){
  if( is_null( $part_type ) )
    return false;

  $part_type_codes = [
    'crystal'     => 'C',
    'crystal-khz' => 'K',
    'oscillator'  => 'O',
    'tcxo'        => 'T',
    'vcxo'        => 'Y',
    'sso'         => 'S',
  ];

  if( 1 < strlen( $part_type ) )
    $part_type_code = $part_type_codes[$part_type];

  $part_type_attributes = [
    'C' => ['part_type', 'size', 'package_option', 'tolerance', 'stability', 'load', 'optemp'],
    'K' => ['part_type', 'size', 'tolerance', 'stability', 'load', 'optemp'],
    'O' => ['part_type', 'size', 'output', 'voltage', 'stability', 'optemp'],
    'T' => ['part_type', 'size', 'output', 'pin_1', 'voltage', 'stability', 'optemp'],
    'Y' => ['part_type', 'size', 'output', 'voltage', 'stability', 'optemp'],
    'S' => ['part_type', 'size', 'enable_type', 'voltage', 'spread', 'optemp'],
  ];

  if( ! array_key_exists( $part_type_code, $part_type_attributes ) )
    return false;

  return $part_type_attributes[$part_type_code];
}

 /**
  * Returns HTML table of Part details.
  */
function get_part_details_table( $post_id ){

  $post = get_post( $post_id );
  $partnum = $post->post_title;
  $from_frequency = get_post_meta( $post_id, 'from_frequency', true );
  $to_frequency = get_post_meta( $post_id, 'to_frequency', true );

  $data = [
    'part' => $partnum . '-' . $from_frequency,
    'package_type' => 'smd',
    'frequency_unit' => 'mhz'
  ];

  //error_log('$data = ' . print_r($data, true) );
  $response = \FoxParts\restapi\get_options( $data, true );

  if( isset( $response->configuredPart ) && 0 < $response->availableParts ){
    $configuredPart = $response->configuredPart;
    $configuredPart['from_frequency'] = ['value' => $from_frequency, 'label' => $from_frequency];
    $configuredPart['to_frequency'] = ['value' => $to_frequency, 'label' => $to_frequency];
  } else {
    return '<p>ERROR: No part details returned.</p>';
    //'<pre>$from_frequency = ' . $from_frequency . ', $to_frequency = ' . $to_frequency . ';<br />$data = ' . print_r( $data, true ) . ';<br />$response = ' . print_r( $response, true ) . '</pre>';
  }

  foreach ($configuredPart as $key => $value) {
    $$key = $value;
  }

  $table_row_maps = [
    'c' => ['product_type','size','tolerance','stability','load','optemp'],
    'o' => ['product_type','size','output','voltage','stability','optemp'],
  ];

  if( ! array_key_exists( strtolower( $product_type['value'] ) , $table_row_maps ) )
    return '<div class="alert alert-warn">No table row map found for Product Type "' . $product_type['label'] . '".</div>';

  $frequency = get_query_var( 'frequency' );

  $row_map = $table_row_maps[ strtolower( $product_type['value'] ) ];

  $rows = [];
  foreach ( $row_map as $variable ) {
    $row = [];
    switch( $variable ){
      case 'optemp':
        $row['heading'] = 'Op Temp';
        break;

      case 'product_type':
        $row['heading'] = 'Product Type';
        $frequency_display = ( $frequency )? $frequency : $from_frequency['value'] . '-' . $to_frequency['value'] ;
        $row['value'] = $product_type['label'] . ' ' . $frequency_display . $frequency_unit['label'] . ' - ' . strtoupper( $package_type['label'] );
        break;

      default:
        $row['heading'] = ucwords( $variable );
    }

    if( ! isset( $row['value'] ) && '_' != $$variable['value'] ){
      $row['value'] = $$variable['label'];
    } else if( '_' == $$variable['value'] ) {
      // Skip attributes without a value (i.e. `_`).
      continue;
    }

    $rows[] = $row;
  }

  $html = '';
  $html.= '<table class="table table-striped table-sm"><colgroup><col style="width: 30%" /><col style="width: 70%;" /></colgroup>';
  foreach( $rows as $row ){
    $html.= '<tr><th scope="row">' . $row['heading'] . '</th><td>' . $row['value'] . '</td></tr>';
  }

  $details = ( $frequency )? get_part_series_details( $partnum, $frequency ) : get_product_family_details( $partnum ) ;

  //$product_family_details = get_product_family_details( $partnum );
  if( $details ){
    foreach( $details as $key => $value ){
      if( ! empty( $value ) ){
        switch( $key ){
          case 'products':
            if( $frequency )
              break;

            $parts = [];
            if( is_array( $value ) && 0 < count( $value ) ){
              foreach( $value as $partnum ){
                $frequency = ( \preg_match( '/[0-9]+\.[0-9]+$/', $partnum, $matches ) ) ? $matches[0] : false ;
                $parts[] = [
                  'series' => str_replace( $matches[0], '', $partnum ),
                  'frequency' => $frequency,
                ];
              }
            }
            foreach( $parts as $part ){
              //$link = add_query_arg( [ 'frequency' => $part['frequency'] ], '/foxpart/' . $part['series'] );
              $link = '/foxpart/' . $part['series'] . '/frequency/' . $part['frequency'];
              $partlist[] = '<a href="' . $link . '" target="_blank">' . $part['series'] . $part['frequency'] . '</a>';
            }
            $html.= '<tr><th scope="row">Parts</th><td>' . implode( ', ', $partlist ) . '</td></tr>';
            break;

          case 'data_sheet_url':
          case 'Image of Part':
          case 'Data Sheet':
          case 'product_photo_part_image';
          case 'standard_lead_time_weeks':
          case 'output':
          case 'name':
          case 'part_type':
            // nothing, skip
            break;

          default:
            $label = ( $frequency )? $key : get_key_label( $key );
            $html.= '<tr><th scope="row">' . $label . '</th><td>' . $value . '</td></tr>';
        }

      }
    }
  }
  $html.= '</table>';

  return $html;
}

function get_product_family_details( $partnum = null, $detail = null ){
  $product_family_details = [];

  if( is_null( $partnum ) )
    return $product_family_details;

  $product_family = get_product_family_from_partnum( $partnum );
  $sf_api_request_url = get_site_url( null, 'wp-json/foxparts/v1/get_product_family?family=' . $product_family );
  $sfRequest = \WP_REST_Request::from_url( $sf_api_request_url );
  $sfResponse = \FoxParts\restapi\get_product_family( $sfRequest );

  if( $sfResponse->data && 0 < count($sfResponse->data) ){
    // Match to correct product series within the product family:
    foreach( $sfResponse->data as $product_series ){
      if( ! is_null( $product_series->products ) && is_array( $product_series->products ) && 0 < count( $product_series->products ) ){
        if( stristr( $product_series->products[0], $partnum ) ){
          //$salesForcePartDetails = $product_series;
          foreach ($product_series as $key => $value) {
            $product_family_details[$key] = $value;
          }
          break;
        }
      }
    }
  }

  if( ! is_null( $detail ) && array_key_exists( $detail, $product_family_details ) ){
    return $product_family_details[$detail];
  } else if( ! is_null( $detail ) && ! array_key_exists( $detail, $product_family_details ) ){
    return false;
  } else {
    return $product_family_details;
  }
}

function get_part_series_details( $partnum = null, $frequency = null, $detail = null ){
  $sf_api_request_url = get_site_url( null, 'wp-json/foxparts/v1/get_web_part?partnum=' . $partnum . $frequency );
  $sfRequest = \WP_REST_Request::from_url( $sf_api_request_url );
  $sfResponse = \FoxParts\restapi\get_web_part( $sfRequest );

  $details = [];
  if( $sfResponse->data ){ // && is_object( $sfResponse->data ) && 0 < count($sfResponse->data)
    // Match to correct product series within the product family:
    //error_log('count($sfResponse->data) = ' . count($sfResponse->data) );
    foreach( $sfResponse->data as $key => $part_detail ){
      //$details[] = ['heading' => $part_detail->label, 'value' => $part_detail->value ];
      $details[$part_detail->label] = $part_detail->value;
    }
  }

  if( ! is_null( $detail ) && array_key_exists( $detail, $details ) ){
    return $details[$detail];
  } else if( ! is_null( $detail ) && ! array_key_exists( $detail, $details ) ){
    return false;
  } else {
    return $details;
  }

}

/**
 * Provided a Fox Part number returns the product family
 *
 * @param      string   $partnum  The partnum
 *
 * @return     string  The product family from partnum.
 */
function get_product_family_from_partnum( $partnum = '' ){
  if( empty( $partnum ) )
    return false;

  // Remove initial `F`
  if( 'F' == substr( $partnum, 0, 1 ) )
    $trimmed_partnum = ltrim( $partnum, 'F' );

  $product_type = substr( $trimmed_partnum, 0, 1 );
  // If product type is a VCXO (i.e. `Y`), product family lengh is 3, otherwise it's 4.
  $length = ( 'Y' == $product_type )? 3 : 4;

  return substr( $trimmed_partnum, 0, $length );
}

function get_key_label( $key ){
  switch( $key ){
    case 'width_mm':
      $label = 'Width (mm)';
      break;

    case 'moisture_sensitivity_level_msl':
      $label = 'Moisture Sensitivity Level (MSL)';
      break;

    case 'reach_compliant':
      $label = 'REACH Compliant';
      break;

    case 'rohs_compliant':
      $label = 'RoHS Compliant';
      break;

    case 'static_sensitive':
      $label = 'Static Sensitive';
      break;

    default:
      $key = str_replace( '_', ' ', $key );
      $label = ( stristr( $key, 'mm' ) )? \ucfirst( str_replace( 'mm', '(mm)', $key ) ) : \ucwords($key);
  }
  return $label;
}

/**
 * Maps Fox Part attribute values to human readable labels
 *
 * @param      array  $atts {
 *  @type string $part_type     The part type code
 *  @type string $package_type  The part's package type
 *  @type string $attribute     The attribute we are mapping
 *  @type string $value         The value we are mapping
 *  @type string $size          The part's size
 * }
 *
 * @return     string  Human readable label for the given value
 */
function map_part_attribute( $atts = [] ){

  $args = shortcode_atts( [
    'part_type'     => null,
    'package_type'  => null,
    'attribute'     => null,
    'value'         => null,
    'size'          => null,
  ], $atts );
  //echo '<pre>$args = '.print_r($args,true).'</pre>';
  $lc_attribute = strtolower( $args['attribute'] );

  switch( $lc_attribute ){
    case 'optemp':
      $labels = [ 'B' => '-10 To +50 C', 'D' => '-10 To +60 C', 'E' => '-10 To +70 C', 'Q' => '-20 To +60 C', 'F' => '-20 To +70 C', 'Z' => '-20 To +75 C', 'N' => '-20 To +85 C', 'G' => '-30 To +70 C', 'H' => '-30 To +75 C', 'J' => '-30 To +80 C', 'K' => '-30 To +85 C', 'L' => '-35 To +80 C', 'V' => '-35 To +85 C', 'P' => '-40 To +105 C', 'I' => '-40 To +125 C', 'Y' => '-40 To +75 C', 'M' => '-40 To +85 C', 'R' => '-55 To +100 C', 'S' => '-55 To +105 C', 'T' => '-55 To +125 C', 'U' => '-55 To +85 C', 'A' => '0 To +50 C', 'C' => '0 To +70 C', 'W' => 'Other' ];
      break;

    case 'output':
      $labels = [ 'A'  => 'Clipped Sine', 'C'  => 'Clipped Sine', 'G'  => 'Clipped Sine', 'H'  => 'HCMOS', 'S'  => 'HCMOS', 'HB' => 'HCMOS', 'HD' => 'HCMOS', 'HH' => 'HCMOS', 'HL' => 'HCMOS', 'HS' => 'HCMOS', 'HK' => 'HCMOS', 'PD' => 'LVPECL', 'PS' => 'LVPECL', 'PU' => 'LVPECL', 'LD' => 'LVDS', 'LS' => 'LVDS', 'SL' => 'HCSL', 'HA' => 'AEC-Q200' ];
      break;

    case 'package_option':
      $labels = ['AS' => 'SMD', 'AQ' => 'SMD', 'BA' => 'SMD', 'BS' => 'SMD', 'ST' => 'Pin-Thru', 'UT' => 'Pin-Thru', '0T' => 'Pin-Thru', '15' => 'Pin-Thru', '26' => 'Pin-Thru', '38' => 'Pin-Thru'];
      break;

    case 'pin_1':
      $labels = [ 'N' => 'Ground', 'V' => 'Voltage Control', 'D' => 'E/D', 'T' => 'VC w/o mech. trimmer' ];
      if( ! is_null( $args['size'] ) && 9 == $args['size'] )
        $labels['N'] = 'No Connect';
      break;

    case 'size':
      $pin_thru_crystals = ['ST','UT','0T','15','26','38'];

      switch( strtolower( $args['part_type'] ) ){
        case 'c':
        case 'crystal':
        case 'o':
        case 'oscillator':
          if( in_array( $args['package_type'], $pin_thru_crystals ) ){
            $args['value'] = $args['value'] .  $args['package_type'];
            $labels = [ '4ST' => 'HC49 3.6mm height', '9ST' => 'HC49 2.5mm height', '4UT' => 'HC49U 13.46mm height', '80T' => 'HC80U', 'T15' => '5.0x1.5 mm', 'T26' => '6.0x2.0 mm', 'T38' => '8.0x3.0 mm' ];
          } else {
            $labels = ['A' => '1.2x1.0 mm', '0' => '1.6x1.2 mm', '1' => '2.0x1.6 mm', '2' => '2.5x2.0 mm', '3' => '3.2x2.5 mm', '4' => 'HC49 SMD (4.5mm)', '5' => '5.0x3.2 mm', '6' => '6.0x3.5 mm', '7' => '7.0x5.0 mm', '8' => '10.0x4.5 mm', '9' => 'HC49 SMD (3.2mm)'];
          }

          if( 'oscillator' == $args['part_type'] || 'O' == $args['part_type'] )
            $labels['8'] = '1.6x1.2 mm';
          break;

        case 'k':
        case 'crystal-khz':
          $labels = [ '161' => '1.6x1.0 mm', '122' => '2.0x1.2 mm', '12A' => '2.0x1.2 mm',  '13A' => '3.2x1.5 mm',  '135' => '3.2x1.5 mm',  '13L' => '3.2x1.5 mm',  '145' => '4.1x1.5 mm', '255' => '4.9x1.8 mm', 'FSX' => '7.0x1.5 mm', 'FSR' => '8.7x3.7 mm', 'FSM' => '10.4x4.0 mm', 'T15' => '5.0x1.5 mm', 'T26' => '6.0x2.0 mm', 'T38' => '8.0x3.0 mm' ];
          break;

        case 't':
        case 'tcxo':
          $labels = [ '1' => '2.0x1.6 mm', '2' => '2.5x2.0 mm', '3' => '3.2x2.5 mm', '5' => '5.0x3.2 mm', '7' => '7.0x5.0 mm', '9' => '11.4x9.6 mm' ];
          break;

        default:
          $labels[$args['value']] = $args['value']; // Just return the value
          break;
      }
      break;
    /* END `size` */

    case 'spread':
      $labels = [ 'A' => '±0.25% Center', 'B' => '±0.5% Center', 'C' => '±0.75% Center', 'D' => '±1.0% Center', 'E' => '±1.5% Center', 'F' => '±2.0% Center', 'G' => '-0.5% Down Spread', 'H' => '-1.0% Down Spread', 'J' => '-1.5% Down Spread', 'K' => '-2.0% Down Spread', 'L' => '-3.0% Down Spread', 'M' => '-4.0% Down Spread', 'N' => '±0.125% Center', 'P' => '-0.25% Down Spread' ];
      break;

    case 'stability':
      $labels = [ 'M' => '-0.036 ppm / (∆ºC)²', 'I' => '-0.04 ppm (∆ºC)² max', 'O' => '-140 ~ +10 ppm', 'K' => '0.28 ppm', 'Q' => '0.37 ppm', 'U' => '0.5 ppm', 'T' => '1.0 ppm', 'S' => '1.5 ppm', 'H' => '10.0 ppm', 'G' => '100 ppb', 'A' => '100.0 ppm', 'Y' => '1000.0 ppm', 'F' => '15.0 ppm', 'R' => '2.0 ppm', 'P' => '2.5 ppm', 'E' => '20.0 ppm', 'V' => '200.0 ppm', 'D' => '25.0 ppm', 'N' => '3.0 ppm', 'C' => '30.0 ppm', 'L' => '5.0 ppm', 'B' => '50.0 ppm', 'W' => '70.0 ppm', 'J' => '8 ppm', 'Z' => 'Other', 'X' => 'Overall' ];
      break;

    case 'tolerance':
      $labels = ['M' => '-0.036 ±1 ppm / (∆ºC)²', 'I' => '-0.04 ppm (∆ºC)² max', 'O' => '-140 ~ +10 ppm', 'K' => '0.28 ppm', 'Q' => '0.37 ppm', 'U' => '0.5 ppm', 'T' => '1.0 ppm', 'S' => '1.5 ppm', 'H' => '10.0 ppm', 'G' => '100 ppb', 'A' => '100.0 ppm', 'Y' => '1000.0 ppm', 'F' => '15.0 ppm', 'R' => '2.0 ppm', 'P' => '2.5 ppm', 'E' => '20.0 ppm', 'V' => '200.0 ppm', 'D' => '25.0 ppm', 'N' => '3.0 ppm', 'C' => '30.0 ppm', 'L' => '5.0 ppm', 'B' => '50.0 ppm', 'W' => '70.0 ppm', 'J' => '8 ppm', 'Z' => 'Other', 'X' => 'Overall' ];
      break;

    case 'voltage':
      $labels = [ 'A' => '5 Volts', 'P' => '5 Volts', 'B' => '3.3 Volts', 'C' => '3.3 Volts', 'D' => '3 Volts', 'E' => '3 Volts', 'F' => '2.85 Volts', 'G' => '2.85 Volts', 'Q' => '2.8 Volts', 'R' => '2.8 Volts', 'S' => '2.7 Volts', 'T' => '2.7 Volts', 'H' => '2.5 Volts', 'J' => '2.5 Volts', 'K' => '1.8 Volts', 'L' => '1.8 Volts', 'M' => '1.0 Volt', 'N' => '1.0 Volt', 'V' => '1.7~3.63V', 'W' => '2.25~3.63V' ];
      break;

    default:
      $labels[$args['value']] = $args['value'];
      break;
  }

  if( array_key_exists( $args['value'], $labels ) ){
    return $labels[ $args['value'] ];
  } else {
    return $args['value'];
  }
}
