<?php
/**
 * SANDBOX HELPER (not theme code). Platform-level settings applied by setup.sh
 * after WordPress, WooCommerce and Yoast SEO are installed and active.
 * Idempotent. Run with: ./wp.sh eval-file php/configure-site.php
 *
 * Content-level fixtures (permalinks, front page, shop page, Yoast category base...)
 * belong to seed.php, not here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI: wp eval-file\n" ); // phpcs:ignore
	exit( 1 );
}

require_once __DIR__ . '/wc-classic-pages.php';

// sanitize_option('WPLANG') rejects languages that are not installed (no language packs offline)
// and silently keeps the old value. We only want lang="vi", so let the value through.
add_filter(
	'sanitize_option_WPLANG',
	static function ( $value, $option, $original ) {
		return $original;
	},
	10,
	3
);

$nt_set = static function ( $key, $value ) {
	update_option( $key, $value );
	WP_CLI::log( sprintf( '  %-34s = %s', $key, is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ) );
};

WP_CLI::log( 'WordPress options' );
$nt_set( 'blogname', 'Cho Thuê Xe Máy Nguyễn Tú' );
$nt_set( 'blogdescription', 'SANDBOX - môi trường thử nghiệm, không phải website thật' );
$nt_set( 'WPLANG', 'vi' );                       // no language pack available offline: only lang="vi" is needed
$nt_set( 'timezone_string', 'Asia/Ho_Chi_Minh' );
$nt_set( 'gmt_offset', '7' );
$nt_set( 'date_format', 'd/m/Y' );
$nt_set( 'time_format', 'H:i' );
$nt_set( 'start_of_week', '1' );
$nt_set( 'blog_public', '1' );                   // indexable, like production
$nt_set( 'show_avatars', '0' );                  // keep pages hermetic (no Gravatar requests)
$nt_set( 'users_can_register', '0' );

WP_CLI::log( 'WooCommerce options' );
$nt_set( 'woocommerce_currency', 'VND' );
$nt_set( 'woocommerce_price_num_decimals', '0' );
$nt_set( 'woocommerce_price_thousand_sep', '.' );
$nt_set( 'woocommerce_price_decimal_sep', ',' );
$nt_set( 'woocommerce_currency_pos', 'right_space' );
$nt_set( 'woocommerce_default_country', 'VN' );
$nt_set( 'woocommerce_coming_soon', 'no' );      // WooCommerce >= 9.1 defaults new stores to "coming soon"
$nt_set( 'woocommerce_store_pages_only', 'no' );
$nt_set( 'woocommerce_allow_tracking', 'no' );
$nt_set( 'woocommerce_calc_taxes', 'no' );
$nt_set( 'woocommerce_task_list_hidden', 'yes' );

WP_CLI::log( 'Activation redirects / notices' );
delete_transient( '_wc_activation_redirect' );
if ( class_exists( 'WPSEO_Options' ) ) {
	WPSEO_Options::set( 'should_redirect_after_install_free', false );
	WP_CLI::log( '  Yoast: should_redirect_after_install_free = false' );
}

WP_CLI::log( 'WooCommerce classic Cart / Checkout / My account pages (sandbox-only IDs 9001-9003)' );
foreach ( nt_sb_ensure_classic_wc_pages() as $nt_id => $nt_slug ) {
	WP_CLI::log( sprintf( '  page %d  /%s/', $nt_id, $nt_slug ) );
}

flush_rewrite_rules( false );
WP_CLI::success( 'Site configured.' );
