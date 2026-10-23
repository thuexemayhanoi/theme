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

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Hook stand-in (hooks are not fired in unit tests).
	 *
	 * @param string   $hook     Hook.
	 * @param callable $callback Callback.
	 */
	function add_action( string $hook, $callback ): bool {
		unset( $hook, $callback );
		return true;
	}
}
if ( ! function_exists( 'remove_accents' ) ) {
	/**
	 * Accent stripping stand-in with ICU, the same fallback nt_slugify() uses without WordPress.
	 *
	 * @param string $text Text.
	 */
	function remove_accents( string $text ): string {
		return (string) transliterator_transliterate( 'Any-Latin; Latin-ASCII', $text );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Text sanitising stand-in (tags removed, whitespace collapsed).
	 *
	 * @param string $text Text.
	 */
	function sanitize_text_field( string $text ): string {
		return trim( (string) preg_replace( '/\s+/', ' ', strip_tags( $text ) ) );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Key sanitising stand-in.
	 *
	 * @param string $key Key.
	 */
	function sanitize_key( string $key ): string {
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
	}
}

foreach ( array( 'lib/text', 'lib/html', 'lib/css', 'lib/prices', 'lib/layout', 'business', 'navigation-labels', 'legacy/helpers', 'pages', 'assist' ) as $nt_unit_file ) {
	require_once NT_TEST_ROOT . '/inc/' . $nt_unit_file . '.php';
}
unset( $nt_unit_file );
