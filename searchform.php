<?php
/**
 * Search form with a visible magnifier button and a real label.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

$nt_uid = wp_unique_id( 'nt-search-' );
?>
<form role="search" method="get" class="nt-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $nt_uid ); ?>">Tìm kiếm</label>
	<input type="search" id="<?php echo esc_attr( $nt_uid ); ?>" class="nt-search__input" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Tìm bài viết, bảng giá, xe máy…" autocomplete="off" enterkeyhint="search">
	<button type="submit" class="nt-search__btn">
		<?php nt_the_icon( 'search', array( 'size' => 20 ) ); ?>
		<span class="screen-reader-text">Tìm</span>
	</button>
</form>
