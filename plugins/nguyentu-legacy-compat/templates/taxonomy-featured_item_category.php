<?php
/**
 * Fallback term archive for featured_item_category.
 *
 * @package NguyenTuLegacyCompat
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="nt-compat" style="max-width:900px;margin:0 auto;padding:24px 16px;">
	<h1><?php single_term_title(); ?></h1>
	<?php
	$nt_c_desc = term_description();
	if ( '' !== trim( wp_strip_all_tags( $nt_c_desc ) ) ) {
		echo wp_kses_post( $nt_c_desc );
	}
	?>
	<?php if ( have_posts() ) : ?>
		<ul>
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
			<?php endwhile; ?>
		</ul>
		<?php the_posts_pagination(); ?>
	<?php endif; ?>
</main>
<?php
get_footer();
