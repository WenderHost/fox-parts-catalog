<?php

namespace FoxParts\utilities;

/**
 * Available functions:
 *
 * get_part_details_table()
 * get_product_family_details()
 * get_product_family_from_partnum()
 * get_key_label()
 *
 */

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
