<?php
/**
 * Quick facts: opening hours, vehicle types and the price/deposit confirmation promise. Printed inside the
 * native hero, or as a strip under authored content (see nt_home_facts_placement()).
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;
?>
<ul class="nt-hero__facts">
	<li><?php nt_the_icon( 'clock', array( 'size' => 18 ) ); ?>Mở cửa <?php echo esc_html( nt_hours_range() ); ?> mỗi ngày</li>
	<li><?php nt_the_icon( 'motorbike', array( 'size' => 18 ) ); ?>Xe số &middot; Xe ga &middot; Xe điện</li>
	<li><?php nt_the_icon( 'shield', array( 'size' => 18 ) ); ?>Xác nhận giá và cọc trước khi ký</li>
</ul>
