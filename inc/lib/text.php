<?php
/**
 * Pure text helpers (no WordPress state, unit-testable without a database).
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * ASCII slug with Vietnamese diacritics removed (đ -> d).
 *
 * Uses WordPress remove_accents() when available so IDs match sanitize_title() output,
 * and falls back to ICU transliteration for unit tests that run without WordPress.
 *
 * @param string $text Source text.
 */
function nt_slugify( string $text ): string {
	if ( function_exists( 'remove_accents' ) ) {
		$text = remove_accents( $text );
	} elseif ( function_exists( 'transliterator_transliterate' ) ) {
		$text = (string) transliterator_transliterate( 'Any-Latin; Latin-ASCII', $text );
	}
	$text = strtolower( $text );
	$text = preg_replace( '/[^a-z0-9]+/', '-', $text ) ?? '';
	return trim( $text, '-' );
}

/**
 * Decode HTML entities that WordPress stores inside term and post titles ("&amp;" -> "&") so a
 * later esc_html() does not double-encode them.
 *
 * @param string $text Stored text.
 */
function nt_decode( string $text ): string {
	return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
}

/**
 * Plain text from an HTML fragment with collapsed whitespace.
 *
 * @param string $html HTML fragment.
 */
function nt_plain_text( string $html ): string {
	$html = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html ) ?? $html;
	// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- pure helper, must not depend on WordPress.
	$text = html_entity_decode( strip_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	return trim( preg_replace( '/\s+/u', ' ', $text ) ?? '' );
}

/**
 * Plain text of stored post content for excerpts. Shortcode tokens are removed but the text
 * between them is kept, which core's strip_shortcodes() would drop (it discards everything inside
 * a layout shortcode such as [row][col]text[/col][/row]).
 *
 * @param string $content Stored post_content.
 */
function nt_content_to_text( string $content ): string {
	$content = preg_replace( '/\[\/?[a-z][a-z0-9_-]*(?:\s[^\]]*)?\]/i', ' ', $content ) ?? $content;
	return nt_plain_text( $content );
}

/**
 * Number of word-like tokens (Vietnamese syllables count as words).
 *
 * @param string $html HTML fragment.
 */
function nt_word_count( string $html ): int {
	return (int) preg_match_all( '/[\p{L}\p{N}][\p{L}\p{N}\'’\-]*/u', nt_plain_text( $html ) );
}

/**
 * Estimated reading time in whole minutes (minimum 1).
 *
 * @param string $html Rendered content.
 * @param int    $wpm  Words per minute.
 */
function nt_reading_minutes( string $html, int $wpm = 220 ): int {
	return max( 1, (int) ceil( nt_word_count( $html ) / max( 1, $wpm ) ) );
}

/**
 * Trim text to N words without cutting inside a word.
 *
 * @param string $text  Plain text.
 * @param int    $words Maximum words.
 * @param string $more  Suffix when truncated.
 */
function nt_trim_words( string $text, int $words = 24, string $more = '…' ): string {
	$parts = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
	if ( false === $parts || count( $parts ) <= $words ) {
		return trim( $text );
	}
	return rtrim( implode( ' ', array_slice( $parts, 0, $words ) ), " \t\n\r\0\x0B,.;:–-" ) . $more;
}

/**
 * Normalise a phone number to digits only (keeps a leading plus).
 *
 * @param string $phone Raw phone value.
 */
function nt_phone_digits( string $phone ): string {
	$phone = trim( $phone );
	$plus  = ( '' !== $phone && '+' === $phone[0] ) ? '+' : '';
	return $plus . preg_replace( '/\D+/', '', $phone );
}
