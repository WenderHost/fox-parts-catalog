<?php

add_action( 'graphql_crystal_fields', function( $fields ) {
  $fields['from_frequency'] = [
    'type' => \WPGraphQL\Types::string(),
    'description' => __( 'The lowest available frequency of the part' ),
    'resolve' => function( \WP_Post $post ) {
        return get_post_meta( $post->ID, 'from_frequency', true );
    },
  ];
  $fields['to_frequency'] = [
    'type' => \WPGraphQL\Types::string(),
    'description' => __( 'The highest available frequency of the part' ),
    'resolve' => function( \WP_Post $post ) {
        return get_post_meta( $post->ID, 'to_frequency', true );
    },
  ];
  $fields['size'] = [
    'type' => \WPGraphQL\Types::string(),
    'description' => __( 'The size of the part (e.g. 1.6x1.2 mm)' ),
    'resolve' => function( \WP_Post $post ) {
        return get_post_meta( $post->ID, 'size', true );
    },
  ];
  $fields['package_option'] = [
    'type' => \WPGraphQL\Types::string(),
    'description' => __( 'The part\'s package option, defaults to `BS`' ),
    'resolve' => function( \WP_Post $post ) {
      return get_post_meta( $post->ID, 'package_option', true );
    },
  ];
  $fields['tolerance'] = [
    'type' => \WPGraphQL\Types::string(),
    'description' => __( 'The tolerance of the part' ),
    'resolve' => function( \WP_Post $post ) {
      return get_post_meta( $post->ID, 'tolerance', true );
    },
  ];
  $fields['stability'] = [
    'type' => \WPGraphQL\Types::string(),
    'description' => __( 'The stability of the part' ),
    'resolve' => function( \WP_Post $post ) {
      return get_post_meta( $post->ID, 'stability', true );
    },
  ];
  $fields['load'] = [
    'type' => \WPGraphQL\Types::string(),
    'description' => __( 'The load of the part' ),
    'resolve' => function( \WP_Post $post ) {
      return get_post_meta( $post->ID, 'load', true );
    },
  ];
  $fields['optemp'] = [
    'type' => \WPGraphQL\Types::string(),
    'description' => __( 'The part\'s operating temperature range' ),
    'resolve' => function( \WP_Post $post ) {
      return get_post_meta( $post->ID, 'optemp', true );
    },
  ];
  return $fields;
} );