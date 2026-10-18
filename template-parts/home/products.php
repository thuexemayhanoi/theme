<?php
/**
 * Product grid from WooCommerce (catalogue order). Hidden when WooCommerce has no products.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_products = nt_home_products( 8 );
if ( array() === $nt_products ) {
	return;
}
$nt_shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';
?>
<section class="nt-section nt-section--tint" aria-labelledby="nt-products-title">
	<div class="nt-container">
		<header class="nt-section__head nt-section__head--row">
			<div>
				<p class="nt-eyebrow">Xe cho thuê</p>
				<h2 id="nt-products-title" class="nt-section__title">Dòng xe đang cho thuê</h2>
			</div>
			<?php if ( '' !== $nt_shop ) : ?>
				<a class="nt-link-arrow" href="<?php echo esc_url( $nt_shop ); ?>">Xem tất cả xe <?php nt_the_icon( 'arrow-right', array( 'size' => 18 ) ); ?></a>
			<?php endif; ?>
		</header>
		<ul class="nt-grid nt-grid--4 nt-grid--list">
			<?php foreach ( $nt_products as $nt_i => $nt_product ) : ?>
				<li><?php nt_product_card( $nt_product, array( 'heading' => 'h3' ) ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
