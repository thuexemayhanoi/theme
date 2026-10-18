<?php
/**
 * Fallback template (WordPress template hierarchy). Lists posts with the shared card layout.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="nt-main" tabindex="-1">
	<div class="nt-container">
		<header class="nt-archive-head">
			<?php nt_breadcrumbs(); ?>
			<h1 class="nt-archive-head__title"><?php echo esc_html( is_home() ? get_bloginfo( 'name' ) : wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="nt-grid nt-grid--3">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content/card', 'post', array( 'heading' => 'h2' ) );
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
