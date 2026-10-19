<?php
/**
 * Exact aliases for audited featured-item canonical URLs which currently answer 404.
 *
 * @package NguyenTuLegacyCompat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Serve existing items at their old canonical URLs without altering post/term data, permalinks,
 * metadata, or the working /featured_item/ and /featured_item_category/ routes.
 */
final class NT_Compat_Legacy_Routes {

	/**
	 * The approved old item aliases.
	 *
	 * @return array<string,int> Relative path (without slashes) => existing post ID.
	 */
	public static function items(): array {
		return array(
			'bo-suu-tap/bang-gia,thue-ngay/thue-xe-may-ha-noi-1-ngay' => 587,
			'bo-suu-tap/bang-gia,thue-ngay/dich-vu-thue-xe-may-moi-ngay-ha-noi' => 682,
			'bo-suu-tap/bang-gia,thue-ngay/thue-xe-may-theo-ngay-ha-noi-dat-xe-nhanh-coc-nhe' => 686,
			'bo-suu-tap/bang-gia,thue-ngay/thue-xe-may-1-ngay-ha-noi-giao-xe-tan-noi-thu-tuc-don-gian' => 693,
			'bo-suu-tap/bang-gia,thue-ngay/thue-xe-may' => 696,
			'bo-suu-tap/bang-gia,thue-tuan/thue-xe-may-ha-noi-theo-tuan-gia-uu-dai-giao-xe-nhanh' => 701,
			'bo-suu-tap/bang-gia,thue-tuan/dich-vu-thue-xe-may-1-tuan-tai-ha-noi-tien-loi-an-toan-coc-nhe' => 703,
			'bo-suu-tap/bang-gia,thue-tuan/thue-xe-may-moi-tuan-ha-noi-tiet-kiem-thuan-tien-giao-tan-noi' => 705,
			'bo-suu-tap/bang-gia,thue-tuan/thue-xe-may-ha-noi-1-tuan-thu-tuc-nhanh-coc-nhe-dich-vu-tot' => 708,
			'bo-suu-tap/bang-gia,thue-tuan/bang-gia-thue-cac-loai-xe-may-1-tuan-ha-noi-giao-xe-tan-noi' => 710,
			'bo-suu-tap/bang-gia,thue-thang/dich-vu-thue-xe-may-thang-ha-noi-thuan-tien-an-toan-uy-tin' => 793,
			'bo-suu-tap/bang-gia,thue-thang/thue-xe-may-ha-noi-hang-thang-giao-xe-tan-noi-coc-nhe-tien-loi' => 795,
			'bo-suu-tap/bang-gia,thue-thang/dich-vu-thue-xe-may-1-thang' => 802,
			'bo-suu-tap/bang-gia,thue-thang/cho-thue-xe-may-cac-loai-theo-thang-ha-noi-tien-loi-gia-tot' => 809,
			'bo-suu-tap/bang-gia,thue-thang/cho-thue-xe-may-thang-ha-noi' => 811,
		);
	}

	/**
	 * The approved old term aliases.
	 *
	 * @return array<string,int> Relative path (without slashes) => existing term ID.
	 */
	public static function terms(): array {
		return array(
			'bo-suu-tap/thue-ngay'  => 78,
			'bo-suu-tap/thue-tuan'  => 79,
			'bo-suu-tap/thue-thang' => 80,
		);
	}

	/** Register the rewrite and redirect hooks. */
	public static function hooks(): void {
		add_action( 'init', array( __CLASS__, 'register' ), 110 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'keep_canonical_request' ), 10, 2 );
	}

	/**
	 * Register only audited paths, after the post type and taxonomy are registered. WordPress
	 * rewrite rules must be flushed once after deployment; the plugin never flushes on traffic.
	 */
	public static function register(): void {
		if ( ! post_type_exists( NT_Compat_Registry::CPT ) || ! taxonomy_exists( NT_Compat_Registry::TAX ) ) {
			return;
		}
		foreach ( self::items() as $path => $id ) {
			$post = get_post( $id );
			if ( ! $post instanceof WP_Post || NT_Compat_Registry::CPT !== $post->post_type || 'publish' !== $post->post_status || basename( $path ) !== $post->post_name ) {
				continue;
			}
			$child = explode( ',', explode( '/', $path )[1] )[1];
			if ( ! has_term( $child, NT_Compat_Registry::TAX, $post ) ) {
				continue;
			}
			add_rewrite_rule( '^' . preg_quote( $path, '#' ) . '/?$', 'index.php?post_type=' . NT_Compat_Registry::CPT . '&p=' . $id, 'top' );
		}
		foreach ( self::terms() as $path => $id ) {
			$term = get_term( $id, NT_Compat_Registry::TAX );
			if ( $term instanceof WP_Term && basename( $path ) === $term->slug ) {
				add_rewrite_rule( '^' . preg_quote( $path, '#' ) . '/?$', 'index.php?' . NT_Compat_Registry::TAX . '=' . $term->slug, 'top' );
			}
		}
	}

	/**
	 * Core redirects singular/taxonomy alias requests to the current generated permalink. For
	 * these exact old URLs only, keep the already stored old canonical if the correct object loaded.
	 *
	 * @param string|false $redirect  Core's proposed destination.
	 * @param string       $requested Original request URL.
	 * @return string|false
	 */
	public static function keep_canonical_request( $redirect, string $requested ) {
		$path = trim( rawurldecode( (string) wp_parse_url( $requested, PHP_URL_PATH ) ), '/' );
		if ( isset( self::items()[ $path ] ) && is_singular( NT_Compat_Registry::CPT ) && get_queried_object_id() === self::items()[ $path ] ) {
			return false;
		}
		if ( isset( self::terms()[ $path ] ) && is_tax( NT_Compat_Registry::TAX ) && get_queried_object_id() === self::terms()[ $path ] ) {
			return false;
		}
		return $redirect;
	}
}
