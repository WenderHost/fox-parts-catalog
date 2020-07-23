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
 * split_part_number()
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
 * Returns the HTML for display details of a Fox Part.
 *
 * @param      int  $post_id  The post ID
 *
 * @return     string  HTML for the part details table.
 */
function get_part_details_table( $post_id ){

  $post = get_post( $post_id );
  $partnum = $post->post_title;
  $sf_api_partnum = standardize_search_string( $partnum, false );
  $from_frequency = get_post_meta( $post_id, 'from_frequency', true );
  $to_frequency = get_post_meta( $post_id, 'to_frequency', true );
  $package_type = get_package_type_from_partnum( $partnum );

  //$frequency = sprintf("%0.5f", (float) get_query_var( 'frequency' ) );
  $frequency = get_query_var( 'frequency' );
  if( ! stristr( $frequency, '.' ) )
    $frequency = sprintf( "%0.1f", $frequency );

  // Get the `load` value from our permalink (e.g. /foxpart/FC3BACA_I/16.0/L/)
  $load = get_query_var('load');

  // Query the data from our REST endpoint
  $data_partnum = ( ! empty( $frequency ) && is_float( $frequency ) )? $partnum . '-' . $frequency : $partnum . '-' . $from_frequency ;
  $data = [
    'part' => $data_partnum,
    'package_type' => $package_type,
    'frequency_unit' => 'mhz'
  ];
  \foxparts_error_log('Call REST API::get_options() with $data = ' . print_r( $data, true ), FOXPC_LOG_API );
  $response = \FoxParts\restapi\get_options( $data, true );

  if( isset( $response->configuredPart ) && 0 < $response->availableParts ){
    $configuredPart = $response->configuredPart;
    $configuredPart['from_frequency'] = ['value' => $from_frequency, 'label' => $from_frequency];
    $configuredPart['to_frequency'] = ['value' => $to_frequency, 'label' => $to_frequency];
  } else {
    return '<p>ERROR: No part details returned.</p>';
  }
  //error_log('$configuredPart = ' . print_r( $configuredPart, true ) );

  $table_row_maps = [
    'c' => ['product_type','size','frequency','tolerance','stability','load','optemp'],
    'o' => ['product_type','size','output','frequency','voltage','stability','optemp'],
    't' => ['product_type','size','frequency','output','voltage','stability','optemp'],
    'y' => ['product_type','size','frequency','output','voltage','stability','optemp'],
  ];


  if( ! array_key_exists( strtolower( $configuredPart['product_type']['value'] ) , $table_row_maps ) )
    return '<div class="alert alert-warn">No table row map found for Product Type "' . $configuredPart['product_type']['label'] . '".</div>';

  $row_map = $table_row_maps[ strtolower( $configuredPart['product_type']['value'] ) ];
  $common_rows = ['length_mm','width_mm','height_mm','packaging','country_of_origin','individual_part_weight_grams','part_life_cycle_status','reach_compliant','rohs_compliant','static_sensitive','moisture_sensitivity_level_msl','cage_code','export_control_classification_number','hts_code','schedule_b_export_code'];
  $row_map = array_merge( $row_map, $common_rows );

  //$details = ( $frequency )? get_part_series_details( $partnum, $frequency ) : get_product_family_details( $partnum ) ;
  // 10/14/2019 (12:22) - Need to handle $frequency like we did in previous line ☝️
  $details = get_part_series_details(['partnum' => $partnum]);
  if( $details ){
    $details->rohs_compliant = 'Y';
  }

  $rows = [];

  // Part Number
  if( $frequency && '0.0' != $frequency ){
    foxparts_error_log('SETTING the part number: substr( $partnum, 7 ) = ' . substr( $partnum, 7, 1 ) );
    $displayedPartnum = $partnum;
    if(
      'c' == strtolower( $configuredPart['product_type']['value'] )
      && '_' == substr( $partnum, 7, 1 )
      && $load
    )
      $displayedPartnum = str_replace('_', $load, $partnum );
    $rows[] = [ 'heading' => 'Part Number', 'value' => $displayedPartnum . $frequency ];
  }

  // Description and Part Series
  if( 0 < count( $details->product_family ) ){
    foreach( $details->product_family as $product_family ){

      if( ! stristr( $sf_api_partnum, strtolower( $product_family->name ) ) )
        continue;

      //\foxparts_error_log( 'PRODUCT FAMILY: '."\n".'👉 $product_family->name = ' . $product_family->name ."\n". '👉 $sf_api_partnum = ' . $sf_api_partnum . "\n" . '👉 $partnum = ' . $partnum );

      $details->part_life_cycle_status = $product_family->part_life_cycle_status;

      if( 0 < count( $product_family->product ) ){
        //\foxparts_error_log('$partnum = ' . $partnum . "\n" . '$product_family->product[0] = ' . print_r( $product_family->product[0], true ));
        //$details->country_of_origin = $product_family->product[0]->country_of_origin;
        //$details->hts_code = $product_family->product[0]->hts_code;
        //$details->schedule_b_export_code = $product_family->product[0]->schedule_b_export_code;
        //$details->circuit_condition = $product_family->product[0]->circuit_condition;

        $split_partnum = split_part_number( $partnum . $frequency );
        //\foxparts_error_log( '$split_partnum = ' . print_r( $split_partnum, true ) );

        foreach( $product_family->product as $product ){
          $split_product_name = split_part_number( $product->name );
          //\foxparts_error_log('$product->name = ' . $product->name );
          //\foxparts_error_log( '$split_product_name = ' . print_r( $split_product_name, true ) );

          if( match_with_underscores( $split_partnum['config'], $split_product_name['config'] ) ){
            //\foxparts_error_log('WE have a match! ' . "\n" . '👉 $split_partnum[\'config\'] = ' . $split_partnum['config'] . "\n" . '👉 $split_product_name[\'config\'] = ' . $split_product_name['config'] . "\n" . '👉 $product = ' . print_r( $product, true ) );
            $details->country_of_origin = $product->country_of_origin;
            $details->hts_code = $product->hts_code;
            $details->schedule_b_export_code = $product->schedule_b_export_code;
            $details->circuit_condition = $product->circuit_condition;

            $rows[] = [ 'heading' => 'Description', 'value' => $product->description ];
            $rows[] = [ 'heading' => 'Part Series', 'value' => $details->name ];
            break;
          }
        }
      }
    }
  }

  $missing = [];
  $load_part_codes = ['4pF' => 'C', '6pF' => 'B', '7pF' => 'V', '8pF' => 'D', '9pF' => 'W', '10pF' => 'E', '12pF' => 'G', '12.5pF' => 'H', '13pF' => 'U', '14pF' => 'X', '15pF' => 'J', '16pF' => 'K', '18pF' => 'L', '19pF' => 'Y', '20pF' => 'M', '22pF' => 'N', '27pF' => 'P', '30pF' => 'Q', '32pF' => 'R', '33pF' => 'S', '50pF' => 'T'];
  foreach ( $row_map as $variable ) {
    $row = [];
    switch( $variable ){
      case 'frequency':
        $row = [ 'heading' => 'Frequency', 'value' => $frequency . 'MHz' ];
        break;

      case 'load':
        if( 'c' == strtolower( $configuredPart['product_type']['value'] ) || 'k' == strtolower( $configuredPart['product_type']['value'] ) ){
          $row['heading'] = 'Load Capacitance (pF)';
          if( isset( $load ) ){
            $row['part_code'] = $load;
            $row['value'] = $details->circuit_condition;
          } else if( isset( $details->circuit_condition ) ){
            $row['value'] = $details->circuit_condition;
            $row['part_code'] = $load_part_codes[$details->circuit_condition];
          }
        }
        break;

      case 'optemp':
        $row['heading'] = 'Operating Temperature';
        break;

      case 'product_type':
        $row['heading'] = 'Part Type';
        $frequency_display = ( $frequency && '0.0' != $frequency )? $frequency : $from_frequency . '-' . $to_frequency ;
        $package_type_labels = ['smd' => 'SMD', 'pin-thru' => 'Pin-Thru', 'vibration resistant' => 'Vibration Resistant'];
        $row['value'] = $configuredPart['product_type']['label'] . ' ' . $frequency_display . $configuredPart['frequency_unit']['label'] . ' - ' . $package_type_labels[ strtolower( $configuredPart['package_type']['label'] ) ];
        $row['part_code'] = $configuredPart['product_type']['value'];
        break;

      case 'stability':
        $row = ['heading' => 'Frequency Stability (PPM)'];
        break;

      case 'length_mm':
      case 'width_mm':
      case 'height_mm':
        $row['heading'] = ucwords( str_replace( '_mm',' (mm)', $variable ) );
        break;

      case 'schedule_b_export_code':
      case 'hts_code':
      case 'country_of_origin':
      case 'circuit_condition':
        if( 'circuit_condition' == $variable ){
          if( 'c' == strtolower( $configuredPart['product_type']['value'] ) || 'k' == strtolower( $configuredPart['product_type']['value'] ) ){
            $row['heading'] = 'Load Capacitance (pF)';
          } else {
            $row['heading'] = 'Output Load';
          }
        } else {
          $row['heading'] = get_key_label( $variable );
        }
        //$row['value'] = ( 'schedule_b_export_code' == $variable )? '<pre>$details = ' . print_r( $details, true) . '</pre>' : '...';
        break;

      default:
        $row['heading'] = get_key_label( $variable );
    }
    //if( stristr( strtolower( $row['heading'] ), 'load' ) )
      //foxparts_error_log('$variable = ' . $variable . "\n" .'$row = ' . print_r( $row, true ) );

    if( ! isset( $row['value'] ) && isset( $configuredPart[$variable] ) && '_' != $configuredPart[$variable]['value'] ){
      $row['value'] = $configuredPart[$variable]['label'];
      $row['part_code'] = $configuredPart[$variable]['value'];
    } else if( ! isset( $row['value'] ) && isset( $details->$variable ) ) {
      $row['value'] = $details->$variable;

      if( 'circuit_condition' == $variable && array_key_exists( $details->$variable, $load_part_codes ) )
        $row['part_code'] = $load_part_codes[$details->$variable];

      unset( $details->$variable );
    } else if(
      array_key_exists( $variable, $configuredPart )
      && '_' == $configuredPart[$variable]['value']
      && ! $$variable
      && ! empty( $$variable )
    ) {
      // Handle attributes with `_` as the value:
      $row['value'] = '';
      $row['part_code'] = '_';
    }

    // Font Awesome Icons for Y/N
    if( isset( $row['value'] ) && 'Y' == $row['value'] )
      $row['value'] = '<i class="fa fa-check-circle"></i>';
    if( isset( $row['value'] ) && 'N' == $row['value'] )
      $row['value'] = '<i class="fa fa-times-circle"></i>';

    if( ! array_key_exists('value', $row ) || '...' == $row['value'] ){
      if( is_user_logged_in() && current_user_can( 'edit_pages' ) ){
        //$row['value'] = '<code>Missing in the API? (This row only shows for logged in admins.)</code>';
        //\foxparts_error_log('$row = ' . print_r($row,true));
        $missing[] = $row['heading'];
        continue;
      } else {
        continue;
      }
    }

    if( 'Frequency' == $row['heading'] && ( '0MHz' == $row['value'] || '0.0MHz' == $row['value'] ) )
      continue;

    $rows[] = $row;
  }
  //foxparts_error_log('$rows = ' . print_r( $rows, true ) );

  //*
  if( $details ){

    foreach( $details as $key => $value ){
      if( ! empty( $value ) ){
        $row = [];
        switch( $key ){

          case 'product_family':
            // If we have a frequency, don't show a list of part numbers:
            if( $frequency && '0.0' != $frequency )
              break;

            if( ! is_user_logged_in() && ! is_admin() )
              break;

            if( 0 < count( $value ) ){
              foreach( $value as $product_family ){
                //\foxparts_error_log('‣ $partnum = ' . $partnum . "\n" . '‣ $product_family->name = ' . $product_family->name );

                if( stristr( $partnum, $product_family->name ) ){
                  if( 0 < count( $product_family->product ) ){
                    foreach( $product_family->product as $product ){
                      /**
                       * # Extract Frequency from Part Number
                       * I built the following regex with help from these:
                       *
                       * - https://regex101.com/r/lV0ExD/1/
                       * - https://stackoverflow.com/questions/24342026/in-php-how-to-get-float-value-from-a-mixed-string
                       */
                      /*
                      if( stristr( $product->name, '-' ) )
                        continue; // Product names with `dashes` are custom products, we skip them
                      /**/

                      $split_product_name = split_part_number( $product->name );
                      //\foxparts_error_log( print_r( $split_product_name, true ) );
                      /*
                      preg_match( '/(^.*?)([\d]+(?:\.[\d]+)+)$/', $product->name, $matches );
                      if( ! $matches )
                        continue;
                      $product_family = $matches[1];
                      $part_frequency = $matches[2];
                      */
                      $product_family = $split_product_name['company'] . $split_product_name['part_type'] . $split_product_name['size_or_output'] . $split_product_name['config'];
                      $part_frequency = $split_product_name['frequency'];

                      if( match_with_underscores( $partnum, $product_family ) ){
                        $product_name = ( ! empty( $part_frequency ) )? '<a href="' . get_site_url() . '/foxpart/' . $product_family . '/' . $part_frequency . '" target="_blank">' . $product->name . '</a>' : $product->name ;
                        $partlist[] = $product_name;
                      }
                    }
                  }
                  $partlist_html = ( isset($partlist) && is_array( $partlist ) && 0 < count( $partlist ) )? implode(', ', $partlist ) : '<code>No parts found.</code>' ;
                  $row = [ 'heading' => 'Products', 'value' => '<div class="alert alert-info" style="margin-bottom: 20px;">NOTE: This row only shows to logged in Fox Website Admins. Regular users will not see the below list of parts:</div>' . $partlist_html ];
                }
              }
            }
            break;

          case 'circuit_condition':
          case 'data_sheet_url':
          case 'product_photo_part_image';
          case 'standard_lead_time_weeks':
          case 'output':
          case 'name':
          case 'package_name':
          case 'part_type':
          case 'extended_product_families':
            // nothing, skip
            break;

          default:
            $label = ( $frequency )? $key : get_key_label( $key );
            if( 'Y' == $value )
              $value = '<i class="fa fa-check-circle"></i>';
            $row = [ 'heading' => $label, 'value' => $value, 'part_code' => $key ];
        }
        if( isset( $row ) && ! empty( $row ) )
          $rows[] = $row;
      }
    }
  }
  /**/
  $html = ( $frequency )? '<h3>Product Details</h3>' : '<h3>Product Family Details</h3>';
  $html.= '<table class="table table-striped table-sm"><colgroup><col style="width: 30%" /><col style="width: 5%;" /><col style="width: 65%;" /></colgroup>';
  if( isset( $last_row ) )
    $rows[] = $last_row;
  foreach( $rows as $key => $row ){
    $value = ( isset( $row['value'] ) )? $row['value'] : '' ;
    $header_col = ( isset( $row['part_code'] ) && ! empty( $row['part_code'] ) )? '<th scope="row">' . $row['heading'] . '</th><td style="text-align: center;"><code style="color: #333;">' . $row['part_code'] . '</code></td>' : '<th scope="row" colspan="2">' . $row['heading'] . '</th>';
    $html.= '<tr>' . $header_col . '<td>' . $value . '</td></tr>';
  }
  $html.= '</table>';
  if( is_user_logged_in() && current_user_can( 'edit_pages' ) && ( 0 < count($missing) ) )
    $html.= '<div class="alert alert-info" style="font-size: 14px;">The following rows had missing data and are not displayed in the table above: <ul><li>' . implode( '</li><li>', $missing ) . '</li></ul></div>';

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
  //\foxparts_error_log( '📡 Requesting: ' . $sf_api_request_url );
  $sfRequest = \WP_REST_Request::from_url( $sf_api_request_url );
  $sfResponse = \FoxParts\restapi\get_part_series( $sfRequest );

  if( $sfResponse->data && 0 < count($sfResponse->data) ){
    // Match to correct product series within the product family:
    foreach( $sfResponse->data as $part_series_data ){
      if( is_object( $part_series_data ) && $part_series == $part_series_data->name ){
        switch( $args['detail'] ){
          case 'data_sheet_url_part':
            //\foxparts_error_log('Accessing `data_sheet_url_part`:');
            // Part specific data sheets are found inside product families
            if( 0 < count( $part_series_data->product_family ) ){
              foreach( $part_series_data->product_family as $product_family ){

                // Set our $haystack to be the longer of the two strings:
                if( strlen( $product_family->name ) > strlen( $args['partnum'] ) ){
                  $haystack = $product_family->name;
                  $needle = $args['partnum'];
                } else {
                  $haystack = $args['partnum'];
                  $needle = $product_family->name;
                }

                if( stristr( $haystack, $needle ) ){
                  if( 0 < count( $product_family->product ) ){

                    $full_part_no = $args['partnum'] . $args['frequency'];
                    $split_full_part_no = split_part_number( $full_part_no );

                    foreach( $product_family->product as $product ){

                      $split_product_name = split_part_number( $product->name );
                      if( $split_product_name['frequency'] == $args['frequency'] ){
                        /**
                         * In the case of Crystals, the config can have an underscore for the `load`.
                         * So we must match the config with underscores with the non-underscored
                         * part numbers returned from the Salesforce API.
                         */
                        $match = match_with_underscores( $split_full_part_no['config'], $split_product_name['config'] );
                        if( $match ){
                          //\foxparts_error_log('👉 $split_full_part_no = ' . $split_full_part_no['config'] . "\n" . '👉 $split_product_name[config] = ' . $split_product_name['config']  );
                          $detail = $args['detail'];
                          if( property_exists( $product, $detail ) && ! empty( $product->$detail ) ){
                            //\foxparts_error_log('`' . $detail . '` = ' . $product->$detail );
                            return $product->$detail;
                          }
                        }
                      }

                      /*
                      if( $full_part_no == $product->name ){
                        //$detail = $args['detail'];
                        return ( property_exists( $product, $args['detail'] ) )? $product->$args['detail'] : '' ;
                      }
                      */

                      //return $product->$detail;
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

/**
 * Gets a human readable label given a key supplied from the Salesforce API JSON
 *
 * @param      string  $key    The key
 *
 * @return     string  The label.
 */
function get_key_label( $key ){
  $keys_and_labels = ['export_control_classification_number' => 'Export Control','hts_code' => 'Harminized Tariff', 'individual_part_weight_grams' => 'Individual Part Weight (g)', 'moisture_sensitivity_level_msl' => 'Moisture Sensitivity Level (MSL)', 'packaging' => 'Packaging Method', 'part_life_cycle_status' => 'Life Cycle Status', 'reach_compliant' => 'REACH Compliant', 'rohs_compliant' => 'RoHS Compliant','schedule_b_export_code' => 'Code Schedule B', 'static_sensitive' => 'Static Sensitive'];
  if( array_key_exists($key, $keys_and_labels ) )
    return $keys_and_labels[$key];

  switch( $key ){
    default:
      $key = str_replace( '_', ' ', $key );
      $ignore = ['my', 'is', 'to', 'the', 'of'];
      $pattern = "~^[a-z]+|\b(?|" . implode("|", $ignore) . ")\b(*SKIP)(*FAIL)|[a-z]+~";
      $modified_label = preg_replace_callback( $pattern, function($m) { return ucfirst($m[0]); }, $key );
      $label = ( stristr( $key, 'mm' ) )? \ucfirst( str_replace( 'mm', '(mm)', $key ) ) : $modified_label;
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
  //foxparts_error_log( '🔔 $atts = ' . print_r( $atts, true ) );
  $lc_attribute = strtolower( $args['attribute'] );

  switch( $lc_attribute ){
    case 'optemp':
      $labels = [ 'B' => '-10 To +50 C', 'D' => '-10 To +60 C', 'E' => '-10 To +70 C', 'Q' => '-20 To +60 C', 'F' => '-20 To +70 C', 'Z' => '-20 To +75 C', 'N' => '-20 To +85 C', 'G' => '-30 To +70 C', 'H' => '-30 To +75 C', 'J' => '-30 To +80 C', 'K' => '-30 To +85 C', 'L' => '-35 To +80 C', 'V' => '-35 To +85 C', 'P' => '-40 To +105 C', 'I' => '-40 To +125 C', 'Y' => '-40 To +75 C', 'M' => '-40 To +85 C', 'R' => '-55 To +100 C', 'S' => '-55 To +105 C', 'T' => '-55 To +125 C', 'U' => '-55 To +85 C', 'A' => '0 To +50 C', 'C' => '0 To +70 C', 'W' => 'Other' ];
      break;

    case 'output':
      $labels = [ 'A'  => 'Clipped Sine', 'C'  => 'Clipped Sine', 'G'  => 'Clipped Sine', 'H'  => 'HCMOS', 'S'  => 'HCMOS', 'HB' => 'HCMOS', 'HD' => 'HCMOS', 'HH' => 'HCMOS', 'HL' => 'HCMOS', 'HS' => 'HCMOS', 'HK' => 'HCMOS', 'PD' => 'LVPECL', 'PS' => 'LVPECL', 'PU' => 'LVPECL', 'LD' => 'LVDS', 'LS' => 'LVDS', 'SL' => 'HCSL', 'HA' => 'HCMOS, AEC-Q200' ];
      break;

    case 'package_option':
      $labels = ['AS' => 'SMD', 'AQ' => 'SMD', 'BA' => 'SMD', 'BS' => 'SMD', 'ST' => 'Pin-Thru', 'UT' => 'Pin-Thru', '0T' => 'Pin-Thru', '15' => 'Pin-Thru', '26' => 'Pin-Thru', '38' => 'Pin-Thru', 'VR' => 'Vibration Resistant'];
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

/**
 * Splits a part number into the `part_series` and `frequency`.
 *
 * @param      string         $partnum  The part number
 *
 * @return     array|boolean  Returns an array with keys part_series and frequency. `False` if $partnum is null.
 */
function split_part_number( $partnum = null ){
  if( is_null( $partnum ) )
    return false;

  $part_number = [ 'part_series' => null, 'frequency' => null, 'config' => null, 'size_or_output' => null, 'part_type' => null, 'packaging' => null ];
  \preg_match( '/(?<company>F|f)?(?![f])(?<!_)(?<part_type>[a-z]){1}(?<size_or_output>[0-9a]{1}|[0-9]{3})?(?<config>[a-z_]+)?(?<frequency>[0-9]+\.[0-9]+)?(?<packaging>-{1}[a-z0-9]*)?$/i', $partnum, $matches );
  if( $matches && $partnum == $matches[0] ){

    foreach( $matches as $key => $value ){
      if( ! is_numeric( $key ) )
        $part_number[$key] = $value;
    }
    $part_number['fullsearch'] = $matches[0];
    $part_number['search'] = $part_number['part_type'] . $part_number['size_or_output'] . $part_number['config'];
  }

  // remove dash from frequency
  if( stristr( $part_number['frequency'], '-' ) ){
    $array = explode( '-', $part_number['frequency'] );
    $no_dash_frequency = $array[0];
    $part_number['fullsearch'] = str_replace( $part_number['frequency'], $no_dash_frequency, $part_number['fullsearch'] );
    $part_number['frequency'] = $no_dash_frequency;
  }

  // Remove load value from Crystals
  if( 'c' == strtolower( $part_number['part_type'] ) && 5 <= strlen( $part_number['config'] ) ){
    $config_array = str_split( $part_number['config'] );
    $part_number['load'] = $config_array[4];
    $config_array[4] = '_';
    $search = $part_number['part_type'] . $part_number['size_or_output'] . implode('', $config_array );
    $part_number['search'] = $search;
  }
  //foxparts_error_log('$part_number = ' . print_r($part_number,true) );
  return $part_number;
}

function match_with_underscores( $with_underscores = null, $without_underscores = null ){
  if( is_null( $with_underscores ) || is_null( $without_underscores ) )
    return false;

  /**
   * Match up to the underscore if $with_underscores is longer than
   * $without_underscores. Example:
   *
   * We are searching on `CABSHH` which has a `config` settings of
   * `BSHH`. We want to be able to match `BSHH_F` in the WP database
   * so, we remove `_F` from $with_underscores.
   */
  if( strlen( $with_underscores ) > strlen( $without_underscores ) ){
    $with_underscores = substr( $with_underscores, 0, strlen( $without_underscores ) );


    //$split_with_underscores = explode( '_', $with_underscores );
    //$with_underscores = $split_with_underscores[0];
    //\foxparts_error_log( '' . "\n" . '👉 $with_underscores = ' . $with_underscores . "\n" . '👉 $without_underscores = ' . $without_underscores );
  }

  if( strlen( $with_underscores ) != strlen( $without_underscores ) )
    return false;

  $with_underscores = str_split( $with_underscores );
  $without_underscores = str_split( $without_underscores );
  //\foxparts_error_log('👉 $with_underscores = ' . print_r( $with_underscores, true ) . "\n" . '👉 $without_underscores = ' . print_r( $without_underscores, true ) );
  foreach( $with_underscores as $key => $value ){
    if( '_' != $value ){

      if( strtolower( $value ) != strtolower( $without_underscores[$key] ) )
        return false;
    }
  }

  return true;
}
