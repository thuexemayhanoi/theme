<?php
/**
 * Content pipeline: heading ids for the table of contents, scrollable tables, and the
 * "does the legacy body already cover this section?" checks that stop the home page from
 * duplicating text that page content already provides.
 *
 * Nothing here changes heading levels, wording, links or images.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Table-of-contents items per post id, filled while the_content runs.
 *
 * @param int                                                    $post_id Post id.
 * @param array<int,array{id:string,text:string,level:int}>|null $items   Items to store, or null to read.
 * @return array<int,array{id:string,text:string,level:int}>
 */
function nt_toc_registry( int $post_id, ?array $items = null ): array {
	static $registry = array();
	if ( null !== $items ) {
		$registry[ $post_id ] = $items;
	}
	return $registry[ $post_id ] ?? array();
}

/**
 * Add heading ids and wrap tables for the main query's content.
 *
 * @param string $content Rendered content.
 */
function nt_filter_the_content( string $content ): string {
	if ( is_admin() || is_feed() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$post_id = (int) get_the_ID();
	$content = nt_html_wrap_tables( $content );
	$result  = nt_html_headings_with_ids( $content, 2, 3 );
	nt_toc_registry( $post_id, $result['items'] );
	return $result['html'];
}
add_filter( 'the_content', 'nt_filter_the_content', 20 );

/**
 * Render the current post's body exactly as the_content() would, but return it.
 *
 * Call inside the loop. The table of contents is then available from nt_toc_registry().
 */
function nt_get_rendered_content(): string {
	$content = apply_filters( 'the_content', (string) get_the_content( null, false ) );
	return str_replace( ']]>', ']]&gt;', $content );
}

/**
 * Table of contents for a post, only when it has enough real headings.
 *
 * @param int $post_id Post id.
 * @param int $min     Minimum number of headings.
 * @return array<int,array{id:string,text:string,level:int}>
 */
function nt_toc_items( int $post_id, int $min = 3 ): array {
	if ( 'off' === nt_toggle( 'toc' ) ) {
		return array();
	}
	$items = nt_toc_registry( $post_id );
	return count( $items ) >= $min ? $items : array();
}

/**
 * Heuristics that tell whether rendered page content already provides a home-page section.
 * Used in "auto" mode so a section is only added natively when the legacy body lacks it.
 *
 * @param string $section Section key.
 * @param string $html    Rendered (or raw) page content.
 */
function nt_content_covers( string $section, string $html ): bool {
	$text = nt_plain_text( $html );
	switch ( $section ) {
		case 'prices':
			return nt_content_has_prices( $html, 3 );
		case 'products':
			return false !== stripos( $html, 'nt-lg-products' ) || false !== stripos( $html, '[ux_products' ) || false !== stripos( $html, '[products' ) || false !== stripos( $html, 'class="products' );
		case 'categories':
			return false !== stripos( $html, '/danh-muc-san-pham/' ) && substr_count( $html, '/danh-muc-san-pham/' ) >= 2;
		case 'faq':
			return (bool) preg_match( '/câu hỏi thường gặp|\bFAQ\b/iu', $text ) || false !== stripos( $html, 'nt-lg-accordion' ) || false !== stripos( $html, '[accordion' );
		case 'process':
			return (bool) preg_match( '/thủ tục|quy trình|các bước/iu', $text );
		case 'posts':
			return false !== stripos( $html, 'nt-lg-posts' ) || false !== stripos( $html, '[blog_posts' ) || false !== stripos( $html, '[ux_blog_posts' );
		case 'rental':
			return false !== stripos( $html, '/bo-suu-tap/thue-ngay' ) && false !== stripos( $html, '/bo-suu-tap/thue-tuan' );
		case 'local':
			$phone = nt_business( 'phone_tel' );
			$disp  = nt_business( 'phone_display' );
			return ( false !== strpos( $text, $phone ) || false !== strpos( $text, $disp ) ) && false !== mb_stripos( $text, nt_business( 'street' ) );
		case 'quickfacts':
			return false;
		default:
			return false;
	}
}

/**
 * Whether a section should render: "on" always, "off" never, "auto" when the body lacks it.
 *
 * @param string $section Section key (also the option key under home.*).
 * @param string $html    Rendered page content.
 */
function nt_section_enabled( string $section, string $html ): bool {
	$mode = nt_toggle( 'home.' . $section );
	if ( 'on' === $mode ) {
		return true;
	}
	if ( 'off' === $mode ) {
		return false;
	}
	return ! nt_content_covers( $section, $html );
}
