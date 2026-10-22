<?php
/**
 * Reference price table. Data comes from the admin-editable price table (defaults = the shop's
 * published reference prices). Electric bikes without a published price show "Liên hệ".
 * Below 640px each row becomes a labelled card, so the table never scrolls sideways.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_rows   = nt_price_rows();
$nt_policy = nt_policy();
?>
<section class="nt-section" id="bang-gia-tham-khao" aria-labelledby="nt-prices-title">
	<div class="nt-container">
		<header class="nt-section__head">
			<p class="nt-eyebrow">Bảng giá</p>
			<h2 id="nt-prices-title" class="nt-section__title">Bảng giá thuê xe máy tham khảo</h2>
			<p class="nt-section__lead"><?php echo esc_html( $nt_policy['price_note'] ); ?></p>
		</header>

		<table class="nt-table nt-table--prices">
			<caption class="screen-reader-text">Bảng giá thuê xe máy theo ngày, tuần, tháng và tiền đặt cọc</caption>
			<thead>
				<tr>
					<th scope="col">Loại xe</th>
					<th scope="col">Theo ngày</th>
					<th scope="col">Theo tuần</th>
					<th scope="col">Theo tháng</th>
					<th scope="col">Tiền cọc</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $nt_rows as $nt_row ) : ?>
					<tr>
						<th scope="row" data-label="Loại xe"><?php echo esc_html( $nt_row['name'] ); ?></th>
						<td data-label="Theo ngày"><?php echo esc_html( $nt_row['day'] ); ?></td>
						<td data-label="Theo tuần"><?php echo esc_html( $nt_row['week'] ); ?></td>
						<td data-label="Theo tháng"><?php echo esc_html( $nt_row['month'] ); ?></td>
						<td data-label="Tiền cọc"><?php echo esc_html( $nt_row['deposit'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p>Xem <a href="<?php echo esc_url( get_permalink( 476 ) ); ?>">giá thuê và các khoản cần dự trù</a> để tách tiền thuê với tiền cọc. Với nhu cầu dài ngày, đối chiếu <a href="<?php echo esc_url( get_permalink( 1060 ) ); ?>">giá và điều kiện thuê xe theo tháng</a> trước khi chọn kỳ thuê.</p>

		<?php
		// Own this component in the theme so a stale, separately installed plugin cannot quote daily multiples.
		remove_action( 'nt_home_prices_after_table', array( 'NT_Smart_Rental', 'echo_calculator' ) );
		get_template_part( 'template-parts/home/calculator', null, array( 'rows' => $nt_rows ) );
		do_action( 'nt_home_prices_after_table' );
		?>

		<div class="nt-policybox">
			<h3 class="nt-policybox__title"><?php nt_the_icon( 'wallet', array( 'size' => 22 ) ); ?>Chính sách đặt cọc</h3>
			<ul class="nt-checklist">
				<li><?php echo esc_html( $nt_policy['deposit_range'] ); ?></li>
				<li><?php echo esc_html( $nt_policy['documents'] ); ?></li>
				<li><?php echo esc_html( $nt_policy['confirm'] ); ?></li>
				<li><?php echo esc_html( $nt_policy['refund'] ); ?></li>
			</ul>
		</div>
	</div>
</section>
