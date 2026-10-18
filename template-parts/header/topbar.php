<?php
/**
 * Top bar (desktop only): hours, address and the optional "top_bar_nav" menu.
 * Hidden below 1024px; the same facts live in the drawer and the footer.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="nt-topbar">
	<div class="nt-container nt-topbar__inner">
		<ul class="nt-topbar__facts">
			<li><?php nt_the_icon( 'clock', array( 'size' => 16 ) ); ?><?php echo nt_hours_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes. ?></li>
			<li><?php nt_the_icon( 'pin', array( 'size' => 16 ) ); ?><span><?php echo esc_html( nt_address( false ) ); ?></span></li>
		</ul>
		<div class="nt-topbar__right">
			<?php
			if ( nt_resolve_menu_id( 'top_bar_nav' ) > 0 ) {
				nt_render_menu(
					'top_bar_nav',
					array(
						'walker'     => '',
						'menu_class' => 'nt-topbar__menu',
						'depth'      => 1,
					)
				);
			}
			?>
			<a class="nt-topbar__phone" href="<?php echo esc_url( nt_tel_url() ); ?>">
				<?php nt_the_icon( 'phone', array( 'size' => 16 ) ); ?>
				<span><?php echo esc_html( nt_business( 'phone_display' ) ); ?></span>
			</a>
		</div>
	</div>
</div>
