<?php

namespace FoxParts\redirection;

function crystals_redirect(){
  //\foxparts_error_log('REQUEST_URI = ' . $_SERVER['REQUEST_URI']);
  $request_uri = $_SERVER['REQUEST_URI'];
  if( preg_match( '/foxpart\/FC([0-9])([a-z]{4})([a-z])([a-z])\/?([0-9]+\.[0-9]+)?/i', $request_uri, $matches ) ){
    //\foxparts_error_log( print_r( $matches, true ) );
    $part_url = 'foxpart/FC' . $matches[1] . $matches[2] . '_' . $matches[4] . '/';

    if( array_key_exists( 5, $matches ) )
      $part_url.= $matches[5];
    \foxparts_error_log('REDIRECTING TO: ' . $part_url );
    exit( \wp_redirect( \site_url( $part_url ) ) );
  }
}
\add_action('template_redirect', __NAMESPACE__ . '\\crystals_redirect');