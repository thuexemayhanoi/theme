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

		<?php
		/*
		 * Old footer-extra widgets duplicate the product catalogue (Latest,
		 * Best Selling, Top Rated). Keep the widgets in WordPress, but leave
		 * the old area hidden by default; a filter can opt back in without
		 * deleting user data or changing any product.
		 */
		?>
		<?php if ( apply_filters( 'nguyentu_enable_footer_extra', false ) && is_active_sidebar( 'footer-extra' ) ) : ?>
			<div class="nt-footer__widgets"><?php dynamic_sidebar( 'footer-extra' ); ?></div>
		<?php endif; ?>

		<div class="nt-footer__bottom">
			<p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( nt_business( 'brand' ) ); ?></p>
			<a class="nt-footer__backtop" href="#nt-page-top" aria-label="Lên đầu trang">
				<?php nt_the_icon( 'arrow-up', array( 'size' => 20 ) ); ?>
				<span>Lên đầu trang</span>
			</a>
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
