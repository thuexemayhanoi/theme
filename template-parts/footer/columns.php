<?php
/**
 * Shared footer navigation, driven by the existing WordPress menus.
 *
 * Primary menu links (including child levels) are never hard-coded, copied or
 * written back to the database. Editing a menu in WordPress updates every
 * page's footer on the next uncached request.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_primary_menu = nt_resolve_menu_id( 'primary' );
$nt_footer_menu  = nt_resolve_menu_id( 'footer' );
$nt_tagline      = trim( (string) get_bloginfo( 'description', 'display' ) );
if ( '' === $nt_tagline ) {
	$nt_tagline = 'Thuê xe máy tại Hà Nội theo ngày, tuần, tháng; xe số, xe ga, xe 50cc và xe điện.';
}

// Avoid repeating links to pages already included in either existing menu.
$nt_linked_pages = array();
foreach ( array_unique( array_filter( array( $nt_primary_menu, $nt_footer_menu ) ) ) as $nt_menu_id ) {
	$nt_items = wp_get_nav_menu_items( $nt_menu_id );
	foreach ( is_array( $nt_items ) ? $nt_items : array() as $nt_item ) {
		if ( 'post_type' === $nt_item->type && 'page' === $nt_item->object ) {
			$nt_linked_pages[] = (int) $nt_item->object_id;
		}
	}
}
$nt_linked_pages = array_unique( $nt_linked_pages );

$nt_info_pages = array();
foreach ( array( 310, 372, 484, 326, 449 ) as $nt_page_id ) {
	if ( ! in_array( $nt_page_id, $nt_linked_pages, true ) && 'publish' === get_post_status( $nt_page_id ) ) {
		$nt_info_pages[] = array(
			'label' => get_the_title( $nt_page_id ),
			'url'   => (string) get_permalink( $nt_page_id ),
		);
	}
}
?>
<div class="nt-footer__intro">
	<section class="nt-footer__col nt-footer__col--brand" aria-labelledby="nt-f-brand">
		<h2 id="nt-f-brand" class="nt-footer__brand"><?php echo esc_html( nt_business( 'brand' ) ); ?></h2>
		<p class="nt-footer__tagline"><?php echo esc_html( $nt_tagline ); ?></p>
		<address class="nt-footer__nap">
			<p><?php nt_the_icon( 'pin', array( 'size' => 18 ) ); ?><span><?php echo esc_html( nt_address() ); ?></span></p>
			<p><?php nt_the_icon( 'phone', array( 'size' => 18 ) ); ?><a href="<?php echo esc_url( nt_tel_url() ); ?>"><?php echo esc_html( nt_business( 'phone_display' ) ); ?></a> <span class="nt-footer__muted">(gọi / Zalo)</span></p>
			<p><?php nt_the_icon( 'clock', array( 'size' => 18 ) ); ?><span>Mở cửa <?php echo esc_html( nt_hours_range() ); ?> mỗi ngày</span></p>
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
</div>

<div class="nt-footer__links">
	<?php if ( $nt_primary_menu > 0 ) : ?>
		<nav class="nt-footer__directory" aria-labelledby="nt-f-directory">
			<h2 id="nt-f-directory" class="nt-footer__title">Khám phá website</h2>
			<?php
			nt_render_menu(
				'primary',
				array(
					'walker'     => '',
					'menu_class' => 'nt-footer__menu',
					'menu_id'    => 'nt-footer-site-map',
					'depth'      => 0,
				)
			);
			?>
		</nav>
	<?php else : ?>
		<?php // Read-only fallback when no primary menu exists; never create menu items. ?>
		<nav class="nt-footer__directory" aria-labelledby="nt-f-directory">
			<h2 id="nt-f-directory" class="nt-footer__title">Thuê xe &amp; cẩm nang</h2>
			<ul class="nt-footer__list nt-footer__fallback">
				<?php foreach ( array( 'bang-gia', 'thue-ngay', 'thue-tuan', 'thue-thang' ) as $nt_slug ) : ?>
					<?php $nt_url = nt_featured_term_url( $nt_slug ); ?>
					<?php $nt_term = '' !== $nt_url ? get_term_by( 'slug', $nt_slug, 'featured_item_category' ) : false; ?>
					<?php if ( $nt_term instanceof WP_Term ) : ?>
						<li><a href="<?php echo esc_url( $nt_url ); ?>"><?php echo esc_html( $nt_term->name ); ?></a></li>
					<?php endif; ?>
				<?php endforeach; ?>
				<?php foreach ( nt_home_vehicle_categories() as $nt_link ) : ?>
					<li><a href="<?php echo esc_url( $nt_link['url'] ); ?>"><?php echo esc_html( $nt_link['name'] ); ?></a></li>
				<?php endforeach; ?>
				<?php foreach ( nt_blog_hubs( 7 ) as $nt_hub ) : ?>
					<?php $nt_hub_url = get_term_link( $nt_hub ); ?>
					<?php if ( ! is_wp_error( $nt_hub_url ) ) : ?>
						<li><a href="<?php echo esc_url( $nt_hub_url ); ?>"><?php echo esc_html( html_entity_decode( $nt_hub->name, ENT_QUOTES, 'UTF-8' ) ); ?></a></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>

	<?php if ( array() !== $nt_info_pages || ( $nt_footer_menu > 0 && $nt_footer_menu !== $nt_primary_menu ) ) : ?>
		<nav class="nt-footer__support" aria-labelledby="nt-f-info">
			<h2 id="nt-f-info" class="nt-footer__title">Hỗ trợ &amp; chính sách</h2>
			<?php
			if ( $nt_footer_menu > 0 && $nt_footer_menu !== $nt_primary_menu ) {
				nt_render_menu(
					'footer',
					array(
						'walker'     => '',
						'menu_class' => 'nt-footer__list',
						'menu_id'    => 'nt-footer-extra-menu',
						'depth'      => 1,
					)
				);
			}
			?>
			<?php if ( array() !== $nt_info_pages ) : ?>
				<ul class="nt-footer__list">
					<?php foreach ( $nt_info_pages as $nt_link ) : ?>
						<li><a href="<?php echo esc_url( $nt_link['url'] ); ?>"><?php echo esc_html( $nt_link['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
</div>
