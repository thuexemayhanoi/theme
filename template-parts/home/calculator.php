<?php
/**
 * Theme-owned estimate, sharing the displayed price table without a separate plugin release.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_calculator_id   = wp_unique_id( 'nt-rental-' );
$nt_calculator_rows = $args['rows'] ?? nt_price_rows();
?>
<section class="ntsc-rental" data-nt-native-rental aria-label="Tính giá thuê xe máy tham khảo">
	<h3>Ước tính tiền thuê xe</h3>
	<div class="ntsc-rental__fields">
		<label for="<?php echo esc_attr( $nt_calculator_id . '-vehicle' ); ?>">Dòng xe
			<select id="<?php echo esc_attr( $nt_calculator_id . '-vehicle' ); ?>" data-nt-rental-vehicle>
				<?php foreach ( $nt_calculator_rows as $nt_calculator_row ) : ?>
					<?php
					$nt_day  = (string) ( $nt_calculator_row['day'] ?? '' );
					$nt_rate = preg_match( '/^\s*[\d.,]+\s*(?:đ|₫|vnđ|vnd)\s*$/iu', $nt_day ) ? nt_price_cell_min( $nt_day ) : null;
					$nt_name = (string) ( $nt_calculator_row['name'] ?? '' );
					?>
					<option value="<?php echo esc_attr( $nt_name ); ?>"
						data-day-rate="<?php echo esc_attr( null === $nt_rate ? '' : (string) $nt_rate ); ?>"
						data-week-price="<?php echo esc_attr( (string) ( $nt_calculator_row['week'] ?? '' ) ); ?>"
						data-month-price="<?php echo esc_attr( (string) ( $nt_calculator_row['month'] ?? '' ) ); ?>"
						data-deposit="<?php echo esc_attr( (string) ( $nt_calculator_row['deposit'] ?? '' ) ); ?>"><?php echo esc_html( $nt_name . ' — ' . $nt_day ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label for="<?php echo esc_attr( $nt_calculator_id . '-days' ); ?>">Số ngày dự kiến
			<input id="<?php echo esc_attr( $nt_calculator_id . '-days' ); ?>" data-nt-rental-days type="number" min="1" max="30" value="1" step="1" inputmode="numeric">
		</label>
	</div>
	<p class="ntsc-rental__result" data-nt-rental-result role="status" aria-live="polite">Xem giá tham khảo trong bảng; tiền cọc được tính riêng.</p>
	<p class="ntsc-rental__note">1–6 ngày tính theo giá ngày; 7 ngày theo giá tuần; 30 ngày theo giá tháng. Kỳ thuê khác, tiền cọc và phí giao nhận cần cửa hàng xác nhận.</p>
	<noscript><p>Cần bật JavaScript để ước tính; bảng giá phía trên luôn có sẵn.</p></noscript>
	<a class="ntsc-rental__link" href="<?php echo esc_url( nt_zalo_url() ); ?>" target="_blank" rel="noopener noreferrer">Nhắn Zalo xác nhận giá</a>
</section>
