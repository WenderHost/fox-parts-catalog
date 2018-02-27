<?php

namespace FoxParts\save_post;

function update_post_title( $post_id ){
  $post_type = get_post_type( $post_id );
  $cpts = ['crystal' => 'C'];

  if( ! array_key_exists( $post_type, $cpts ) )
    return $post_id;

  global $post_data;
  $meta = get_metadata( 'post', $post_id );

  $check_keys = ['size','package_option','tolerance','stability','optemp'];
  foreach( $check_keys as $key ){
    if( ! array_key_exists( $key, $meta ) )
      return $post_id;
  }

  $part_name = 'F' . $cpts[$post_type] . $meta['size'][0] . $meta['package_option'][0] . $meta['tolerance'][0] . $meta['stability'][0] . '_' . $meta['optemp'][0];

  $post_slug = sanitize_title( $part_name );

  remove_action('save_post', __NAMESPACE__ . '\\update_post_title');
  wp_update_post([
    'ID' => $post_id,
    'post_title' => $part_name,
    'post_name' => $post_slug,
  ]);
  add_action( 'save_post', __NAMESPACE__ . '\\update_post_title' );
}
add_action( 'save_post', __NAMESPACE__ . '\\update_post_title' );