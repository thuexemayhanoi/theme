<?php
/**
 * Read-only verified Nguyễn Tú business contract.
 *
 * The active theme's nt_business()/nt_price_rows() take precedence. When the theme
 * changes, the last theme options (if any) and verified owner defaults remain usable.
 * This module does not write WordPress options or post metadata.
 *
 * @package NguyenTuSmartCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Only publish Nguyễn Tú facts on the intended production host.
 * Non-production environments can opt in explicitly with this filter for staging.
 */
function nt_smart_site_allowed(): bool {
	$host    = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
	$allowed = in_array( $host, array( 'thuexemaynguyentu.com', 'www.thuexemaynguyentu.com' ), true );
	return (bool) apply_filters( 'nt_smart_site_allowed', $allowed, $host );
}

/**
 * Verified facts provided by the business owner; never invent reviews or availability.
 *
 * @return array<string,string>
 */
function nt_smart_business_defaults(): array {
	return array(
		'brand'         => 'Cho Thuê Xe Máy Nguyễn Tú',
		'gbp_name'      => 'Thuê xe máy ở Hà Nội - Nguyễn Tú',
		'phone_display' => '0942 467 674',
		'phone_intl'    => '+84942467674',
		'zalo_url'      => 'https://zalo.me/0942467674',
		'maps_url'      => 'https://maps.google.com/maps?cid=7448790013669858019',
		'facebook_url'  => 'https://www.facebook.com/chothuexemaynguyentu/',
		'street'        => '112 Nguyễn Văn Cừ',
		'ward'          => 'Bồ Đề',
		'district'      => 'Long Biên',
		'city'          => 'Hà Nội',
		'country_code'  => 'VN',
		'open'          => '09:00',
		'close'         => '21:00',
		'site_url'      => 'https://thuexemaynguyentu.com/',
	);
}

/**
 * Single read-only accessor shared by future rental and FAQ modules.
 * Existing theme options are consulted; no new duplicate configuration is created.
 *
 * @return array<string,string>
 */
function nt_smart_business(): array {
	$profile = nt_smart_business_defaults();

	if ( function_exists( 'nt_business' ) ) {
		$theme = nt_business();
		if ( is_array( $theme ) ) {
			foreach ( $profile as $key => $default ) {
				if ( isset( $theme[ $key ] ) && is_string( $theme[ $key ] ) && '' !== trim( $theme[ $key ] ) ) {
					$profile[ $key ] = trim( $theme[ $key ] );
				}
			}
		}
	} else {
		$options = get_option( 'nguyentu_options', array() );
		if ( is_array( $options ) && isset( $options['business'] ) && is_array( $options['business'] ) ) {
			foreach ( $profile as $key => $default ) {
				$value = $options['business'][ $key ] ?? null;
				if ( is_string( $value ) && '' !== trim( $value ) ) {
					$profile[ $key ] = trim( $value );
				}
			}
		}
	}

	return (array) apply_filters( 'ntsc_business_profile', $profile );
}

/**
 * Verified rental reference prices, not schema Product offers or live availability.
 *
 * @return array<int,array<string,string>>
 */
function nt_smart_reference_prices(): array {
	if ( function_exists( 'nt_price_rows' ) ) {
		return (array) nt_price_rows();
	}

	return array(
		array(
			'name'    => 'Honda Wave',
			'day'     => '150.000đ',
			'week'    => '700.000đ',
			'month'   => '900.000–1.200.000đ',
			'deposit' => '2–3 triệu',
		),
		array(
			'name'    => 'Honda Vision',
			'day'     => '200.000đ',
			'week'    => '1.000.000đ',
			'month'   => '1.800.000–2.000.000đ',
			'deposit' => '4–5 triệu',
		),
		array(
			'name'    => 'Honda Air Blade',
			'day'     => '200.000đ',
			'week'    => '1.000.000đ',
			'month'   => '1.500.000đ',
			'deposit' => '3 triệu',
		),
		array(
			'name'    => 'Honda Click / Yamaha Mio',
			'day'     => '150.000đ',
			'week'    => '700.000đ',
			'month'   => '900.000–1.200.000đ',
			'deposit' => '2 triệu',
		),
		array(
			'name'    => 'Xe tay ga 50cc',
			'day'     => '200.000đ',
			'week'    => 'Báo giá riêng',
			'month'   => 'Báo giá riêng',
			'deposit' => '4 triệu',
		),
		array(
			'name'    => 'Xe máy điện / Xe đạp điện',
			'day'     => 'Liên hệ',
			'week'    => 'Liên hệ',
			'month'   => 'Liên hệ',
			'deposit' => 'Xác nhận theo mẫu xe',
		),
	);
}
