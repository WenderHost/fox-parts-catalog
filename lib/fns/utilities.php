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
 * Given a Fox Part number, returns the part's package_type
 *
 * @param      string          $partnum  The partnum
 *
 * @return     boolean|string  The package type from partnum.
 */
function get_package_type_from_partnum( $partnum = null ){
  if( empty( $partnum ) || is_null( $partnum ) )
    return false;

  // Remove initial `F`
  if( 'F' == substr( $partnum, 0, 1 ) )
    $trimmed_partnum = ltrim( $partnum, 'F' );

  $product_type = substr( $trimmed_partnum, 0, 1 );

  // Only Crystals and KHz-Crystals have `pin-thru` package_type options, all others are `smd`:
  $pin_thru_product_types = ['C','K'];
  if( ! in_array( $product_type, $pin_thru_product_types ) )
    return 'smd';

  // If product type is a Khz Crystal (i.e. `K`), package option length is 3, otherwise it's 2.
  $length = ( 'K' == $product_type )? 3 : 2;
  $package_option = substr( $trimmed_partnum, 2, $length );
  $package_type = map_part_attribute([
    'attribute'   => 'package_option',
    'value'       => $package_option,
  ]);
  return $package_type;
};

 /**
  * Returns HTML table of Part details.
  */
function get_part_details_table( $post_id ){

  $post = get_post( $post_id );
  $partnum = $post->post_title;
  $from_frequency = get_post_meta( $post_id, 'from_frequency', true );
  $to_frequency = get_post_meta( $post_id, 'to_frequency', true );
  $package_type = get_package_type_from_partnum( $partnum );

  $frequency = get_query_var( 'frequency' );

  // Query the data from our REST endpoint
  $data_partnum = ( ! empty( $frequency ) && is_float( $frequency ) )? $partnum . '-' . $frequency : $partnum . '-' . $from_frequency ;
  $data = [
    'part' => $data_partnum,
    'package_type' => $package_type,
    'frequency_unit' => 'mhz'
  ];
  error_log('$data = ' . print_r( $data, true ) );
  $response = \FoxParts\restapi\get_options( $data, true );

  if( isset( $response->configuredPart ) && 0 < $response->availableParts ){
    $configuredPart = $response->configuredPart;
    $configuredPart['from_frequency'] = ['value' => $from_frequency, 'label' => $from_frequency];
    $configuredPart['to_frequency'] = ['value' => $to_frequency, 'label' => $to_frequency];
  } else {
    return '<p>ERROR: No part details returned.</p>';
  }
  error_log('$configuredPart = ' . print_r( $configuredPart, true ) );

  $table_row_maps = [
    'c' => ['product_type','size','frequency','tolerance','stability','load','optemp','length_mm','width_mm','height_mm'],
    'o' => ['product_type','size','frequency','voltage','stability','optemp','output','load_capacitance','length_mm','width_mm','height_mm','packaging','reel_diameter_in_inches','country_of_origin'],
  ];

  if( ! array_key_exists( strtolower( $configuredPart['product_type']['value'] ) , $table_row_maps ) )
    return '<div class="alert alert-warn">No table row map found for Product Type "' . $configuredPart['product_type']['label'] . '".</div>';

  $row_map = $table_row_maps[ strtolower( $configuredPart['product_type']['value'] ) ];

  //$details = ( $frequency )? get_part_series_details( $partnum, $frequency ) : get_product_family_details( $partnum ) ;
  // 10/14/2019 (12:22) - Need to handle $frequency like we did in previous line ☝️
  $details = get_part_series_details(['partnum' => $partnum]);
  if( $details ){
    $details->load_capacitance = '<code>Missing in API?</code>';
    $details->reel_diameter_in_inches = '<code>Missing in API?</code>';
    $details->country_of_origin = '<code>Missing in API?</code>';
  }

  $rows = [];
  //$rows[] = ['heading' => 'ConfiguredPart','value' => '<pre>' . print_r($configuredPart,true) . '</pre>' . '; $row_map = <pre>' . print_r($row_map,true) . '</pre>'];
  //$rows[] = [ 'heading' => 'Details', 'value' => '<pre>'. print_r($details,true).'</pre>' ];

  // Part Number
  if( $frequency )
    $rows[] = [ 'heading' => 'Part Number', 'value' => $partnum . $frequency ];

  // Description and Part Series
  if( 0 < count( $details->product_family ) ){
    foreach( $details->product_family as $product_family ){
      if( 0 < count( $product_family->product ) ){
        foreach( $product_family->product as $product ){
          if( $partnum . $frequency == $product->name ){
            $rows[] = [ 'heading' => 'Description', 'value' => $product->description ];
            $rows[] = [ 'heading' => 'Part Series', 'value' => $details->name ];
            $last_row = [ 'heading' => 'Life Cycle Status', 'value' => $product_family->part_life_cycle_status ];
          }
        }
      }
    }
  }

  foreach ( $row_map as $variable ) {
    //$rows[] = ['heading' => $variable, 'value' => '$variable = ' . $variable];
    $row = [];
    switch( $variable ){
      case 'frequency':
        $row = [ 'heading' => 'Frequency', 'value' => $frequency . 'MHz' ];
        break;

      case 'optemp':
        $row['heading'] = 'Operating Temperature';
        break;

      case 'product_type':
        $row['heading'] = 'Part Type';
        $frequency_display = ( $frequency )? $frequency : $from_frequency . '-' . $to_frequency ;
        $package_type_labels = ['smd' => 'SMD', 'pin-thru' => 'Pin-Thru'];
        $row['value'] = $configuredPart['product_type']['label'] . ' ' . $frequency_display . $configuredPart['frequency_unit']['label'] . ' - ' . $package_type_labels[ strtolower( $configuredPart['package_type']['label'] ) ];
        break;

      case 'stability':
        $row = ['heading' => 'Frequency Stability (PPM)'];
        break;

      case 'length_mm':
      case 'width_mm':
      case 'height_mm':
        $row['heading'] = ucwords( str_replace( '_mm',' (mm)', $variable ) );
        break;

      default:
        $row['heading'] = ucwords( $variable );
    }

    if( ! isset( $row['value'] ) && isset( $configuredPart[$variable] ) && '_' != $configuredPart[$variable]['value'] ){
      $row['value'] = $configuredPart[$variable]['label'];
    } else if( ! isset( $row['value'] ) && $details->$variable ) {
      $row['value'] = $details->$variable;
      unset( $details->$variable );
    } else if( '_' == $configuredPart[$variable]['value'] ) {
      // Skip attributes without a value (i.e. `_`).
      continue;
    }
    //if( ! isset( $row['value'] ) || empty( $row['value'] ) )
      //$row['value'] = '(' . $details->$variable . ') $variable = ' . $variable;

    $rows[] = $row;
  }

  if( $details ){
    //$rows[] = ['heading' => '$details', 'value' => print_r($details,true)];

    foreach( $details as $key => $value ){
      if( ! empty( $value ) ){
        $row = [];
        switch( $key ){
          case 'country_of_origin':
            $row = ['heading' => 'Country of Origin', 'value' => $value];
            break;

          case 'load_capacitance':
            $row = ['heading' => 'Load Capacitance (pF)', 'value' => $value];
            break;

          case 'product_family':
            // If we have a frequency, don't show a list of part numbers:
            if( $frequency )
              break;

            if( 0 < count( $value ) ){
              foreach( $value as $product_family ){
                if( stristr( $partnum, $product_family->name ) ){
                  if( 0 < count( $product_family->product ) ){
                    foreach( $product_family->product as $product ){
                      /**
                       * Extract Frequency from Part Number:
                       *
                       * I built the following regex with help from these:
                       *
                       * - https://regex101.com/r/lV0ExD/1/
                       * - https://stackoverflow.com/questions/24342026/in-php-how-to-get-float-value-from-a-mixed-string
                       */
                      preg_match( '/(^.*?)([\d]+(?:\.[\d]+)+)$/', $product->name, $matches );
                      $product_family = $matches[1];
                      $frequency = $matches[2];
                      $product_name = ( ! empty( $frequency ) )? '<a href="' . get_site_url() . '/foxpart/' . $product_family . '/' . $frequency . '" target="_blank">' . $product->name . '</a>' : $product->name ;
                      $partlist[] = $product_name;
                    }
                  }
                  $row = [ 'heading' => 'Products', 'value' => implode(', ', $partlist ) ];
                  //$html.= '<tr><th scope="row">Parts</th><td>' . implode( ', ', $partlist ) . '</td></tr>';
                }
              }
            }
            break;

          case 'individual_part_weight_grams':
            $row = ['heading' => 'Individual Part Weight', 'value' => $value];
            break;

          case 'packaging':
            $row = ['heading' => 'Packaging Method', 'value' => $value];
            break;

          case 'reel_diameter_in_inches':
            $row = ['heading' => 'Reel diameter in inches', 'value' => $value];
            break;

          case 'cage_code':
          case 'data_sheet_url':
          case 'export_control_classification_number':
          case 'Image of Part':
          case 'Data Sheet':
          case 'product_photo_part_image';
          case 'standard_lead_time_weeks':
          case 'output':
          case 'moisture_sensitivity_level_msl':
          case 'name':
          case 'package_name':
          case 'part_type':
          case 'reach_compliant':
          case 'static_sensitive':
            // nothing, skip
            break;

          default:
            $label = ( $frequency )? $key : get_key_label( $key );
            if( is_array( $value ) )
              $value = '<pre>'.print_r($value,true).'</pre>';
            //$html.= '<tr><th scope="row">' . $label . '</th><td>' . $value . '</td></tr>';
            $row = [ 'heading' => $label, 'value' => $value ];
        }
        if( isset( $row ) && ! empty( $row ) )
          $rows[] = $row;
      }
    }
  }
  $html = '<h3>Product Details</h3>';
  $html.= '<table class="table table-striped table-sm"><colgroup><col style="width: 30%" /><col style="width: 70%;" /></colgroup>';
  if( isset( $last_row ) )
    $rows[] = $last_row;
  foreach( $rows as $key => $row ){
    $html.= '<tr><th scope="row">' . $row['heading'] . '</th><td>' . $row['value'] . '</td></tr>';
  }
  $html.= '</table>';
  $html.= '<h3>REACH/RoHS Compliant</h3>';
  $html.= '<table class="table table-striped table-sm"><colgroup><col style="width: 30%" /><col style="width: 70%;" /></colgroup>';
  $rows = [
    [
      'heading' => 'Static Sensitive',
      'value' => $details->static_sensitive,
    ],
    [
      'heading' => 'Moisture Sensitivity Level',
      'value' => $details->moisture_sensitivity_level_msl,
    ],
    [
      'heading' => 'Cage Code',
      'value' => $details->cage_code,
    ],
    [
      'heading' => 'Export Control',
      'value' => $details->export_control_classification_number,
    ],
    [
      'heading' => 'Harmonized Tariff',
      'value' => '<code>Missing in API?</code>',
    ],
    [
      'heading' => 'Code Schedule B',
      'value' => '<code>Missing in API?</code>',
    ],
  ];
  foreach( $rows as $row ){
    $html.= '<tr><th scope="row">' . $row['heading'] . '</th><td>' . $row['value'] . '</td></tr>';
  }
  $html.= '</table>';

  return $html;
}

//function get_product_family_details( $partnum = null, $detail = null ){
//$partnum = null, $detail = null
function get_part_series_details( $atts ){
  $args = shortcode_atts([
    'partnum'   => null,
    'detail'    => null,
    'frequency' => null,
  ], $atts );
  $product_family_details = [];

  if( is_null( $args['partnum'] ) )
    return $product_family_details;

  $part_series = get_part_series_from_partnum( $args['partnum'] );
  $sf_api_request_url = get_site_url( null, 'wp-json/foxparts/v1/get_part_series?partnum=' . $part_series );
  error_log( basename(__FILE__) . '::' . __LINE__ . ' $sf_api_request_url = ' . $sf_api_request_url );
  $sfRequest = \WP_REST_Request::from_url( $sf_api_request_url );
  $sfResponse = \FoxParts\restapi\get_part_series( $sfRequest );

  if( $sfResponse->data && 0 < count($sfResponse->data) ){
    // Match to correct product series within the product family:
    foreach( $sfResponse->data as $part_series_data ){
      if( $part_series == $part_series_data->name ){
        switch( $args['detail'] ){
          case 'data_sheet_url_part':
            // Part specific data sheets are found inside product families
            if( 0 < count( $part_series_data->product_family ) ){
              foreach( $part_series_data->product_family as $product_family ){
                if( stristr( $product_family->name, $args['partnum'] ) ){
                  if( 0 < count( $product_family->product ) ){
                    $full_part_no = $args['partnum'] . $args['freqeuncy'];
                    foreach( $product_family->product as $product ){
                      if( $full_part_no == $product->name )
                        $detail = $args['detail'];
                        return $product->$detail;
                    }
                  }
                }
              }
            }
            break;

          default:
            // By default we're searching for properties at the part series level:
            if( ! is_null( $args['detail'] ) && property_exists( $part_series_data, $args['detail'] ) ){
              $detail = $args['detail'];
              return $part_series_data->$detail;
            } else if( ! is_null( $args['detail'] ) && ! property_exists( $part_series_data, $args['detail'] ) ){
              return false;
            } else {
              return $part_series_data;
            }
        } // switch( $args['detail'] )
      }
    }
  }
  return false;
}

/*
// 10/04/2019 (09:56) - commented this out to see if it's being used anywhere, doesn't appear to be:
function get_part_series( $partnum = null ){
  if( is_null( $partnum ) )
    return false;

  $sf_api_request_url = get_site_url( null, 'wp-json/foxparts/v1/get_part_series?partnum=' . $partnum );
  $sfRequest = \WP_REST_Request::from_url( $sf_api_request_url );
  $sfResponse = \FoxParts\restapi\get_part_series( $sfRequest );

  if( $sfResponse->data && 0 < count( $sfResponse->data->part_series ) ){
    return $sfResponse->data->part_series;
  } else {
    return false;
  }
}
/**/

/*
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
/**/

/**
 * Provided a Fox Part number returns the part series
 *
 * @param      string   $partnum  The partnum
 *
 * @return     string  The part series from partnum.
 */
function get_part_series_from_partnum( $partnum = '' ){
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

/**
 * Standardizes a Fox Part search string.
 *
 * Standardized string has the following properties when $for_wp is `true`:
 * - Begins with the letter `f`.
 * - All letters are lowercase.
 * - Second letter in the string will be one of the array keys in FOXPC_PART_TYPES.
 *
 * When $for_wp == `false`, the string begins with a Fox Part Type code.
 *
 * @param      string   $s      Fox Part search string
 * @param      boolean  $for_wp Standardize string for a WordPress search where all `foxpart` CPTs start with `F`?
 *
 * @return     boolean|string  Returns our standardized part search string.
 */
function standardize_search_string( $s = null, $for_wp = true ){
  if( is_null( $s ) )
    return false;

  $s_array = array_map( 'strtolower', str_split( $s ) );

  if( 'f' != $s_array[0] && true == $for_wp ){
    array_unshift( $s_array, 'f' );
  } else if ( 'f' == $s_array[0] && false == $for_wp ){
    array_shift( $s_array );
  }

  $part_type_check_key = ( $for_wp )? 1 : 0 ;
  if( ! array_key_exists( strtoupper( $s_array[$part_type_check_key] ), FOXPC_PART_TYPES ) )
    return false;

  return implode( '', $s_array );
}
