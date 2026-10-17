<?php
/**
 * Shared helpers for the legacy shortcode handlers.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalise shortcode attributes: lower-case keys, fill defaults, never fail on bad input.
 *
 * @param array<string,string> $defaults Attribute defaults.
 * @param mixed                $atts     Raw attributes.
 * @param string               $tag      Shortcode tag.
 * @return array<string,string>
 */
function nt_lg_atts( array $defaults, $atts, string $tag ): array {
	$atts = is_array( $atts ) ? array_change_key_case( $atts, CASE_LOWER ) : array();
	return shortcode_atts( $defaults, $atts, $tag );
}

/**
 * Truthy shortcode value: "true", "1", "yes", "on".
 *
 * @param mixed $value Raw value.
 */
function nt_lg_bool( $value ): bool {
	return in_array( strtolower( trim( (string) $value ) ), array( 'true', '1', 'yes', 'on' ), true );
}

/**
 * Render nested shortcodes and trim leftovers from automatic paragraphing.
 *
 * @param string $content Inner content.
 */
function nt_lg_inner( string $content ): string {
	$html = do_shortcode( $content );
	// Leading/trailing <br> or empty paragraphs are never intentional.
	$html = preg_replace( '#^(?:\s|<br\s*/?>|<p>\s*</p>)+#i', '', $html ) ?? $html;
	$html = preg_replace( '#(?:\s|<br\s*/?>|<p>\s*</p>)+$#i', '', $html ) ?? $html;
	return $html;
}

/**
 * CSS class tokens for the visibility attribute.
 *
 * @param string $visibility Raw attribute value.
 * @return string[]
 */
function nt_lg_visibility_classes( string $visibility ): array {
	$map = array(
		'hidden'          => 'nt-lg-hidden',
		'hide-for-small'  => 'nt-lg-hide-sm',
		'hide-for-medium' => 'nt-lg-hide-md',
		'show-for-small'  => 'nt-lg-show-sm',
		'show-for-medium' => 'nt-lg-show-md',
	);
	$out = array();
	foreach ( preg_split( '/\s+/', strtolower( trim( $visibility ) ), -1, PREG_SPLIT_NO_EMPTY ) ?: array() as $token ) {
		if ( isset( $map[ $token ] ) ) {
			$out[] = $map[ $token ];
		}
	}
	return $out;
}

/**
 * Build a class attribute value.
 *
 * @param string[] $classes Class tokens.
 */
function nt_lg_class( array $classes ): string {
	return esc_attr( implode( ' ', array_values( array_unique( array_filter( $classes ) ) ) ) );
}

/**
 * Safe HTML id from an attribute.
 *
 * @param string $id Raw id.
 */
function nt_lg_id_attr( string $id ): string {
	$id = trim( $id );
	return ( '' !== $id && preg_match( '/^[A-Za-z][A-Za-z0-9_:\-.]{0,63}$/', $id ) ) ? ' id="' . esc_attr( $id ) . '"' : '';
}

/**
 * Whether the page's first hero image has already been marked as the LCP candidate.
 *
 * @param bool $claim Claim the slot (returns true once per request).
 */
function nt_lg_claim_lcp( bool $claim = true ): bool {
	static $taken = false;
	if ( $taken ) {
		return false;
	}
	if ( $claim ) {
		$taken = true;
	}
	return true;
}

/**
 * Responsive image for an attachment id or a plain URL.
 *
 * Alt text is whatever the media library holds; nothing is rewritten. Decorative background
 * images pass $decorative = true and get an empty alt.
 *
 * @param string              $ref  Attachment id or URL.
 * @param array<string,mixed> $args size, class, sizes, decorative, eager.
 */
function nt_lg_image_html( string $ref, array $args = array() ): string {
	$args = wp_parse_args(
		$args,
		array(
			'size'       => 'large',
			'class'      => 'nt-lg-img',
			'sizes'      => '100vw',
			'decorative' => false,
			'eager'      => false,
		)
	);
	$ref  = trim( $ref );
	if ( '' === $ref ) {
		return '';
	}
	$attr = array(
		'class'    => (string) $args['class'],
		'sizes'    => (string) $args['sizes'],
		'decoding' => 'async',
		'loading'  => $args['eager'] ? 'eager' : 'lazy',
	);
	if ( $args['eager'] ) {
		$attr['fetchpriority'] = 'high';
	}
	if ( $args['decorative'] ) {
		$attr['alt'] = '';
	}
	if ( ctype_digit( $ref ) ) {
		$html = wp_get_attachment_image( (int) $ref, (string) $args['size'], false, $attr );
		return is_string( $html ) ? $html : '';
	}
	$url = esc_url( $ref );
	if ( '' === $url ) {
		return '';
	}
	return sprintf(
		'<img src="%s" class="%s" alt="%s" loading="%s" decoding="async">',
		$url,
		esc_attr( (string) $args['class'] ),
		$args['decorative'] ? '' : '',
		$args['eager'] ? 'eager' : 'lazy'
	);
}

/**
 * Link target/rel attributes for an anchor. A "rel" given in the content is preserved as is.
 *
 * @param string $target Target attribute.
 * @param string $rel    Rel attribute from the content.
 */
function nt_lg_link_attrs( string $target, string $rel = '' ): string {
	$out = '';
	$rel = trim( $rel );
	if ( '_blank' === $target ) {
		$out .= ' target="_blank"';
		$rel  = trim( $rel . ' noopener' );
	}
	if ( '' !== $rel ) {
		$out .= ' rel="' . esc_attr( implode( ' ', array_unique( explode( ' ', $rel ) ) ) ) . '"';
	}
	return $out;
}

/**
 * Parse a comma list of ids/slugs into separate arrays.
 *
 * @param string $value Attribute value.
 * @return array{ids:int[],slugs:string[]}
 */
function nt_lg_id_slug_list( string $value ): array {
	$ids   = array();
	$slugs = array();
	foreach ( explode( ',', $value ) as $part ) {
		$part = trim( $part );
		if ( '' === $part ) {
			continue;
		}
		if ( ctype_digit( $part ) ) {
			$ids[] = (int) $part;
		} elseif ( preg_match( '/^[a-z0-9\-_]+$/i', $part ) ) {
			$slugs[] = sanitize_title( $part );
		}
	}
	return array(
		'ids'   => $ids,
		'slugs' => $slugs,
	);
}

/**
 * Map a numeric or unit font-size value to a safe CSS length (bare 1.2 means 1.2em).
 *
 * @param string $value Attribute value.
 */
function nt_lg_font_size( string $value ): string {
	$value = trim( $value );
	if ( preg_match( '/^\d(?:\.\d+)?$/', $value ) ) {
		return $value . 'em';
	}
	if ( preg_match( '/^\d{2,3}%$/', $value ) ) {
		return $value;
	}
	return nt_css_len( $value );
}

/**
 * Map a Flatsome icon name to an inline icon key.
 *
 * @param string $icon Attribute value such as "icon-phone".
 */
function nt_lg_icon_key( string $icon ): string {
	$icon = strtolower( trim( $icon ) );
	$map  = array(
		'icon-phone'            => 'phone',
		'icon-phone-call'       => 'phone-call',
		'icon-whatsapp'         => 'chat',
		'icon-chat'             => 'chat',
		'icon-angle-right'      => 'arrow-right',
		'icon-arrow-right'      => 'arrow-right',
		'icon-long-arrow-right' => 'arrow-right',
		'icon-map-pin-fill'     => 'pin',
		'icon-map-pin'          => 'pin',
		'icon-clock'            => 'clock',
		'icon-envelop'          => 'mail',
		'icon-checkmark'        => 'check',
		'icon-search'           => 'search',
	);
	return $map[ $icon ] ?? '';
}
