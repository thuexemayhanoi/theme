<?php
/**
 * Uninstall: removes only this plugin's own option. Posts, terms, menus and media are never touched.
 *
 * @package NguyenTuLegacyCompat
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'nguyentu_legacy_compat' );
