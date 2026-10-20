<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HomeAssetsTest extends TestCase {

	private $original_query;
	private $original_widgets;

	protected function setUp(): void {
		$this->original_query   = $GLOBALS['wp_query'];
		$this->original_widgets = wp_get_sidebars_widgets();
		wp_register_sidebar_widget( 'nt-qa-widget', 'QA footer widget', '__return_empty_string' );
		wp_set_sidebars_widgets( array_merge( $this->original_widgets, array( 'footer-extra' => array( 'nt-qa-widget' ) ) ) );
	}

	protected function tearDown(): void {
		$GLOBALS['wp_query'] = $this->original_query;
		wp_set_sidebars_widgets( $this->original_widgets );
		wp_unregister_sidebar_widget( 'nt-qa-widget' );
		remove_filter( 'nguyentu_enable_footer_extra', '__return_true' );
		wp_dequeue_script( 'yith-wcwl-add-to-wishlist' );
		wp_dequeue_style( 'yith-wcwl-main' );
	}

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
	public function test_visible_footer_widgets_keep_their_wishlist_assets(): void {
		$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => (int) get_option( 'page_on_front' ) ) );
		add_filter( 'nguyentu_enable_footer_extra', '__return_true' );
		wp_enqueue_script( 'yith-wcwl-add-to-wishlist' );
		nt_editorial_home_wishlist_assets();
		$this->assertTrue( wp_script_is( 'yith-wcwl-add-to-wishlist', 'enqueued' ) );
	}

}
