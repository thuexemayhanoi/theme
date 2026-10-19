<?php
/**
 * Permanent SEO footer: one curated manifest of existing WordPress object IDs.
 * New posts/categories never bloat this list; renamed/published objects resolve on each render.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * The labels describe the destinations; their URLs always come from WordPress.
 *
 * @return array<string,array<int,array<string,int|string>>>
 */
function nt_footer_manifest(): array {
	return array(
		'rental' => array(
			array( 'type' => 'term', 'id' => 77, 'taxonomy' => 'featured_item_category', 'label' => 'Bảng giá thuê xe máy' ),
			array( 'type' => 'term', 'id' => 78, 'taxonomy' => 'featured_item_category', 'label' => 'Thuê theo ngày' ),
			array( 'type' => 'term', 'id' => 79, 'taxonomy' => 'featured_item_category', 'label' => 'Thuê theo tuần' ),
			array( 'type' => 'term', 'id' => 80, 'taxonomy' => 'featured_item_category', 'label' => 'Thuê theo tháng' ),
			array( 'type' => 'post', 'id' => 669, 'label' => 'Xe ga 50cc' ),
			array( 'type' => 'term', 'id' => 71, 'taxonomy' => 'product_cat', 'label' => 'Xe số' ),
			array( 'type' => 'term', 'id' => 72, 'taxonomy' => 'product_cat', 'label' => 'Xe ga' ),
			array( 'type' => 'post', 'id' => 422, 'label' => 'Thuê xe máy điện' ),
			array( 'type' => 'post', 'id' => 647, 'label' => 'Thuê xe đạp điện' ),
		),
		'hubs'   => array(
			array( 'type' => 'term', 'id' => 162, 'taxonomy' => 'category', 'label' => 'Hướng dẫn thuê xe' ),
			array( 'type' => 'term', 'id' => 127, 'taxonomy' => 'category', 'label' => 'Chia sẻ & kinh nghiệm' ),
			array( 'type' => 'term', 'id' => 126, 'taxonomy' => 'category', 'label' => 'Điểm đến & du lịch' ),
			array( 'type' => 'term', 'id' => 156, 'taxonomy' => 'category', 'label' => 'Đánh giá & so sánh xe' ),
			array( 'type' => 'term', 'id' => 157, 'taxonomy' => 'category', 'label' => 'Xe điện: kinh nghiệm sử dụng' ),
			array( 'type' => 'term', 'id' => 158, 'taxonomy' => 'category', 'label' => 'Kỹ thuật & bảo dưỡng' ),
			array( 'type' => 'term', 'id' => 159, 'taxonomy' => 'category', 'label' => 'Pháp lý & kinh nghiệm' ),
		),
		'info'   => array(
			array( 'type' => 'post', 'id' => 310, 'label' => 'Giới thiệu' ),
			array( 'type' => 'post', 'id' => 372, 'label' => 'Liên hệ' ),
			array( 'type' => 'post', 'id' => 484, 'label' => 'Câu hỏi thường gặp' ),
			array( 'type' => 'post', 'id' => 326, 'label' => 'Chính sách bảo mật' ),
			array( 'type' => 'post', 'id' => 449, 'label' => 'Điều khoản sử dụng' ),
		),
	);
}

/**
 * Resolve public term/product/page IDs only; no drafts or invented permalink paths.
 *
 * @param array<string,mixed> $item Curated link specification.
 * @return array{label:string,url:string}|null
 */
function nt_footer_resolve_link( array $item ): ?array {
	$id    = (int) ( $item['id'] ?? 0 );
	$label = trim( (string) ( $item['label'] ?? '' ) );
	if ( $id <= 0 || '' === $label ) {
		return null;
	}

	$url = '';
	if ( 'post' === ( $item['type'] ?? '' ) ) {
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return null;
		}
		$type = get_post_type_object( $post->post_type );
		if ( ! $type || ! $type->public ) {
			return null;
		}
		$url = (string) get_permalink( $post );
	} elseif ( 'term' === ( $item['type'] ?? '' ) ) {
		$taxonomy = (string) ( $item['taxonomy'] ?? '' );
		$tax      = taxonomy_exists( $taxonomy ) ? get_taxonomy( $taxonomy ) : false;
		if ( ! $tax || ! $tax->public ) {
			return null;
		}
		$term = get_term( $id, $taxonomy );
		if ( ! $term instanceof WP_Term ) {
			return null;
		}
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			return null;
		}
		$url = (string) $link;
	} else {
		return null;
	}

	// Current site's permalink; never send crawlers to a guessed host or external redirect.
	$home_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$host      = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	$scheme    = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
	if ( '' === $url || '' === $host || $host !== $home_host || ! in_array( $scheme, array( 'https', 'http' ), true ) ) {
		return null;
	}

	return array(
		'label' => $label,
		'url'   => $url,
	);
}

/**
 * Preserve group order while removing redundant destination URLs and anchor text.
 * WordPress object caching avoids repeat SQL; no stale transients to invalidate.
 *
 * @return array<string,array<int,array{label:string,url:string}>>
 */
function nt_footer_groups(): array {
	$lists = array();
	$urls  = array();
	$texts = array();
	foreach ( nt_footer_manifest() as $group => $items ) {
		$lists[ $group ] = array();
		foreach ( $items as $item ) {
			$link = nt_footer_resolve_link( $item );
			if ( null === $link ) {
				continue;
			}
			$key  = untrailingslashit( strtolower( $link['url'] ) );
			$name = strtolower( $link['label'] );
			if ( isset( $urls[ $key ] ) || isset( $texts[ $name ] ) ) {
				continue;
			}
			$urls[ $key ]   = true;
			$texts[ $name ] = true;
			$lists[ $group ][] = $link;
		}
	}
	return $lists;
}

/**
 * Output a lightweight semantic list of real links.
 *
 * @param array<int,array{label:string,url:string}> $links Curated destinations.
 */
function nt_footer_list( array $links ): void {
	if ( array() === $links ) {
		return;
	}
	echo '<ul class="nt-footer__list">';
	foreach ( $links as $link ) {
		printf( '<li><a href="%s">%s</a></li>', esc_url( $link['url'] ), esc_html( $link['label'] ) );
	}
	echo '</ul>';
}
