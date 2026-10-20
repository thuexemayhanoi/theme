<?php
/**
 * Data helpers for the native home-page sections. Everything is read from WordPress
 * (products, terms, posts, price table); nothing is hard-coded post content.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hero heading decision. The SEO H1 stored in the page content always wins; the theme only
 * falls back to the page title when the content has no <h1> at all.
 *
 * @param WP_Post $page     Front page.
 * @param string  $rendered Rendered page content.
 * @return array{content_has_h1:bool,h1:string}
 */
function nt_home_hero( WP_Post $page, string $rendered ): array {
	$has = nt_html_has_h1( $rendered );
	return array(
		'content_has_h1' => $has,
		'h1'             => $has ? nt_html_first_h1_text( $rendered ) : wp_strip_all_tags( get_the_title( $page ) ),
	);
}

/**
 * Day / week / month rental cards with "from" prices taken from the price table.
 *
 * @return array<int,array{key:string,label:string,unit:string,icon:string,from:string,url:string}>
 */
function nt_home_rental_options(): array {
	$rows = nt_price_rows();
	$defs = array(
		'day'   => array( 'Thuê xe theo ngày', 'ngày', 'day', 'thue-ngay' ),
		'week'  => array( 'Thuê xe theo tuần', 'tuần', 'week', 'thue-tuan' ),
		'month' => array( 'Thuê xe theo tháng', 'tháng', 'month', 'thue-thang' ),
	);
	$out  = array();
	foreach ( $defs as $key => $def ) {
		$min   = nt_price_column_min( $rows, $key );
		$out[] = array(
			'key'   => $key,
			'label' => $def[0],
			'unit'  => $def[1],
			'icon'  => $def[2],
			'from'  => null === $min ? '' : nt_format_vnd( $min ),
			'url'   => nt_featured_term_url( $def[3] ),
		);
	}
	return $out;
}

/**
 * Vehicle categories (children of "Loại xe") with an icon each.
 *
 * @return array<int,array{name:string,url:string,count:int,icon:string}>
 */
function nt_home_vehicle_categories(): array {
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return array();
	}
	$parent = get_term_by( 'slug', 'loai-xe', 'product_cat' );
	$terms  = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => ( $parent instanceof WP_Term ) ? $parent->term_id : 0,
			'hide_empty' => true,
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
			'orderby'    => 'term_id',
		)
	);
	if ( ! is_array( $terms ) ) {
		return array();
	}
	$icons = array(
		'xe-so'   => 'motorbike',
		'xe-ga'   => 'scooter',
		'xe-dien' => 'scooter-electric',
	);
	$out   = array();
	foreach ( $terms as $term ) {
		$link = get_term_link( $term );
		if ( ! is_string( $link ) ) {
			continue;
		}
		$out[] = array(
			'name'  => nt_decode( $term->name ),
			'url'   => $link,
			'count' => (int) $term->count,
			'icon'  => $icons[ $term->slug ] ?? 'bike',
		);
	}
	return $out;
}

/**
 * Published products in the catalogue's own order.
 *
 * @param int $limit Maximum number of products.
 * @return WC_Product[]
 */
function nt_home_products( int $limit = 8 ): array {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return array();
	}
	$products = wc_get_products(
		array(
			'status'  => 'publish',
			'limit'   => $limit,
			'orderby' => 'menu_order',
			'order'   => 'ASC',
			'return'  => 'objects',
		)
	);
	return is_array( $products ) ? $products : array();
}

/**
 * Latest posts with thumbnails primed.
 *
 * @param int $limit Maximum number of posts.
 */
function nt_home_posts_query( int $limit = 6 ): WP_Query {
	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
	if ( $query->have_posts() ) {
		update_post_thumbnail_cache( $query );
	}
	return $query;
}

/**
 * Top-level blog categories for hub links (children of the root "Blog" category).
 *
 * @param int $limit Maximum number of categories.
 * @return WP_Term[]
 */
function nt_blog_hubs( int $limit = 8 ): array {
	$root = nt_blog_root_id();
	if ( $root <= 0 ) {
		return array();
	}
	$terms = get_terms(
		array(
			'taxonomy'   => 'category',
			'parent'     => $root,
			'hide_empty' => false,
			'orderby'    => 'name',
			'number'     => $limit,
		)
	);
	return is_array( $terms ) ? $terms : array();
}

/**
 * Steps of the booking process, worded strictly from the shop's published policy.
 *
 * @return array<int,array{title:string,text:string,icon:string}>
 */
function nt_home_process_steps(): array {
	$policy = nt_policy();
	return array(
		array(
			'title' => 'Chọn xe và liên hệ',
			'text'  => 'Gọi ' . nt_business( 'phone_display' ) . ' hoặc nhắn Zalo để chọn dòng xe và thời gian thuê.',
			'icon'  => 'headset',
		),
		array(
			'title' => 'Xác nhận điều kiện thuê',
			'text'  => $policy['confirm'],
			'icon'  => 'checklist',
		),
		array(
			'title' => 'Ký hợp đồng và nhận xe',
			'text'  => 'Giấy tờ bảo đảm và mức cọc được trao đổi rõ ràng, sau đó ký hợp đồng và nhận xe.',
			'icon'  => 'key',
		),
		array(
			'title' => 'Trả xe và nhận lại tiền cọc',
			'text'  => $policy['refund'],
			'icon'  => 'handshake',
		),
	);
}

/**
 * Factual trust points (address, hours, written confirmation). No ratings or counts.
 *
 * @return array<int,array{title:string,text:string,icon:string}>
 */
function nt_home_trust_points(): array {
	return array(
		array(
			'title' => 'Địa chỉ cửa hàng rõ ràng',
			'text'  => nt_address(),
			'icon'  => 'store',
		),
		array(
			'title' => 'Mở cửa mỗi ngày',
			'text'  => 'Giờ hoạt động niêm yết ' . nt_hours_range() . ', tất cả các ngày trong tuần.',
			'icon'  => 'clock',
		),
		array(
			'title' => 'Xác nhận trước khi ký',
			'text'  => 'Giá thuê, tiền cọc, thời hạn, tình trạng xe và chi phí giao nhận được xác nhận rõ ràng trước khi ký hợp đồng.',
			'icon'  => 'shield',
		),
	);
}

/**
 * Reversible homepage presentation only. Does not update Page #562 or its SEO fields.
 * Default editorial; administrators can switch back to the original legacy body.
 */
function nt_home_layout(): string {
	$layout = nt_option( 'home_layout', 'editorial' );
	return 'legacy' === $layout ? 'legacy' : 'editorial';
}

/**
 * Editorial hero uses the existing Page #562 H1 verbatim when present in its raw HTML.
 * Avoid rendering its enormous Flatsome shortcode body just to obtain a heading.
 *
 * @param WP_Post $page The existing published homepage.
 */
function nt_home_editorial_h1( WP_Post $page ): string {
	$raw = (string) $page->post_content;
	if ( nt_html_has_h1( $raw ) ) {
		$title = nt_html_first_h1_text( $raw );
		if ( '' !== trim( $title ) ) {
			return $title;
		}
	}
	return 'Thuê Xe Máy Hà Nội – Nguyễn Tú';
}
