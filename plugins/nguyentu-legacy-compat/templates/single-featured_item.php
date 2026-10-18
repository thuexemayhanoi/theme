<?php
/**
 * Fallback single featured item. One H1 (unless the content already has one), content as stored.
 *
 * @package NguyenTuLegacyCompat
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="nt-compat" style="max-width:900px;margin:0 auto;padding:24px 16px;">
	<?php
	while ( have_posts() ) :
		the_post();
		$nt_c_content = apply_filters( 'the_content', get_the_content() );
		?>
		<article id="post-<?php the_ID(); ?>">
			<?php if ( ! preg_match( '/<h1[\s>]/i', $nt_c_content ) ) : ?>
				<h1><?php the_title(); ?></h1>
			<?php endif; ?>
			<?php echo $nt_c_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content filter output. ?>
		</article>
	<?php endwhile; ?>
</main>
<?php
get_footer();
