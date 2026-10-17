<?php
/**
 * Generic archive (tags, authors, dates, post-type archives). Categories use category.php.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="nt-main nt-archive" tabindex="-1">
	<div class="nt-container">
		<?php get_template_part( 'template-parts/content/archive-header' ); ?>

		<?php if ( have_posts() ) : ?>
			<div class="nt-grid nt-grid--3">
				<?php
				$nt_i = 0;
				while ( have_posts() ) :
					the_post();
					if ( 'featured_item' === get_post_type() ) {
						get_template_part( 'template-parts/featured/card', null, array( 'heading' => 'h2' ) );
					} else {
						get_template_part(
							'template-parts/content/card',
							'post',
							array(
								'heading' => 'h2',
								'eager'   => $nt_i < 2,
							)
						);
					}
					++$nt_i;
				endwhile;
				?>
			</div>
			<?php nt_pagination(); ?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content/none' ); ?>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
