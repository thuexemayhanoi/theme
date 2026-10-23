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

	public function test_loop_accessible_name_starts_with_visible_woocommerce_button_label(): void {
		$product = wc_get_product( 659 );
		$this->assertInstanceOf( WC_Product::class, $product );
		$this->assertNotFalse( has_filter( 'woocommerce_loop_add_to_cart_args', 'nt_wc_loop_button_accessible_label' ) );
		$args = array(
			'attributes' => array(
				'aria-label' => 'A label that does not contain the visible action',
				'data-product_id' => 659,
			),
			'quantity' => 1,
		);
		$fixed = nt_wc_loop_button_accessible_label( $args, $product );
		$this->assertSame( $product->add_to_cart_text() . ': ' . $product->get_name(), $fixed['attributes']['aria-label'] );
		$this->assertSame( $args['attributes']['data-product_id'], $fixed['attributes']['data-product_id'] );
		$this->assertSame( $args['quantity'], $fixed['quantity'] );
		$matching = $fixed;
		$matching['attributes']['aria-label'] = 'Prefix ' . $product->add_to_cart_text() . ' included';
		$this->assertSame( $matching, nt_wc_loop_button_accessible_label( $matching, $product ) );
		$this->assertSame( $args, nt_wc_loop_button_accessible_label( $args, null ) );
	}

	public function test_woo_wrapper_hooks_replace_the_default_layout(): void {
		$this->assertFalse( has_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper' ) );
		$this->assertNotFalse( has_action( 'woocommerce_before_main_content', 'nt_wc_wrapper_start' ) );
		$this->assertFalse( has_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb' ) );
	}
}
