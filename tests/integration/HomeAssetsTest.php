<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HomeAssetsTest extends TestCase {

	public function test_editorial_home_omits_unused_wishlist_bundle_but_keeps_registration(): void {
		$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => (int) get_option( 'page_on_front' ) ) );
		wp_register_script( 'yith-wcwl-add-to-wishlist', '/wishlist.js', array( 'react' ), '1', true );
		wp_enqueue_script( 'yith-wcwl-add-to-wishlist' );
		wp_enqueue_style( 'yith-wcwl-main', '/wishlist.css' );
		nt_editorial_home_wishlist_assets();
		$this->assertFalse( wp_script_is( 'yith-wcwl-add-to-wishlist', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'yith-wcwl-add-to-wishlist', 'registered' ) );
		$this->assertFalse( wp_style_is( 'yith-wcwl-main', 'enqueued' ) );
	}

	public function test_product_page_keeps_wishlist_bundle(): void {
		$GLOBALS['wp_query'] = new WP_Query( array( 'post_type' => 'product', 'name' => 'honda-wave' ) );
		wp_enqueue_script( 'yith-wcwl-add-to-wishlist' );
		nt_editorial_home_wishlist_assets();
		$this->assertTrue( wp_script_is( 'yith-wcwl-add-to-wishlist', 'enqueued' ) );
		wp_dequeue_script( 'yith-wcwl-add-to-wishlist' );
	}
}
