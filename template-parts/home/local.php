<?php
/**
 * Local business card: verified NAP, hours, contact buttons and a click-to-load map (no iframe,
 * no third-party request until the visitor asks for it).
 *
 * Args: title, heading_id.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_title = (string) ( $args['title'] ?? 'Địa chỉ và giờ mở cửa' );
$nt_hid   = (string) ( $args['heading_id'] ?? 'nt-local-title' );
?>
<section class="nt-section nt-local" aria-labelledby="<?php echo esc_attr( $nt_hid ); ?>">
	<div class="nt-container">
		<div class="nt-local__grid">
			<div class="nt-local__info">
				<p class="nt-eyebrow">Cửa hàng</p>
				<h2 id="<?php echo esc_attr( $nt_hid ); ?>" class="nt-section__title"><?php echo esc_html( $nt_title ); ?></h2>
				<address class="nt-local__nap">
					<p class="nt-local__name"><?php echo esc_html( nt_business( 'brand' ) ); ?></p>
					<p><?php nt_the_icon( 'pin', array( 'size' => 20 ) ); ?><span><?php echo esc_html( nt_address() ); ?></span></p>
					<p><?php nt_the_icon( 'phone', array( 'size' => 20 ) ); ?><span><a href="<?php echo esc_url( nt_tel_url() ); ?>"><?php echo esc_html( nt_business( 'phone_display' ) ); ?></a> (gọi / Zalo)</span></p>
					<p><?php nt_the_icon( 'clock', array( 'size' => 20 ) ); ?><span>Giờ thường lệ <?php echo esc_html( nt_hours_range() ); ?>. <?php echo nt_hours_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes. ?></span></p>
				</address>
				<?php nt_cta_buttons( array( 'size' => 'md' ) ); ?>
			</div>
			<div class="nt-map" data-nt-map data-src="<?php echo esc_url( nt_maps_embed_url() ); ?>">
				<div class="nt-map__facade">
					<?php nt_the_icon( 'map', array( 'size' => 44 ) ); ?>
					<p class="nt-map__addr"><?php echo esc_html( nt_address( false ) ); ?></p>
					<button type="button" class="nt-btn nt-btn--primary nt-btn--md" data-nt-map-load hidden>Xem bản đồ</button>
					<a class="nt-btn nt-btn--ghost nt-btn--md" href="<?php echo esc_url( nt_maps_url() ); ?>" target="_blank" rel="noopener" data-nt-map-link>Mở Google Maps</a>
				</div>
			</div>
		</div>
	</div>
</section>
