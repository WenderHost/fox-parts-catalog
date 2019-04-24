<?php
/**
 * Plugin Name:     Fox Electronics Parts Catalog
 * Plugin URI:      https://github.com/WenderHost/fox-parts-catalog
 * Description:     Adds CPTs necessary to store the Fox Electronics Parts Catalog inside the WordPress database.
 * Author:          Michael Wender
 * Author URI:      https://mwender.com
 * Text Domain:     fox-electronics
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Proposal_Manager
 */
define( 'FOXPC_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'FOXPC_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'FOXPC_PART_TYPES', ['C' => 'crystal', 'K' => 'crystal-khz','O' => 'oscillator','T' => 'tcxo','Y' => 'vcxo','S' => 'sso'] );

// Initialize TypeRocket
require ( 'lib/typerocket/init.php' );

// Load Post Types
require ( 'lib/post-types/foxpart.php' );

// Load Functions
require ( 'lib/fns/enqueues.php' );
require ( 'lib/fns/rest-api.php' );
require ( 'lib/fns/save_post.php' );
require ( 'lib/fns/shortcodes.php' );

// WP-CLI
require ( 'lib/wpcli/foxparts.php' );

/*
add_filter( 'allowed_http_origins', 'add_allowed_origins' );
function add_allowed_origins( $origins ) {
    $origins[] = 'http://localhost:3000';
    return $origins;
}
*/