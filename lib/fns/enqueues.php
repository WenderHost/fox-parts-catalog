<?php

namespace FoxParts\enqueues;

/**
 * Adds scripts/styles for FOXSelect.
 */
function add_enqueues(){
  // Register theme styles
  wp_register_style( 'foxselect-theme', get_stylesheet_directory_uri() . '/assets/css/foxselect.css', [], filemtime( get_stylesheet_directory() . '/assets/css/foxselect.css') );

  // Register FOXSelect React App JS
  $scripts = glob( plugin_dir_path( __FILE__ ) . '../foxselect/static/js/main.*' );
  $x = 0;
  foreach( $scripts as $script ){
    if( '.js' == substr( $script, -3 ) ){
      wp_register_script( 'foxselect-' . $x, plugin_dir_url( __FILE__ ) . '../foxselect/static/js/' . basename( $script ), null, null, true );
      $x++;
    }
  }

  // Register FOXSelect React App CSS
  $styles = glob( plugin_dir_path( __FILE__ ) . '../foxselect/static/css/main.*' );
  $y = 0;
  foreach( $styles as $style ){
    if( '.css' == substr( $style, -4 ) ){
      wp_register_style( 'foxselect-' . $y, plugin_dir_url( __FILE__ ) . '../foxselect/static/css/' . basename( $style ) );
      $y++;
    }
  }
}
\add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\add_enqueues' );

/**
 * Checks post_content for FOXSelect shortcode, and removes our parent theme's styles if found.
 */
function remove_enqueues(){
  global $post;
  if( stristr( $post->post_content, '[foxselect' ) )
    wp_dequeue_style( 'business-pro-min' );
}
\add_action( 'wp_print_styles', __NAMESPACE__ . '\\remove_enqueues', 100 );