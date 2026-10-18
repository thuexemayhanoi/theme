<?php
/**
 * Pure local-business clock. All timestamps come from the server, never the visitor's clock.
 *
 * @package NguyenTu
 */
defined( 'ABSPATH' ) || exit;

/** Validate a 24-hour time. */
function nt_valid_time( string $value ): bool {
	return 1 === preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value );
}

/**
 * Resolve today's interval and the next actual opening, including dated closures.
 * Exceptions map YYYY-MM-DD to "closed" or "HH:MM-HH:MM". Closed weekdays use ISO 1..7.
 * Overnight hours are deliberately rejected rather than guessed.
 */
function nt_hours_at( int $timestamp, string $open, string $close, array $closed_days = array(), array $exceptions = array() ): array {
	$zone = new DateTimeZone( 'Asia/Ho_Chi_Minh' );
	$now  = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $zone );
	$open = nt_valid_time( $open ) ? $open : '09:00';
	$close = nt_valid_time( $close ) && $close > $open ? $close : '21:00';
	if ( $close <= $open ) {
		$open = '09:00';
	}
	$next = null;
	$is_open = false;
	for ( $offset = 0; $offset <= 370; ++$offset ) {
		$date = $now->setTime( 0, 0 )->modify( '+' . $offset . ' days' );
		$key = $date->format( 'Y-m-d' );
		$override = $exceptions[ $key ] ?? null;
		if ( 'closed' === $override || ( null === $override && in_array( (int) $date->format( 'N' ), $closed_days, true ) ) ) {
			continue;
		}
		$times = is_string( $override ) ? explode( '-', $override ) : array( $open, $close );
		if ( 2 !== count( $times ) || ! nt_valid_time( $times[0] ) || ! nt_valid_time( $times[1] ) || $times[0] >= $times[1] ) {
			continue;
		}
		$start = new DateTimeImmutable( $key . ' ' . $times[0], $zone );
		$end = new DateTimeImmutable( $key . ' ' . $times[1], $zone );
		if ( 0 === $offset && $now >= $start && $now < $end ) {
			$is_open = true;
			$next = $end;
			break;
		}
		if ( $start > $now ) {
			$next = $start;
			break;
		}
	}
	$label = $is_open ? 'Đang mở cửa' : 'Đã đóng cửa';
	if ( ! $is_open && $next ) {
		$label .= ' — Mở lại lúc ' . $next->format( 'H:i' );
		if ( $next->format( 'Y-m-d' ) !== $now->format( 'Y-m-d' ) ) {
			$label .= ' ngày ' . $next->format( 'd/m' );
		}
	}
	if ( ! $is_open && ! $next ) {
		$label .= ' — Liên hệ để xác nhận lịch mở cửa';
	}
	return array( 'open' => $is_open, 'label' => $label, 'server_time' => $timestamp, 'next_change' => $next ? $next->getTimestamp() : null, 'timezone' => $zone->getName() );
}
