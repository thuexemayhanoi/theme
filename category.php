<?php
/**
 * Blog category archive (hierarchical: /blog/, /blog/xe-dien/, /blog/xe-dien/xe-dap-dien/ ...).
 *
 * The queried term drives everything: H1, description, breadcrumb ancestry, child chips and the
 * server-side post list with real pagination URLs. Canonical/robots stay with Yoast.
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
					$nt_is_lead = ( 0 === $nt_i && ! is_paged() );
					get_template_part(
						'template-parts/content/card',
						'post',
						array(
							'heading' => 'h2',
							'lead'    => $nt_is_lead,
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
