<?php
/**
 * Featured item term archive: /bo-suu-tap/bang-gia/, /bo-suu-tap/thue-ngay/ ... The URL, slug and
 * hierarchy are whatever WordPress resolved; the term drives H1, description and the item list.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_term   = get_queried_object();
$nt_period = ( $nt_term instanceof WP_Term ) ? nt_featured_period_for_slug( $nt_term->slug ) : '';

get_header();
?>
<main id="main" class="nt-main nt-archive nt-featured-archive" tabindex="-1">
	<div class="nt-container">
		<?php get_template_part( 'template-parts/content/archive-header' ); ?>

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
			<?php
			if ( '' !== $nt_period ) {
				nt_featured_price_box( $nt_period );
			}
			?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content/none' ); ?>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
