<?php
/**
 * Inline SVG icon helper. Icons are embedded (see icons-data.php), so they cost no request.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/icons-data.php';

/**
 * Return an inline, decorative SVG icon.
 *
 * @param string              $name Icon key from icons-data.php.
 * @param array<string,mixed> $args size (px), class (extra classes).
 */
function nt_icon( string $name, array $args = array() ): string {
	$data = nt_icons_data();
	if ( ! isset( $data[ $name ] ) ) {
		return '';
	}
	$size  = max( 12, min( 96, (int) ( $args['size'] ?? 20 ) ) );
	$class = trim( 'nt-icon nt-icon--' . $name . ' ' . (string) ( $args['class'] ?? '' ) );
	return sprintf(
		'<svg class="%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
		esc_attr( $class ),
		$size,
		$data[ $name ]
	);
}

/**
 * Echo an icon. The markup comes from the embedded, trusted icon table.
 *
 * @param string              $name Icon key.
 * @param array<string,mixed> $args See nt_icon().
 */
function nt_the_icon( string $name, array $args = array() ): void {
	echo nt_icon( $name, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static trusted SVG paths.
}
