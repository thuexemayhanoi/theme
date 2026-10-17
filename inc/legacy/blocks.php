<?php
/**
 * Legacy content-block shortcodes: button, message box, raw HTML, accordion, tabs, anchors,
 * share, follow, search, lightbox.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * [button]
 *
 * @param mixed $atts Attributes.
 */
function nt_lg_button( $atts ): string {
	$a = nt_lg_atts(
		array(
			'text'       => '',
			'link'       => '',
			'target'     => '',
			'rel'        => '',
			'style'      => '',
			'color'      => 'primary',
			'size'       => 'normal',
			'radius'     => '',
			'expand'     => '',
			'icon'       => '',
			'icon_pos'   => 'left',
			'class'      => '',
			'visibility' => '',
			'id'         => '',
		),
		$atts,
		'button'
	);
	$text = trim( $a['text'] );
	if ( '' === $text ) {
		return '';
	}
	$colors = array(
		'primary'   => 'primary',
		'secondary' => 'accent',
		'alert'     => 'accent',
		'success'   => 'success',
		'white'     => 'white',
	);
	$color  = $colors[ strtolower( $a['color'] ) ] ?? 'primary';
	$styles = array( 'outline', 'link', 'underline' );
	$style  = in_array( strtolower( $a['style'] ), $styles, true ) ? strtolower( $a['style'] ) : 'solid';
	$sizes  = array( 'xsmall', 'smaller', 'small', 'normal', 'large', 'larger', 'xlarge' );
	$size   = in_array( strtolower( $a['size'] ), $sizes, true ) ? strtolower( $a['size'] ) : 'normal';

	$classes = array( 'nt-btn', 'nt-lg-btn', 'nt-lg-btn--' . $color, 'nt-lg-btn--' . $style, 'nt-lg-btn--' . $size );
	if ( nt_lg_bool( $a['expand'] ) ) {
		$classes[] = 'nt-lg-btn--expand';
	}
	$classes = array_merge( $classes, nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) );

	$icon_key = nt_lg_icon_key( $a['icon'] );
	$icon     = '' !== $icon_key ? nt_icon( $icon_key, array( 'size' => 18 ) ) : '';
	$label    = '<span>' . esc_html( $text ) . '</span>';
	$inner    = ( 'right' === $a['icon_pos'] ) ? $label . $icon : $icon . $label;
	$radius   = nt_css_len( $a['radius'] );

	return sprintf(
		'<a class="%1$s" href="%2$s"%3$s%4$s%5$s>%6$s</a>',
		nt_lg_class( $classes ),
		esc_url( $a['link'] ),
		nt_lg_link_attrs( $a['target'], $a['rel'] ),
		'' !== $radius ? ' style="border-radius:' . esc_attr( $radius ) . '"' : '',
		nt_lg_id_attr( $a['id'] ),
		$inner
	);
}

/**
 * [message_box]
 *
 * @param mixed  $atts    Attributes.
 * @param string $content Inner content.
 */
function nt_lg_message_box( $atts, $content = '' ): string {
	$a = nt_lg_atts(
		array(
			'bg_color'   => '',
			'padding'    => '',
			'text_color' => '',
			'class'      => '',
			'visibility' => '',
		),
		$atts,
		'message_box'
	);
	$style = nt_css_style(
		array(
			'background-color' => nt_css_color( $a['bg_color'] ),
			'padding'          => nt_css_box( $a['padding'] ),
			'color'            => nt_css_color( $a['text_color'] ),
		)
	);
	return sprintf(
		'<div class="%s"%s>%s</div>',
		nt_lg_class( array_merge( array( 'nt-lg-message' ), nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) ),
		'' !== $style ? ' style="' . esc_attr( $style ) . '"' : '',
		nt_lg_inner( $content )
	);
}

/**
 * [ux_html]: authored HTML, rendered exactly like the rest of post_content (same trust level).
 *
 * @param mixed  $atts    Attributes.
 * @param string $content Authored HTML.
 */
function nt_lg_html( $atts, $content = '' ): string {
	$a = nt_lg_atts(
		array(
			'class'      => '',
			'visibility' => '',
		),
		$atts,
		'ux_html'
	);
	return sprintf(
		'<div class="%s">%s</div>',
		nt_lg_class( array_merge( array( 'nt-lg-html' ), nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) ),
		do_shortcode( $content )
	);
}

/**
 * [accordion]: items become native <details> elements. Collapsed content stays in the HTML.
 *
 * @param mixed  $atts    Attributes.
 * @param string $content Accordion items.
 */
function nt_lg_accordion( $atts, $content = '' ): string {
	$a = nt_lg_atts(
		array(
			'class'      => '',
			'visibility' => '',
		),
		$atts,
		'accordion'
	);
	return sprintf(
		'<div class="%s">%s</div>',
		nt_lg_class( array_merge( array( 'nt-lg-accordion' ), nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) ),
		nt_lg_inner( $content )
	);
}

/**
 * [accordion-item title="..."]
 *
 * @param mixed  $atts    Attributes.
 * @param string $content Item body.
 */
function nt_lg_accordion_item( $atts, $content = '' ): string {
	$a = nt_lg_atts(
		array(
			'title' => '',
			'open'  => '',
		),
		$atts,
		'accordion-item'
	);
	return sprintf(
		'<details class="nt-lg-accordion__item"%s><summary>%s</summary><div class="nt-lg-accordion__body">%s</div></details>',
		nt_lg_bool( $a['open'] ) ? ' open' : '',
		esc_html( $a['title'] ),
		nt_lg_inner( $content )
	);
}

/**
 * [tabgroup]: tabs are flattened into stacked, fully visible sections (nothing is hidden).
 *
 * @param mixed  $atts    Attributes.
 * @param string $content Tabs.
 */
function nt_lg_tabgroup( $atts, $content = '' ): string {
	$a = nt_lg_atts(
		array(
			'class'      => '',
			'visibility' => '',
		),
		$atts,
		'tabgroup'
	);
	return sprintf(
		'<div class="%s">%s</div>',
		nt_lg_class( array_merge( array( 'nt-lg-tabs' ), nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) ),
		nt_lg_inner( $content )
	);
}

/**
 * [tab title="..."]
 *
 * @param mixed  $atts    Attributes.
 * @param string $content Tab body.
 */
function nt_lg_tab( $atts, $content = '' ): string {
	$a = nt_lg_atts( array( 'title' => '' ), $atts, 'tab' );
	return sprintf(
		'<section class="nt-lg-tab">%s<div class="nt-lg-tab__body">%s</div></section>',
		'' !== $a['title'] ? '<h3 class="nt-lg-tab__title">' . esc_html( $a['title'] ) . '</h3>' : '',
		nt_lg_inner( $content )
	);
}

/**
 * [scroll_to link="#anchor"]: an in-page anchor target.
 *
 * @param mixed $atts Attributes.
 */
function nt_lg_scroll_to( $atts ): string {
	$a  = nt_lg_atts(
		array(
			'link'  => '',
			'title' => '',
		),
		$atts,
		'scroll_to'
	);
	$id = ltrim( trim( $a['link'] ), '#' );
	return preg_match( '/^[A-Za-z][A-Za-z0-9_\-]{0,63}$/', $id ) ? '<span id="' . esc_attr( $id ) . '" class="nt-lg-anchor"></span>' : '';
}

/**
 * [share]
 */
function nt_lg_share(): string {
	ob_start();
	nt_share_links( (string) get_permalink(), wp_strip_all_tags( get_the_title() ) );
	return (string) ob_get_clean();
}

/**
 * [search]
 */
function nt_lg_search(): string {
	return '<div class="nt-lg-search">' . get_search_form( array( 'echo' => false ) ) . '</div>';
}

/**
 * [lightbox link="..."]: without the lightbox script the content becomes a normal link.
 *
 * @param mixed  $atts    Attributes.
 * @param string $content Inner content.
 */
function nt_lg_lightbox( $atts, $content = '' ): string {
	$a    = nt_lg_atts( array( 'link' => '' ), $atts, 'lightbox' );
	$text = nt_lg_inner( $content );
	if ( '' === $a['link'] || false !== stripos( $text, '<a ' ) ) {
		return $text;
	}
	return sprintf( '<a class="nt-lg-lightbox" href="%s">%s</a>', esc_url( $a['link'] ), $text );
}

/**
 * [follow facebook="..." instagram="..."]: plain text links for the networks that were given.
 *
 * @param mixed $atts Attributes.
 */
function nt_lg_follow( $atts ): string {
	$a = nt_lg_atts(
		array(
			'facebook'  => '',
			'instagram' => '',
			'youtube'   => '',
			'tiktok'    => '',
			'twitter'   => '',
			'linkedin'  => '',
			'pinterest' => '',
		),
		$atts,
		'follow'
	);
	$labels = array(
		'facebook'  => 'Facebook',
		'instagram' => 'Instagram',
		'youtube'   => 'YouTube',
		'tiktok'    => 'TikTok',
		'twitter'   => 'X',
		'linkedin'  => 'LinkedIn',
		'pinterest' => 'Pinterest',
	);
	$items = '';
	foreach ( $labels as $key => $label ) {
		$url = esc_url( $a[ $key ] );
		if ( '' !== $url ) {
			$items .= sprintf( '<li><a href="%s" target="_blank" rel="noopener me">%s</a></li>', $url, esc_html( $label ) );
		}
	}
	return '' === $items ? '' : '<ul class="nt-lg-follow">' . $items . '</ul>';
}
