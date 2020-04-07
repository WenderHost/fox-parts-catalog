<?php
/**
 * Plugin Name:       Fox Electronics Parts Catalog
 * Plugin URI:        https://github.com/WenderHost/fox-parts-catalog
 * Description:       Adds CPTs necessary to store the Fox Electronics Parts Catalog inside the WordPress database.
 * GitHub Plugin URI: https://github.com/WenderHost/fox-parts-catalog
 * Author:            Michael Wender
 * Author URI:        https://mwender.com
 * Text Domain:       fox-electronics
 * Domain Path:       /languages
 * Version:           1.0.0
 *
 * @package         Proposal_Manager
 */
define( 'FOXPC_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'FOXPC_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'FOXPC_PART_TYPES', ['C' => 'crystal', 'K' => 'crystal-khz','O' => 'oscillator','T' => 'tcxo','Y' => 'vcxo','S' => 'sso'] );
define( 'FOXPC_FOXSELECT_URL', '#elementor-action%3Aaction%3Dpopup%3Aopen%26settings%3DeyJpZCI6IjMxMDQyIiwidG9nZ2xlIjpmYWxzZX0%3D' );

// Initialize TypeRocket
require ( 'lib/typerocket/init.php' );

// Load Post Types
require ( 'lib/post-types/foxpart.php' );

// Load Functions
require ( 'lib/fns/enqueues.php' );
require ( 'lib/fns/redirection.php' );
require ( 'lib/fns/rest-api.php' );
require ( 'lib/fns/save_post.php' );
require ( 'lib/fns/shortcodes.php' );
require ( 'lib/fns/query_vars.php' );
require ( 'lib/fns/utilities.php' );

// WP-CLI
require ( 'lib/wpcli/foxparts.php' );

function foxparts_error_log( $message = null ){
  static $counter = 1;

  $bt = debug_backtrace();
  $caller = array_shift( $bt );

  if( 1 == $counter )
    error_log( "\n\n" . str_repeat('-', 25 ) . ' STARTING DEBUG [' . date('h:i:sa', current_time('timestamp') ) . '] ' . str_repeat('-', 25 ) . "\n\n" );
  error_log( "\n 👉 " . $counter . '. ' . basename( $caller['file'] ) . '::' . $caller['line'] . "\n" . $message . "\n---\n" );
  $counter++;
}
