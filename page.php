<?php
/**
 * Static pages. The rendered body is built first so the theme never prints a second <h1> when
 * the page content (often a legacy hero) already contains the SEO heading.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="nt-main nt-page" tabindex="-1">
	<?php
	while ( have_posts() ) :
		the_post();
		$nt_content  = nt_get_page_body();
		$nt_print_h1 = nt_should_print_h1( $nt_content );
		$nt_is_shop  = nt_is_shop_context();
		// Legacy layouts bring their own sections; cart, checkout and account need the full container (tables, two columns).
		$nt_is_wide  = nt_content_is_wide( $nt_content );
		$nt_box_wide = $nt_is_wide || $nt_is_shop;
		?>
		<article id="post-<?php the_ID(); ?>" class="nt-pagebody<?php echo ! $nt_is_shop ? ' nt-static-page' : ''; ?>"<?php echo ! $nt_is_shop ? ' data-nt-page-body="20261021"' : ''; ?>>
			<?php if ( 'full' === nt_pagehead_mode( $nt_print_h1, $nt_is_wide ) ) : ?>
				<header class="nt-pagehead">
					<div class="nt-container<?php echo $nt_box_wide ? '' : ' nt-container--narrow'; ?>">
						<?php nt_breadcrumbs(); ?>
						<?php if ( $nt_print_h1 ) : ?>
							<h1 class="nt-pagehead__title"><?php the_title(); ?></h1>
						<?php endif; ?>
					</div>
				</header>
			<?php else : ?>
				<div class="nt-pagecrumbs">
					<div class="nt-container"><?php nt_breadcrumbs(); ?></div>
				</div>
			<?php endif; ?>

			<div class="nt-pagecontent<?php echo $nt_is_wide ? ' nt-pagecontent--wide' : ''; ?>">
				<div class="<?php echo $nt_is_wide ? '' : ( $nt_is_shop ? 'nt-container ' : 'nt-container nt-container--narrow nt-document-page ' ); ?>nt-prose entry-content">
					<?php echo $nt_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content filter output. ?>
				</div>
			</div>

			<?php
			// Contact page: the verified NAP card, unless the page body already shows phone + address.
			if ( 'lien-he' === get_post_field( 'post_name' ) && ! nt_content_covers( 'local', $nt_content ) ) :
				?>
				<div class="nt-container nt-container--narrow">
					<?php
					get_template_part(
						'template-parts/home/local',
						null,
						array(
							'heading_id' => 'nt-contact-nap',
							'title'      => 'Thông tin liên hệ',
						)
					);
					?>
				</div>
			<?php endif; ?>
		</article>
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
