<?php
/**
 * Homepage rental essentials. Reuses published policies and existing supporting URLs;
 * does not assert inventory, branches, reviews, free delivery or foreign-licence validity.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="nt-section nt-home-mag__decisions" aria-labelledby="nt-home-rental-process">
	<div class="nt-container nt-prose">
		<h2 id="nt-home-rental-process">Thủ tục thuê xe máy tại Nguyễn Tú</h2>
		<p>Để chọn xe phù hợp cho lịch trình ở Hà Nội, hãy gửi dòng xe, ngày nhận và trả, cùng địa điểm nhận xe dự kiến. Cửa hàng sẽ xác nhận chiếc xe còn sẵn, tổng tiền thuê và điều kiện trước khi chốt lịch.</p>
		<ol>
			<li><strong>Chọn xe và kỳ thuê:</strong> xem <a href="<?php echo esc_url( get_permalink( 1062 ) ); ?>">cách chọn xe ga hay xe số</a>, thông tin <a href="<?php echo esc_url( get_permalink( 412 ) ); ?>">Honda Vision</a> hoặc <a href="<?php echo esc_url( get_permalink( 895 ) ); ?>">xe ga 50cc</a>. Thuê dài ngày thì đối chiếu <a href="<?php echo esc_url( get_permalink( 1060 ) ); ?>">giá thuê xe máy theo tháng</a>.</li>
			<li><strong>Xác nhận giấy tờ và tiền cọc:</strong> chuẩn bị giấy tờ tùy thân còn hiệu lực; giấy phép lái xe phải phù hợp với đúng phương tiện. Tiền cọc tách riêng tiền thuê, theo dòng xe và thỏa thuận ghi trong hợp đồng.</li>
			<li><strong>Kiểm tra khi nhận xe:</strong> cùng đối chiếu phanh, lốp, đèn, gương, mức nhiên liệu hoặc pin; ghi nhận tình trạng có sẵn và giấy tờ đi kèm.</li>
			<li><strong>Chốt cách trả xe:</strong> thống nhất thời gian, địa điểm, cách tính quá giờ, nhiên liệu và điều kiện hoàn cọc trước khi ký. Không mặc định mọi đơn đều tính một ngày bằng 24 giờ.</li>
		</ol>
		<p>Đọc chi tiết <a href="<?php echo esc_url( get_permalink( 829 ) ); ?>">giấy tờ và thủ tục thuê xe</a>, <a href="<?php echo esc_url( get_permalink( 891 ) ); ?>">hợp đồng, đặt cọc và trách nhiệm hai bên</a> trước khi nhận xe.</p>

		<h2 id="nt-home-service-area">Điểm nhận xe Long Biên và lịch trình tại Hà Nội</h2>
		<p><strong>Điểm nhận xe hiện công bố: <?php echo esc_html( nt_address() ); ?>.</strong> Giờ mở cửa <?php echo esc_html( nt_hours_range() ); ?>. Nếu tìm “thuê xe máy gần đây”, hãy so khoảng cách tới điểm nhận xe với vị trí lưu trú và giờ đến; không mặc định có chi nhánh tại mỗi khu vực được nhắc trên website.</p>
		<p>Khách ở <a href="<?php echo esc_url( get_permalink( 847 ) ); ?>">Long Biên</a>, <a href="<?php echo esc_url( get_permalink( 836 ) ); ?>">Phố Cổ</a>, <a href="<?php echo esc_url( get_permalink( 841 ) ); ?>">Hoàn Kiếm</a>, <a href="<?php echo esc_url( get_permalink( 857 ) ); ?>">Ba Đình</a> hoặc <a href="<?php echo esc_url( get_permalink( 862 ) ); ?>">Tây Hồ</a> có thể xem hướng dẫn theo lịch trình. Nhận/trả tại địa chỉ khác cần xác nhận theo địa điểm, thời gian, giá trị hợp đồng và <a href="<?php echo esc_url( get_permalink( 887 ) ); ?>">điều kiện, phí giao nhận</a>; không mặc định giao miễn phí hoặc giao mọi đơn.</p>
		<p>Xem <a href="<?php echo esc_url( get_permalink( 372 ) ); ?>">thông tin liên hệ và chỉ đường</a> hoặc gọi <a href="<?php echo esc_url( nt_tel_url() ); ?>"><?php echo esc_html( nt_business( 'phone_display' ) ); ?></a> trước khi tới.</p>

		<div lang="en">
			<h2>Motorbike rental in Hanoi: essentials for visitors</h2>
			<p>Nguyen Tu lists pickup at 112 Nguyen Van Cu, Bo De, Long Bien, Hanoi, from 09:00 to 21:00. The table above shows reference daily, weekly and monthly rates in Vietnamese dong (VND); the deposit is separate. Confirm the actual bike, rental period, total price and return terms before booking.</p>
			<p>Check identification and driving-licence requirements for the specific vehicle before renting. A foreign licence is not automatically valid in Vietnam. Ask about delivery availability and fees for your address; delivery is not included by default. Contact <a href="<?php echo esc_url( nt_tel_url() ); ?>">+84 942 467 674</a> to confirm arrangements.</p>
		</div>

		<h2 id="nt-home-rental-faq">Câu hỏi thường gặp khi thuê xe máy Hà Nội</h2>
		<details>
			<summary>Giá thuê xe máy Hà Nội tại Nguyễn Tú bao nhiêu?</summary>
			<p>Xem bảng giá phía trên theo từng dòng xe, với các cột ngày, tuần, tháng và tiền cọc. Đây là giá tham khảo; cần xác nhận đúng phiên bản, kỳ thuê và tình trạng xe trước khi đặt. Xem <a href="<?php echo esc_url( get_permalink( 476 ) ); ?>">cách đối chiếu tổng chi phí thuê xe</a> để không nhầm tiền thuê với tiền cọc.</p>
		</details>
		<details>
			<summary>Thuê xe máy theo tháng khác thuê theo ngày thế nào?</summary>
			<p>Giá tháng được công bố riêng theo dòng xe, không lấy giá ngày nhân 30. Hãy xác nhận kỳ thuê, ngày trả, điều kiện gia hạn và trách nhiệm bảo dưỡng. Xem <a href="<?php echo esc_url( get_permalink( 1060 ) ); ?>">bảng giá và điều kiện thuê theo tháng</a>.</p>
		</details>
		<details>
			<summary>Cần chuẩn bị giấy tờ và tiền cọc gì?</summary>
			<p>Chuẩn bị giấy tờ tùy thân còn hiệu lực và giấy phép lái xe phù hợp với phương tiện. Mức cọc theo từng dòng xe được ghi trong bảng giá; phương án bảo đảm và điều kiện hoàn cọc phải được xác nhận trong hợp đồng. Không mặc định thuê xe không cần cọc.</p>
		</details>
		<details>
			<summary>Có thể nhận xe ở Phố Cổ hoặc sân bay Nội Bài không?</summary>
			<p>Điểm nhận xe đang công bố ở 112 Nguyễn Văn Cừ, Long Biên. Giao nhận ở nơi khác phụ thuộc hợp đồng, địa chỉ, giờ hẹn và phí được xác nhận trước. Nếu tự di chuyển từ sân bay, xem <a href="<?php echo esc_url( get_permalink( 1061 ) ); ?>">hướng dẫn từ Nội Bài đến điểm nhận xe</a>; không mặc định có quầy thuê xe tại sân bay.</p>
		</details>
		<details>
			<summary>Thuê Honda Vision hay xe 50cc phù hợp hơn?</summary>
			<p>Chọn theo khả năng điều khiển, giấy phép phù hợp, hành lý và lịch trình. Kiểm tra thông tin <a href="<?php echo esc_url( get_permalink( 412 ) ); ?>">Honda Vision</a> và <a href="<?php echo esc_url( get_permalink( 895 ) ); ?>">xe ga 50cc</a>; tên gọi 50cc không thay thế việc xác minh phân loại xe, độ tuổi và điều kiện điều khiển thực tế.</p>
		</details>
		<details>
			<summary>Làm thế nào để xác nhận còn xe và đặt thuê?</summary>
			<p>Gọi <a href="<?php echo esc_url( nt_tel_url() ); ?>"><?php echo esc_html( nt_business( 'phone_display' ) ); ?></a> hoặc gửi lịch thuê qua Zalo. Nêu dòng xe, ngày nhận/trả và địa điểm dự kiến; chỉ chốt sau khi đã rõ giá, cọc, giấy tờ và giao nhận. Danh mục xe trên website không phải xác nhận xe còn sẵn theo thời gian thực.</p>
		</details>
	</div>
</section>
