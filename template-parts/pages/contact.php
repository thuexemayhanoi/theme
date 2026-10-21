<?php
/**
 * Native contact body replacing the archived UX Builder layout.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="nt-service-page nt-contact-page">
	<section class="nt-service-page__intro" aria-labelledby="nt-contact-heading">
		<p class="nt-service-page__eyebrow">Liên hệ · <?php echo esc_html( nt_business( 'short_name' ) ); ?></p>
		<h1 id="nt-contact-heading"><?php echo esc_html( $args['heading'] ); ?></h1>
		<p class="nt-service-page__lead">Muốn thuê xe nhanh? Gọi hoặc nhắn Zalo để kiểm tra mẫu xe, giá thuê và thời gian giao nhận trước khi gửi yêu cầu.</p>
		<div class="nt-service-page__actions"><?php nt_cta_buttons(); ?></div>
	</section>
	<nav class="nt-service-page__nav" aria-label="Nội dung liên hệ">
		<a href="#nt-contact-details">Địa chỉ và giờ mở cửa</a><a href="#nt-contact-prepare">Thông tin cần gửi</a>
		<?php if ( '' !== $args['form'] ) : ?>
			<a href="#nt-contact-form">Gửi yêu cầu</a>
		<?php endif; ?>
	</nav>
	<section id="nt-contact-details" class="nt-service-page__section" aria-labelledby="nt-contact-details-title">
		<h2 id="nt-contact-details-title">Thông tin liên hệ Nguyễn Tú</h2>
		<div class="nt-service-page__grid">
			<div class="nt-service-page__card">
				<h3>Địa điểm chính tại Long Biên</h3>
				<ul class="nt-service-page__facts">
					<li><strong>Địa chỉ</strong><?php echo esc_html( nt_address() ); ?></li>
					<li><strong>Điện thoại / Zalo</strong><a href="<?php echo esc_url( nt_tel_url() ); ?>"><?php echo esc_html( nt_business( 'phone_display' ) ); ?></a></li>
					<li><strong>Giờ hoạt động</strong><?php echo esc_html( nt_hours_range() ); ?> mỗi ngày</li>
					<li><strong>Email</strong><a href="<?php echo esc_url( 'mailto:' . nt_business( 'email' ) ); ?>"><?php echo esc_html( nt_business( 'email' ) ); ?></a></li>
				</ul>
			</div>
			<div class="nt-service-page__card">
				<h3>Nhận xe và chỉ đường</h3>
				<p>Khách có thể nhận xe tại điểm chính hoặc gửi địa chỉ cụ thể để xác nhận phương án giao nhận. Phí và thời gian giao nhận được thống nhất trước khi đặt.</p>
				<p>Nên gọi trước khi đến để kiểm tra xe còn sẵn và chuẩn bị giấy tờ theo hướng dẫn.</p>
				<a class="nt-btn nt-btn--ghost" href="<?php echo esc_url( nt_maps_url() ); ?>" target="_blank" rel="noopener">Mở chỉ đường Google Maps</a>
			</div>
		</div>
		<div class="nt-service-page__card nt-contact-page__map" aria-labelledby="nt-contact-map-title">
			<h3 id="nt-contact-map-title">Bản đồ cửa hàng Nguyễn Tú</h3>
			<p>Vị trí nhận xe: <?php echo esc_html( nt_address() ); ?></p>
			<iframe
				class="nt-contact-page__map-frame"
				title="Bản đồ Cho Thuê Xe Máy Nguyễn Tú tại 112 Nguyễn Văn Cừ, Hà Nội"
				src="<?php echo esc_url( 'https://maps.google.com/maps?q=' . rawurlencode( (string) nt_business( 'gbp_name' ) . ', ' . nt_address( false ) ) . '&hl=vi&z=16&output=embed' ); ?>"
				width="960"
				height="400"
				loading="lazy"
				referrerpolicy="strict-origin-when-cross-origin"
				allowfullscreen
			></iframe>
			<p class="nt-contact-page__map-link"><a href="<?php echo esc_url( nt_maps_url() ); ?>" target="_blank" rel="noopener noreferrer">Xem vị trí chính xác trên Google Maps</a></p>
		</div>
	</section>
	<section id="nt-contact-prepare" class="nt-service-page__section" aria-labelledby="nt-contact-prepare-title">
		<h2 id="nt-contact-prepare-title">Thông tin cần chuẩn bị trước khi liên hệ</h2>
		<p>Gửi các thông tin dưới đây để được tư vấn đúng nhu cầu. Nguyễn Tú sẽ xác nhận mẫu xe, giá hiện tại, tiền cọc và điều kiện thuê trước khi chốt đơn.</p>
		<table><thead><tr><th scope="col">Thông tin</th><th scope="col">Nội dung nên gửi</th></tr></thead><tbody>
			<tr><th scope="row">Thời gian</th><td>Ngày giờ nhận và trả xe; thuê theo ngày, tuần hoặc tháng</td></tr>
			<tr><th scope="row">Nhu cầu</th><td>Xe số, xe ga, xe 50cc, xe điện hoặc mẫu xe cụ thể</td></tr>
			<tr><th scope="row">Địa điểm</th><td>Nhận tại điểm chính hay yêu cầu giao xe; địa chỉ nhận và trả</td></tr>
			<tr><th scope="row">Liên hệ</th><td>Số điện thoại hoặc tài khoản Zalo để phản hồi</td></tr>
		</tbody></table>
		<div class="nt-service-page__notice">Trước khi nhận xe, cần thống nhất giá thuê, tiền cọc, điều kiện hoàn cọc và chi phí giao nhận nếu có. Gửi yêu cầu chưa đồng nghĩa đã xác nhận đặt xe.</div>
	</section>
	<?php if ( '' !== $args['form'] ) : ?>
		<section id="nt-contact-form" class="nt-service-page__section" aria-labelledby="nt-contact-form-title">
			<h2 id="nt-contact-form-title">Gửi yêu cầu tư vấn</h2>
			<div class="nt-service-page__card nt-contact-page__form">
				<p>Điền thông tin liên hệ và nhu cầu thuê xe. Nếu cần trao đổi ngay, hãy gọi hoặc nhắn Zalo theo số ở trên.</p>
				<?php echo $args['form']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- exact CF7 shortcode from this page; the_content renders it once. ?>
			</div>
		</section>
	<?php endif; ?>
	<section class="nt-service-page__closing" aria-labelledby="nt-contact-faq-title">
		<h2 id="nt-contact-faq-title">Câu hỏi nhanh về liên hệ thuê xe</h2>
		<h3>Có cần gọi trước không?</h3><p>Nên liên hệ trước để kiểm tra xe, giá và thời gian giao nhận.</p>
		<h3>Có thể yêu cầu giao xe không?</h3><p>Hãy gửi địa điểm cụ thể để được xác nhận phương án giao nhận và chi phí nếu có.</p>
		<h3>Giá và tiền cọc có cố định không?</h3><p>Cần xác nhận lại theo mẫu xe, thời gian thuê và thời điểm đặt. Xem thêm <a href="<?php echo esc_url( get_permalink( 484 ) ); ?>">câu hỏi thường gặp</a> trước khi thuê.</p>
	</section>
</div>
