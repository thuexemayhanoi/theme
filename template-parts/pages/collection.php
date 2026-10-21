<?php
/**
 * Text-first rental-period hub. Real term permalinks replace decorative image links.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_periods = array(
	array( 'slug' => 'thue-ngay', 'title' => 'Thuê xe máy theo ngày', 'text' => 'Chuyến đi ngắn, lịch trình linh hoạt. Xác nhận giá hiện tại và thời gian nhận, trả xe.' ),
	array( 'slug' => 'thue-tuan', 'title' => 'Thuê xe máy theo tuần', 'text' => 'Lưu trú và di chuyển nhiều ngày. Xác nhận mẫu xe, giá cho cả kỳ thuê và tiền cọc.' ),
	array( 'slug' => 'thue-thang', 'title' => 'Thuê xe máy theo tháng', 'text' => 'Sử dụng dài ngày tại Hà Nội. Xác nhận giá theo mẫu xe, điều kiện thuê và hoàn cọc.' ),
);
?>
<div class="nt-service-page nt-collection-page">
	<section class="nt-service-page__intro" aria-labelledby="nt-collection-heading">
		<p class="nt-service-page__eyebrow">Thuê theo thời gian · <?php echo esc_html( nt_business( 'short_name' ) ); ?></p>
		<h1 id="nt-collection-heading"><?php echo esc_html( $args['heading'] ); ?></h1>
		<p class="nt-service-page__lead">Chọn hình thức thuê xe máy theo ngày, tuần hoặc tháng, rồi xác nhận mẫu xe, giá hiện tại, tiền cọc và cách nhận xe trước khi đặt.</p>
		<div class="nt-service-page__actions"><?php nt_cta_buttons(); ?></div>
	</section>
	<section class="nt-service-page__section" aria-labelledby="nt-collection-prices">
		<h2 id="nt-collection-prices">Bảng giá thuê xe máy Hà Nội</h2>
		<p>Chọn thời gian thuê phù hợp để xem thông tin và bảng giá tương ứng.</p>
		<div class="nt-service-page__grid nt-service-page__grid--3">
			<?php foreach ( $nt_periods as $nt_period ) : ?>
				<div class="nt-service-page__card">
					<h3><?php echo esc_html( $nt_period['title'] ); ?></h3>
					<p><?php echo esc_html( $nt_period['text'] ); ?></p>
					<?php $nt_url = nt_featured_term_url( $nt_period['slug'] ); ?>
					<?php if ( '' !== $nt_url ) : ?><a class="nt-btn nt-btn--ghost" href="<?php echo esc_url( $nt_url ); ?>">Xem bảng giá</a><?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<section class="nt-service-page__section" aria-labelledby="nt-collection-choose">
		<h2 id="nt-collection-choose">Chọn hình thức thuê xe máy phù hợp</h2>
		<p>Thời gian lưu trú và lịch trình thực tế giúp bạn chọn gói thuê. Giá và tình trạng xe cần được xác nhận theo mẫu xe và thời điểm đặt.</p>
		<table><thead><tr><th scope="col">Hình thức thuê</th><th scope="col">Phù hợp với</th><th scope="col">Nên xác nhận trước khi đặt</th></tr></thead><tbody>
			<tr><th scope="row">Theo ngày</th><td>Chuyến đi ngắn, lịch trình linh hoạt</td><td>Xe còn sẵn, giá hiện tại, giờ nhận và trả</td></tr>
			<tr><th scope="row">Theo tuần</th><td>Lưu trú và di chuyển nhiều ngày</td><td>Mẫu xe, giá theo thời gian thuê, tiền cọc</td></tr>
			<tr><th scope="row">Theo tháng</th><td>Sử dụng dài ngày tại Hà Nội</td><td>Xe phù hợp, giá hiện tại, điều kiện thuê và hoàn cọc</td></tr>
		</tbody></table>
	</section>
	<section class="nt-service-page__section" aria-labelledby="nt-collection-check">
		<h2 id="nt-collection-check">Thông tin cần xác nhận trước khi thuê</h2>
		<p>Xác nhận cùng lúc các thông tin quan trọng để hạn chế phát sinh. Nếu lịch trình thay đổi, hãy báo sớm để thống nhất lại thời gian thuê và cách giao nhận.</p>
		<ul><li><strong>Mẫu xe:</strong> đúng dòng xe hoặc mẫu xe mong muốn, còn sẵn tại thời điểm đặt.</li><li><strong>Thời gian:</strong> ngày bắt đầu và thời điểm trả xe.</li><li><strong>Chi phí:</strong> giá thuê, tiền cọc, điều kiện hoàn cọc và khoản phát sinh nếu có.</li><li><strong>Nhận và trả:</strong> địa điểm, thời gian và phương án giao nhận.</li></ul>
	</section>
	<section class="nt-service-page__section" aria-labelledby="nt-collection-process">
		<h2 id="nt-collection-process">Quy trình đặt thuê nhanh</h2>
		<ol><li>Chọn thời gian thuê theo ngày, tuần hoặc tháng.</li><li>Chọn mẫu xe phù hợp nhu cầu di chuyển.</li><li>Liên hệ để xác nhận xe còn sẵn, giá hiện tại, tiền cọc và điểm nhận, trả.</li><li>Thống nhất thông tin thuê trước khi nhận xe.</li></ol>
	</section>
	<section class="nt-service-page__closing" aria-labelledby="nt-collection-faq">
		<h2 id="nt-collection-faq">Câu hỏi nhanh về thuê xe máy</h2>
		<h3>Nên thuê theo ngày hay tuần?</h3><p>Chọn theo thời gian lưu trú và lịch trình thực tế.</p>
		<h3>Có thể chọn loại xe trước không?</h3><p>Có thể trao đổi mẫu xe mong muốn; tình trạng còn sẵn cần được xác nhận tại thời điểm đặt.</p>
		<h3>Cần xác nhận gì trước khi nhận xe?</h3><p>Giá, tiền cọc, thời gian thuê và phương án nhận, trả xe.</p>
		<a class="nt-btn nt-btn--call" href="<?php echo esc_url( get_permalink( 372 ) ); ?>">Liên hệ tư vấn thuê xe</a>
	</section>
</div>
