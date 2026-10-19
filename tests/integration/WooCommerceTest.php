<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WooCommerceTest extends TestCase {

	public function test_woocommerce_is_active_with_the_classic_shop_page(): void {
		$this->assertTrue( class_exists( 'WooCommerce' ) );
		$this->assertSame( 3040, (int) wc_get_page_id( 'shop' ) );
		$this->assertSame( '/shop/', nt_it_path( (string) wc_get_page_permalink( 'shop' ) ) );
		$this->assertGreaterThan( 0, (int) wc_get_page_id( 'cart' ) );
		$this->assertGreaterThan( 0, (int) wc_get_page_id( 'checkout' ) );
	}

	public function test_priced_products_keep_prices_and_unpriced_ones_ask_for_contact(): void {
		$wave = wc_get_product( 410 );
		$this->assertSame( 'honda-wave', $wave->get_slug() );
		$this->assertSame( '150000', (string) $wave->get_price() );
		$this->assertTrue( $wave->is_purchasable() );

		$sirius = wc_get_product( 659 );
		$this->assertSame( '', (string) $sirius->get_price() );
		$this->assertFalse( $sirius->is_purchasable(), 'products without a price cannot be added to the cart' );
		$this->assertStringContainsString( 'Liên hệ báo giá', $sirius->get_price_html() );
	}

	public function test_home_helpers_use_real_catalogue_data(): void {
		$cats = nt_home_vehicle_categories();
		$this->assertSame( array( 'Xe Số', 'Xe Ga', 'Xe Điện' ), array_column( $cats, 'name' ) );
		$this->assertSame( array( 4, 6, 3 ), array_column( $cats, 'count' ) );
		$products = nt_home_products( 20 );
		$this->assertCount( 13, $products );
	}

	public function test_product_card_markup_is_escaped_and_links_to_the_product(): void {
		ob_start();
		nt_product_card( wc_get_product( 410 ), array( 'heading' => 'h3' ) );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( '/san-pham/honda-wave/', $html );
		$this->assertStringContainsString( '<h3 class="nt-card__title">', $html );
		$this->assertSame( 1, substr_count( $html, 'class="nt-card__title"' ) );
	}

	public function test_woo_wrapper_hooks_replace_the_default_layout(): void {
		$this->assertFalse( has_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper' ) );
		$this->assertNotFalse( has_action( 'woocommerce_before_main_content', 'nt_wc_wrapper_start' ) );
		$this->assertFalse( has_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb' ) );
	}

	public function test_authored_archive_headings_replace_only_the_generated_heading(): void {
		$shop      = get_post( 3040 );
		$electric  = get_term( 73, 'product_cat' );
		$shop_body = $shop->post_content;
		$term_body = $electric->description;
		try {
			wp_update_post( array( 'ID' => 3040, 'post_content' => '<h1>Chọn mẫu xe máy tại Nguyễn Tú</h1><p>Mẫu xe đang có.</p>' ) );
			wp_update_term( 73, 'product_cat', array( 'description' => '[title text="Thuê Xe Máy Điện Hà Nội" tag_name="h1"]' ) );
			foreach ( array(
				'/shop/' => 'Chọn mẫu xe máy tại Nguyễn Tú',
				'/danh-muc-san-pham/loai-xe/xe-dien/' => 'Thuê Xe Máy Điện Hà Nội',
			) as $path => $heading ) {
				$response = wp_remote_get( home_url( $path ), array( 'timeout' => 15 ) );
				$this->assertFalse( is_wp_error( $response ), $path );
				$this->assertSame( 200, wp_remote_retrieve_response_code( $response ), $path );
				$body = wp_remote_retrieve_body( $response );
				$this->assertSame( 1, preg_match_all( '#<h1\b#i', $body ), $path );
				$this->assertStringContainsString( $heading, $body, $path );
			}
			$this->assertSame( 1, preg_match_all( '#<h1\b#i', wp_remote_retrieve_body( wp_remote_get( home_url( '/danh-muc-san-pham/loai-xe/xe-so/' ) ) ) ) );
		} finally {
			wp_update_post( array( 'ID' => 3040, 'post_content' => $shop_body ) );
			wp_update_term( 73, 'product_cat', array( 'description' => $term_body ) );
		}
	}
}
