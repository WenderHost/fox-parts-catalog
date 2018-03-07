<?php
/**
 * Plugin Name:     Fox Electronics Parts Catalog
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     Adds CPTs necessary to store the Fox Electronics Parts Catalog inside the WordPress database.
 * Author:          Michael Wender
 * Author URI:      https://mwender.com
 * Text Domain:     proposal-manager
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Proposal_Manager
 */
define( 'FOXPC_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'FOXPC_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );

// Initialize TypeRocket
require ( 'lib/typerocket/init.php' );

// Load Post Types
require ( 'lib/post-types/crystal.php' );
require ( 'lib/post-types/crystal.custom-fields.php' );
require ( 'lib/post-types/crystal.filters.php' );
require ( 'lib/fns/rest-api.php' );
require ( 'lib/fns/save_post.php' );
require ( 'lib/wpcli/foxparts.php' );

/*
add_filter( 'allowed_http_origins', 'add_allowed_origins' );
function add_allowed_origins( $origins ) {
    $origins[] = 'http://localhost:3000';
    return $origins;
}
*/