<?php
/**
 * WooCommerce integration through supports and hooks only: no template overrides, so Woo
 * updates, Yoast WooCommerce, payment gateways and tracking plugins keep working untouched.
 * Cart, checkout and account pages are rendered by their own shortcodes inside page.php and
 * are never cached or altered by the theme.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Declare WooCommerce support. Image sizes are intentionally NOT declared so existing product
 * thumbnails and the owner's Customizer settings stay exactly as they are.
 */
function nt_wc_support(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'nt_wc_support', 20 );

/**
 * Replace Woo's wrapper, breadcrumb and sidebar with the theme's layout.
 */
function nt_wc_hooks(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
	remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

	add_action( 'woocommerce_before_main_content', 'nt_wc_wrapper_start', 10 );
	add_action( 'woocommerce_after_main_content', 'nt_wc_wrapper_end', 10 );
	add_action( 'woocommerce_single_product_summary', 'nt_wc_contact_box', 35 );
	add_filter( 'woocommerce_empty_price_html', 'nt_wc_empty_price' );
	add_filter( 'woocommerce_output_related_products_args', 'nt_wc_related_args' );
}
add_action( 'init', 'nt_wc_hooks' );

/**
 * Open the single main landmark for shop pages.
 */
function nt_wc_wrapper_start(): void {
	echo '<main id="main" class="nt-main nt-shop" tabindex="-1"><div class="nt-container">';
	nt_breadcrumbs();
}

/**
 * Close the main landmark.
 */
function nt_wc_wrapper_end(): void {
	echo '</div></main>';
}

/**
 * Products without a price show a contact prompt instead of an empty box.
 */
function nt_wc_empty_price(): string {
	return '<span class="nt-price-contact">Liên hệ báo giá</span>';
}

/**
 * Show four related products in a row.
 *
 * @param array<string,mixed> $args Related products args.
 * @return array<string,mixed>
 */
function nt_wc_related_args( array $args ): array {
	$args['posts_per_page'] = 4;
	$args['columns']        = 4;
	return $args;
}

/**
 * Booking contact box under the add-to-cart area. Rental prices are reference prices, so the
 * box always reminds that price, deposit and condition are confirmed before booking.
 */
function nt_wc_contact_box(): void {
	?>
	<div class="nt-wc-contact">
		<p class="nt-wc-contact__title">Đặt xe nhanh</p>
		<?php nt_cta_buttons( array( 'size' => 'md', 'maps' => false ) ); ?>
		<p class="nt-note"><?php echo esc_html( nt_policy()['confirm'] ); ?></p>
	</div>
	<?php
}

/**
 * Header cart link: rendered only when the cart page exists and the cart has items, so the
 * header costs nothing on catalogue-style browsing and stays cache friendly.
 */
function nt_wc_header_cart(): void {
	if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_get_cart_url' ) || ! WC()->cart || WC()->cart->is_empty() ) {
		return;
	}
	if ( 'off' === nt_toggle( 'header_cart' ) ) {
		return;
	}
	$count = (int) WC()->cart->get_cart_contents_count();
	?>
	<a class="nt-iconbtn nt-cartlink" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
		<?php nt_the_icon( 'wallet', array( 'size' => 22 ) ); ?>
		<span class="screen-reader-text">Giỏ hàng, <?php echo esc_html( (string) $count ); ?> sản phẩm</span>
		<span class="nt-cartlink__count" aria-hidden="true"><?php echo esc_html( (string) $count ); ?></span>
	</a>
	<?php
}

/**
 * Compact product card used by the home page and the legacy product grid shortcode.
 *
 * @param WC_Product $product Product.
 * @param array<string,mixed> $args heading (h2|h3), eager (bool).
 */
function nt_product_card( $product, array $args = array() ): void {
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	$heading = in_array( $args['heading'] ?? 'h3', array( 'h2', 'h3', 'h4' ), true ) ? $args['heading'] : 'h3';
	$link    = get_permalink( $product->get_id() );
	$img_id  = $product->get_image_id();
	?>
	<article class="nt-card nt-card--product">
		<a class="nt-card__media" href="<?php echo esc_url( (string) $link ); ?>" tabindex="-1" aria-hidden="true">
			<?php
			if ( $img_id ) {
				echo wp_get_attachment_image( (int) $img_id, 'woocommerce_thumbnail', false, array( 'class' => 'nt-card__img', 'sizes' => '(min-width: 1024px) 280px, (min-width: 640px) 30vw, 46vw', 'loading' => ! empty( $args['eager'] ) ? 'eager' : 'lazy', 'decoding' => 'async' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image helper.
			} else {
				echo '<span class="nt-card__placeholder">' . nt_icon( 'scooter', array( 'size' => 48 ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted icon markup.
			}
			?>
		</a>
		<div class="nt-card__body">
			<<?php echo esc_html( $heading ); ?> class="nt-card__title"><a href="<?php echo esc_url( (string) $link ); ?>"><?php echo esc_html( $product->get_name() ); ?></a></<?php echo esc_html( $heading ); ?>>
			<p class="nt-card__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></p>
		</div>
	</article>
	<?php
}
