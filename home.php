<?php
/**
 * Posts index. The live site has no "posts page" (page_for_posts = 0; /blog/ is category #69),
 * but if one is ever assigned this template renders it with the same card grid.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="nt-main nt-archive" tabindex="-1">
	<div class="nt-container">
		<header class="nt-archive-head">
			<?php nt_breadcrumbs(); ?>
			<h1 class="nt-archive-head__title"><?php echo esc_html( single_post_title( '', false ) ?: 'Bài viết mới' ); ?></h1>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="nt-grid nt-grid--3">
				<?php
				$nt_i = 0;
				while ( have_posts() ) :
					the_post();
					get_template_part(
						'template-parts/content/card',
						'post',
						array(
							'heading' => 'h2',
							'lead'    => 0 === $nt_i && ! is_paged(),
							'eager'   => $nt_i < 2,
						)
					);
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
