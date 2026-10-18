<?php
/**
 * Footer columns. Links are built from real, existing objects only (published pages, existing
 * terms); a link to something that does not exist is simply not rendered.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_info_pages = array();
foreach ( array( 310, 372, 484, 326, 449 ) as $nt_page_id ) {
	if ( 'publish' === get_post_status( $nt_page_id ) ) {
		$nt_info_pages[] = array(
			'label' => get_the_title( $nt_page_id ),
			'url'   => (string) get_permalink( $nt_page_id ),
		);
	}
}

$nt_price_links = array();
foreach ( array( 'bang-gia', 'thue-ngay', 'thue-tuan', 'thue-thang' ) as $nt_slug ) {
	$nt_url  = nt_featured_term_url( $nt_slug );
	$nt_term = '' !== $nt_url ? get_term_by( 'slug', $nt_slug, 'featured_item_category' ) : false;
	if ( $nt_term instanceof WP_Term ) {
		$nt_price_links[] = array(
			'label' => $nt_term->name,
			'url'   => $nt_url,
		);
	}
}
$nt_vehicle_links = nt_home_vehicle_categories();
$nt_hubs          = nt_blog_hubs( 7 );
$nt_footer_menu   = nt_resolve_menu_id( 'footer' );
?>
<div class="nt-footer__grid">
	<section class="nt-footer__col nt-footer__col--brand" aria-labelledby="nt-f-brand">
		<h2 id="nt-f-brand" class="nt-footer__title"><?php echo esc_html( nt_business( 'brand' ) ); ?></h2>
		<address class="nt-footer__nap">
			<p><?php nt_the_icon( 'pin', array( 'size' => 18 ) ); ?><span><?php echo esc_html( nt_address() ); ?></span></p>
			<p><?php nt_the_icon( 'phone', array( 'size' => 18 ) ); ?><a href="<?php echo esc_url( nt_tel_url() ); ?>"><?php echo esc_html( nt_business( 'phone_display' ) ); ?></a> <span class="nt-footer__muted">(gọi / Zalo)</span></p>
			<p><?php nt_the_icon( 'clock', array( 'size' => 18 ) ); ?><?php echo nt_hours_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes. ?></p>
		</address>
		<?php
		nt_cta_buttons(
			array(
				'size'       => 'sm',
				'call_label' => 'Gọi ngay',
			)
		);
		?>
	</section>

	<?php if ( array() !== $nt_price_links || array() !== $nt_vehicle_links ) : ?>
		<nav class="nt-footer__col" aria-labelledby="nt-f-rent">
			<h2 id="nt-f-rent" class="nt-footer__title">Thuê xe &amp; bảng giá</h2>
			<ul class="nt-footer__list">
				<?php foreach ( $nt_price_links as $nt_link ) : ?>
					<li><a href="<?php echo esc_url( $nt_link['url'] ); ?>"><?php echo esc_html( $nt_link['label'] ); ?></a></li>
				<?php endforeach; ?>
				<?php foreach ( $nt_vehicle_links as $nt_link ) : ?>
					<li><a href="<?php echo esc_url( $nt_link['url'] ); ?>"><?php echo esc_html( $nt_link['name'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>

	<?php if ( array() !== $nt_hubs ) : ?>
		<nav class="nt-footer__col" aria-labelledby="nt-f-blog">
			<h2 id="nt-f-blog" class="nt-footer__title">Cẩm nang &amp; blog</h2>
			<ul class="nt-footer__list">
				<?php foreach ( $nt_hubs as $nt_hub ) : ?>
					<li><a href="<?php echo esc_url( (string) get_term_link( $nt_hub ) ); ?>"><?php echo esc_html( html_entity_decode( $nt_hub->name, ENT_QUOTES, 'UTF-8' ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>

	<nav class="nt-footer__col" aria-labelledby="nt-f-info">
		<h2 id="nt-f-info" class="nt-footer__title">Thông tin</h2>
		<?php
		if ( $nt_footer_menu > 0 ) {
			nt_render_menu(
				'footer',
				array(
					'walker'     => '',
					'menu_class' => 'nt-footer__list',
					'depth'      => 1,
				)
			);
		} else {
			?>
			<ul class="nt-footer__list">
				<?php foreach ( $nt_info_pages as $nt_link ) : ?>
					<li><a href="<?php echo esc_url( $nt_link['url'] ); ?>"><?php echo esc_html( $nt_link['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
			<?php
		}
		?>
	</nav>
</div>
