<?php
/**
 * Site footer: NAP, key hub links, optional footer menu, widgets and (when no QuickCall plugin is
 * active) the single mobile contact bar.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;
?>
<footer id="site-footer" class="nt-footer">
	<div class="nt-container">
		<?php get_template_part( 'template-parts/footer/columns' ); ?>

		<?php if ( is_active_sidebar( 'footer-extra' ) ) : ?>
			<div class="nt-footer__widgets"><?php dynamic_sidebar( 'footer-extra' ); ?></div>
		<?php endif; ?>

		<div class="nt-footer__bottom">
			<p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( nt_business( 'brand' ) ); ?>. Giá thuê hiển thị là giá tham khảo, cần xác nhận trước khi đặt xe.</p>
		</div>
	</div>
</footer>

<?php if ( nt_show_cta_bar() ) : ?>
	<div class="nt-ctabar" role="region" aria-label="Liên hệ nhanh">
		<a class="nt-ctabar__btn nt-ctabar__btn--call" href="<?php echo esc_url( nt_tel_url() ); ?>">
			<?php nt_the_icon( 'phone', array( 'size' => 20 ) ); ?><span>Gọi ngay</span>
		</a>
		<a class="nt-ctabar__btn nt-ctabar__btn--zalo" href="<?php echo esc_url( nt_zalo_url() ); ?>" target="_blank" rel="noopener">
			<?php nt_the_icon( 'chat', array( 'size' => 20 ) ); ?><span>Zalo</span>
		</a>
	</div>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
