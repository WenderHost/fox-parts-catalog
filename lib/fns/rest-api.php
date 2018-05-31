<?php
namespace FoxParts\restapi;

function init_rest_api(){

  // Get available options for a given part configuration
  register_rest_route( 'foxparts/v1', 'get_options/(?P<part>(F)([a-zA-Z0-9_\[\],]{6,32})-[0-9]+.[0-9]+)/(?P<package_type>(SMD|Pin-Thru))/(?P<frequency_unit>(MHz|kHz))', [
    'methods' => 'GET',
    'callback' => __NAMESPACE__ . '\\get_options'
  ]);

  $enable_cors = defined('JWT_AUTH_CORS_ENABLE') ? JWT_AUTH_CORS_ENABLE : false;
  if( $enable_cors )
    header('Access-Control-Allow-Origin: *');
}
add_action( 'rest_api_init', __NAMESPACE__ . '\\init_rest_api', 15 );

/**
 * Retrieve available options given a part's configuration.
 *
 * @param      string $part The Fox part number
 */
function get_options( $data ){

  $response = new \stdClass();

  $part = $data['part'];
  $package_type = strtolower($data['package_type']);
  $frequency_unit = strtolower($data['frequency_unit']);
  $part_array = explode( '-', $part );
  $options = $part_array[0];
  $frequency = $part_array[1];

  error_log( str_repeat( '-', 80 ) );

  $start = ( 'F' == substr( $options, 0, 1) )? 1 : 0;
  $options_array = str_split( substr( $options, $start ) );
  $part_type = $options_array[0];

  $configuredPart = [];
  $configuredPart['frequency'] = $frequency;
  $configuredPart['package_type'] = $package_type;
  $configuredPart['frequency_unit'] = $frequency_unit;
  $configuredPart['number'] = $options;

  /**
   * FOX PART NUMBER MAPPINGS
   *
   * Here we map each Part No. component to the corresponding key(s) of our
   * incoming part number contained in $options_array.
   */
  $part_no_maps = [
    'C' => ['part_type' => [0], 'size' => [1], 'package_option' => [2,3], 'tolerance' => [4], 'stability' => [5], 'load' => [6], 'optemp' => [7]],
    'K' => ['part_type' => [0], 'size' => [1,2,3], 'tolerance' => [4], 'stability' => [5], 'optemp' => [6]],
    'O' => ['part_type' => [0], 'size' => [1], 'output' => [2,3], 'voltage' => [4], 'stability' => [5], 'optemp' => [6]],
    'T' => ['part_type' => [0], 'size' => [1], 'output' => [2], 'pin_1' => [3], 'voltage' => [4], 'stability' => [5], 'optemp' => [6]],
    'Y' => ['part_type' => [0], 'size' => [1], 'output' => [2], 'voltage' => [3], 'stability' => [4], 'optemp' => [5]],
  ];

  if( ! array_key_exists( $part_type, $part_no_maps ) ){
    $response = new \WP_Error('nometakeys', __( 'No meta keys have been mapped for `' . $part_type . '`.', 'fox-parts-catalog' ) );
    wp_send_json( $response, 400 );
  }

  $meta_keys = $part_no_maps[$part_type];
  foreach( $meta_keys as $key => $key_ids ){
    if( ! is_array( $key_ids ) ){
      $response = new \WP_Error('notanarray', __( 'Invalid key mapping for `' . $part_type . '`. I was expecting an array.', 'fox-parts-catalog' ) );
      wp_send_json( $response, 400 );
    }
    $value = '';
    foreach( $key_ids as $id ){

      // Check for an array of options within our Part No (e.g. [PS,PD,PU])
      if( '[' == $options_array[$id] ){
        $chars = '';
        while ( '[' != next($options_array) ) {
          // nothing, advance the array to the next sub-array (i.e. `[`)
        }
        while( ']' != ( $char = next($options_array ) ) ){
          $chars.= $char;
        }
        $value = explode( ',', $chars );

        // Rebuild $options_array by replacing the sub-array with `_` so we can continue parsing it as normal
        $options = str_replace( '[' . $chars . ']', str_repeat('_',count($key_ids)), $options );
        $options_array = str_split( substr( $options, $start ) );
        break;
      } else {
        $value .= $options_array[$id];
      }
    }
    $configuredPart[$key] = $value;
  }
  $response->configuredPart = $configuredPart;
  error_log( 'configuredPart = ' . print_r( $configuredPart, true ) );

  $tax_query = [
    [
      'taxonomy' => 'part_type',
      'field' => 'slug',
      'terms' => get_part_type_slug( $part_type ),
    ]
  ];

  $meta_query = [];

  // Frequency
  if( isset( $configuredPart['frequency'] )
      && '' != $configuredPart['frequency']
      && '_' != $configuredPart['frequency']
      && '0.0' != $configuredPart['frequency'] ){ // && '0.032768' != $configuredPart['frequency']

    $frequency = (float) $configuredPart['frequency'];

    if( 'khz' == $frequency_unit )
      $frequency = (float) $frequency/1000;

    /**
     * WP_Query BUG for floating point no. and type = DECIMAL
     *
     * When `type` = DECIMAL for floating point numbers,
     * the resulting SQL used by WordPress casts the meta field value
     * as DECIMAL but the query seems to ignore everything after the
     * decimal. To fix, we set the type to CHAR for numbers with a
     * decimal.
     */
    $type = ( stristr( $frequency, '.') )? 'CHAR' : 'DECIMAL' ;

    $meta_query[] = [
      'relation' => 'AND',
      [
        'key' => 'from_frequency',
        'value' => $frequency,
        'compare' => '<=',
        'type' => $type
      ],
      [
        'key' => 'to_frequency',
        'value' => $frequency,
        'compare' => '>=',
        'type' => $type
      ]
    ];
  }

  // Package Option
  switch($configuredPart['part_type']){
    case 'C':
      // If we have a `package_option`, query by that option
      if( isset( $configuredPart['package_option'] ) && '' != $configuredPart['package_option'] && ! stristr( $configuredPart['package_option'], '_' ) ){
        $meta_query[] = [
          'key' => 'package_option',
          'value' => $configuredPart['package_option'],
          'compare' => '='
        ];
      } else {
        // If we don't have a `package_option`, narrow our results based on the `package_type`
        $pin_thru_crystal_part_types = ['ST','UT','0T'];
        $compare = ( 'pin-thru' == $configuredPart['package_type'] )? 'IN' : 'NOT IN';
        $meta_query[] = [
          'key' => 'package_option',
          'value' => $pin_thru_crystal_part_types,
          'compare' => $compare,
        ];
      }
      break;

    case 'K':
      $pin_thru_khzcrystal_part_types = ['T15','T26','T38'];
      $compare = ( 'pin-thru' == $configuredPart['package_type'] )? 'IN' : 'NOT IN';
      $meta_query[] = [
        'key' => 'size',
        'value' => $pin_thru_khzcrystal_part_types,
        'compare' => $compare,
      ];
      break;

    default:
      if( isset( $configuredPart['package_option'] ) && '' != $configuredPart['package_option'] && ! stristr( $configuredPart['package_option'], '_' ) ){
        $meta_query[] = [
          'key' => 'package_option',
          'value' => $configuredPart['package_option'],
          'compare' => '='
        ];
      }

  }

  // All other options
  $allowed_meta_queries = ['size','output','tolerance','voltage','stability','optemp','pin_1'];
  foreach ( $allowed_meta_queries as $meta_field ) {
    if( isset( $configuredPart[$meta_field] ) && '' != $configuredPart[$meta_field] ){
      $value = $configuredPart[$meta_field];
      if( is_string( $value ) && stristr($value, '_' ) )
        continue;

      $compare = ( is_array( $value ) )? 'IN' : '=';
      $meta_query[] = [
        'key' => $meta_field,
        'value' => $value,
        'compare' => $compare
      ];
    }
  }

  //error_log( '$meta_query = ' . print_r( $meta_query, true ) . '; $tax_query = ' . print_r( $tax_query, true ) );

  $query = new \WP_Query([
    'post_type' => 'foxpart',
    'posts_per_page' => -1,
    'tax_query' => $tax_query,
    'meta_query' => $meta_query,
  ]);

  $response->message = ( ! $query->have_posts() )? '0 part options found (' . $data['part'] . ', ' . strlen( $configuredPart['number'] ) . ' chars in part no).' : $query->post_count . ' part options found.';
  $response->availableParts = $query->post_count;
  error_log( $response->message );

  foreach ($configuredPart as $key => $value) {
      $var = $key . '_options';
      $$var = [];
      if( $query->have_posts() ){
        foreach ($query->posts as $post ) {
          $option = get_post_meta( $post->ID, $key, true );

          // MHz_Crystals_Pin-Thru sizes are size+package_option
          if( 'size' == $key && 'C' == $part_type && $package_type == 'pin-thru' ){
            $package_option = get_post_meta( $post->ID, 'package_option', true );
            if( $package_option )
              $option .= $package_option;
          }

          if( ! in_array( $option, $$var ) )
            $$var[] = $option;
        }
      }
      //if( 'size' == $key && 'C' == $part_type && $package_type == 'pin-thru' )
        //error_log( 'Size options for Pin-Thur: ' . print_r( $$var, true ) );
      $response->partOptions[$key] = ( $mapped_values = map_values_to_labels(['setting' => $key, 'values' => $$var, 'package_type' => $package_type, 'part_type' => $part_type]) )? $mapped_values : $$var ;
  }

  wp_send_json( $response, 200 );
}

/**
 * Provided a part type code, returns the part type slug.
 *
 * @param      string  $part_type_code  The part type code
 *
 * @return     string   The part type slug.
 */
function get_part_type_slug( $part_type_code ){
  $part_types = FOXPC_PART_TYPES;

  if( ! array_key_exists( $part_type_code, $part_types ) ){
    $response = new \WP_Error('invalidparttype', __('No part type slug found for ' . $part_type_code,'fox-parts-catalog') );
    wp_send_json( $response, 400 );
  }

  return $part_types[$part_type_code];
}

/**
 * Provided a setting, returns values mapped to labels.
 *
 * @param      array $atts{
 *    @type      string         $setting       The setting
 *    @type      array          $values        The values
 *    @type      string         $package_type  SMD or Pin-Thru
 * }
 *
 * @return     array|boolean  Returns the values mapped to labels.
 */
function map_values_to_labels( $atts ){

  $args = shortcode_atts([
    'setting'         => null,
    'values'          => [],
    'package_type'    => 'SMD',
    'package_option'  => null,
    'part_type'       => null,
  ], $atts );

  if( 0 == count( $args['values'] ) || ! is_array( $args['values'] ) )
    return false;

  $mapped_values = [];

  asort( $args['values'] );

  $labels = [];
  switch( $args['setting'] ){
    case 'load':
      $labels = ['B' => '6 pF', 'C' => '4 pF', 'D' => '8 pF', 'E' => '10 pF', 'G' => '12 pF', 'H' => '12.5 pF', 'J' => '15 pF', 'K' => '16 pF', 'L' => '18 pF', 'M' => '20 pF', 'N' => '22 pF', 'P' => '27 pF', 'Q' => '30 pF', 'R' => '32 pF', 'S' => '33 pF', 'T' => '50 pF', 'U' => '13 pF', 'V' => '7 pF', 'W' => '9 pF', 'X' => '14 pF', 'Y' => '19 pF'];
      break;

    case 'optemp':
      $labels = ['B' => '-10 To +50 C', 'D' => '-10 To +60 C', 'E' => '-10 To +70 C', 'Q' => '-20 To +60 C', 'F' => '-20 To +70 C', 'Z' => '-20 To +75 C', 'N' => '-20 To +85 C', 'G' => '-30 To +70 C', 'H' => '-30 To +75 C', 'J' => '-30 To +80 C', 'K' => '-30 To +85 C', 'L' => '-35 To +80 C', 'V' => '-35 To +85 C', 'P' => '-40 To +105 C', 'I' => '-40 To +125 C', 'Y' => '-40 To +75 C', 'M' => '-40 To +85 C', 'R' => '-55 To +100 C', 'S' => '-55 To +105 C', 'T' => '-55 To +125 C', 'U' => '-55 To +85 C', 'A' => '0 To +50 C', 'C' => '0 To +70 C', 'W' => 'Other'];
      break;

    case 'output':
      $labels = [
        'A'  => 'Clipped Sine',
        'C'  => 'Clipped Sine',
        'G'  => 'Clipped Sine',
        'H'  => 'HCMOS',
        'S'  => 'HCMOS',
        'HB' => 'HCMOS',
        'HD' => 'HCMOS',
        'HH' => 'HCMOS',
        'HL' => 'HCMOS',
        'HS' => 'HCMOS',
        'HK' => 'HCMOS',
        'PD' => 'LVPECL',
        'PS' => 'LVPECL',
        'PU' => 'LVPECL',
        'LD' => 'LVDS',
        'LS' => 'LVDS',
        'SL' => 'HCSL',
        'HA' => 'AEC-Q200',
      ];
      break;

    case 'pin_1':
      $labels = [
        'N' => 'No Connect',
        'V' => 'Voltage Control',
        'D' => 'E/D',
        'T' => 'VC w/o mech. trimmer'
      ];
      break;

    case 'size':
      if( 'pin-thru' == strtolower( $args['package_type'] ) ){
        $labels = [
          '4ST' => 'HC49 2.6mm height',
          '9ST' => 'HC49 2.5mm height',
          '4UT' => 'HC49U 13.46mm height',
          '80T' => 'HC80U',
          'T15' => '5.0x1.5 mm',
          'T26' => '6.0x2.0 mm',
          'T38' => '8.0x3.0 mm',
        ];
      } else if( 'K' == $args['part_type'] ) {
        $labels = [
          '161' => '1.6x1.0 mm',
          '122' => '2.0x1.2 mm',
          '12A' => '2.0x1.2 mm', /* (AEC-Q200) */
          '13A' => '3.2x1.5 mm', /* (AEC-Q200) */
          '135' => '3.2x1.5 mm', /* ESR 70K Ohm (Std) */
          '13L' => '3.2x1.5 mm', /* ESR 50K Ohm (Optional) */
          '145' => '4.1x1.5 mm',
          '255' => '4.9x1.8 mm',
          'FSX' => '7.0x1.5 mm',
          'FSR' => '8.7x3.7 mm',
          'FSM' => '10.4x4.0 mm',
        ];
      } else if( 'T' == $args['part_type'] ){
        $labels = [
          '1' => '2.0x1.6 mm',
          '2' => '2.5x2.0 mm',
          '3' => '3.2x2.5 mm',
          '5' => '5.0x3.2 mm',
          '7' => '7.0x5.0 mm',
          '9' => '11.4x9.6 mm',
        ];
      } else {
        $labels = [
          'A' => '1.2x1.0 mm',
          '0' => '1.6x1.2 mm',
          '1' => '2.0x1.6 mm',
          '2' => '2.5x2.0 mm',
          '3' => '3.2x2.5 mm',
          '4' => '4.0x2.5 mm',
          '5' => '5.0x3.2 mm',
          '6' => '6.0x3.5 mm',
          '7' => '7.0x5.0 mm',
          '8' => '10.0x4.5 mm',
          '8' => '11.0x5.0 mm',
          '4SD' => 'HC49 SMD (4.5mm)',
          '9SD' => 'HC49 SMD (3.2mm)'
        ];
      }
      break;

    case 'stability':
      $labels = [
        'M' => '-0.036 ppm / (∆ºC)²',
        'I' => '-0.04 ppm (∆ºC)² max',
        'O' => '-140 ~ +10 ppm',
        'K' => '0.28 ppm',
        'Q' => '0.37 ppm',
        'U' => '0.5 ppm',
        'T' => '1.0 ppm',
        'S' => '1.5 ppm',
        'H' => '10.0 ppm',
        'G' => '100 ppb',
        'A' => '100.0 ppm',
        'Y' => '1000.0 ppm',
        'F' => '15.0 ppm',
        'R' => '2.0 ppm',
        'P' => '2.5 ppm',
        'E' => '20.0 ppm',
        'V' => '200.0 ppm',
        'D' => '25.0 ppm',
        'N' => '3.0 ppm',
        'C' => '30.0 ppm',
        'L' => '5.0 ppm',
        'B' => '50.0 ppm',
        'W' => '70.0 ppm',
        'J' => '8 ppm',
        'Z' => 'Other',
        'X' => 'Overall'
      ];
      break;

    case 'tolerance':
      $labels = ['M' => '-0.036+-1 ppm (Delta temp)E^2', 'I' => '-0.04 ppm (Delta Temp)E^2 max', 'O' => '-140 ~ +10 ppm', 'K' => '0.28 ppm', 'Q' => '0.37 ppm', 'U' => '0.5 ppm', 'T' => '1.0 ppm', 'S' => '1.5 ppm', 'H' => '10.0 ppm', 'G' => '100 ppb', 'A' => '100.0 ppm', 'Y' => '1000.0 ppm', 'F' => '15.0 ppm', 'R' => '2.0 ppm', 'P' => '2.5 ppm', 'E' => '20.0 ppm', 'V' => '200.0 ppm', 'D' => '25.0 ppm', 'N' => '3.0 ppm', 'C' => '30.0 ppm', 'L' => '5.0 ppm', 'B' => '50.0 ppm', 'W' => '70.0 ppm', 'J' => '8 ppm', 'Z' => 'Other', 'X' => 'Overall'];
      break;

    case 'voltage':
      $labels = [
        'A' => '5 Volts',
        'P' => '5 Volts',
        'B' => '3.3 Volts',
        'C' => '3.3 Volts',
        'D' => '3 Volts',
        'E' => '3 Volts',
        'F' => '2.85 Volts',
        'G' => '2.85 Volts',
        'Q' => '2.8 Volts',
        'R' => '2.8 Volts',
        'S' => '2.7 Volts',
        'T' => '2.7 Volts',
        'H' => '2.5 Volts',
        'J' => '2.5 Volts',
        'K' => '1.8 Volts',
        'L' => '1.8 Volts',
        'M' => '1.0 Volt',
        'N' => '1.0 Volt',
      ];
      break;

    default:
      // nothing
      break;
  }

  $values = [];
  if( 'output' == $args['setting'] )
    error_log('Mapping these values: ' . print_r($args['values'],true));

  foreach ($args['values'] as $key => $value) {

    // Get our $label by Mapping $value to a label
    $label = ( array_key_exists( $value, $labels ) )? $labels[$value] : 'no label (' . $value . ')';
    $values[$label][] = $value;

    unset($labels[$value],$args['values'][$key]);

    if( 0 == count( $args['values'] ) ){
      foreach( $values as $label => $value ){
        $mapped_values[] = ['value' => implode( ',', $value ), 'label' => $label ];
      }
    }
  }
  if( 'output' == $args['setting'] )
    error_log('output options = ' . print_r($mapped_values,true));

  return $mapped_values;
}