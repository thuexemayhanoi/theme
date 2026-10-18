<?php
/**
 * Single featured item (bảng giá / "Bộ sưu tập" landing page). The stored content is rendered
 * as-is through the legacy bridge; the theme adds a breadcrumb, a reference price box (only when
 * the body does not already list prices) and links to sibling items.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="nt-main nt-featured" tabindex="-1">
	<?php
	while ( have_posts() ) :
		the_post();
		$nt_id       = (int) get_the_ID();
		$nt_content  = nt_get_rendered_content();
		$nt_print_h1 = nt_should_print_h1( $nt_content );
		$nt_is_wide  = nt_content_is_wide( $nt_content );
		$nt_terms    = get_the_terms( $nt_id, 'featured_item_category' );
		$nt_period   = '';
		if ( is_array( $nt_terms ) ) {
			foreach ( $nt_terms as $nt_term ) {
				$nt_period = '' !== $nt_period ? $nt_period : nt_featured_period_for_slug( $nt_term->slug );
			}
		}
		$nt_siblings = nt_featured_sibling_ids( $nt_id, 4 );
		?>
		<article id="post-<?php the_ID(); ?>" class="nt-pagebody">
			<?php if ( 'full' === nt_pagehead_mode( $nt_print_h1, $nt_is_wide ) ) : ?>
				<header class="nt-pagehead">
					<div class="nt-container nt-container--narrow">
						<?php nt_breadcrumbs(); ?>
						<?php if ( $nt_print_h1 ) : ?>
							<h1 class="nt-pagehead__title"><?php the_title(); ?></h1>
						<?php endif; ?>
					</div>
				</header>
			<?php else : ?>
				<?php // A wide body with its own H1: keep the visible breadcrumb (the bridge strips [ux_breadcrumbs]) without a second heading. ?>
				<div class="nt-pagecrumbs">
					<div class="nt-container"><?php nt_breadcrumbs(); ?></div>
				</div>
			<?php endif; ?>

			<div class="nt-pagecontent<?php echo $nt_is_wide ? ' nt-pagecontent--wide' : ''; ?>">
				<div class="<?php echo $nt_is_wide ? '' : 'nt-container nt-container--narrow '; ?>nt-prose entry-content">
					<?php echo $nt_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content filter output. ?>
				</div>
			</div>

			<div class="nt-container nt-container--narrow">
				<?php
				if ( '' !== $nt_period && ! nt_content_has_prices( $nt_content, 3 ) ) {
					nt_featured_price_box( $nt_period );
				}
				nt_cta_buttons(
					array(
						'size'  => 'md',
						'class' => 'nt-cta--center',
					)
				);
				?>
			</div>

			<?php if ( array() !== $nt_siblings ) : ?>
				<section class="nt-related" aria-labelledby="nt-feat-more">
					<div class="nt-container">
						<h2 id="nt-feat-more" class="nt-section__title">Xem thêm</h2>
						<div class="nt-grid nt-grid--4">
							<?php
							$nt_more = new WP_Query(
								array(
									'post_type'           => 'featured_item',
									'post__in'            => $nt_siblings,
									'orderby'             => 'post__in',
									'posts_per_page'      => count( $nt_siblings ),
									'no_found_rows'       => true,
									'ignore_sticky_posts' => true,
								)
							);
							while ( $nt_more->have_posts() ) {
								$nt_more->the_post();
								get_template_part( 'template-parts/featured/card', null, array( 'heading' => 'h3' ) );
							}
							wp_reset_postdata();
							?>
						</div>
					</div>
				</section>
			<?php endif; ?>
		</article>
	<?php endwhile; ?>
</main>
<?php
get_footer();
