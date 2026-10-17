<?php
/**
 * Empty state: never a dead end.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="nt-empty">
	<h2 class="nt-empty__title"><?php echo is_search() ? 'Không tìm thấy kết quả phù hợp' : 'Chưa có bài viết trong mục này'; ?></h2>
	<p class="nt-empty__text">
		<?php echo is_search() ? 'Hãy thử từ khóa ngắn hơn hoặc xem các mục bên dưới.' : 'Mục này đang được cập nhật. Bạn có thể tìm kiếm hoặc xem các chuyên mục khác.'; ?>
	</p>
	<?php get_search_form(); ?>
	<?php get_template_part( 'template-parts/content/hubs' ); ?>
</section>
