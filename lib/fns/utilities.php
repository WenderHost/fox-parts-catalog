<?php

namespace FoxParts\utilities;

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
  }

  foreach ($configuredPart as $key => $value) {
    $$key = $value;
  }

  /**
   * Salesforce API Additional Part Details
   *
   * How do we specify the "ideal" frequency? That is, how do
   * we specify the frequency that matches a part in
   * Salesforce? For example, AFAIK only `FO7HSCBE60.0` exists
   * inside Salesforce. However, if we are accessing the page
   * for this part via WordPress, by default the frequency
   * isn't set, and I have to pick a frequency between the
   * `from_frequency` and `to_frequency` we have in the
   * database. In the case of `FO7HSCBE`, those are 0.012MHz
   * to 170MHz.
   **/
  $sf_api_request_url = get_site_url( null, 'wp-json/foxparts/v1/get_web_part?partnum=' . $partnum . '60.0' );
  $sfRequest = \WP_REST_Request::from_url( $sf_api_request_url );
  $sfResponse = \FoxParts\restapi\get_web_part( $sfRequest );

  $extraData = [];
  if( $sfResponse->data ){
    $salesForcePartDetails = $sfResponse->data;
    foreach ($salesForcePartDetails as $key => $value) {
      $extraData[$key] = $value;
    }
  }

  $table_row_maps = [
    'c' => ['product_type','size','tolerance','stability','load','optemp'],
    'o' => ['product_type','size','output','voltage','stability','optemp'],
  ];

  if( ! array_key_exists( strtolower( $product_type['value'] ) , $table_row_maps ) )
    return '<div class="alert alert-warn">No table row map found for Product Type "' . $product_type['label'] . '".</div>';

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
        $row['value'] = $product_type['label'] . ' ' . $from_frequency['value'] . '-' . $to_frequency['value'] . $frequency_unit['label'] . ' - ' . strtoupper( $package_type['label'] );
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
  if( $extraData ){
    foreach( $extraData as $key => $value ){
      if( ! empty( $value->value ) ){
        switch( $key ){
          case 'data_sheet_url':
            $html.= '<tr><th scope="row">' . $value->label . '</th><td><a href="' . $value->value . '" target="_blank">Download Datasheet</a></td></tr>';
            break;

          case 'product_photo_part_image_url':
            $html.= '<tr><th scope="row">' . $value->label . '</th><td><img src="' . $value->value . '" alt="Part ' . $extraData['part_number']->value . '" style="width: 120px;" /></td></tr>';
            break;

          default:
            $html.= '<tr><th scope="row">' . $value->label . '</th><td>' . $value->value . '</td></tr>';
        }

      }
    }
  }
  $html.= '</table>';

  return $html;
}