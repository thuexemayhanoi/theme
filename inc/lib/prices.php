<?php
/**
 * Price table parsing and price-text detection (pure functions).
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Parse the admin textarea into rows.
 *
 * Format, one row per line, columns separated by "|":
 *   Loại xe | Theo ngày | Theo tuần | Theo tháng | Tiền cọc
 * Blank lines and lines starting with "#" are ignored. Missing columns become "".
 *
 * @param string $raw Textarea value.
 * @return array<int,array{name:string,day:string,week:string,month:string,deposit:string}>
 */
function nt_parse_price_lines( string $raw ): array {
	$rows = array();
	foreach ( preg_split( '/\R/u', $raw ) ?: array() as $line ) {
		$line = trim( $line );
		if ( '' === $line || '#' === $line[0] ) {
			continue;
		}
		$cols = array_map( 'trim', explode( '|', $line ) );
		$cols = array_pad( $cols, 5, '' );
		$name = nt_plain_text( $cols[0] );
		if ( '' === $name ) {
			continue;
		}
		$rows[] = array(
			'name'    => $name,
			'day'     => nt_plain_text( $cols[1] ),
			'week'    => nt_plain_text( $cols[2] ),
			'month'   => nt_plain_text( $cols[3] ),
			'deposit' => nt_plain_text( $cols[4] ),
		);
	}
	return $rows;
}

/**
 * Serialise rows back to the textarea format.
 *
 * @param array<int,array<string,string>> $rows Rows from nt_parse_price_lines().
 */
function nt_format_price_lines( array $rows ): string {
	$lines = array();
	foreach ( $rows as $row ) {
		$lines[] = implode(
			' | ',
			array( $row['name'] ?? '', $row['day'] ?? '', $row['week'] ?? '', $row['month'] ?? '', $row['deposit'] ?? '' )
		);
	}
	return implode( "\n", $lines );
}

/**
 * Count money-like amounts in a fragment: 150.000đ, 1.200.000 VNĐ, 150k, 2 triệu.
 *
 * @param string $html HTML fragment.
 */
function nt_count_price_mentions( string $html ): int {
	$text = nt_plain_text( $html );
	$n    = (int) preg_match_all( '/\d{1,3}(?:[.,]\d{3})+\s*(?:đ|₫|vnđ|vnd|đồng)/iu', $text );
	$n   += (int) preg_match_all( '/\b\d{2,4}\s?k\b/iu', $text );
	$n   += (int) preg_match_all( '/\b\d+(?:[.,]\d+)?\s?triệu\b/iu', $text );
	return $n;
}

/**
 * Whether the fragment already presents a price list (3+ money amounts).
 *
 * @param string $html     HTML fragment.
 * @param int    $threshold Minimum mentions.
 */
function nt_content_has_prices( string $html, int $threshold = 3 ): bool {
	return nt_count_price_mentions( $html ) >= $threshold;
}

/**
 * Lowest numeric amount found in a price cell ("900.000–1.200.000đ" -> 900000).
 *
 * @param string $cell Table cell text.
 * @return int|null Null when the cell holds no amount (e.g. "Liên hệ").
 */
function nt_price_cell_min( string $cell ): ?int {
	if ( ! preg_match_all( '/\d{1,3}(?:[.,]\d{3})+|\d{4,}/u', $cell, $m ) ) {
		return null;
	}
	$vals = array_map(
		static fn( string $v ): int => (int) preg_replace( '/\D+/', '', $v ),
		$m[0]
	);
	return array() === $vals ? null : min( $vals );
}

/**
 * Format an integer amount as Vietnamese dong text: 150000 -> "150.000đ".
 *
 * @param int $amount Amount in VND.
 */
function nt_format_vnd( int $amount ): string {
	return number_format( $amount, 0, ',', '.' ) . 'đ';
}

/**
 * Cheapest numeric value in one column of the price rows.
 *
 * @param array<int,array<string,string>> $rows Rows.
 * @param string                          $col  Column key: day, week or month.
 * @return int|null
 */
function nt_price_column_min( array $rows, string $col ): ?int {
	$min = null;
	foreach ( $rows as $row ) {
		$v = nt_price_cell_min( $row[ $col ] ?? '' );
		if ( null !== $v && ( null === $min || $v < $min ) ) {
			$min = $v;
		}
	}
	return $min;
}
