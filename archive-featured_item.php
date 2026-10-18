<?php
/**
 * Featured item post-type archive (only reachable when the registration enables an archive).
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="nt-main nt-archive nt-featured-archive" tabindex="-1">
	<div class="nt-container">
		<header class="nt-archive-head">
			<?php nt_breadcrumbs(); ?>
			<h1 class="nt-archive-head__title"><?php echo esc_html( post_type_archive_title( '', false ) ?: 'Bộ sưu tập' ); ?></h1>
		</header>
		<?php if ( have_posts() ) : ?>
			<div class="nt-grid nt-grid--3">
				<?php
				while ( have_posts() ) {
					the_post();
					get_template_part( 'template-parts/featured/card', null, array( 'heading' => 'h2' ) );
				}
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
