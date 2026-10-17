<?php
/**
 * Helpful destinations for empty states and the 404 page: blog hubs, rental pages, contact.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_hubs    = nt_blog_hubs( 8 );
$nt_vehicle = nt_home_vehicle_categories();
?>
<div class="nt-hubs">
	<?php if ( array() !== $nt_hubs ) : ?>
		<div class="nt-hubs__col">
			<h3 class="nt-hubs__title">Chuyên mục blog</h3>
			<ul class="nt-hubs__list">
				<?php foreach ( $nt_hubs as $nt_hub ) : ?>
					<li><a href="<?php echo esc_url( (string) get_term_link( $nt_hub ) ); ?>"><?php echo esc_html( html_entity_decode( $nt_hub->name, ENT_QUOTES, 'UTF-8' ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
	<?php if ( array() !== $nt_vehicle ) : ?>
		<div class="nt-hubs__col">
			<h3 class="nt-hubs__title">Thuê xe máy</h3>
			<ul class="nt-hubs__list">
				<?php foreach ( $nt_vehicle as $nt_cat ) : ?>
					<li><a href="<?php echo esc_url( $nt_cat['url'] ); ?>"><?php echo esc_html( $nt_cat['name'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
	<div class="nt-hubs__col">
		<h3 class="nt-hubs__title">Cần xe ngay?</h3>
		<?php nt_cta_buttons( array( 'size' => 'md', 'class' => 'nt-cta--stack' ) ); ?>
	</div>
</div>
