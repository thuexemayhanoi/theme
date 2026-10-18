<?php
/**
 * Latest posts (internal links into the blog silo). Published posts only.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_query = nt_home_posts_query( 6 );
if ( ! $nt_query->have_posts() ) {
	return;
}
$nt_root = nt_blog_root_id();
$nt_blog = $nt_root > 0 ? get_term_link( $nt_root, 'category' ) : '';
?>
<section class="nt-section" aria-labelledby="nt-posts-title">
	<div class="nt-container">
		<header class="nt-section__head nt-section__head--row">
			<div>
				<p class="nt-eyebrow">Cẩm nang</p>
				<h2 id="nt-posts-title" class="nt-section__title">Bài viết mới trên blog</h2>
			</div>
			<?php if ( is_string( $nt_blog ) && '' !== $nt_blog ) : ?>
				<a class="nt-link-arrow" href="<?php echo esc_url( $nt_blog ); ?>">Xem toàn bộ blog <?php nt_the_icon( 'arrow-right', array( 'size' => 18 ) ); ?></a>
			<?php endif; ?>
		</header>
		<div class="nt-grid nt-grid--3">
			<?php
			while ( $nt_query->have_posts() ) {
				$nt_query->the_post();
				get_template_part( 'template-parts/content/card', 'post', array( 'heading' => 'h3' ) );
			}
			wp_reset_postdata();
			?>
		</div>
	</div>
</section>
