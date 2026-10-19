<?php
/**
 * Plugin Name: Nguyễn Tú Smart Core
 * Plugin URI: https://github.com/thuexemayhanoi/theme
 * Description: Verified business facts and conflict-safe structured data. Works independently of the Nguyễn Tú theme.
 * Version: 1.0.1
 * Requires at least: 6.3
 * Requires PHP: 8.2
 * Author: Nguyễn Tú
 * License: GPL-2.0-or-later
 * Text Domain: nguyentu-smart-core
 *
 * @package NguyenTuSmartCore
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'NTSC_VERSION' ) ) {
	return;
}

define( 'NTSC_VERSION', '1.0.1' );
define( 'NTSC_PATH', plugin_dir_path( __FILE__ ) );
define( 'NTSC_MAIN_FILE', __FILE__ );

require_once NTSC_PATH . 'includes/business.php';
require_once NTSC_PATH . 'includes/schema.php';
require_once NTSC_PATH . 'includes/admin.php';
require_once NTSC_PATH . 'includes/audit.php';
require_once NTSC_PATH . 'includes/rental.php';

add_action( 'plugins_loaded', array( 'NT_Smart_Schema', 'register' ), 20 );
add_action( 'init', array( 'NT_Smart_Rental', 'register' ) );
add_action( 'nt_home_prices_after_table', array( 'NT_Smart_Rental', 'echo_calculator' ) );
add_action( 'admin_menu', array( 'NT_Smart_Admin', 'register' ) );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( 'NT_Smart_Admin', 'action_links' ) );
