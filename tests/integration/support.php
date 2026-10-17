<?php
/**
 * Shared helpers for integration tests.
 *
 * @package NguyenTu
 */

declare(strict_types=1);

/**
 * Parsed baseline snapshot (the audit that the migration must preserve).
 *
 * @return array<string,mixed>
 */
function nt_it_baseline(): array {
	static $data = null;
	if ( null === $data ) {
		$data = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/docs/wordpress-baseline-2026-10-17.json' ), true, 512, JSON_THROW_ON_ERROR );
	}
	return $data;
}

/**
 * Path (no host, trailing slash) of a URL.
 *
 * @param string $url URL.
 */
function nt_it_path( string $url ): string {
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	return '/' === $path ? '/' : trailingslashit( $path );
}

/**
 * Parse an HTML fragment into a DOMXPath.
 *
 * @param string $html HTML.
 */
function nt_it_xpath( string $html ): DOMXPath {
	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8" ?><div id="root">' . $html . '</div>' );
	libxml_clear_errors();
	return new DOMXPath( $dom );
}

/**
 * Render a post's content the way the front end does.
 *
 * @param int $post_id Post id.
 */
function nt_it_render( int $post_id ): string {
	global $post, $wp_query;
	nt_lg_claim_lcp( false, true ); // every render stands for one request: the first banner is the LCP candidate again.
	$post = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	setup_postdata( $post );
	$wp_query->in_the_loop = true;
	$html                  = nt_get_rendered_content();
	$wp_query->in_the_loop = false;
	wp_reset_postdata();
	return $html;
}

/**
 * XPath predicate matching one class token (contains(@class,"x") also matches BEM children).
 *
 * @param string $class Class token.
 */
function nt_it_has_class( string $class ): string {
	return "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
}
