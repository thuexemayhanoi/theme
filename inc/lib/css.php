<?php
/**
 * Strict CSS value sanitisers used when legacy shortcode attributes become inline styles.
 *
 * Everything returns an empty string when the value is not on the allow-list, so a
 * shortcode attribute can never smuggle arbitrary CSS or markup into the page.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Length such as 60, 60px, 4rem, 50%, 40vh. Bare numbers are treated as px.
 *
 * @param mixed  $value   Raw attribute value.
 * @param string $default Fallback.
 */
function nt_css_len( $value, string $default = '' ): string {
	$value = trim( (string) $value );
	if ( 'auto' === $value || '0' === $value ) {
		return $value;
	}
	if ( preg_match( '/^-?\d{1,4}(?:\.\d{1,3})?$/', $value ) ) {
		return $value . 'px';
	}
	if ( preg_match( '/^-?\d{1,4}(?:\.\d{1,3})?(?:px|%|rem|em|vh|vw|ch)$/i', $value ) ) {
		return strtolower( $value );
	}
	return $default;
}

/**
 * Up to four space separated lengths (padding / margin shorthand).
 *
 * @param mixed  $value   Raw attribute value.
 * @param string $default Fallback.
 */
function nt_css_box( $value, string $default = '' ): string {
	$parts = preg_split( '/\s+/', trim( (string) $value ), -1, PREG_SPLIT_NO_EMPTY );
	if ( false === $parts || array() === $parts || count( $parts ) > 4 ) {
		return $default;
	}
	$out = array();
	foreach ( $parts as $part ) {
		$len = nt_css_len( $part );
		if ( '' === $len ) {
			return $default;
		}
		$out[] = $len;
	}
	return implode( ' ', $out );
}

/**
 * Colour: hex, rgb()/rgba()/hsl()/hsla() with plain numbers, or a few keywords.
 *
 * @param mixed  $value   Raw attribute value.
 * @param string $default Fallback.
 */
function nt_css_color( $value, string $default = '' ): string {
	$value = trim( (string) $value );
	if ( preg_match( '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value ) ) {
		return strtolower( $value );
	}
	if ( preg_match( '/^(?:rgb|hsl)a?\(\s*[\d.]+%?\s*[, ]\s*[\d.]+%?\s*[, ]\s*[\d.]+%?\s*(?:[,\/]\s*[\d.]+%?\s*)?\)$/i', $value ) ) {
		return strtolower( preg_replace( '/\s+/', ' ', $value ) ?? '' );
	}
	if ( in_array( strtolower( $value ), array( 'transparent', 'currentcolor', 'white', 'black' ), true ) ) {
		return strtolower( $value );
	}
	return $default;
}

/**
 * Background position such as "50% 50%", "center", "left top".
 *
 * @param mixed  $value   Raw attribute value.
 * @param string $default Fallback.
 */
function nt_css_position( $value, string $default = '' ): string {
	$value = strtolower( trim( (string) $value ) );
	if ( preg_match( '/^(?:(?:left|right|center|top|bottom|-?\d{1,3}(?:\.\d+)?%?)(?:\s+|$)){1,2}$/', $value . ' ' ) ) {
		return trim( $value );
	}
	return $default;
}

/**
 * Integer clamp for column spans and similar.
 *
 * @param mixed $value Raw attribute value.
 * @param int   $min   Minimum.
 * @param int   $max   Maximum.
 * @param int   $def   Fallback when not numeric.
 */
function nt_css_int( $value, int $min, int $max, int $def ): int {
	if ( ! is_numeric( $value ) ) {
		return $def;
	}
	return max( $min, min( $max, (int) $value ) );
}

/**
 * Build a style attribute value from custom properties and declarations.
 *
 * Empty values are skipped. Callers must still run esc_attr() on the result.
 *
 * @param array<string,string> $decls Map of property => value (already sanitised).
 */
function nt_css_style( array $decls ): string {
	$out = '';
	foreach ( $decls as $prop => $val ) {
		if ( '' === $val || ! preg_match( '/^(?:--[a-z0-9-]+|[a-z-]+)$/', (string) $prop ) ) {
			continue;
		}
		$out .= $prop . ':' . $val . ';';
	}
	return $out;
}

/**
 * Split a class attribute into safe class tokens.
 *
 * @param mixed $value Raw attribute value.
 * @return string[]
 */
function nt_css_classes( $value ): array {
	$out   = array();
	$parts = preg_split( '/\s+/', trim( (string) $value ), -1, PREG_SPLIT_NO_EMPTY );
	foreach ( false === $parts ? array() : $parts as $part ) {
		if ( preg_match( '/^[A-Za-z_][A-Za-z0-9_-]{0,63}$/', $part ) ) {
			$out[] = $part;
		}
	}
	return $out;
}
