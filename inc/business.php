<?php
/**
 * Verified business data (NAP, hours, reference prices) and option accessors.
 *
 * Defaults come from the owner's "dữ liệu chuẩn" and are never guessed or invented.
 * Admins may override individual fields under Giao diện > Nguyễn Tú; a blank override
 * always falls back to the verified default.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Verified defaults. Do not change without new information from the shop owner.
 *
 * @return array<string,string>
 */
function nt_business_defaults(): array {
	return array(
		'brand'           => 'Cho Thuê Xe Máy Nguyễn Tú',
		'gbp_name'        => 'Thuê xe máy ở Hà Nội - Nguyễn Tú',
		'short_name'      => 'Nguyễn Tú',
		'phone_display'   => '0942 467 674',
		'phone_tel'       => '0942467674',
		'phone_intl'      => '+84942467674',
		'email'           => 'nguyentuantu8x@gmail.com',
		'zalo_url'        => 'https://zalo.me/0942467674',
		'facebook_url'    => 'https://www.facebook.com/chothuexemaynguyentu/',
		'tripadvisor_url' => 'https://www.tripadvisor.com.vn/Attraction_Review-g21333608-d9826317-Reviews-Hanoi_Motorbike_Rental_Nguyen_Tu-Long_Bien_Hanoi.html',
		'maps_url'        => 'https://maps.google.com/maps?cid=7448790013669858019',
		'quick_maps_url'  => 'https://share.google/KHz2FVQKhtFilL01j',
		'street'          => '112 Nguyễn Văn Cừ',
		'ward'            => 'Bồ Đề',
		'district'        => 'Long Biên',
		'city'            => 'Hà Nội',
		'country'         => 'Việt Nam',
		'country_code'    => 'VN',
		'open'            => '09:00',
		'close'           => '21:00',
		'site_url'        => 'https://thuexemaynguyentu.com/',
		'blog_url'        => 'https://thuexemaynguyentu.com/blog/',
		'prod_host'       => 'thuexemaynguyentu.com',
	);
}

/**
 * Reference price table rows from the owner's data (VND). Reference only: must be
 * confirmed with the shop before booking. Electric bikes have no published price.
 *
 * @return array<int,array{name:string,day:string,week:string,month:string,deposit:string}>
 */
function nt_price_defaults(): array {
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

/**
 * Read one value from the theme option array using a dot path: nt_option( 'home.prices', 'auto' ).
 *
 * @param string $path    Dot path.
 * @param mixed  $default Value when unset or empty string.
 * @return mixed
 */
function nt_option( string $path, $default = null ) {
	static $cache = null;
	if ( null === $cache ) {
		$stored = get_option( 'nguyentu_options', array() );
		$cache  = is_array( $stored ) ? $stored : array();
	}
	$node = $cache;
	foreach ( explode( '.', $path ) as $segment ) {
		if ( ! is_array( $node ) || ! array_key_exists( $segment, $node ) ) {
			return $default;
		}
		$node = $node[ $segment ];
	}
	return ( '' === $node || null === $node ) ? $default : $node;
}

/**
 * Footer-only service description. Never changes WordPress Site Tagline or SEO metadata.
 *
 * @return string Owner-approved default description.
 */
function nt_footer_tagline_default(): string {
	return 'Thuê xe máy tại Hà Nội theo ngày, tuần, tháng; tư vấn nhu cầu thuê theo giờ. Có xe số, xe ga, xe 50cc và xe điện. Liên hệ xác nhận giá, tiền cọc và điều kiện nhận xe.';
}

/**
 * Footer description can be overridden independently under Giao diện > Nguyễn Tú.
 */
function nt_footer_tagline(): string {
	return (string) nt_option( 'footer.tagline', nt_footer_tagline_default() );
}

/**
 * Business data: verified defaults overridden by non-empty admin values.
 *
 * @param string|null $key Single key, or null for the whole array.
 * @return mixed
 */
function nt_business( ?string $key = null ) {
	static $data = null;
	if ( null === $data ) {
		$data      = nt_business_defaults();
		$overrides = nt_option( 'business', array() );
		if ( is_array( $overrides ) ) {
			foreach ( $overrides as $k => $v ) {
				if ( isset( $data[ $k ] ) && is_string( $v ) && '' !== trim( $v ) ) {
					$data[ $k ] = trim( $v );
				}
			}
		}
		$data = (array) apply_filters( 'nguyentu_business', $data );
	}
	if ( null === $key ) {
		return $data;
	}
	return $data[ $key ] ?? '';
}

/**
 * Street address on one line: "112 Nguyễn Văn Cừ, Bồ Đề, Long Biên, Hà Nội, Việt Nam".
 *
 * @param bool $with_country Append the country name.
 */
function nt_address( bool $with_country = true ): string {
	$parts = array_filter(
		array(
			nt_business( 'street' ),
			nt_business( 'ward' ),
			nt_business( 'district' ),
			nt_business( 'city' ),
			$with_country ? nt_business( 'country' ) : '',
		)
	);
	return implode( ', ', $parts );
}

/**
 * Opening hours text, e.g. "09:00–21:00".
 */
function nt_hours_range(): string {
	return nt_business( 'open' ) . '–' . nt_business( 'close' );
}

/**
 * Verified fixed contact URLs.
 */
function nt_tel_url(): string {
	return 'tel:' . nt_business( 'phone_tel' );
}

/**
 * Zalo chat URL.
 */
function nt_zalo_url(): string {
	return nt_business( 'zalo_url' );
}

/**
 * WhatsApp contact link computed from the same international business phone.
 * Changing the phone number in one place updates this link too.
 */
function nt_whatsapp_url(): string {
	$digits = preg_replace( '/[^0-9]/', '', (string) nt_business( 'phone_intl' ) );
	return 'https://wa.me/' . (string) $digits;
}

/**
 * Google Maps profile URL (verified CID link).
 */
function nt_maps_url(): string {
	return nt_business( 'maps_url' );
}

/**
 * Map embed URL built from the verified address (click-to-load only; never auto-loaded).
 */
function nt_maps_embed_url(): string {
	return 'https://maps.google.com/maps?q=' . rawurlencode( nt_address( false ) ) . '&hl=vi&z=16&output=embed';
}

/**
 * Reference price rows: admin textarea when present, else the owner's defaults.
 *
 * @return array<int,array{name:string,day:string,week:string,month:string,deposit:string}>
 */
function nt_price_rows(): array {
	$raw = nt_option( 'prices', '' );
	if ( is_string( $raw ) && '' !== trim( $raw ) ) {
		$rows = nt_parse_price_lines( $raw );
		if ( array() !== $rows ) {
			return $rows;
		}
	}
	return nt_price_defaults();
}

/**
 * Deposit and booking policy sentences, taken from the owner's data.
 *
 * @return array<string,string>
 */
function nt_policy(): array {
	return array(
		'deposit_range' => 'Tiền đặt cọc xe máy từ 2.000.000đ đến 5.000.000đ, tùy từng loại xe. Mức cọc cụ thể phụ thuộc vào dòng xe được thuê.',
		'documents'     => 'Một số phương án giấy tờ bảo đảm có thể được trao đổi riêng theo điều kiện và thỏa thuận hợp pháp.',
		'confirm'       => 'Tiền cọc, giá thuê, thời hạn thuê, tình trạng xe và chi phí giao nhận phải được xác nhận rõ ràng trước khi ký hợp đồng.',
		'refund'        => 'Tiền cọc được hoàn trả theo điều khoản hợp đồng sau khi khách bàn giao xe và hoàn thành các nghĩa vụ liên quan.',
		'price_note'    => 'Giá thuê trong bảng là giá tham khảo, cần xác nhận trước khi đặt xe.',
	);
}

/**
 * Short FAQ built only from verified facts so it can never contradict the shop.
 *
 * @return array<int,array{q:string,a:string}>
 */
function nt_faq_items(): array {
	$rows   = nt_price_rows();
	$day    = nt_price_column_min( $rows, 'day' );
	$week   = nt_price_column_min( $rows, 'week' );
	$month  = nt_price_column_min( $rows, 'month' );
	$policy = nt_policy();

	$price_bits = array();
	if ( null !== $day ) {
		$price_bits[] = 'theo ngày từ ' . nt_format_vnd( $day );
	}
	if ( null !== $week ) {
		$price_bits[] = 'theo tuần từ ' . nt_format_vnd( $week );
	}
	if ( null !== $month ) {
		$price_bits[] = 'theo tháng từ ' . nt_format_vnd( $month );
	}
	$price_answer = ( array() === $price_bits )
		? 'Vui lòng gọi hoặc nhắn Zalo để được báo giá theo dòng xe.'
		: 'Giá tham khảo: ' . implode( ', ', $price_bits ) . ', tùy dòng xe. ' . $policy['price_note'];

	return array(
		array(
			'q' => 'Giá thuê xe máy ở Hà Nội tại ' . nt_business( 'short_name' ) . ' là bao nhiêu?',
			'a' => $price_answer,
		),
		array(
			'q' => 'Tiền đặt cọc thuê xe máy là bao nhiêu?',
			'a' => $policy['deposit_range'] . ' ' . $policy['documents'],
		),
		array(
			'q' => 'Tiền cọc có được hoàn lại không?',
			'a' => $policy['refund'],
		),
		array(
			'q' => 'Cửa hàng ở đâu và mở cửa lúc mấy giờ?',
			'a' => nt_address() . '. Giờ hoạt động niêm yết: ' . nt_hours_range() . ', tất cả các ngày trong tuần.',
		),
		array(
			'q' => 'Có cho thuê xe máy điện và xe đạp điện không?',
			'a' => 'Có. Giá và mẫu xe máy điện, xe đạp điện được báo theo từng mẫu, vui lòng gọi ' . nt_business( 'phone_display' ) . ' hoặc nhắn Zalo để được tư vấn.',
		),
		array(
			'q' => 'Cần xác nhận những gì trước khi nhận xe?',
			'a' => $policy['confirm'],
		),
	);
}

/**
 * Whether a theme section is enabled: option "on"/"off" wins, otherwise "auto".
 *
 * @param string $path Option path.
 * @return string on|off|auto
 */
function nt_toggle( string $path ): string {
	$v = nt_option( $path, 'auto' );
	return in_array( $v, array( 'on', 'off', 'auto' ), true ) ? $v : 'auto';
}
