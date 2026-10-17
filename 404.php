<?php
/**
 * 404: a real 404 status (set by WordPress), one H1, a search box and useful destinations.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="nt-main nt-404" tabindex="-1">
	<div class="nt-container nt-container--narrow">
		<header class="nt-archive-head nt-archive-head--center">
			<p class="nt-eyebrow">Lỗi 404</p>
			<h1 class="nt-archive-head__title">Không tìm thấy trang bạn cần</h1>
			<p class="nt-archive-head__desc">Đường dẫn có thể đã thay đổi hoặc không còn tồn tại. Hãy tìm kiếm hoặc chọn một mục bên dưới.</p>
			<?php get_search_form(); ?>
		</header>
		<?php get_template_part( 'template-parts/content/hubs' ); ?>
		<p class="nt-404__back"><a class="nt-btn nt-btn--primary nt-btn--md" href="<?php echo esc_url( home_url( '/' ) ); ?>">Về trang chủ</a></p>
	</div>
</main>
<?php
get_footer();
