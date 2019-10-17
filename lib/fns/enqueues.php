<?php

namespace FoxParts\enqueues;

/**
 * Adds scripts/styles for FOXSelect.
 */
function add_enqueues(){

  // FoxSelect Loader Script
  wp_enqueue_script( 'foxselect-loader', plugin_dir_url( __FILE__ ) . '../js/foxselect-loader.js', ['jquery'], filemtime( plugin_dir_path( __FILE__ ) . '../js/foxselect-loader.js' ), true );
  wp_localize_script( 'foxselect-loader', 'fsloaderVars', ['resturl' => get_rest_url( null, 'foxparts/v1/get_options/' ), 'ieurl' => '/foxselect-ie'] );

  // Register Additional Scripts
  wp_register_script( 'handlebars', 'https://cdnjs.cloudflare.com/ajax/libs/handlebars.js/4.1.2/handlebars.min.js', null, '4.1.2' );
  wp_register_script( 'productlist', plugin_dir_url( __FILE__ ) . '../js/product-list.js', ['handlebars','jquery'], filemtime( plugin_dir_path( __FILE__ ) . '../js/product-list.js' ), true );

  // Register FOXSelect React App JS
  $scripts = glob( plugin_dir_path( __FILE__ ) . '../foxselect/static/js/main.*' );
  $x = 0;
  foreach( $scripts as $script ){
    if( '.js' == substr( $script, -3 ) ){
      wp_register_script( 'foxselect-' . $x, plugin_dir_url( __FILE__ ) . '../foxselect/static/js/' . basename( $script ), ['foxselect-loader'], null, true );
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

  // Register DataTables.js
  wp_register_script( 'datatables', '//cdn.datatables.net/1.10.20/js/jquery.dataTables.min.js', ['jquery'], '1.10.20', true );
  wp_register_script( 'datatables-init', plugin_dir_url( __FILE__ ) . '../js/datatables-init.js', ['datatables'], filemtime( plugin_dir_path( __FILE__ ) . '../js/datatables-init.js' ), true );

  // CSS
  $site_url = get_site_url();
  $dev_servers = ['https://foxonline.local','https://localhost:3000'];
  if( in_array( $site_url, $dev_servers ) ){
    wp_enqueue_style( 'foxparts-styles', plugin_dir_url( __FILE__ ) . '../css/main.css', null, plugin_dir_path( __FILE__ ) . '../css/main.css' );
  } else {
    wp_enqueue_style( 'foxparts-styles', plugin_dir_url( __FILE__ ) . '../dist/main.css', null, plugin_dir_path( __FILE__ ) . '../dist/main.css' );
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
//\add_action( 'wp_print_styles', __NAMESPACE__ . '\\remove_enqueues', 100 );