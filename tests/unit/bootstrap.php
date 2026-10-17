<?php
/**
 * Unit-test bootstrap: loads the pure theme libraries with tiny stand-ins for the few WordPress
 * functions they touch. No WordPress, database or network is used.
 *
 * @package NguyenTu
 */

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
define( 'NT_TEST_ROOT', dirname( __DIR__, 2 ) );

$GLOBALS['nt_test_options'] = array();

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Options stand-in.
	 *
	 * @param string $name    Option name.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( string $name, $default = false ) {
		return $GLOBALS['nt_test_options'][ $name ] ?? $default;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Filter stand-in (identity).
	 *
	 * @param string $tag   Hook.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( string $tag, $value ) {
		return $value;
	}
}
if ( ! function_exists( 'sanitize_title' ) ) {
	/**
	 * Slug stand-in.
	 *
	 * @param string $title Title.
	 */
	function sanitize_title( string $title ): string {
		return nt_slugify( $title );
	}
}
if ( ! function_exists( 'shortcode_atts' ) ) {
	/**
	 * Shortcode attribute stand-in.
	 *
	 * @param array<string,mixed> $pairs Defaults.
	 * @param array<string,mixed> $atts  Attributes.
	 * @return array<string,mixed>
	 */
	function shortcode_atts( array $pairs, array $atts ): array {
		return array_merge( $pairs, array_intersect_key( $atts, $pairs ) );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Escaping stand-in.
	 *
	 * @param string $text Text.
	 */
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * URL escaping stand-in.
	 *
	 * @param string $url URL.
	 */
	function esc_url( string $url ): string {
		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}
}

foreach ( array( 'lib/text', 'lib/html', 'lib/css', 'lib/prices', 'business', 'legacy/helpers' ) as $nt_unit_file ) {
	require_once NT_TEST_ROOT . '/inc/' . $nt_unit_file . '.php';
}
unset( $nt_unit_file );
