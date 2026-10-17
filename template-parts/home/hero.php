<?php
/**
 * Native home hero. Used only when the page content has no <h1> of its own; the H1 text then is
 * the page's own title (never a rewritten keyword string).
 *
 * Args: h1 (string), lead (string), facts (bool).
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_h1    = (string) ( $args['h1'] ?? get_the_title() );
$nt_lead  = trim( (string) ( $args['lead'] ?? '' ) );
$nt_rows  = nt_price_rows();
$nt_day   = nt_price_column_min( $nt_rows, 'day' );
$nt_week  = nt_price_column_min( $nt_rows, 'week' );
$nt_month = nt_price_column_min( $nt_rows, 'month' );
if ( '' === $nt_lead ) {
	$nt_lead = 'Thuê xe số, xe ga, xe 50cc, xe máy điện và xe đạp điện tại ' . nt_address( false ) . '. Giá thuê, tiền cọc và thủ tục được xác nhận rõ ràng trước khi ký hợp đồng.';
}
?>
<section class="nt-hero" aria-labelledby="nt-hero-title">
	<div class="nt-container nt-hero__grid">
		<div class="nt-hero__copy">
			<p class="nt-eyebrow">Cho thuê xe máy Hà Nội &middot; Long Biên</p>
			<h1 id="nt-hero-title" class="nt-hero__title"><?php echo esc_html( $nt_h1 ); ?></h1>
			<p class="nt-hero__lead"><?php echo esc_html( $nt_lead ); ?></p>
			<?php nt_cta_buttons( array( 'size' => 'lg', 'class' => 'nt-hero__cta' ) ); ?>
			<?php if ( ! empty( $args['facts'] ) ) : ?>
				<ul class="nt-hero__facts">
					<li><?php nt_the_icon( 'clock', array( 'size' => 18 ) ); ?>Mở cửa <?php echo esc_html( nt_hours_range() ); ?> mỗi ngày</li>
					<li><?php nt_the_icon( 'motorbike', array( 'size' => 18 ) ); ?>Xe số &middot; Xe ga &middot; Xe điện</li>
					<li><?php nt_the_icon( 'shield', array( 'size' => 18 ) ); ?>Xác nhận giá và cọc trước khi ký</li>
				</ul>
			<?php endif; ?>
		</div>

		<aside class="nt-hero__card" aria-label="Giá thuê tham khảo">
			<p class="nt-hero__card-title">Giá thuê tham khảo</p>
			<ul class="nt-hero__prices">
				<?php if ( null !== $nt_day ) : ?>
					<li><span>Theo ngày</span><strong>từ <?php echo esc_html( nt_format_vnd( $nt_day ) ); ?></strong></li>
				<?php endif; ?>
				<?php if ( null !== $nt_week ) : ?>
					<li><span>Theo tuần</span><strong>từ <?php echo esc_html( nt_format_vnd( $nt_week ) ); ?></strong></li>
				<?php endif; ?>
				<?php if ( null !== $nt_month ) : ?>
					<li><span>Theo tháng</span><strong>từ <?php echo esc_html( nt_format_vnd( $nt_month ) ); ?></strong></li>
				<?php endif; ?>
			</ul>
			<p class="nt-note"><?php echo esc_html( nt_policy()['price_note'] ); ?></p>
			<span class="nt-hero__art" aria-hidden="true"><?php nt_the_icon( 'scooter', array( 'size' => 96 ) ); ?></span>
		</aside>
	</div>
</section>
