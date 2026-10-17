<?php
/**
 * Related posts: same topic, published only, capped, bulk-primed (no N+1).
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_related = nt_related_query( (int) get_the_ID(), 4 );
if ( ! $nt_related instanceof WP_Query || ! $nt_related->have_posts() ) {
	return;
}
?>
<section class="nt-related" aria-labelledby="nt-related-title">
	<div class="nt-container">
		<h2 id="nt-related-title" class="nt-section__title">Bài viết liên quan</h2>
		<div class="nt-grid nt-grid--4">
			<?php
			while ( $nt_related->have_posts() ) {
				$nt_related->the_post();
				get_template_part( 'template-parts/content/card', 'post', array( 'heading' => 'h3' ) );
			}
			wp_reset_postdata();
			?>
		</div>
	</div>
</section>
