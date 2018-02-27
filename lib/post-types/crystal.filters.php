<?php
/**
 * Filters for Crystal requests
 */
namespace FoxParts\crystals;

function filter_request( $request, $operation_name, $variables ){
    error_log( str_repeat('-', 80 ) );
    error_log( '$request = ' . $request . '; $operation_name = ' . $operation_name . '; $variables = ' . $variables );
}
add_action( 'do_graphql_request', __NAMESPACE__ . '\\filter_request', 10, 3 );
