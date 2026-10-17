<?php
/**
 * Search results. Only published content is ever queried (WordPress default); the page is kept
 * out of the index by Yoast / WordPress core robots handling, not by this template.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

global $wp_query;
$nt_total = (int) $wp_query->found_posts;

get_header();
?>
<main id="main" class="nt-main nt-archive nt-search-page" tabindex="-1">
	<div class="nt-container">
		<header class="nt-archive-head">
			<?php nt_breadcrumbs(); ?>
			<h1 class="nt-archive-head__title">Kết quả tìm kiếm cho &ldquo;<?php echo esc_html( get_search_query() ); ?>&rdquo;</h1>
			<p class="nt-archive-head__meta"><?php echo esc_html( $nt_total ); ?> kết quả</p>
			<?php get_search_form(); ?>
		</header>

		<?php if ( have_posts() ) : ?>
			<ol class="nt-results">
				<?php
				while ( have_posts() ) :
					the_post();
					$nt_type  = get_post_type();
					$nt_label = array(
						'post'          => 'Bài viết',
						'page'          => 'Trang',
						'product'       => 'Sản phẩm',
						'featured_item' => 'Bảng giá',
					)[ $nt_type ] ?? 'Nội dung';
					?>
					<li class="nt-result">
						<p class="nt-result__type"><?php echo esc_html( $nt_label ); ?></p>
						<h2 class="nt-result__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<p class="nt-result__excerpt"><?php echo esc_html( nt_trim_words( wp_strip_all_tags( get_the_excerpt() ), 34 ) ); ?></p>
						<p class="nt-result__url"><?php echo esc_html( preg_replace( '#^https?://#', '', (string) get_permalink() ) ); ?></p>
					</li>
				<?php endwhile; ?>
			</ol>
			<?php nt_pagination(); ?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content/none' ); ?>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
