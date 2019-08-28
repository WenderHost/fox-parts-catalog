<?php

namespace FoxParts\query_vars;

function add_query_vars( $vars ){
  $vars[] = 'frequency';
  return $vars;
}
add_filter( 'query_vars', __NAMESPACE__ . '\\add_query_vars' );

/*
function add_rewrites(){
  add_rewrite_tag( '%frequency%', '([0-9]+\.[0-9]+)' );
  add_rewrite_rule( '^foxpart/[0-9a-zA-Z]+/([0-9]+\.[0-9]+)/?', 'index.php?post_type=foxpart&frequency=$matches[1]', 'bottom' );
}
add_action( 'init', __NAMESPACE__ . '\\add_rewrites', 10, 0 );

function append_foxpart_link( $url, $post ){
  if( 'foxpart' == get_post_type( $post ) && $frequency = get_query_var( 'frequency' ) ){
    error_log('foxpart is post_type and frequency = ' . $frequency );
  }
  return $url;
}
add_filter( 'post_type_link', __NAMESPACE__ . '\\append_foxpart_link', 10, 2 );
/**/

function foxpart_endpoint(){
  add_rewrite_endpoint( 'frequency', EP_PERMALINK );
}
add_action( 'init', __NAMESPACE__ . '\\foxpart_endpoint' );

/**
 * Modify the search query
 */
function modfiy_search_query_for_foxparts( $query ){
  if( ! is_admin() && $query->is_main_query() && $query->is_search() ){

    // If we have a full part number (w/ frequency), just search on the part without the frequency:
    $frequency = ( \preg_match( '/(F[a-zA-Z]{1}[0-9]{1}[a-zA-Z]+)([0-9]+\.[0-9]+)/', $query->get('s'), $matches ) ) ? $matches[2] : false ;
    if( $frequency ){
      $query->set( 'frequency', $frequency );
      $query->set( 's', $matches[1] );
    }
  }

}
add_action( 'pre_get_posts', __NAMESPACE__ . '\\modfiy_search_query_for_foxparts' );
