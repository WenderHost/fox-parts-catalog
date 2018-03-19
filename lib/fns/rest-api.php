<?php
namespace FoxParts\restapi;

function init_rest_api(){

  // Get available options for a given part configuration
  register_rest_route( 'foxparts/v1', 'get_options/part=(?P<part>(F)([a-zA-Z0-9_]{8})-[0-9]+.[0-9]+)', [
    'methods' => 'GET',
    'callback' => __NAMESPACE__ . '\\get_options'
  ]);
}
add_action( 'rest_api_init', __NAMESPACE__ . '\\init_rest_api' );

/**
 * Retrieve available options given a part's configuration.
 *
 * @param      string $part The Fox part number
 */
function get_options( $data ){

  $response = new \stdClass();

  $part = $data['part'];
  $part_array = explode( '-', $part );
  $options = $part_array[0];
  $frequency = $part_array[1];

  $start = ( 'F' == substr( $options, 0, 1) )? 1 : 0;
  $options_array = str_split( substr( $options, $start ) );
  $part_type = $options_array[0];

  $configuredPart = [];
  $configuredPart['frequency'] = $frequency;

  $keys = [
    'C' => [ 0 => 'part_type', 1 => 'size', 2 => 'package_option', 3 => 'package_option', 4 => 'tolerance', 5 => 'stability', 6 => 'load', 7 => 'optemp' ],
    'O' => [ 0 => 'part_type', 1 => 'size', 2 => 'package_option', 3 => 'package_option', 4 => 'voltage', 5 => 'stability', 6 => 'optemp' ],
  ];
  if( ! array_key_exists( $part_type, $keys ) ){
    $response = new \WP_Error('nometakeys', __( 'No meta keys have been mapped for `' . $part_type . '`.', 'fox-parts-catalog' ) );
    wp_send_json( $response, 400 );
  }

  $meta_keys = $keys[$part_type];

  //$keys = [ 0 => 'part_type', 1 => 'size', 2 => 'package_option', 3 => 'package_option', 4 => 'tolerance', 5 => 'stability', 6 => 'load', 7 => 'optemp' ];
  for ($i=0; $i < count($options_array); $i++) {
    $key = ( array_key_exists( $i, $meta_keys ) )? $meta_keys[$i] : false;

    switch ($i) {
      case 3:
        continue;
      case 2:
        $configuredPart[$meta_keys[$i]] = $options_array[$i].$options_array[$i + 1];
        break;
      default:
        if( ! $key )
          break(2);
        $configuredPart[$key] = $options_array[$i];
        break;
    }
  }
  $response->configuredPart = $configuredPart;
  error_log( str_repeat( '-', 80 ) );

  $tax_query = [
    [
      'taxonomy' => 'part_type',
      'field' => 'slug',
      'terms' => get_part_type_slug( $options_array[0] ),
    ]
  ];

  $meta_query = [];
  if( isset( $configuredPart['frequency'] ) && '' != $configuredPart['frequency'] && '_' != $configuredPart['frequency'] && '0.0' != $configuredPart['frequency'] ){
    $frequency = floatval( $configuredPart['frequency'] );
    $meta_query[] = [
      'relation' => 'AND',
      [
        'key' => 'from_frequency',
        'value' => $frequency,
        'compare' => '<=',
        'type' => 'DECIMAL'
      ],
      [
        'key' => 'to_frequency',
        'value' => $frequency,
        'compare' => '>=',
        'type' => 'DECIMAL'
      ]
    ];
  }

  if( isset( $configuredPart['size'] ) && '' != $configuredPart['size'] && '_' != $configuredPart['size'] ){
    $meta_query[] = [
      'key' => 'size',
      'value' => $configuredPart['size'],
      'compare' => '='
    ];
  }

  if( isset( $configuredPart['package_option'] ) && '' != $configuredPart['package_option'] && '_' != $configuredPart['package_option'] ){
    $meta_query[] = [
      'key' => 'package_option',
      'value' => $configuredPart['package_option'],
      'compare' => '='
    ];
  }

  if( isset( $configuredPart['tolerance'] ) && '' != $configuredPart['tolerance'] && '_' != $configuredPart['tolerance'] ){
    $meta_query[] = [
      'key' => 'tolerance',
      'value' => $configuredPart['tolerance'],
      'compare' => '='
    ];
  }

  if( isset( $configuredPart['stability'] ) && '' != $configuredPart['stability'] && '_' != $configuredPart['stability'] ){
    $meta_query[] = [
      'key' => 'stability',
      'value' => $configuredPart['stability'],
      'compare' => '='
    ];
  }

  if( isset( $configuredPart['voltage'] ) && '' != $configuredPart['voltage'] && '_' != $configuredPart['voltage'] ){
    $meta_query[] = [
      'key' => 'voltage',
      'value' => $configuredPart['voltage'],
      'compare' => '='
    ];
  }

  /**
   * 03/07/2018 (11:29) - Data supplied by Fox doesn't include `load`
   * so we shouldn't filter by `load` since that will nullify all
   * results.

  if( isset( $configuredPart['load'] ) && '' != $configuredPart['load'] && '_' != $configuredPart['load'] ){
    $meta_query[] = [
      'key' => 'load',
      'value' => $configuredPart['load'],
      'compare' => '='
    ];
  }
  */

  if( isset( $configuredPart['optemp'] ) && '' != $configuredPart['optemp'] && '_' != $configuredPart['optemp'] ){
    $meta_query[] = [
      'key' => 'optemp',
      'value' => $configuredPart['optemp'],
      'compare' => '='
    ];
  }

  error_log( '$meta_query = ' . print_r( $meta_query, true ) . '; $tax_query = ' . print_r( $tax_query, true ) );

  $query = new \WP_Query([
    'post_type' => 'foxpart',
    'posts_per_page' => -1,
    'tax_query' => $tax_query,
    'meta_query' => $meta_query,
  ]);

  $response->message = ( ! $query->have_posts() )? '0 part options found.' : $query->post_count . ' part options found.';
  $response->availableParts = $query->post_count;
  error_log( $response->message );

  foreach ($configuredPart as $key => $value) {
      $var = $key . '_options';
      $$var = [];
      if( $query->have_posts() ){
        foreach ($query->posts as $post ) {
          $option = get_post_meta( $post->ID, $key, true );
          if( ! in_array( $option, $$var ) )
            $$var[] = $option;
        }
      }
      $response->partOptions[$key] = ( $mapped_values = map_values_to_labels( $key, $$var ) )? $mapped_values : $$var ;
  }

  wp_send_json( $response, 200 );
}

/**
 * Provided a part type code, returns the part type slug.
 *
 * @param      string  $part_type  The part type code
 *
 * @return     string   The part type slug.
 */
function get_part_type_slug( $part_type_code ){
  $part_types = FOXPC_PART_TYPES;

  if( ! array_key_exists( $part_type_code, $part_types ) ){
    $response = new \WP_Error('invalidparttype', __('No part type slug found for ' . $part_type_code,'fox-parts-catalog') );
    wp_send_json( $response, 400 );
  }

  return $part_types[$part_type];
}

/**
 * Provided a setting, returns values mapped to labels.
 *
 * @param      string         $setting  The setting
 * @param      array          $values   The values
 *
 * @return     array|boolean  Returns the values mapped to labels.
 */
function map_values_to_labels( $setting, $values = [] ){

  if( 0 == count( $values ) || ! is_array( $values ) )
    return false;

  $mapped_values = [];

  asort( $values );

  switch( $setting ){
    case 'optemp':
      $labels = ['B' => '-10 To +50 C', 'D' => '-10 To +60 C', 'E' => '-10 To +70 C', 'Q' => '-20 To +60 C', 'F' => '-20 To +70 C', 'Z' => '-20 To +75 C', 'N' => '-20 To +85 C', 'G' => '-30 To +70 C', 'H' => '-30 To +75 C', 'J' => '-30 To +80 C', 'K' => '-30 To +85 C', 'L' => '-35 To +80 C', 'V' => '-35 To +85 C', 'P' => '-40 To +105 C', 'I' => '-40 To +125 C', 'Y' => '-40 To +75 C', 'M' => '-40 To +85 C', 'R' => '-55 To +100 C', 'S' => '-55 To +105 C', 'T' => '-55 To +125 C', 'U' => '-55 To +85 C', 'A' => '0 To +50 C', 'C' => '0 To +70 C', 'W' => 'Other'];
    break;
    case 'size':
      $labels = ['A' => '1.2x1.0 mm', '0' => '1.6x1.2 mm', '1' => '2.0x1.6 mm', '2' => '2.5x2.0 mm', '3' => '3.2x2.5 mm', '4' => '4.0x2.5 mm', '5' => '5.0x3.2 mm', '6' => '6.0x3.5 mm', '7' => '7.0x5.0 mm', '8' => '10.0x4.5 mm', '8' => '11.0x5.0 mm', '4SD' => 'HC49 SMD (4.5mm)', '9SD' => 'HC49 SMD (3.2mm)'];
    break;
    case 'stability':
      $labels = ['M' => '-0.036+-1 ppm (Delta temp)E^2', 'I' => '-0.04 ppm (Delta Temp)E^2 max', 'O' => '-140 ~ +10 ppm', 'K' => '0.28 ppm', 'Q' => '0.37 ppm', 'U' => '0.5 ppm', 'T' => '1.0 ppm', 'S' => '1.5 ppm', 'H' => '10.0 ppm', 'G' => '100 ppb', 'A' => '100.0 ppm', 'Y' => '1000.0 ppm', 'F' => '15.0 ppm', 'R' => '2.0 ppm', 'P' => '2.5 ppm', 'E' => '20.0 ppm', 'V' => '200.0 ppm', 'D' => '25.0 ppm', 'N' => '3.0 ppm', 'C' => '30.0 ppm', 'L' => '5.0 ppm', 'B' => '50.0 ppm', 'W' => '70.0 ppm', 'J' => '8 ppm', 'Z' => 'Other', 'X' => 'Overall'];
    break;
    case 'tolerance':
      $labels = ['M' => '-0.036+-1 ppm (Delta temp)E^2', 'I' => '-0.04 ppm (Delta Temp)E^2 max', 'O' => '-140 ~ +10 ppm', 'K' => '0.28 ppm', 'Q' => '0.37 ppm', 'U' => '0.5 ppm', 'T' => '1.0 ppm', 'S' => '1.5 ppm', 'H' => '10.0 ppm', 'G' => '100 ppb', 'A' => '100.0 ppm', 'Y' => '1000.0 ppm', 'F' => '15.0 ppm', 'R' => '2.0 ppm', 'P' => '2.5 ppm', 'E' => '20.0 ppm', 'V' => '200.0 ppm', 'D' => '25.0 ppm', 'N' => '3.0 ppm', 'C' => '30.0 ppm', 'L' => '5.0 ppm', 'B' => '50.0 ppm', 'W' => '70.0 ppm', 'J' => '8 ppm', 'Z' => 'Other', 'X' => 'Overall'];
      break;

    default:
      return false;
      break;
  }

  foreach ($values as $key => $value) {
    $mapped_values[] = [ 'value' => $value, 'label' => $labels[$value] ];
  }

  return $mapped_values;
}