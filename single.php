<?php
/**
 * Single post: breadcrumb with category ancestry, one H1, author/date/reading time, featured
 * image, optional table of contents (real headings only), related posts, previous/next within the
 * same topic. All SEO metadata and schema come from Yoast; nothing is added here.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="nt-main nt-single" tabindex="-1">
	<?php
	while ( have_posts() ) :
		the_post();
		$nt_id       = (int) get_the_ID();
		$nt_content  = nt_get_rendered_content();
		$nt_print_h1 = nt_should_print_h1( $nt_content );
		$nt_toc      = nt_toc_items( $nt_id );
		$nt_thumb_id = (int) get_post_thumbnail_id( $nt_id );
		// Do not show the same picture twice when the writer already put it at the top of the body.
		$nt_dupe_img = $nt_thumb_id > 0 && (bool) preg_match( '/wp-image-' . $nt_thumb_id . '\b/', $nt_content );
		$nt_chips    = nt_category_chips( $nt_id, 3 );
		?>
		<article id="post-<?php the_ID(); ?>" class="nt-article">
			<header class="nt-article__head">
				<div class="nt-container nt-container--narrow">
					<?php nt_breadcrumbs(); ?>
					<?php if ( '' !== $nt_chips ) : ?>
						<div class="nt-article__chips"><?php echo $nt_chips; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in nt_category_chips(). ?></div>
					<?php endif; ?>
					<?php if ( $nt_print_h1 ) : ?>
						<h1 class="nt-article__title"><?php the_title(); ?></h1>
					<?php endif; ?>
					<p class="nt-article__meta">
						<span class="nt-article__author"><?php nt_the_icon( 'user', array( 'size' => 16 ) ); ?>
							<a href="<?php echo esc_url( get_author_posts_url( (int) get_the_author_meta( 'ID' ) ) ); ?>" rel="author"><?php the_author(); ?></a></span>
						<span><?php echo nt_date_html( $nt_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
						<?php if ( get_the_modified_date( 'Ymd' ) !== get_the_date( 'Ymd' ) ) : ?>
							<span>Cập nhật <?php echo nt_date_html( $nt_id, 'modified' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></span>
						<?php endif; ?>
						<span><?php nt_the_icon( 'clock', array( 'size' => 16 ) ); ?><?php echo esc_html( nt_reading_time_label( $nt_content ) ); ?></span>
					</p>
				</div>
			</header>

			<div class="nt-container nt-article__layout<?php echo array() !== $nt_toc || is_active_sidebar( 'sidebar-blog' ) || 'on' === nt_toggle( 'post_cta' ) ? ' has-aside' : ''; ?>">
				<div class="nt-article__main">
					<?php if ( $nt_thumb_id > 0 && ! $nt_dupe_img ) : ?>
						<figure class="nt-article__hero">
							<?php
							echo get_the_post_thumbnail( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper.
								$nt_id,
								'large',
								array(
									'class'         => 'nt-article__img',
									'sizes'         => '(min-width: 1100px) 760px, 100vw',
									'loading'       => 'eager',
									'fetchpriority' => 'high',
									'decoding'      => 'async',
								)
							);
							$nt_caption = get_the_post_thumbnail_caption( $nt_id );
							if ( '' !== $nt_caption ) {
								echo '<figcaption>' . esc_html( $nt_caption ) . '</figcaption>';
							}
							?>
						</figure>
					<?php endif; ?>

					<?php if ( array() !== $nt_toc ) : ?>
						<?php
						get_template_part(
							'template-parts/content/toc',
							null,
							array(
								'items'   => $nt_toc,
								'variant' => 'inline',
							)
						);
						?>
					<?php endif; ?>

					<div class="nt-prose entry-content">
						<?php echo $nt_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content filter output. ?>
					</div>

					<?php
					wp_link_pages(
						array(
							'before' => '<nav class="nt-pagelinks" aria-label="Các trang của bài viết">',
							'after'  => '</nav>',
						)
					);
					$nt_tags = get_the_tag_list( '<ul class="nt-tags"><li>', '</li><li>', '</li></ul>' );
					if ( $nt_tags ) {
						echo $nt_tags; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core helper.
					}
					if ( 'off' !== nt_toggle( 'share' ) ) {
						nt_share_links( (string) get_permalink(), wp_strip_all_tags( get_the_title() ) );
					}
					get_template_part( 'template-parts/content/author-box' );
					get_template_part( 'template-parts/content/post-nav' );
					?>
				</div>

				<aside class="nt-article__aside" aria-label="Thông tin thêm">
					<?php if ( array() !== $nt_toc ) : ?>
						<?php
						get_template_part(
							'template-parts/content/toc',
							null,
							array(
								'items'   => $nt_toc,
								'variant' => 'sticky',
							)
						);
						?>
					<?php endif; ?>
					<?php if ( 'off' !== nt_toggle( 'post_cta' ) ) : ?>
						<?php get_template_part( 'template-parts/content/cta-box' ); ?>
					<?php endif; ?>
					<?php dynamic_sidebar( 'sidebar-blog' ); ?>
				</aside>
			</div>
		</article>

		<?php nt_cluster_navigation(); ?>
		<?php get_template_part( 'template-parts/content/related' ); ?>

		<?php
		if ( comments_open() || get_comments_number() ) {
			echo '<div class="nt-container nt-container--narrow">';
			comments_template();
			echo '</div>';
		}
	endwhile;
	?>
</main>
<?php
get_footer();
