<?php
/**
 * Brand: custom logo when set (explicit dimensions come from WordPress), otherwise the name.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="nt-brand">
	<?php if ( has_custom_logo() ) : ?>
		<?php the_custom_logo(); ?>
	<?php else : ?>
		<a class="nt-brand__link" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<span class="nt-brand__mark" aria-hidden="true"><?php nt_the_icon( 'scooter', array( 'size' => 28 ) ); ?></span>
			<span class="nt-brand__text">
				<span class="nt-brand__name"><?php echo esc_html( nt_business( 'short_name' ) ); ?></span>
				<span class="nt-brand__tag">Cho thuê xe máy Hà Nội</span>
			</span>
		</a>
	<?php endif; ?>
</div>
