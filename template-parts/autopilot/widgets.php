<?php
/**
 * Accessible native contact and local-knowledge assistant, no duplicate plugin UI.
 *
 * @package NguyenTu
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="nt-tools" data-nt-tools>
	<?php if ( nt_native_contact_enabled() ) : ?>
	<details class="nt-quickcall" data-nt-quickcall>
		<summary>Liên hệ</summary>
		<div class="nt-quickcall__links">
			<a href="<?php echo esc_url( nt_tel_url() ); ?>"><?php nt_the_icon( 'phone', array( 'size' => 20 ) ); ?> Gọi <?php echo esc_html( nt_business( 'phone_display' ) ); ?></a>
			<?php if ( nt_zalo_url() ) : ?><a href="<?php echo esc_url( nt_zalo_url() ); ?>" target="_blank" rel="noopener">Zalo Nguyễn Tú</a><?php endif; ?>
			<?php if ( nt_whatsapp_url() ) : ?><a href="<?php echo esc_url( nt_whatsapp_url() ); ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
		</div>
	</details>
	<?php endif; ?>
	<?php if ( ! nt_chat_widget_active() && 'off' !== nt_option( 'assistant', 'on' ) ) : ?>
	<button class="nt-assistant-toggle" data-nt-chat-toggle aria-controls="nt-assistant" aria-expanded="false" type="button">Hỏi Nguyễn Tú</button>
	<section id="nt-assistant" class="nt-assistant" role="dialog" aria-label="Nguyễn Tú Assistant" hidden>
		<div class="nt-assistant__head"><strong>Nguyễn Tú Assistant</strong><button type="button" data-nt-chat-close aria-label="Đóng trợ lý">×</button></div>
		<p class="nt-assistant__notice">Trợ lý tra cứu website, không tự xác nhận đặt xe. Hỗ trợ tiếng Việt và tiếng Anh.</p>
		<p><?php echo nt_hours_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></p>
		<div class="nt-assistant__messages" role="log" aria-live="polite" aria-relevant="additions" data-nt-messages><p>Khách muốn hỏi giá thuê, thủ tục hay tìm bài viết?</p></div>
		<form data-nt-chat-form><label for="nt-question">Câu hỏi / Your question</label><div class="nt-assistant__input"><input id="nt-question" name="question" maxlength="300" autocomplete="off" required><button type="submit">Gửi</button></div></form>
		<p class="nt-assistant__handoff"><a href="<?php echo esc_url( nt_tel_url() ); ?>">Gọi cửa hàng</a><?php if ( nt_zalo_url() ) : ?> · <a href="<?php echo esc_url( nt_zalo_url() ); ?>" rel="noopener" target="_blank">Nhắn Zalo</a><?php endif; ?></p>
	</section>
	<?php endif; ?>
</div>
