<?php

namespace FoxParts\save_post;

/**
 * Updates the post name of  a `foxpart` CPT to match the
 * configured part no.
 *
 * @param      int  $post_id  The Post ID
 *
 * @return     int  Returns the Post ID upon failure.
 */
function update_post_title( $post_id ){
  $post_type = get_post_type( $post_id );
  if( 'foxpart' != $post_type )
    return $post_id;

  $part_types = FOXPC_PART_TYPES;

  $terms = wp_get_post_terms( $post_id, 'part_type' );
  if( ! $terms ){
    return $post_id;
  } else if ( 1 < count( $terms ) ){
    return $post_id;
  }
  $part_type = $terms[0]->slug;

  // Verify that this is a Taxonomy we need to update
  if( ! in_array( $part_type, $part_types ) )
    return $post_id;

  // Verify all custom fields are set
  global $post_data;
  $meta = get_metadata( 'post', $post_id );
  switch ( $part_type ) {
    case 'crystal':
      $check_keys = ['size','package_option','tolerance','stability','optemp'];
      break;

    case 'crystal-khz':
      $check_keys = ['size','tolerance','stability','optemp'];
      break;

    case 'oscillator':
      $check_keys = ['size','output','voltage','stability','optemp'];
      break;

    default:
      return $post_id;
      break;
  }
  foreach( $check_keys as $key ){
    if( ! array_key_exists( $key, $meta ) )
      return $post_id;
  }

  // Build the part name
  switch ( $part_type ) {
    case 'crystal':
      $part_name = 'F' . array_search( $part_type, $part_types ) . $meta['size'][0] . $meta['package_option'][0] . $meta['tolerance'][0] . $meta['stability'][0] . '_' . $meta['optemp'][0];
      break;

    case 'crystal-khz':
      $part_name = 'F' . array_search( $part_type, $part_types ) . $meta['size'][0] . $meta['tolerance'][0] . $meta['stability'][0] . $meta['optemp'][0];
      break;

    case 'oscillator':
      $part_name = 'F' . array_search( $part_type, $part_types ) . $meta['size'][0] . $meta['output'][0] . $meta['voltage'][0] . $meta['stability'][0] . '_' . $meta['optemp'][0];
      break;

    default:
      return $post_id;
      break;
  }

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