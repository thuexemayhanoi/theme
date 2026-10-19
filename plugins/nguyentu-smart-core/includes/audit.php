<?php
/**
 * Bounded, read-only SEO and site health diagnostics.
 *
 * @package NguyenTuSmartCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Shows a small editorial sample, never crawls URLs or modifies posts.
 */
final class NT_Smart_Audit {

	/**
	 * Count distinct internal links to other paths in stored post content.
	 *
	 * @param string $html Article HTML.
	 * @param string $own_url Current post URL.
	 * @return int
	 */
	public static function count_internal_links( string $html, string $own_url ): int {
		$host      = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$own_path  = (string) wp_parse_url( $own_url, PHP_URL_PATH );
		$links     = array();
		$processor = new WP_HTML_Tag_Processor( substr( $html, 0, 150000 ) );
		while ( $processor->next_tag( 'a' ) ) {
			$href = $processor->get_attribute( 'href' );
			if ( ! is_string( $href ) || '' === $href || '#' === $href[0] ) {
				continue;
			}
			$parts = wp_parse_url( $href );
			if ( ! is_array( $parts ) ) {
				continue;
			}
			$link_host = strtolower( (string) ( $parts['host'] ?? $host ) );
			$path      = (string) ( $parts['path'] ?? '' );
			if ( $host !== $link_host || '' === $path || '/' === $path || $own_path === $path ) {
				continue;
			}
			$links[ untrailingslashit( $path ) ] = true;
		}
		return count( $links );
	}

	/**
	 * Inspect only the most recent twenty public posts.
	 *
	 * @return array<int,array{id:int,title:string,links:int}>
	 */
	public static function latest_posts(): array {
		$posts = get_posts(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'posts_per_page'         => 20,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$rows = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post || '' !== $post->post_password ) {
				continue;
			}
			$url     = get_permalink( $post );
			$rows[] = array(
				'id'    => (int) $post->ID,
				'title' => get_the_title( $post ),
				'links' => self::count_internal_links( (string) $post->post_content, is_string( $url ) ? $url : '' ),
			);
		}
		return $rows;
	}

	/**
	 * Render read-only signals and orphan-link suggestions in Tools.
	 */
	public static function render(): void {
		echo '<h2>Sức khỏe WordPress (chỉ đọc)</h2><table class="widefat striped" style="max-width:850px"><tbody>';
		$health = array(
			'WordPress' => get_bloginfo( 'version' ),
			'PHP'       => PHP_VERSION,
			'SEO'       => NT_Smart_Schema::seo_owner() ?: 'Chưa xác định plugin SEO.',
			'WP-Cron'   => ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? 'Tắt cron theo truy cập: cần kiểm tra cron máy chủ.' : 'Không tắt cron theo truy cập.',
		);
		foreach ( $health as $label => $value ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
		}
		echo '</tbody></table>';

		$rows = self::latest_posts();
		$weak = array_values( array_filter( $rows, static fn ( array $row ): bool => 0 === $row['links'] ) );
		echo '<h2>Gợi ý liên kết nội bộ</h2><p>Đã kiểm tra ' . esc_html( (string) count( $rows ) ) . ' bài mới nhất, có ' . esc_html( (string) count( $weak ) ) . ' bài chưa thấy liên kết nội bộ trong nội dung đã lưu. Không phải báo cáo quét toàn website hoặc kết luận có URL 404.</p>';
		if ( $weak ) {
			echo '<ul>';
			foreach ( $weak as $row ) {
				echo '<li><a href="' . esc_url( get_edit_post_link( $row['id'] ) ) . '">' . esc_html( $row['title'] ) . '</a> — cân nhắc liên kết Hub/Pillar phù hợp.</li>';
			}
			echo '</ul>';
		}
		echo '<p>Không tự tạo liên kết, sửa slug, title, canonical, schema hoặc nội dung.</p>';
	}
}
