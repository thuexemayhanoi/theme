<?php
/**
 * Pure HTML structure helpers (no WordPress state, unit-testable without a database).
 *
 * These helpers never rewrite editor content except for adding missing heading ids
 * and wrapping tables for horizontal scrolling; they never change heading levels,
 * text, links or images.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remove markup that does not render as page content so structural counts stay honest.
 *
 * @param string $html HTML fragment.
 */
function nt_html_strip_inert( string $html ): string {
	$html = preg_replace( '#<!--.*?-->#s', '', $html ) ?? $html;
	return preg_replace( '#<(script|style|template|noscript)\b[^>]*>.*?</\1>#is', '', $html ) ?? $html;
}

/**
 * Count opening tags of a given element name.
 *
 * @param string $html HTML fragment.
 * @param string $tag  Element name, e.g. "h1".
 */
function nt_html_count_tag( string $html, string $tag ): int {
	return (int) preg_match_all( '#<' . preg_quote( $tag, '#' ) . '(?=[\s/>])#i', nt_html_strip_inert( $html ) );
}

/**
 * Whether the fragment already carries an <h1> (so a template must not add a second one).
 *
 * @param string $html HTML fragment.
 */
function nt_html_has_h1( string $html ): bool {
	return nt_html_count_tag( $html, 'h1' ) > 0;
}

/**
 * Text of the first <h1> in the fragment, or an empty string.
 *
 * @param string $html HTML fragment.
 */
function nt_html_first_h1_text( string $html ): string {
	if ( preg_match( '#<h1\b[^>]*>(.*?)</h1>#is', nt_html_strip_inert( $html ), $m ) ) {
		return nt_plain_text( $m[1] );
	}
	return '';
}

/**
 * Add missing id attributes to headings and return the table-of-contents items.
 *
 * Existing ids are kept untouched (anchors that other pages link to must survive). Every id the
 * fragment already carries, on any element and wherever it appears, is reserved before a missing
 * one is generated, so a generated id never duplicates an authored anchor that comes later.
 *
 * @param string $html      HTML fragment.
 * @param int    $min_level Lowest heading level to include (2 = h2).
 * @param int    $max_level Highest heading level to include (3 = h3).
 * @return array{html:string,items:array<int,array{id:string,text:string,level:int}>}
 */
function nt_html_headings_with_ids( string $html, int $min_level = 2, int $max_level = 3 ): array {
	$items = array();
	$used  = array();
	$min   = max( 1, min( 6, $min_level ) );
	$max   = max( $min, min( 6, $max_level ) );
	// Hidden markup must not create TOC links; an unfinished comment hides the rest of the fragment.
	$inert = '<!--.*?(?:-->|$)|<(?:script|style|template|noscript)\b[^>]*>.*?</(?:script|style|template|noscript)>';
	$re    = '#(?:' . $inert . ')(*SKIP)(*F)|<h([' . $min . '-' . $max . '])\b([^>]*)>(.*?)</h\1>#is';

	if ( preg_match_all( '/\sid\s*=\s*(["\'])(.*?)\1/i', $html, $existing ) ) {
		foreach ( $existing[2] as $existing_id ) {
			$used[ $existing_id ] = true;
		}
	}

	$out = preg_replace_callback(
		$re,
		static function ( array $m ) use ( &$items, &$used ): string {
			$level = (int) $m[1];
			$attrs = $m[2];
			$inner = $m[3];
			$text  = nt_plain_text( $inner );
			if ( '' === $text ) {
				return $m[0];
			}
			if ( preg_match( '/\sid\s*=\s*(["\'])(.*?)\1/i', $attrs, $idm ) ) {
				$id = $idm[2];
			} else {
				$base = nt_slugify( $text );
				$base = '' === $base ? 'muc' : $base;
				$id   = $base;
				$n    = 2;
				while ( isset( $used[ $id ] ) ) {
					$id = $base . '-' . $n;
					++$n;
				}
				$attrs .= ' id="' . $id . '"';
			}
			$used[ $id ] = true;
			$items[]     = array(
				'id'    => $id,
				'text'  => $text,
				'level' => $level,
			);
			return '<h' . $level . $attrs . '>' . $inner . '</h' . $level . '>';
		},
		$html
	);

	return array(
		'html'  => $out ?? $html,
		'items' => $items,
	);
}

/**
 * Wrap bare <table> elements so wide tables scroll inside their own region instead of
 * causing page-level horizontal overflow on phones.
 *
 * @param string $html HTML fragment.
 */
function nt_html_wrap_tables( string $html ): string {
	if ( false === stripos( $html, '<table' ) ) {
		return $html;
	}
	return preg_replace_callback(
		'#<table\b[^>]*>.*?</table>#is',
		static function ( array $m ): string {
			return '<div class="nt-table-wrap" role="region" aria-label="Bảng dữ liệu, có thể cuộn ngang" tabindex="0">' . $m[0] . '</div>';
		},
		$html
	) ?? $html;
}

/**
 * Collect href values of anchors in a fragment (used by tests and the link graph report).
 *
 * @param string $html HTML fragment.
 * @return string[]
 */
function nt_html_links( string $html ): array {
	if ( ! preg_match_all( '#<a\b[^>]*\shref\s*=\s*(["\'])(.*?)\1#is', nt_html_strip_inert( $html ), $m ) ) {
		return array();
	}
	return array_values( array_unique( $m[2] ) );
}
