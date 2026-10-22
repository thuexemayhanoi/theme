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
	$re    = '#<h([' . $min . '-' . $max . '])\b([^>]*)>(.*?)</h\1>#is';

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
 * Where the in-text table of contents goes: straight after the first <h2> of the body.
 *
 * The scan follows tag nesting, so a heading that sits inside another block (a group, a column, a
 * quote, a list item, a <details>) is never used: a contents list put there could break that
 * block's layout. When the first <h2> cannot be used, the list goes before the first section, that
 * is after the last top-level block that ends before the first <h2>/<h3> (usually the opening
 * paragraph); when there is no such block either, it stays in front of the text, where it used to
 * be. The body itself is never rewritten here: the result is a byte offset and a label.
 *
 * @param string $html Rendered body (after the_content filters).
 * @return array{placement:string,offset:int} Placement is "after-first-h2", "after-intro" or "before-content" (offset 0).
 */
function nt_html_toc_placement( string $html ): array {
	$fallback = array(
		'placement' => 'before-content',
		'offset'    => 0,
	);
	if ( '' === $html || false === stripos( $html, '<h' ) ) {
		return $fallback;
	}
	// A comment that never closes hides the rest of the document from the browser: do not guess.
	$comment_opens = substr_count( $html, '<!--' );
	if ( $comment_opens > 0 && (int) preg_match_all( '/<!--.*?-->/s', $html ) < $comment_opens ) {
		return $fallback;
	}
	$matched = preg_match_all( '#<!--.*?-->|<(/?)([a-zA-Z][a-zA-Z0-9:-]*)((?:"[^"]*+"|\'[^\']*+\'|[^>"\'])*+)>#s', $html, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
	if ( ! $matched ) {
		return $fallback;
	}

	$void     = array_flip( array( 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' ) );
	$opaque   = array_flip( array( 'script', 'style', 'textarea', 'title', 'noscript', 'template', 'iframe' ) );
	$ends_p   = array_flip( array( 'address', 'article', 'aside', 'blockquote', 'details', 'div', 'dl', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'main', 'menu', 'nav', 'ol', 'p', 'pre', 'section', 'table', 'ul' ) );
	$stack    = array();
	$skip_to  = 0;
	$h2       = null; // First non-empty <h2>: top-level flag and the offset after its closing tag.
	$h2_open  = null; // A candidate <h2> that is still open.
	$heading  = null; // Offset of the first <h2> or <h3> tag, at any depth.
	$block_to = array(); // Offsets after each top-level element.

	foreach ( $tags as $tag ) {
		$text   = $tag[0][0];
		$offset = $tag[0][1];
		if ( $offset < $skip_to || '<!--' === substr( $text, 0, 4 ) ) {
			continue;
		}
		$after = $offset + strlen( $text );
		$name  = strtolower( $tag[2][0] );

		if ( '/' === $tag[1][0] ) {
			$i = count( $stack ) - 1;
			while ( $i >= 0 && $stack[ $i ] !== $name ) {
				--$i;
			}
			if ( $i < 0 ) {
				continue; // A closing tag that closes nothing.
			}
			if ( null !== $h2_open && $i === $h2_open['depth'] && 'h2' === $name ) {
				$inner = substr( $html, $h2_open['inner'], $offset - $h2_open['inner'] );
				if ( null === $h2 && '' !== nt_plain_text( $inner ) ) {
					$h2 = array(
						'top' => $h2_open['top'],
						'end' => $after,
					);
				}
				$h2_open = null;
			}
			if ( 0 === $i ) {
				$block_to[] = $after;
			}
			array_splice( $stack, $i );
			continue;
		}

		// A block-level start tag implicitly ends an open paragraph, as the browser's parser does.
		if ( isset( $ends_p[ $name ] ) && array() !== $stack && 'p' === end( $stack ) ) {
			array_pop( $stack );
			if ( array() === $stack ) {
				$block_to[] = $offset;
			}
		}
		if ( isset( $opaque[ $name ] ) ) {
			// Raw text or inert content: nothing inside it is markup of the page. The scan resumes after its closing tag.
			$close   = stripos( $html, '</' . $name, $after );
			$gt      = false === $close ? false : strpos( $html, '>', $close );
			$skip_to = false === $gt ? strlen( $html ) : $gt + 1;
			if ( array() === $stack ) {
				$block_to[] = $skip_to;
			}
			continue;
		}
		if ( null === $heading && ( 'h2' === $name || 'h3' === $name ) ) {
			$heading = $offset;
		}
		if ( isset( $void[ $name ] ) || '/' === substr( rtrim( $tag[3][0] ), -1 ) ) {
			if ( array() === $stack ) {
				$block_to[] = $after;
			}
			continue;
		}
		if ( 'h2' === $name && null === $h2 && null === $h2_open ) {
			$h2_open = array(
				'depth' => count( $stack ),
				'top'   => array() === $stack,
				'inner' => $after,
			);
		}
		$stack[] = $name;
	}

	if ( null !== $h2 && $h2['top'] ) {
		return array(
			'placement' => 'after-first-h2',
			'offset'    => $h2['end'],
		);
	}
	if ( null !== $heading ) {
		$best = 0;
		foreach ( $block_to as $end ) {
			if ( $end <= $heading ) {
				$best = max( $best, $end );
			}
		}
		if ( $best > 0 ) {
			return array(
				'placement' => 'after-intro',
				'offset'    => $best,
			);
		}
	}
	return $fallback;
}

/**
 * Insert markup at a byte offset of an HTML fragment.
 *
 * @param string $html      HTML fragment.
 * @param int    $offset    Byte offset, as returned by nt_html_toc_placement().
 * @param string $insertion Markup to insert.
 */
function nt_html_insert_at( string $html, int $offset, string $insertion ): string {
	$offset = max( 0, min( strlen( $html ), $offset ) );
	return substr( $html, 0, $offset ) . $insertion . substr( $html, $offset );
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
