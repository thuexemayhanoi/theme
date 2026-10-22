<?php
/**
 * Compact editorial-commerce homepage inspired by the Nguyen Ha magazine hierarchy,
 * but grounded in Nguyen Tu's published WooCommerce products, prices and Blog terms.
 *
 * Only presentation changes: the original Page #562 shortcodes remain stored untouched.
 * Price calculator is injected by Smart Core through nt_home_prices_after_table.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_page      = get_post();
$nt_h1        = $nt_page instanceof WP_Post ? nt_home_editorial_h1( $nt_page ) : 'Thuê Xe Máy Hà Nội – Nguyễn Tú';
$nt_hubs      = array_values(
	array_filter(
		nt_blog_hubs( 8 ),
		static function ( $term ): bool {
			return $term instanceof WP_Term && nt_term_total( $term ) > 0;
		}
	)
);
$nt_hubs      = array_slice( $nt_hubs, 0, 6 );
$nt_blog_id   = nt_blog_root_id();
$nt_blog      = $nt_blog_id > 0 ? get_term_link( $nt_blog_id, 'category' ) : '';
$nt_featured  = new WP_Query(
	array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 4,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
$nt_shown     = array();
$nt_shortcuts = array(
	array(
		'label' => 'Thuê xe số',
		'slug'  => 'xe-so',
	),
	array(
		'label' => 'Thuê xe ga',
		'slug'  => 'xe-ga',
	),
	array(
		'label' => 'Thuê xe điện',
		'slug'  => 'xe-dien',
	),
	array(
		'label'       => 'Xe máy 50cc',
		'post_id'     => 895, // Published 50cc rental guide; resolve its real URL.
		'fallback_id' => 669, // Original 50cc Woo product; available in the legacy snapshot.
	),
);
?>
<section class="nt-home-mag__intro" aria-labelledby="nt-home-mag-title">
	<div class="nt-container nt-home-mag__intro-grid">
		<div class="nt-home-mag__intro-copy">
			<p class="nt-eyebrow">Thuê xe máy Hà Nội · Nguyễn Tú</p>
			<h1 id="nt-home-mag-title" class="nt-home-mag__title"><?php echo esc_html( $nt_h1 ); ?></h1>
			<p class="nt-home-mag__lead">Chọn xe số, xe ga hoặc xe điện; xem bảng giá, ước tính tiền thuê và đọc cẩm nang đi xe tại Hà Nội. Giá, tiền cọc và giao nhận được xác nhận trước khi thuê.</p>
			<?php
			nt_cta_buttons(
				array(
					'size'       => 'md',
					'class'      => 'nt-home-mag__actions',
					'zalo'       => false,
					'call_label' => 'Gọi ngay',
				)
			);
			?>
			<?php if ( wp_attachment_is_image( 4990 ) ) : ?>
				<figure class="nt-home-mag__hero-visual">
					<?php echo wp_get_attachment_image( 4990, 'large', false, array(
						'class'         => 'nt-home-mag__hero-img',
						'loading'       => 'eager',
						'decoding'      => 'async',
						'fetchpriority' => 'high',
						'sizes'         => '(min-width: 900px) 55vw, 100vw',
					) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress image helper. ?>
					<figcaption>Ảnh minh họa</figcaption>
				</figure>
			<?php endif; ?>
		</div>
		<div class="nt-home-mag__find">
			<p class="nt-home-mag__find-title">Bạn đang tìm gì?</p>
			<?php get_search_form(); ?>
			<nav aria-label="Lối tắt thuê xe" class="nt-home-mag__shortcuts">
				<?php
				foreach ( $nt_shortcuts as $nt_shortcut ) :
					$nt_url = '';
					if ( isset( $nt_shortcut['post_id'] ) ) {
						$nt_rental_guide = get_post( (int) $nt_shortcut['post_id'] );
						if ( $nt_rental_guide instanceof WP_Post && 'post' === $nt_rental_guide->post_type && 'publish' === $nt_rental_guide->post_status ) {
							$nt_url = get_permalink( $nt_rental_guide );
						}
						if ( ( ! is_string( $nt_url ) || '' === $nt_url ) && isset( $nt_shortcut['fallback_id'] ) ) {
							$nt_product = get_post( (int) $nt_shortcut['fallback_id'] );
							if ( $nt_product instanceof WP_Post && 'product' === $nt_product->post_type && 'publish' === $nt_product->post_status ) {
								$nt_url = get_permalink( $nt_product );
							}
						}
					} else {
						$nt_term = get_term_by( 'slug', $nt_shortcut['slug'], 'product_cat' );
						if ( $nt_term instanceof WP_Term ) {
							$nt_url = get_term_link( $nt_term );
						}
					}
					if ( ! is_string( $nt_url ) || '' === $nt_url ) {
						continue;
					}
					?>
					<a class="nt-chip nt-chip--lg" href="<?php echo esc_url( $nt_url ); ?>"><?php echo esc_html( $nt_shortcut['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>
		</div>
	</div>
</section>

<?php if ( $nt_featured->have_posts() ) : ?>
<section class="nt-section nt-home-mag__featured" aria-labelledby="nt-home-featured-title">
	<div class="nt-container">
		<header class="nt-section__head nt-section__head--row">
			<div>
				<p class="nt-eyebrow">Nội dung nổi bật</p>
				<h2 class="nt-section__title" id="nt-home-featured-title">Cẩm nang thuê và sử dụng xe</h2>
			</div>
			<?php if ( is_string( $nt_blog ) && '' !== $nt_blog ) : ?>
				<a class="nt-link-arrow" href="<?php echo esc_url( $nt_blog ); ?>">Đọc tất cả bài viết <?php nt_the_icon( 'arrow-right', array( 'size' => 18 ) ); ?></a>
			<?php endif; ?>
		</header>
		<ul class="nt-home-mag__featured-grid">
			<?php
			$nt_featured_index = 0;
			while ( $nt_featured->have_posts() ) :
				$nt_featured->the_post();
				$nt_shown[] = (int) get_the_ID();
				?>
				<li>
					<?php
					get_template_part(
						'template-parts/content/card',
						'post',
						array(
							'heading' => 'h3',
							'lead'    => 0 === $nt_featured_index,
							'eager'   => 0 === $nt_featured_index,
						)
					);
					?>
				</li>
				<?php ++$nt_featured_index; ?>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		</ul>
	</div>
</section>
<?php endif; ?>

<?php
// A live WooCommerce catalogue, NOT hardcoded product tiles or duplicate legacy shortcodes.
get_template_part( 'template-parts/home/products' );

// Retain exactly one authoritative price table and Smart Core's live estimate hook.
get_template_part( 'template-parts/home/prices' );

// Uploaded visual assets; optional and non-destructive if an attachment is later removed.
$nt_service_visuals     = array(
	4987 => 'Chọn xe và tìm hiểu điều kiện thuê',
	4978 => 'Kiểm tra và nhận xe',
);
$nt_has_service_visuals = array_filter( array_keys( $nt_service_visuals ), 'wp_attachment_is_image' );
?>
<?php if ( array() !== $nt_has_service_visuals ) : ?>
<section class="nt-home-mag__visuals" aria-label="Hình minh họa dịch vụ thuê xe">
	<div class="nt-container nt-home-mag__visual-grid">
		<?php foreach ( $nt_service_visuals as $nt_visual_id => $nt_visual_label ) : ?>
			<?php if ( ! wp_attachment_is_image( $nt_visual_id ) ) {
				continue;
			} ?>
			<figure class="nt-home-mag__visual-card">
				<?php echo wp_get_attachment_image( $nt_visual_id, 'large', false, array(
					'class'    => 'nt-home-mag__visual-img',
					'loading'  => 'lazy',
					'decoding' => 'async',
					'sizes'    => '(min-width: 900px) 48vw, 100vw',
				) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by WordPress image helper. ?>
				<figcaption><strong><?php echo esc_html( $nt_visual_label ); ?></strong><span>Ảnh AI minh họa, không phải ảnh chụp cửa hàng hoặc nhân viên.</span></figcaption>
			</figure>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>

<?php if ( array() !== $nt_hubs ) : ?>
<section class="nt-section nt-home-mag__hubs nt-section--tint" aria-labelledby="nt-home-hubs-title">
	<div class="nt-container">
		<header class="nt-section__head">
			<p class="nt-eyebrow">Khám phá theo chủ đề</p>
			<h2 id="nt-home-hubs-title" class="nt-section__title">Cẩm nang và kinh nghiệm đi xe</h2>
			<p class="nt-section__lead">Chọn một chuyên mục để đọc các hướng dẫn và kinh nghiệm phù hợp.</p>
		</header>
		<ul class="nt-home-mag__hub-grid">
			<?php
			foreach ( $nt_hubs as $nt_hub ) :
				$nt_hub_link = get_term_link( $nt_hub );
				if ( is_wp_error( $nt_hub_link ) ) {
					continue;
				}
				?>
				<li><a class="nt-home-mag__hub" href="<?php echo esc_url( $nt_hub_link ); ?>">
					<span class="nt-home-mag__hub-name"><?php echo esc_html( $nt_hub->name ); ?></span>
					<span class="nt-home-mag__hub-count"><?php echo esc_html( (string) nt_term_total( $nt_hub ) ); ?> bài viết</span>
					<?php nt_the_icon( 'arrow-right', array( 'size' => 18 ) ); ?>
				</a></li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
<?php endif; ?>

<?php
// Magazine-style topical rows: published posts from the real category and its children.
// Never repeat an article already shown in the featured or previous rows.
foreach ( array_slice( $nt_hubs, 0, 2 ) as $nt_hub ) :
	$nt_hub_posts = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => 3,
			'post__not_in'        => $nt_shown,
			'tax_query'           => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded three-item category query; excludes duplicate posts.
				array(
					'taxonomy'         => 'category',
					'field'            => 'term_id',
					'terms'            => array( (int) $nt_hub->term_id ),
					'include_children' => true,
				),
			),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
	if ( ! $nt_hub_posts->have_posts() ) {
		continue;
	}
	$nt_hub_link = get_term_link( $nt_hub );
	?>
	<section class="nt-section nt-home-mag__topic" aria-labelledby="nt-home-topic-<?php echo esc_attr( (string) $nt_hub->term_id ); ?>">
		<div class="nt-container">
			<header class="nt-section__head nt-section__head--row">
				<div><p class="nt-eyebrow">Theo chuyên mục</p><h2 class="nt-section__title" id="nt-home-topic-<?php echo esc_attr( (string) $nt_hub->term_id ); ?>"><?php echo esc_html( $nt_hub->name ); ?></h2></div>
				<?php if ( ! is_wp_error( $nt_hub_link ) ) : ?>
					<a class="nt-link-arrow" href="<?php echo esc_url( $nt_hub_link ); ?>">Xem chuyên mục <?php nt_the_icon( 'arrow-right', array( 'size' => 18 ) ); ?></a>
				<?php endif; ?>
			</header>
			<div class="nt-grid nt-grid--3">
				<?php
				while ( $nt_hub_posts->have_posts() ) :
					$nt_hub_posts->the_post();
					$nt_shown[] = (int) get_the_ID();
					get_template_part( 'template-parts/content/card', 'post', array( 'heading' => 'h3' ) );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</div>
	</section>
<?php endforeach; ?>

<?php
$nt_latest = new WP_Query(
	array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => 4,
		'post__not_in'        => $nt_shown,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
if ( $nt_latest->have_posts() ) :
	?>
	<section class="nt-section nt-home-mag__latest nt-section--tint" aria-labelledby="nt-home-latest-title">
		<div class="nt-container">
			<header class="nt-section__head nt-section__head--row">
				<h2 id="nt-home-latest-title" class="nt-section__title">Bài viết mới nhất</h2>
				<?php if ( is_string( $nt_blog ) && '' !== $nt_blog ) : ?>
					<a class="nt-link-arrow" href="<?php echo esc_url( $nt_blog ); ?>">Xem thêm <?php nt_the_icon( 'arrow-right', array( 'size' => 18 ) ); ?></a>
				<?php endif; ?>
			</header>
			<div class="nt-grid nt-grid--4">
				<?php
				while ( $nt_latest->have_posts() ) :
					$nt_latest->the_post();
					get_template_part( 'template-parts/content/card', 'post', array( 'heading' => 'h3' ) );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		</div>
	</section>
<?php endif; ?>

<section class="nt-home-mag__closing" aria-labelledby="nt-home-closing-title">
	<div class="nt-container nt-home-mag__closing-grid">
		<div>
			<h2 id="nt-home-closing-title">Chọn xe và xác nhận điều kiện thuê</h2>
			<p>Địa chỉ: <?php echo esc_html( nt_address() ); ?>. Mở cửa <?php echo esc_html( nt_hours_range() ); ?>. Giá thuê và tiền cọc được xác nhận khi đặt.</p>
			<p>Chuẩn bị <a href="<?php echo esc_url( get_permalink( 829 ) ); ?>">giấy tờ và các bước nhận xe</a>, xem <a href="<?php echo esc_url( get_permalink( 847 ) ); ?>">điểm nhận xe tại Long Biên</a> hoặc hỏi <a href="<?php echo esc_url( get_permalink( 887 ) ); ?>">điều kiện và phí giao nhận</a> theo địa chỉ cụ thể trước khi chốt lịch.</p>
		</div>
		<?php nt_cta_buttons( array( 'size' => 'md' ) ); ?>
	</div>
</section>
