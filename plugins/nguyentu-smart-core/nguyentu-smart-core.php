<?php
/**
 * Plugin Name: Nguyễn Tú Smart Core
 * Plugin URI: https://github.com/thuexemayhanoi/theme
 * Description: Verified business facts and conflict-safe structured data. Works independently of the Nguyễn Tú theme.
 * Version: 0.1.0
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

define( 'NTSC_VERSION', '0.1.0' );
define( 'NTSC_PATH', plugin_dir_path( __FILE__ ) );
define( 'NTSC_MAIN_FILE', __FILE__ );

require_once NTSC_PATH . 'includes/business.php';
require_once NTSC_PATH . 'includes/schema.php';
require_once NTSC_PATH . 'includes/admin.php';

add_action( 'plugins_loaded', array( 'NTSC_Schema', 'register' ), 20 );
add_action( 'admin_menu', array( 'NTSC_Admin', 'register' ) );
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( 'NTSC_Admin', 'action_links' ) );
