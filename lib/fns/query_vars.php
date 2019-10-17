<?php

namespace FoxParts\query_vars;

function add_query_vars( $vars ){
  $vars[] = 'frequency';
  return $vars;
}
add_filter( 'query_vars', __NAMESPACE__ . '\\add_query_vars' );

/**
 * Add rewrite which contains the part frequency after the part slug/name
 */
function add_rewrites(){
  add_rewrite_tag( '%frequency%', '([0-9]+\.[0-9]+)' );
  add_rewrite_rule( 'foxpart/([0-9a-zA-Z_]+)/([0-9]+\.[0-9]+)/?', 'index.php?post_type=foxpart&name=$matches[1]&frequency=$matches[2]', 'top' );
}
add_action( 'init', __NAMESPACE__ . '\\add_rewrites', 10, 0 );

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
