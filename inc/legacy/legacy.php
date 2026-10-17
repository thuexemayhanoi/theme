<?php
/**
 * Legacy content bridge.
 *
 * Pages 562 (home), 310, 372, 424, 432 and 494 contain Flatsome UX Builder shortcodes. Once the
 * previous theme is switched off those shortcodes would print as raw text. This bridge renders
 * a documented, finite set of them as clean semantic HTML with the theme's own classes.
 *
 * Boundaries (see docs/LEGACY-COMPAT.md for the full matrix):
 *  - It is NOT a UX Builder clone and does not copy any proprietary code. It reads the public
 *    shortcode syntax and maps the commonly used structural tags to plain HTML.
 *  - A tag is registered ONLY when no other plugin/theme already registered it, so with
 *    Flatsome active (rollback) the bridge does nothing at all.
 *  - Stored post_content is never modified; everything happens at render time.
 *  - Tags that are not supported are removed from the output (their inner content is kept)
 *    and replaced by an HTML comment, so raw "[ux_something]" text can never reach visitors.
 *  - Heading levels, text, links and images in the content are preserved as authored.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/media.php';
require_once __DIR__ . '/blocks.php';
require_once __DIR__ . '/dynamic.php';

/**
 * Tag => handler. Tags listed here are rendered; see nt_legacy_stripped_tags() for the rest.
 *
 * @return array<string,string>
 */
function nt_legacy_handlers(): array {
	return array(
		// Structure.
		'section'               => 'nt_lg_section',
		'row'                   => 'nt_lg_row',
		'row_inner'             => 'nt_lg_row',
		'col'                   => 'nt_lg_col',
		'col_inner'             => 'nt_lg_col',
		'gap'                   => 'nt_lg_gap',
		'divider'               => 'nt_lg_divider',
		'title'                 => 'nt_lg_title',
		'ux_banner'             => 'nt_lg_banner',
		'text_box'              => 'nt_lg_text_box',
		'ux_text'               => 'nt_lg_text',
		// Media.
		'ux_image'              => 'nt_lg_image',
		'ux_image_box'          => 'nt_lg_image_box',
		'ux_gallery'            => 'nt_lg_gallery',
		'ux_video'              => 'nt_lg_video',
		'ux_slider'             => 'nt_lg_slider',
		'map'                   => 'nt_lg_map',
		// Content blocks.
		'button'                => 'nt_lg_button',
		'message_box'           => 'nt_lg_message_box',
		'ux_html'               => 'nt_lg_html',
		'accordion'             => 'nt_lg_accordion',
		'accordion-item'        => 'nt_lg_accordion_item',
		'tabgroup'              => 'nt_lg_tabgroup',
		'tab'                   => 'nt_lg_tab',
		'scroll_to'             => 'nt_lg_scroll_to',
		'share'                 => 'nt_lg_share',
		'search'                => 'nt_lg_search',
		'lightbox'              => 'nt_lg_lightbox',
		'follow'                => 'nt_lg_follow',
		// Dynamic (data from WordPress / WooCommerce).
		'ux_products'           => 'nt_lg_products',
		'ux_product_categories' => 'nt_lg_product_categories',
		'blog_posts'            => 'nt_lg_blog_posts',
		'ux_portfolio'          => 'nt_lg_portfolio',
	);
}

/**
 * Known Flatsome tags that the bridge deliberately does NOT render (decorative or
 * interactive widgets that need Flatsome's JavaScript). They are stripped, content kept.
 *
 * @return string[]
 */
function nt_legacy_stripped_tags(): array {
	return array(
		'ux_hotspot',
		'ux_countdown',
		'ux_lottie',
		'ux_banner_grid',
		'col_grid',
		'ux_instagram_feed',
		'ux_stack',
		'ux_menu',
		'ux_menu_link',
		'ux_menu_title',
		'ux_sidebar',
		'ux_pages',
		'ux_payment_icons',
		'ux_logo',
		'logo',
		'product_flip',
		'featured_box',
		'team_member',
		'testimonial',
		'page_header',
		'ux_breadcrumbs',
		'ux_price_table',
		'ux_page_title',
		'ux_bestseller',
		'ux_dynamic_link',
		'ux_dynamic_text',
	);
}

/**
 * Tags whose output is complete, generated markup with no author text inside: parked while the
 * late wpautop runs (see nt_lg_protect()). Containers that hold author text are not listed, they
 * need that paragraphing.
 *
 * @return string[]
 */
function nt_legacy_parked_tags(): array {
	return array( 'ux_products', 'ux_product_categories', 'blog_posts', 'ux_portfolio', 'search', 'ux_gallery', 'share', 'follow', 'ux_video' );
}

/**
 * Whether the bridge registered its handlers in this request (false while the previous theme
 * or another plugin already provides the shortcodes).
 */
function nt_legacy_active(): bool {
	return (bool) ( $GLOBALS['nt_legacy_registered'] ?? false );
}

/**
 * Register every handler whose tag is still free.
 */
function nt_legacy_register(): void {
	if ( ! apply_filters( 'nguyentu_legacy_bridge_enabled', true ) ) {
		return;
	}
	$registered = array();
	foreach ( nt_legacy_handlers() as $tag => $callback ) {
		if ( shortcode_exists( $tag ) || ! is_callable( $callback ) ) {
			continue;
		}
		$handler = $callback;
		if ( in_array( $tag, nt_legacy_parked_tags(), true ) ) {
			$handler = static fn ( $atts = array(), $content = '', $name = '' ): string => nt_lg_protect( (string) $callback( $atts, $content, $name ) );
		}
		add_shortcode( $tag, $handler );
		$registered[] = $tag;
	}
	$GLOBALS['nt_legacy_registered'] = array() !== $registered;
	$GLOBALS['nt_legacy_tags']       = $registered;
}
add_action( 'init', 'nt_legacy_register', 30 );

/**
 * Regex alternation of every tag the bridge knows about (rendered or stripped).
 */
function nt_legacy_tag_pattern(): string {
	$tags = array_merge( array_keys( nt_legacy_handlers() ), nt_legacy_stripped_tags() );
	return implode( '|', array_map( static fn( string $t ): string => preg_quote( $t, '/' ), $tags ) );
}

/**
 * Whether a string contains any shortcode the bridge handles or strips.
 *
 * @param string $content Raw post content.
 */
function nt_legacy_has_shortcodes( string $content ): bool {
	if ( false === strpos( $content, '[' ) ) {
		return false;
	}
	return (bool) preg_match( '/\[(?:' . nt_legacy_tag_pattern() . '|ux_[a-z0-9_]+)(?=[\s\]\/])/i', $content );
}

/**
 * Whether the bridge's stylesheet is needed on this request.
 */
function nt_legacy_needs_css(): bool {
	if ( ! nt_legacy_active() || ! is_singular() ) {
		return false;
	}
	$post = get_queried_object();
	return $post instanceof WP_Post && nt_legacy_has_shortcodes( (string) $post->post_content );
}

/**
 * Layout shortcodes produce block HTML, so WordPress's usual order (wpautop, then shortcodes)
 * would leave stray <p> and <br> inside rows and columns. For content that uses the bridge,
 * wpautop is moved to AFTER shortcode rendering, where it only formats genuine text nodes.
 *
 * @param string $content Content before filters at priority 9.
 */
function nt_legacy_prepare_content( string $content ): string {
	$legacy = nt_legacy_active() && nt_legacy_has_shortcodes( $content );
	$autop  = false;
	if ( $legacy && false !== has_filter( 'the_content', 'wpautop' ) ) {
		remove_filter( 'the_content', 'wpautop' );
		add_filter( 'the_content', 'nt_legacy_late_autop', 12 );
		$autop = true;
	}
	nt_legacy_frame(
		'push',
		array(
			'wrap'  => $legacy,
			'autop' => $autop,
		)
	);
	return $content;
}
add_filter( 'the_content', 'nt_legacy_prepare_content', 9 );

/**
 * Late wpautop, after do_shortcode. Only the run that took wpautop over gives it back; a run nested
 * inside it (which finds wpautop missing) simply gets the formatting it would have had.
 *
 * @param string $content Content after shortcodes.
 */
function nt_legacy_late_autop( string $content ): string {
	if ( ! empty( nt_legacy_frame()['autop'] ) ) {
		remove_filter( 'the_content', 'nt_legacy_late_autop', 12 );
		add_filter( 'the_content', 'wpautop' );
	}
	$out = wpautop( $content );
	// A paragraph holding only HTML comments (a marker line at the top of the content) is just noise.
	return preg_replace( '#<p>\s*((?:<!--.*?-->\s*)+)</p>#s', '$1', $out ) ?? $out;
}

/**
 * Remove tokens of unsupported tags so raw shortcode text can never be displayed, keep their
 * inner content, and wrap legacy output in the scoped container.
 *
 * @param string $content Content after shortcodes.
 */
function nt_legacy_finish_content( string $content ): string {
	$frame = nt_legacy_frame( 'pop' );
	if ( false !== strpos( $content, '[' ) ) {
		$pattern = '/\[(\/?)(' . nt_legacy_tag_pattern() . '|ux_[a-z0-9_]+)(?:\s[^\]]*)?\]/i';
		$content = (string) preg_replace_callback(
			$pattern,
			static function ( array $m ): string {
				if ( '/' === $m[1] ) {
					return '';
				}
				$tag = strtolower( $m[2] );
				nt_lg_note_unsupported( $tag );
				return '<!-- nt-legacy: [' . esc_html( $tag ) . '] is not supported by the theme, content kept -->';
			},
			$content
		);
	}
	$content = nt_lg_restore( $content );
	if ( 1 === $frame['depth'] ) {
		$GLOBALS['nt_lg_parked'] = array(); // outermost run finished: nothing is parked any more.
	}
	if ( $frame['wrap'] ) {
		$content = '<div class="nt-legacy">' . $content . '</div>';
	}
	return $content;
}
add_filter( 'the_content', 'nt_legacy_finish_content', 99 );

/**
 * Record an unsupported tag seen while rendering (read by tests and the status screen).
 *
 * @param string $tag Tag name.
 */
function nt_lg_note_unsupported( string $tag ): void {
	$GLOBALS['nt_legacy_unsupported'][ $tag ] = ( $GLOBALS['nt_legacy_unsupported'][ $tag ] ?? 0 ) + 1;
}

/**
 * Unsupported tags noted so far in this request.
 *
 * @return array<string,int>
 */
function nt_legacy_unsupported_seen(): array {
	return (array) ( $GLOBALS['nt_legacy_unsupported'] ?? array() );
}
