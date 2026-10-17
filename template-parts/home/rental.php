<?php
/**
 * Rental period cards (day / week / month). Links point only at featured-item terms that exist.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_options = nt_home_rental_options();
?>
<section class="nt-section nt-section--tint" aria-labelledby="nt-rental-title">
	<div class="nt-container">
		<header class="nt-section__head">
			<p class="nt-eyebrow">Thuê linh hoạt</p>
			<h2 id="nt-rental-title" class="nt-section__title">Chọn thời gian thuê xe máy</h2>
			<p class="nt-section__lead">Giá bên dưới là mức tham khảo thấp nhất theo từng gói; giá cụ thể tùy dòng xe và được xác nhận trước khi đặt.</p>
		</header>
		<ul class="nt-cards nt-cards--3">
			<?php foreach ( $nt_options as $nt_opt ) : ?>
				<li class="nt-feature">
					<span class="nt-feature__icon" aria-hidden="true"><?php nt_the_icon( $nt_opt['icon'], array( 'size' => 28 ) ); ?></span>
					<h3 class="nt-feature__title">
						<?php if ( '' !== $nt_opt['url'] ) : ?>
							<a class="nt-feature__link" href="<?php echo esc_url( $nt_opt['url'] ); ?>"><?php echo esc_html( $nt_opt['label'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $nt_opt['label'] ); ?>
						<?php endif; ?>
					</h3>
					<?php if ( '' !== $nt_opt['from'] ) : ?>
						<p class="nt-feature__price">từ <strong><?php echo esc_html( $nt_opt['from'] ); ?></strong> / <?php echo esc_html( $nt_opt['unit'] ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
