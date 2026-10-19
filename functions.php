<?php
/**
 * Nguyễn Tú Editorial Commerce — theme bootstrap.
 *
 * The theme is a presentation layer only. It never writes posts, terms, menus,
 * media or SEO metadata, and it never replaces what Yoast SEO outputs.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

define( 'NT_VERSION', '1.0.0' );
define( 'NT_DIR', get_template_directory() );
define( 'NT_URI', get_template_directory_uri() );

if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>';
			echo esc_html(
				sprintf(
					/* translators: %s: current PHP version */
					__( 'Theme Nguyễn Tú Editorial Commerce cần PHP 8.2 trở lên. Phiên bản hiện tại: %s.', 'nguyentu' ),
					PHP_VERSION
				)
			);
			echo '</p></div>';
		}
	);
	return;
}

/**
 * Module load order matters: pure libraries first, then data, then WordPress glue.
 */
$nt_modules = array(
	'lib/text',
	'lib/html',
	'lib/css',
	'lib/prices',
	'lib/layout',
	'icons',
	'business',
	'footer',
	'settings',
	'setup',
	'enqueue',
	'menus',
	'seo',
	'schema',
	'content',
	'related',
	'template-tags',
	'home',
	'featured',
	'woocommerce',
	'legacy/legacy',
	'cli',
);

foreach ( $nt_modules as $nt_module ) {
	require_once NT_DIR . '/inc/' . $nt_module . '.php';
}
unset( $nt_modules, $nt_module );
