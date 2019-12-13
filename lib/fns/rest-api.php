<?php
namespace FoxParts\restapi;

/**
 * Initialize our REST API with these routes:
 *
 * - foxparts/v1/get_options
 * - foxparts/v1/get_web_part (12/11/2019 (11:30) - this doesn't appear to be in use)
 * - foxparts/v1/get_part_series
 */
function init_rest_api(){

  // Get available options for a given part configuration
  register_rest_route( 'foxparts/v1', 'get_options/(?P<part>(F)([a-zA-Z0-9_\[\],]{6,32})-[0-9]+.[0-9]+)/(?P<package_type>(SMD|Pin-Thru))/(?P<frequency_unit>(MHz|kHz|mhz|khz))', [
    'methods' => 'GET',
    'callback' => __NAMESPACE__ . '\\get_options'
  ]);

  register_rest_route( 'foxparts/v1', 'get_web_part', [
    'methods' => 'GET',
    'callback' => __NAMESPACE__ . '\\get_web_part'
  ]);

  register_rest_route( 'foxparts/v1', 'get_part_series', [
    'methods' => 'GET',
    'callback' => __NAMESPACE__ . '\\get_part_series'
  ]);

  $enable_cors = defined('JWT_AUTH_CORS_ENABLE') ? JWT_AUTH_CORS_ENABLE : false;
  if( $enable_cors )
    header('Access-Control-Allow-Origin: *');
}
add_action( 'rest_api_init', __NAMESPACE__ . '\\init_rest_api', 15 );

/**
 * Retrieve available options given a part's configuration.
 *
 * @param      array $data {
 *    @type string $part            Part number
 *    @type string $package_type    Either `SMD` or `Pin-Thru`
 *    @type string $frequency_unit  Either `MHz` or `kHz`
 * }
 * @param      bool  $return Return the data
 */
function get_options( $data, $return = false ){

  $response = new \stdClass();

  $part = $data['part'];
  $package_type = strtolower($data['package_type']);
  $frequency_unit = strtolower($data['frequency_unit']);

  // TODO: We're no longer passing the dash between
  // the part options and the frequency. Need to
  // code an alternate method to split the frequency
  // and the part options.
  $part_array = explode( '-', $part );

  $options = $part_array[0];
  $frequency = $part_array[1];

  //error_log( str_repeat( '-', 80 ) );

  $start = ( 'F' == substr( $options, 0, 1) )? 1 : 0;
  $options_array = str_split( substr( $options, $start ) );
  $part_type = $options_array[0];

  $configuredPart = [];
  $configuredPart['frequency'] = ['label' => $frequency, 'value' => $frequency];
  $configuredPart['package_type'] = ['label' => $package_type, 'value' => $package_type];

  $frequency_unit_labels = ['mhz' => 'MHz', 'khz' => 'kHz'];
  $configuredPart['frequency_unit'] = ['label' => $frequency_unit_labels[$frequency_unit], 'value' => $frequency_unit ];

  $configuredPart['number'] = ['label' => $options, 'value' => $options];

  /**
   * FOX PART NUMBER MAPPINGS
   *
   * Here we map each Part No. component to the corresponding key(s) of our
   * incoming part number contained in $options_array.
   */
  $part_no_maps = [
    'C' => ['part_type' => [0], 'size' => [1], 'package_option' => [2,3], 'tolerance' => [4], 'stability' => [5], 'load' => [6], 'optemp' => [7]],
    'K' => ['part_type' => [0], 'size' => [1,2,3], 'tolerance' => [4], 'stability' => [5], 'load' => [6], 'optemp' => [7]],
    'O' => ['part_type' => [0], 'size' => [1], 'output' => [2,3], 'voltage' => [4], 'stability' => [5], 'optemp' => [6]],
    'T' => ['part_type' => [0], 'size' => [1], 'output' => [2], 'pin_1' => [3], 'voltage' => [4], 'stability' => [5], 'optemp' => [6]],
    'Y' => ['part_type' => [0], 'size' => [1], 'output' => [2], 'voltage' => [3], 'stability' => [4], 'optemp' => [5]],
    'S' => ['part_type' => [0], 'size' => [1], 'enable_type' => [2], 'voltage' => [3], 'spread' => [4], 'optemp' => [5]],
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
    //$configuredPart[$key] = $value;
    $map_values_args = [
      'setting' => $key,
      'values' => [$value],
      'package_type' => $package_type,
      'part_type' => $part_type
    ]; //

    if(
      'size' == $key &&
      'pin-thru' == $package_type &&
      'C' == $part_type
    ){
      $map_values_args['package_option'] = $options_array[2] . $options_array[3];
      $map_values_args['pin-thru-size-label'] = true;
    }

    // Exception for TCXO's
    // - Normally N=Pin 1 Connection,
    // - In `T9CN`, N=No Connect
    // To account for this exception, we send along size info
    if( 'pin_1' == $key && $configuredPart['size'] ){
      $map_values_args['size'] = $configuredPart['size']['value'];
      \foxparts_error_log('$key = '.$key.';$configuredPart[\'size\'] = ' . print_r( $configuredPart['size'], true) );
    }

    $mapped_value = map_values_to_labels( $map_values_args );

    $configuredPart[$key] = $mapped_value[0];

    // In the FOXSelect React app, we call it `product_type` instead of `part_type`.
    if( 'part_type' == $key )
      $configuredPart['product_type'] = $mapped_value[0];
  }
  $response->configuredPart = $configuredPart;
  //\foxparts_error_log( 'configuredPart = ' . print_r( $configuredPart, true ) );

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
      && '' != $configuredPart['frequency']['value']
      && '_' != $configuredPart['frequency']['value']
      && '0.0' != $configuredPart['frequency']['value'] ){ // && '0.032768' != $configuredPart['frequency']

    $frequency = (float) $configuredPart['frequency']['value'];

    if( 'khz' == $frequency_unit )
      $frequency = (float) $frequency/1000;

    /**
     * WP_Query BUG for floating point no. and type = DECIMAL
     *
     * When `type` = DECIMAL for floating point numbers with a zero
     * before the decimal, the resulting SQL used by WordPress casts
     * the meta field value as DECIMAL but the query seems to ignore
     * everything after the decimal. To fix, we set the type to CHAR
     * for numbers with a zero before the decimal.
     *
     * 08/12/2019 (09:58) - UPDATE: The above seems to apply even
     * when we don't have a zero before the decimal as in the case
     * of `FO7HSBEM` which has a from_frequency of `1.8` and a
     * to_frequency of 50.004. When we cast those values as
     * DECIMAL, we get nothing. Casting them as CHAR returns the
     * correct result.
     */
    $type = ( stristr( $frequency, '.') /* && 0 == substr( $frequency, 0, 1 ) */ )? 'CHAR' : 'DECIMAL' ;
    //*
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
    /**/
  }

  // Package Option
  switch($configuredPart['part_type']['value']){
    case 'C':
      // If we have a `package_option`, query by that option
      if( isset( $configuredPart['package_option'] ) && '' != $configuredPart['package_option']['value'] && ! stristr( $configuredPart['package_option']['value'], '_' ) ){
        $meta_query[] = [
          'key' => 'package_option',
          'value' => $configuredPart['package_option']['value'],
          'compare' => '='
        ];
      } else {
        // If we don't have a `package_option`, narrow our results based on the `package_type`
        $pin_thru_crystal_part_types = ['ST','UT','0T'];
        $compare = ( 'pin-thru' == $configuredPart['package_type']['value'] )? 'IN' : 'NOT IN';
        $meta_query[] = [
          'key' => 'package_option',
          'value' => $pin_thru_crystal_part_types,
          'compare' => $compare,
        ];
      }
      break;

    case 'K':
      $pin_thru_khzcrystal_part_types = ['T15','T26','T38'];
      $compare = ( 'pin-thru' == $configuredPart['package_type']['value'] )? 'IN' : 'NOT IN';
      $meta_query[] = [
        'key' => 'size',
        'value' => $pin_thru_khzcrystal_part_types,
        'compare' => $compare,
      ];
      break;

    default:
      if( isset( $configuredPart['package_option'] ) && '' != $configuredPart['package_option']['value'] && ! stristr( $configuredPart['package_option']['value'], '_' ) ){
        $meta_query[] = [
          'key' => 'package_option',
          'value' => $configuredPart['package_option']['value'],
          'compare' => '='
        ];
      }

  }

  // All other options
  $allowed_meta_queries = ['size','output','tolerance','voltage','stability','optemp','pin_1','spread','enable_type'];
  foreach ( $allowed_meta_queries as $meta_field ) {
    if( isset( $configuredPart[$meta_field] ) && '' != $configuredPart[$meta_field]['value'] ){
      $value = $configuredPart[$meta_field]['value'];
      if( is_string( $value ) && stristr($value, '_' ) )
        continue;

      /** MULTIVARIATE VALUES
        *
        * Some parts supply multiple values for one option (e.g. C,G when choosing
        * `Clipped Sine` for `output` prior to selecting the `Pin 1 Connection`). When this
        * happens, we convert/explode the value into an array to check against both in our
        * meta query:
       */
      if( stristr( $value, ',' ) )
        $value = explode(',', $value );

      $compare = ( is_array( $value ) )? 'IN' : '=';
      $meta_query[] = [
        'key' => $meta_field,
        'value' => $value,
        'compare' => $compare
      ];
    }
  }

  //error_log( '$meta_query = ' . print_r( $meta_query, true ) . '; $tax_query = ' . print_r( $tax_query, true ) );

  $query_args = [
    'post_type' => 'foxpart',
    'posts_per_page' => -1,
    'tax_query' => $tax_query,
    'meta_query' => $meta_query,
  ];
  $query = new \WP_Query( $query_args );
  //error_log( basename(__FILE__) . ', LINE ' . __LINE__ . ': $meta_query = ' . print_r( $meta_query, true ) );

  $response->message = ( ! $query->have_posts() )? '0 part options found' : $query->post_count . ' part options found';
  $response->message.= ' (' . $data['part'] . ', ' . strlen( $configuredPart['number']['value'] ) . ' chars in part no).';
  $response->availableParts = $query->post_count;
  //error_log( basename( __FILE__ ) . ', LINE: ' . __LINE__ . ': ' . $response->message );

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
      $size = ( ! stristr($configuredPart['size']['value'], '_') )? $configuredPart['size']['value'] : '';

      $response->partOptions[$key] = ( $mapped_values = map_values_to_labels(['setting' => $key, 'values' => $$var, 'package_type' => $package_type, 'part_type' => $part_type, 'size' => $size ]) )? $mapped_values : $$var ;
  }
  //error_log('['. basename(__FILE__) .', line '. __LINE__ .'] $response:' . print_r($response,true));

  if( $return ){
    return $response;
  } else {
    wp_send_json( $response, 200 );
  }
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
 * Returns part series given a part number
 *
 * @param      \WP_REST_Request  $request  The request
 */
//function get_product_family( \WP_REST_Request $request ){
function get_part_series( \WP_REST_Request $request ){
  if( is_wp_error( $request ) )
    return $request;

  if( ! isset( $_SESSION['SF_SESSION'] ) )
    sf_login();

  if( ! defined( 'FOXELECTRONICS_SF_API_ROUTE' ) )
    return new \WP_Error( 'missingconst', __('Please make sure `FOXELECTRONICS_SF_API_ROUTE` is defined in your `wp-config.php`') );

  $default_data = [
    'product_photo_part_image' => '',
    'name'            => '---',
    'part_type'       => '---',
    'details'         => '<h5 style="margin-bottom: .25rem;">No Results Found</h5><p>Your search did not yield any results. Please follow these guidelines:</p><ul><li>Part Number search only works with Fox\'s <a href="/support/partnumberformat/" target="_blank">new part number scheme</a>.</li><li>Old standards like <code>FOXSDLF/143-20</code> and Configured Part Numbers <code>e.g. 116-25-13</code> can not be searched.</li><li>Try shortening the Part Number.</li><li>If you know your product specifications, try <a href="' . FOXPC_FOXSELECT_URL . '">FoxSelect</a>.</li><li>Still having trouble? Send a <a href="/contact-us/">request for assistance</a>.</li></ul>',
  ];

  $params = $request->get_params();
  $partnum = $params['partnum'];
  if( empty( $partnum ) )
    return new \WP_Error( 'noproductfamily', __('No `partnum` provided.') );

  // Standardize our search string
  $partnum = \FoxParts\utilities\standardize_search_string( $partnum, false );
  // Don't throw an error if we have an `invalid` part number, return the `default_data` which contains help on searching:
  if( ! $partnum ){
    //return new \WP_Error( 'invalidsearchstring', __('Your search string was invalid.') );
    $response = new \stdClass();
    $response->data = [$default_data];
    return $response;
  }
  //foxparts_error_log('🔔 $partnum = ' . $partnum );

  // Split the partnum into `part_series` and `frequency`
  $partnum = \FoxParts\utilities\split_part_number( $partnum );
  if( ! $partnum )
    return new \WP_Error( 'cantsplitpartnum', __('I was unable to split the part number into `part_series` and `frequency`.') );
  foxparts_error_log('🔔 $partnum = ' . print_r( $partnum, true ) );

  $query['part'] = $partnum['search']; // Our SF API uses `part` as the query param

  $access_token  = $_SESSION['SF_SESSION']->access_token;
  if( empty( $access_token ) )
    return new \WP_Error( 'noaccesstoken', __('No Access Token provided.') );

  $instance_url  = $_SESSION['SF_SESSION']->instance_url;
  if( empty( $instance_url ) )
    return new \WP_Error( 'noinstanceurl', __('No Instance URL provided.') );

  $transient_key = 'fox_part_series_' . $partnum['search'] . $partnum['frequency'];
  //\foxparts_error_log('$transient_key = ' . $transient_key );
  if( false === ( $response = get_transient( $transient_key ) ) ){
    $request_url = trailingslashit( $instance_url ) . FOXELECTRONICS_SF_API_ROUTE . 'ProductFamily' . '?' . http_build_query( $query );
    //\foxparts_error_log( '📡 $request_url = ' . $request_url );
    $response = wp_remote_get( $request_url, [
      'method' => 'GET',
      'timeout' => 30,
      'redirection' => 5,
      'headers' => [
        'Authorization' => 'Bearer ' . $access_token,
      ],
    ]);

    if( ! is_wp_error( $response ) ){
      $data = json_decode( wp_remote_retrieve_body( $response ) );
      $response = new \stdClass();
      $data->part_series[0]->full_part_number = ( ! empty( $partnum['frequency'] ) )? true : false ;
      //foxparts_error_log('$data->part_series = ' . print_r( $data->part_series, true));
      $response->data = $data->part_series;

    }

    set_transient( $transient_key , $response, HOUR_IN_SECONDS * 24 );
  }

  if( empty( $response->data ) ){
    foxparts_error_log('🔔 No response data found...extended results will be unavailable! We need to create a Part Series so the following `foreach` can run:' );
    //$PartSeries = new \stdClass();
    $PartSeries = (object) ['width_mm' => null, 'static_sensitive' => null, 'reach_compliant' => null, 'product_photo_part_image' => null, 'part_type' => null, 'packaging' => null, 'package_name' => null, 'output' => null, 'name' => null, 'moisture_sensitivity_level_msl' => null, 'length_mm' => null, 'individual_part_weight_grams' => null, 'height_mm' => null, 'export_control_classification_number' => null, 'data_sheet_url' => null, 'cage_code' => null];
    /*
    foreach( $partseries_props as $prop ){
      $PartSeries->$$prop = '';
    }
    */
    $response->data[0] = $PartSeries;
  }

  // Extended Product Families (12/13/2019 (07:36) - used for Crystals)
  foreach( $response->data as $key => $part_series ){
    $response->data[$key]->extended_product_families = [];
    /**
     * 11/25/2019 15:21 - Continue working here:
     *
     * When we searching on something beyond a part series, I need
     * to search on whatever that string may be (e.g. `cabshh` ).
     * Otherwise, we'll return too many results.
     */
    if( 'c' == $partnum['part_type'] ){
      $args = [
        'post_type'       => 'foxpart',
        'posts_per_page'  => -1,
        's'               => 'F' . $partnum['search'], // $part_series->name
      ];
      \foxparts_error_log('Extended results query vars: ' . print_r( $args, true ) );
      $foxparts = get_posts( $args );
      \foxparts_error_log('$foxparts = ' . print_r( $foxparts, true ) );
      if( $foxparts ){
        \foxparts_error_log('We have some parts...');
        foreach( $foxparts as $part ){
          $part_obj = new \stdClass();
          $part_obj->name = get_the_title( $part->ID );
          $part_obj->tolerance = \FoxParts\utilities\map_part_attribute(['part_type' => 'crystal', 'attribute' => 'tolerance', 'value' => get_post_meta( $part->ID, 'tolerance', true )]);
          $part_obj->stability = \FoxParts\utilities\map_part_attribute(['part_type' => 'crystal', 'attribute' => 'stability', 'value' => get_post_meta( $part->ID, 'stability', true )]);
          $part_obj->optemp = \FoxParts\utilities\map_part_attribute(['part_type' => 'crystal', 'attribute' => 'optemp', 'value' => get_post_meta( $part->ID, 'optemp', true )]);
          $part_obj->from_frequency = get_post_meta( $part->ID, 'from_frequency', true );
          $part_obj->to_frequency = get_post_meta( $part->ID, 'to_frequency', true );

          $split_part_obj_name = \FoxParts\utilities\split_part_number( $part_obj->name );
          $match = \FoxParts\utilities\match_with_underscores( $split_part_obj_name['config'], $partnum['config'] );
          if( $match ){
            //\foxparts_error_log("We have a match!\n" . '🔔 $split_part_obj_name = ' . print_r( $split_part_obj_name, true ) . "\n" . '🔔 $partnum = ' . print_r( $partnum, true ) );
            $response->data[$key]->extended_product_families[] = $part_obj;
          }


        }
      }
      //foxparts_error_log('$response = ' . print_r( $response, true ) );
    }
  } // foreach( $response->data as $key => $part_series )

  return $response;
}

/**
 * Gets part details from the Salesforce API.
 *
 * @param      \WP_REST_Request    $request  The request
 *
 * @return     $object  The web part details.
 */
function get_web_part( \WP_REST_Request $request ){
  if( is_wp_error( $request ) )
    return $request;

  if( ! isset( $_SESSION['SF_SESSION'] ) )
    sf_login();

  if( ! defined( 'FOXELECTRONICS_SF_API_ROUTE' ) )
    return new \WP_Error( 'missingconst', __('Please make sure `FOXELECTRONICS_SF_API_ROUTE` is defined in your `wp-config.php`') );

  $params = $request->get_params();
  $partnum = $params['partnum'];
  if( empty( $partnum ) )
    return new \WP_Error( 'nopartnum', __('No `partnum` provided.') );
  $query['partnum'] = $partnum;

  $access_token  = $_SESSION['SF_SESSION']->access_token;
  if( empty( $access_token ) )
    return new \WP_Error( 'noaccesstoken', __('No Access Token provided.') );

  $instance_url  = $_SESSION['SF_SESSION']->instance_url;
  if( empty( $instance_url ) )
    return new \WP_Error( 'noinstanceurl', __('No Instance URL provided.') );

  $transient_key = 'foxpart_' . $partnum;

  if( false === ( $response = get_transient( $transient_key ) ) ){
    $request_url = trailingslashit( $instance_url ) . FOXELECTRONICS_SF_API_ROUTE . 'WebPart' . '?' . http_build_query( $query );

    $response = wp_remote_get( $request_url, [
      'method' => 'GET',
      'timeout' => 30,
      'redirection' => 5,
      'headers' => [
        'Authorization' => 'Bearer ' . $access_token,
      ],
    ]);

    if( ! is_wp_error( $response ) ){
      $data = json_decode( wp_remote_retrieve_body( $response ) );
      $response = new \stdClass();
      $response->data = $data;
    }

    set_transient( $transient_key , $response, HOUR_IN_SECONDS * 4 );
  }

  return $response;
}

/**
 * Logs into SalesForce using Session ID Authentication.
 *
 * Upon a successful login, our Salesforce access credentials
 * object is stored in $_SESSION['SF_SESSION'].
 *
 * Example:
 *
 * $_SESSION['SF_SESSION'] = {
 *   "access_token": "00D0t0000000Pmo!AQUAQA67ZA36gbSdaQUwfYwYIuiybQThERXoUXACW70ZhIWerHgeTcOtvhJv.orEe_6D.eJxZUulx76qJ_u7DPs6ZWH34yE8",
 *   "instance_url": "https://foxonline--foxpart1.cs77.my.salesforce.com",
 *   "id": "https://test.salesforce.com/id/00D0t0000000PmoEAE/0050t000001o5DeAAI",
 *   "token_type": "Bearer",
 *   "issued_at": "1550173766327",
 *   "signature": "buz6bzPJNBCkCBS1+ABC79hFOXRhLJMB3c1MTtRORFI="
 * }
 *
 * @return    object      Login Response or WP Error.
 */
function sf_login(){
  // Verify we have credentials we need to attempt an authentication:
  $check_const = [
    'FOXELECTRONICS_CLIENT_ID',
    'FOXELECTRONICS_CLIENT_SECRET',
    'FOXELECTRONICS_USERNAME',
    'FOXELECTRONICS_PASSWORD',
    'FOXELECTRONICS_SECURITY_TOKEN',
    'FOXELECTRONICS_SF_AUTH_EP',
  ];
  foreach ($check_const as $const) {
    if( ! defined( $const ) ){
      return new \WP_Error( 'missingconst', __('Please make sure the following constants are defined in your `wp-config.php`: ' . implode( ', ', $check_const ) . '.') );
    }
  }

  $args = [
    'method' => 'POST',
    'timeout' => 30,
    'redirection' => 5,
    'body' => [
      'grant_type' => 'password',
      'client_id' => FOXELECTRONICS_CLIENT_ID,
      'client_secret' => FOXELECTRONICS_CLIENT_SECRET,
      'username' => FOXELECTRONICS_USERNAME,
      'password' => FOXELECTRONICS_PASSWORD . FOXELECTRONICS_SECURITY_TOKEN
    ],
  ];
  //error_log('$args = ' . print_r( $args, true ));

  // Login to SalesForce
  $response = wp_remote_post( FOXELECTRONICS_SF_AUTH_EP, $args );

  if( ! is_wp_error( $response ) ){
    $data = json_decode( wp_remote_retrieve_body( $response ) );
    $_SESSION['SF_SESSION'] = $data;
    $response = new \stdClass();
    $response->data = $data;
  }

  return $response;
}

/**
 * Provided a setting, returns values mapped to labels.
 *
 * @param      array $atts{
 *    @type      string         $setting             The part setting (e.g. size, stability, load, etc.)
 *    @type      array          $values              The values
 *    @type      string         $package_type        SMD or Pin-Thru
 *    @type      bool           $pin-thru-size-label Return with correct label for a Pin-Thru part size. (false)
 * }
 *
 * @return     array|boolean  Returns the values mapped to labels.
 */
function map_values_to_labels( $atts ){

  $args = shortcode_atts([
    'setting'               => null,
    'values'                => [],
    'package_type'          => 'SMD',
    'package_option'        => null,
    'part_type'             => null,
    'product_type'          => null,
    'pin-thru-size-label'   => false,
    'size'                  => null,
  ], $atts );

  if( 'pin_1' == $args['setting'] )
    \foxparts_error_log('map_values_to_labels('.print_r($args,true).')');

  if( 0 == count( $args['values'] ) || ! is_array( $args['values'] ) )
    return false;

  $mapped_values = [];

  //asort( $args['values'] );
  /*
  usort( $args['values'], function($a,$b){
    if( is_numeric($a) && ! is_numeric( $b ) )
      return 1;
    else if( ! is_numeric( $a ) && is_numeric( $b ) )
      return -1;
    else
      return ($a < $b)? -1 : 1;
  });
  */

  $labels = [];
  switch( $args['setting'] ){
    case 'enable_type':
      $labels = [
        'B' => 'Tristate',
        'S' => 'Standby',
      ];
      break;

    case 'load':
      if( 'K' == $args['part_type'] || 'K' == $args['product_type'] ){
        $labels = [
          'B' => '6pF',
          'V' => '7pF',
          'D' => '8pF',
          'W' => '9pF',
          'H' => '12.5pF',
        ];
      } else {
        $labels = ['B' => '6pF', 'C' => '4pF', 'D' => '8pF', 'E' => '10pF', 'G' => '12pF', 'H' => '12.5pF', 'J' => '15pF', 'K' => '16pF', 'L' => '18pF', 'M' => '20pF', 'N' => '22pF', 'P' => '27pF', 'Q' => '30pF', 'R' => '32pF', 'S' => '33pF', 'T' => '50pF', 'U' => '13pF', 'V' => '7pF', 'W' => '9pF', 'X' => '14pF', 'Y' => '19pF'];
      }
      break;

    case 'optemp':
      $labels = [
        'B' => '-10 To +50 C',
        'D' => '-10 To +60 C',
        'E' => '-10 To +70 C',
        'Q' => '-20 To +60 C',
        'F' => '-20 To +70 C',
        'Z' => '-20 To +75 C',
        'N' => '-20 To +85 C',
        'G' => '-30 To +70 C',
        'H' => '-30 To +75 C',
        'J' => '-30 To +80 C',
        'K' => '-30 To +85 C',
        'L' => '-35 To +80 C',
        'V' => '-35 To +85 C',
        'P' => '-40 To +105 C',
        'I' => '-40 To +125 C',
        'Y' => '-40 To +75 C',
        'M' => '-40 To +85 C',
        'R' => '-55 To +100 C',
        'S' => '-55 To +105 C',
        'T' => '-55 To +125 C',
        'U' => '-55 To +85 C',
        'A' => '0 To +50 C',
        'C' => '0 To +70 C',
        'W' => 'Other'
      ];
      break;

    case 'part_type':
    case 'product_type':
      $labels = [
        'C' => 'Crystal',
        'K' => 'Crystal',
        'O' => 'Oscillator',
        'T' => 'TCXO/VC-TCXO',
        'Y' => 'VCXO',
        'S' => 'SSO',
      ];
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
        'N' => 'Ground', // No Connect
        'V' => 'Voltage Control',
        'D' => 'E/D',
        'T' => 'VC w/o mech. trimmer'
      ];
      if( ! is_null( $args['size'] ) && 9 == $args['size'] )
        $labels['N'] = 'No Connect';
      break;

    case 'size':
      if( 'pin-thru' == strtolower( $args['package_type'] ) ){
        $labels = [
          '4ST' => 'HC49 3.6mm height',
          '9ST' => 'HC49 2.5mm height',
          '4UT' => 'HC49U 13.46mm height',
          '80T' => 'HC80U',
          'T15' => '5.0x1.5 mm',
          'T26' => '6.0x2.0 mm',
          'T38' => '8.0x3.0 mm',
        ];
      } else if( 'K' == $args['part_type'] || 'K' == $args['product_type'] ) {
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
      } else if( 'T' == $args['part_type'] || 'T' == $args['product_type'] ){
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
          /*'4' => '4.0x2.5 mm',*/
          '4' => 'HC49 SMD (4.5mm)',
          '5' => '5.0x3.2 mm',
          '6' => '6.0x3.5 mm',
          '7' => '7.0x5.0 mm',
          '8' => '10.0x4.5 mm',
          /* '8' => '11.0x5.0 mm', */
          /*'4SD' => 'HC49 SMD (4.5mm)',*/
          '9' => 'HC49 SMD (3.2mm)' /* Actually this is a 9SD, so we have to account for this in FOXSelect */
        ];

        if( 'O' == $args['part_type'] || 'O' == $args['product_type'] )
          $labels['8'] = '1.6x1.2 mm';
      }
      break;

    case 'spread':
      $labels = [
        'A' => '±0.25% Center',
        'B' => '±0.5% Center',
        'C' => '±0.75% Center',
        'D' => '±1.0% Center',
        'E' => '±1.5% Center',
        'F' => '±2.0% Center',
        'G' => '-0.5% Down Spread',
        'H' => '-1.0% Down Spread',
        'J' => '-1.5% Down Spread',
        'K' => '-2.0% Down Spread',
        'L' => '-3.0% Down Spread',
        'M' => '-4.0% Down Spread',
        'N' => '±0.125% Center',
        'P' => '-0.25% Down Spread',
      ];
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
      $labels = ['M' => '-0.036 ±1 ppm / (∆ºC)²', 'I' => '-0.04 ppm (∆ºC)² max', 'O' => '-140 ~ +10 ppm', 'K' => '0.28 ppm', 'Q' => '0.37 ppm', 'U' => '0.5 ppm', 'T' => '1.0 ppm', 'S' => '1.5 ppm', 'H' => '10.0 ppm', 'G' => '100 ppb', 'A' => '100.0 ppm', 'Y' => '1000.0 ppm', 'F' => '15.0 ppm', 'R' => '2.0 ppm', 'P' => '2.5 ppm', 'E' => '20.0 ppm', 'V' => '200.0 ppm', 'D' => '25.0 ppm', 'N' => '3.0 ppm', 'C' => '30.0 ppm', 'L' => '5.0 ppm', 'B' => '50.0 ppm', 'W' => '70.0 ppm', 'J' => '8 ppm', 'Z' => 'Other', 'X' => 'Overall'];
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
        'V' => '1.7~3.63V',
        'W' => '2.25~3.63V',
      ];
      break;

    default:
      // nothing
      break;
  }

  $values = [];

  foreach ($args['values'] as $key => $value) {

    // Get our $label by Mapping $value to a label
    //if( 'output' == $args['setting'] )
    //  error_log( 'Mapping to `'.$args['setting'].'`. $args = ' . print_r( $args, true ) );

    if( ! is_array( $value ) && array_key_exists( $value, $labels ) ){
      $label = $labels[$value];
    } else if( 'size' == $args['setting'] && ! is_null( $args['package_option'] ) && array_key_exists( $value . $args['package_option'], $labels ) ){
      $label = $labels[ $value . $args['package_option'] ];
    } else if( is_array( $value ) ){
      // Sometimes our $value is an array. Example: TCXOs might come in
      // like so: FT1[C,G]____-25.0. The `output` is getting sent as
      // being a `C` or a `G`. We don't know until the user choses the
      // Pin 1 Connection. In this case, the `output` value comes here
      // as an array, and the following handles that scenario:
      $label = '';
      foreach( $value as $label_array_key ){
        if( ! empty( $label ) && $label != $labels[$label_array_key] ){
          $label.= ', ' . $labels[$label_array_key];
        } else {
          $label = $labels[$label_array_key];
        }
      }
      if( empty( $label ) )
        $label = 'no label (' . implode( ', ', $value ) . ')';
    } else {
      $label = 'no label (' . $value . ')';
    }

    $values[$label][] = $value;

    unset($args['values'][$key]);

    if( is_array( $value ) ){
      foreach ($value as $label_array_key ) {
        unset( $labels[$label_array_key] );
      }
    } else {
      unset($labels[$value]);
    }


    if( 0 == count( $args['values'] ) ){
      foreach( $values as $label => $value ){
        // Multi-variate options come in with $value[0] as an
        // array of possible options. We need to move those
        // options up to $value so we can map them inside
        // $mapped_values[].
        if( is_array( $value[0] ) )
          $value = $value[0];

        $mapped_values[] = ['value' => implode( ',', $value ), 'label' => $label ];
      }
    }
  }

  if( 'load' == $args['setting'] && 1 == count( $mapped_values ) && empty( $mapped_values[0]['value'] ) ){
    $mapped_values = [];
    foreach( $labels as $value => $label ){
      $mapped_values[] = ['value' => $value, 'label' => $label];
    }

  }

  usort( $mapped_values, function( $a, $b ){
    return strnatcmp( $a['label'], $b['label'] );
  });

  return $mapped_values;
}