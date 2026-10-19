<?php
/**
 * Four-column footer, with links driven by the existing WordPress primary menu.
 * Changes in WordPress menus automatically propagate; no URL or menu data is rewritten.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_primary_menu = nt_resolve_menu_id( 'primary' );
$nt_footer_menu  = nt_resolve_menu_id( 'footer' );
$nt_tagline      = nt_footer_tagline();

$nt_items = $nt_primary_menu > 0 ? wp_get_nav_menu_items( $nt_primary_menu ) : array();
$nt_items = is_array( $nt_items ) ? $nt_items : array();
$nt_by_id = array();
foreach ( $nt_items as $nt_item ) {
	$nt_by_id[ (int) $nt_item->ID ] = $nt_item;
}

$nt_groups      = array();
$nt_counts      = array(
	'rental' => 0,
	'blog'   => 0,
	'info'   => 0,
);
$nt_contact_id  = 0;
$nt_contact_url = '';

foreach ( $nt_items as $nt_item ) {
	// Classify by the top-level menu item so descendants stay with their parent.
	$nt_root = $nt_item;
	for ( $nt_depth = 0; $nt_depth < 32; ++$nt_depth ) {
		$nt_parent_id = (int) $nt_root->menu_item_parent;
		if ( 0 === $nt_parent_id || ! isset( $nt_by_id[ $nt_parent_id ] ) || (int) $nt_root->ID === $nt_parent_id ) {
			break;
		}
		$nt_root = $nt_by_id[ $nt_parent_id ];
	}
	$nt_path  = trailingslashit( (string) wp_parse_url( (string) $nt_root->url, PHP_URL_PATH ) );
	$nt_group = 'info';
	if (
		( 'taxonomy' === $nt_root->type && in_array( $nt_root->object, array( 'product_cat', 'featured_item_category' ), true ) ) ||
		str_starts_with( $nt_path, '/bo-suu-tap/' ) ||
		str_starts_with( $nt_path, '/danh-muc-san-pham/' ) ||
		str_starts_with( $nt_path, '/san-pham/' )
	) {
		$nt_group = 'rental';
	} elseif (
		( 'taxonomy' === $nt_root->type && 'category' === $nt_root->object ) ||
		str_starts_with( $nt_path, '/blog/' )
	) {
		$nt_group = 'blog';
	}
	$nt_groups[ (int) $nt_item->ID ] = $nt_group;

	if (
		0 === (int) $nt_item->menu_item_parent &&
		( ( 'post_type' === $nt_item->type && 'page' === $nt_item->object && 372 === (int) $nt_item->object_id ) ||
			'/lien-he/' === $nt_path )
	) {
		$nt_contact_id  = (int) $nt_item->ID;
		$nt_contact_url = (string) $nt_item->url;
	}
}
foreach ( $nt_groups as $nt_item_id => $nt_group ) {
	if ( $nt_item_id !== $nt_contact_id ) {
		++$nt_counts[ $nt_group ];
	}
}
if ( '' === $nt_contact_url && 'publish' === get_post_status( 372 ) ) {
	$nt_contact_url = (string) get_permalink( 372 );
}

// Identify pages already linked by either menu, avoiding duplicate policy links.
$nt_linked_pages = array( 372 );
foreach ( array_unique( array_filter( array( $nt_primary_menu, $nt_footer_menu ) ) ) as $nt_menu_id ) {
	$nt_menu_items = wp_get_nav_menu_items( $nt_menu_id );
	foreach ( is_array( $nt_menu_items ) ? $nt_menu_items : array() as $nt_item ) {
		if ( 'post_type' === $nt_item->type && 'page' === $nt_item->object ) {
			$nt_linked_pages[] = (int) $nt_item->object_id;
		}
	}
}
$nt_linked_pages = array_unique( $nt_linked_pages );
$nt_info_pages   = array();
foreach ( array( 310, 484, 326, 449 ) as $nt_page_id ) {
	if ( ! in_array( $nt_page_id, $nt_linked_pages, true ) && 'publish' === get_post_status( $nt_page_id ) ) {
		$nt_info_pages[] = array(
			'label' => get_the_title( $nt_page_id ),
			'url'   => (string) get_permalink( $nt_page_id ),
		);
	}
}

// Filter only these footer copies of primary navigation, never header or mobile.
$nt_active_group  = '';
$nt_footer_filter = static function ( $items, $args ) use ( &$nt_active_group, $nt_groups, $nt_contact_id ) {
	if ( ! str_starts_with( (string) ( $args->menu_id ?? '' ), 'nt-footer-group-' ) ) {
		return $items;
	}
	return array_values(
		array_filter(
			$items,
			static function ( $item ) use ( $nt_active_group, $nt_groups, $nt_contact_id ): bool {
				return (int) $item->ID !== $nt_contact_id &&
					( $nt_groups[ (int) $item->ID ] ?? '' ) === $nt_active_group;
			}
		)
	);
};
?>
<div class="nt-footer__grid">
	<section class="nt-footer__column nt-footer__column--brand" aria-labelledby="nt-f-brand">
		<p id="nt-f-brand" class="nt-footer__brand"><?php echo esc_html( nt_business( 'brand' ) ); ?></p>
		<p class="nt-footer__tagline"><?php echo esc_html( $nt_tagline ); ?></p>
		<nav class="nt-footer__social" aria-label="Mạng xã hội và nhắn tin Nguyễn Tú">
			<?php $nt_facebook = (string) nt_business( 'facebook_url' ); ?>
			<?php if ( '' !== $nt_facebook ) : ?>
				<a href="<?php echo esc_url( $nt_facebook ); ?>" target="_blank" rel="noopener noreferrer">Facebook</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( nt_zalo_url() ); ?>" target="_blank" rel="noopener noreferrer">Zalo</a>
			<a href="<?php echo esc_url( nt_whatsapp_url() ); ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
		</nav>
	</section>
	<section class="nt-footer__column nt-footer__column--rental" aria-labelledby="nt-f-rental">
		<h2 id="nt-f-rental" class="nt-footer__title">Thuê xe &amp; bảng giá</h2>
		<?php if ( $nt_primary_menu > 0 && $nt_counts['rental'] > 0 ) : ?>
			<nav aria-label="Thuê xe và bảng giá">
				<?php
				$nt_active_group = 'rental';
				add_filter( 'wp_nav_menu_objects', $nt_footer_filter, 20, 2 );
				nt_render_menu(
					'primary',
					array(
						'walker'     => '',
						'menu_class' => 'nt-footer__menu',
						'menu_id'    => 'nt-footer-group-rental',
						'depth'      => 0,
					)
				);
				remove_filter( 'wp_nav_menu_objects', $nt_footer_filter, 20 );
				?>
			</nav>
		<?php elseif ( 0 === $nt_primary_menu ) : ?>
			<ul class="nt-footer__menu">
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
			</ul>
		<?php endif; ?>
	</section>
	<section class="nt-footer__column nt-footer__column--blog" aria-labelledby="nt-f-blog">
		<h2 id="nt-f-blog" class="nt-footer__title">Cẩm nang &amp; Blog</h2>
		<?php if ( $nt_primary_menu > 0 && $nt_counts['blog'] > 0 ) : ?>
			<nav aria-label="Cẩm nang và chuyên mục Blog">
				<?php
				$nt_active_group = 'blog';
				add_filter( 'wp_nav_menu_objects', $nt_footer_filter, 20, 2 );
				nt_render_menu(
					'primary',
					array(
						'walker'     => '',
						'menu_class' => 'nt-footer__menu',
						'menu_id'    => 'nt-footer-group-blog',
						'depth'      => 0,
					)
				);
				remove_filter( 'wp_nav_menu_objects', $nt_footer_filter, 20 );
				?>
			</nav>
		<?php elseif ( 0 === $nt_primary_menu ) : ?>
			<ul class="nt-footer__menu">
				<?php foreach ( nt_blog_hubs( 7 ) as $nt_hub ) : ?>
					<?php $nt_hub_url = get_term_link( $nt_hub ); ?>
					<?php if ( ! is_wp_error( $nt_hub_url ) ) : ?>
						<li><a href="<?php echo esc_url( $nt_hub_url ); ?>"><?php echo esc_html( $nt_hub->name ); ?></a></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
	<section class="nt-footer__column nt-footer__column--contact" aria-labelledby="nt-f-contact">
		<h2 id="nt-f-contact" class="nt-footer__title">
			<?php if ( '' !== $nt_contact_url ) : ?>
				<a href="<?php echo esc_url( $nt_contact_url ); ?>">Liên hệ</a>
			<?php else : ?>
				Liên hệ
			<?php endif; ?>
		</h2>
		<?php get_template_part( 'template-parts/footer/contact' ); ?>
		<?php if ( $nt_counts['info'] > 0 || $nt_footer_menu > 0 || array() !== $nt_info_pages ) : ?>
			<nav class="nt-footer__info" aria-label="Trang chủ, giới thiệu và chính sách">
				<?php if ( $nt_primary_menu > 0 && $nt_counts['info'] > 0 ) : ?>
					<?php
					$nt_active_group = 'info';
					add_filter( 'wp_nav_menu_objects', $nt_footer_filter, 20, 2 );
					nt_render_menu(
						'primary',
						array(
							'walker'     => '',
							'menu_class' => 'nt-footer__menu',
							'menu_id'    => 'nt-footer-group-info',
							'depth'      => 0,
						)
					);
					remove_filter( 'wp_nav_menu_objects', $nt_footer_filter, 20 );
					?>
				<?php endif; ?>
				<?php if ( $nt_footer_menu > 0 && $nt_footer_menu !== $nt_primary_menu ) : ?>
					<?php
					nt_render_menu(
						'footer',
						array(
							'walker'     => '',
							'menu_class' => 'nt-footer__list',
							'menu_id'    => 'nt-footer-extra-menu',
							'depth'      => 1,
						)
					);
					?>
				<?php endif; ?>
				<?php if ( array() !== $nt_info_pages ) : ?>
					<ul class="nt-footer__list">
						<?php foreach ( $nt_info_pages as $nt_link ) : ?>
							<li><a href="<?php echo esc_url( $nt_link['url'] ); ?>"><?php echo esc_html( $nt_link['label'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</section>
</div>
