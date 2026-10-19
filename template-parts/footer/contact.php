<?php
/**
 * Reusable contact block: NAP and CTA modes both read centralized inc/business.php.
 * Brand column shows NAP; information column shows CTAs without duplicate NAP.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_contact_mode = isset( $args['mode'] ) ? (string) $args['mode'] : 'all';
?>
<div class="nt-footer__contact" role="group" aria-label="Thông tin liên hệ Nguyễn Tú">
	<?php if ( 'nap' === $nt_contact_mode || 'all' === $nt_contact_mode ) : ?>
		<address class="nt-footer__nap">
			<p><?php nt_the_icon( 'pin', array( 'size' => 18 ) ); ?><span><?php echo esc_html( nt_address() ); ?></span></p>
			<p><?php nt_the_icon( 'phone', array( 'size' => 18 ) ); ?><a href="<?php echo esc_url( nt_tel_url() ); ?>"><?php echo esc_html( nt_business( 'phone_display' ) ); ?></a></p>
			<p><?php nt_the_icon( 'clock', array( 'size' => 18 ) ); ?><span>Mở cửa <?php echo esc_html( nt_hours_range() ); ?> mỗi ngày</span></p>
		</address>
	<?php endif; ?>
	<?php if ( 'actions' === $nt_contact_mode || 'all' === $nt_contact_mode ) : ?>
		<?php
		nt_cta_buttons(
			array(
				'size'       => 'sm',
				'call_label' => 'Gọi ngay',
			)
		);
		?>
	<?php endif; ?>
</div>
