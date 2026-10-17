<?php
/**
 * Vehicle categories (children of "Loại xe"). Real terms only; hidden when WooCommerce has none.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_cats = nt_home_vehicle_categories();
if ( array() === $nt_cats ) {
	return;
}
?>
<section class="nt-section" aria-labelledby="nt-cats-title">
	<div class="nt-container">
		<header class="nt-section__head">
			<p class="nt-eyebrow">Loại xe</p>
			<h2 id="nt-cats-title" class="nt-section__title">Xe số, xe ga và xe điện</h2>
		</header>
		<ul class="nt-cards nt-cards--3">
			<?php foreach ( $nt_cats as $nt_cat ) : ?>
				<li class="nt-feature nt-feature--link">
					<span class="nt-feature__icon" aria-hidden="true"><?php nt_the_icon( $nt_cat['icon'], array( 'size' => 32 ) ); ?></span>
					<h3 class="nt-feature__title"><a class="nt-feature__link" href="<?php echo esc_url( $nt_cat['url'] ); ?>"><?php echo esc_html( $nt_cat['name'] ); ?></a></h3>
					<p class="nt-feature__meta"><?php echo esc_html( (string) $nt_cat['count'] ); ?> mẫu xe</p>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
