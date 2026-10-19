<?php
/**
 * Shared local-business contact details used inside the footer's "Liên hệ" menu item.
 * The NAP values remain centralized in inc/business.php.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="nt-footer__contact" role="group" aria-label="Thông tin liên hệ Nguyễn Tú">
	<address class="nt-footer__nap">
		<p><?php nt_the_icon( 'pin', array( 'size' => 18 ) ); ?><span><?php echo esc_html( nt_address() ); ?></span></p>
		<p><?php nt_the_icon( 'phone', array( 'size' => 18 ) ); ?><a href="<?php echo esc_url( nt_tel_url() ); ?>"><?php echo esc_html( nt_business( 'phone_display' ) ); ?></a> <span class="nt-footer__muted">(gọi / Zalo)</span></p>
		<p><?php nt_the_icon( 'clock', array( 'size' => 18 ) ); ?><span>Mở cửa <?php echo esc_html( nt_hours_range() ); ?> mỗi ngày</span></p>
	</address>
	<?php
	nt_cta_buttons(
		array(
			'size'       => 'sm',
			'call_label' => 'Gọi ngay',
		)
	);
	?>
</div>
