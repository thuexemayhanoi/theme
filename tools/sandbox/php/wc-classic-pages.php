<?php
/**
 * SANDBOX HELPER (not theme code).
 *
 * Makes WooCommerce use CLASSIC shortcode pages for Cart / Checkout / My account,
 * with the high, sandbox-only IDs 9001-9003 (they do not exist on production).
 * Idempotent. Used by php/configure-site.php (setup.sh) and seed.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI: wp eval-file\n" ); // phpcs:ignore
	exit( 1 );
}

if ( ! function_exists( 'nt_sb_ensure_classic_wc_pages' ) ) {

	/**
	 * Creates pages 9001-9003 (if missing) and points the WooCommerce page options at them.
	 * WooCommerce's own auto-created (block based) cart/checkout/account pages are deleted.
	 *
	 * @param int $author_id Post author for created pages (0 = user 1).
	 * @return array<int,string> page ID => slug of the sandbox pages.
	 */
	function nt_sb_ensure_classic_wc_pages( $author_id = 0 ) {
		$defs = array(
			9001 => array(
				'option'  => 'woocommerce_cart_page_id',
				'slug'    => 'cart',
				'title'   => 'Giỏ hàng (sandbox)',
				'content' => '[woocommerce_cart]',
			),
			9002 => array(
				'option'  => 'woocommerce_checkout_page_id',
				'slug'    => 'checkout',
				'title'   => 'Thanh toán (sandbox)',
				'content' => '[woocommerce_checkout]',
			),
			9003 => array(
				'option'  => 'woocommerce_myaccount_page_id',
				'slug'    => 'my-account',
				'title'   => 'Tài khoản của tôi (sandbox)',
				'content' => '[woocommerce_my_account]',
			),
		);

		kses_remove_filters();
		$out = array();

		foreach ( $defs as $id => $def ) {
			$current = (int) get_option( $def['option'] );
			if ( $current > 0 && $current !== $id ) {
				$old = get_post( $current );
				if ( $old && 'page' === $old->post_type ) {
					wp_delete_post( $current, true ); // WooCommerce default (block) page.
				}
			}

			$existing = get_post( $id );
			if ( $existing && 'page' !== $existing->post_type ) {
				throw new RuntimeException( "Post ID $id exists but is a {$existing->post_type}; sandbox needs it for a WooCommerce page." );
			}
			if ( ! $existing ) {
				$new_id = wp_insert_post(
					wp_slash(
						array(
							'import_id'      => $id,
							'post_type'      => 'page',
							'post_status'    => 'publish',
							'post_title'     => $def['title'],
							'post_name'      => $def['slug'],
							'post_content'   => $def['content'],
							'post_author'    => $author_id ? (int) $author_id : 1,
							'comment_status' => 'closed',
							'ping_status'    => 'closed',
						)
					),
					true
				);
				if ( is_wp_error( $new_id ) ) {
					throw new RuntimeException( "Could not create page $id: " . $new_id->get_error_message() );
				}
				if ( (int) $new_id !== $id ) {
					throw new RuntimeException( "Page got ID $new_id instead of $id (ID already in use?)." );
				}
				update_post_meta( $id, '_nt_sandbox_extra', '1' );
			}
			update_option( $def['option'], $id );
			$out[ $id ] = $def['slug'];
		}

		return $out;
	}
}
