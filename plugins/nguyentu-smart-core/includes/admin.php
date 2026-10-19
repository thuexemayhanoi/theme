<?php
/**
 * Read-only status page. No settings wizard, credentials or writes.
 *
 * @package NguyenTuSmartCore
 */

defined( 'ABSPATH' ) || exit;

final class NTSC_Admin {

	public static function register(): void {
		add_management_page(
			'Nguyễn Tú Smart Core',
			'Nguyễn Tú Core',
			'manage_options',
			'nguyentu-smart-core',
			array( __CLASS__, 'page' )
		);
	}

	/**
	 * @param array<int,string> $links Existing WordPress plugin action links.
	 * @return array<int,string>
	 */
	public static function action_links( $links ): array {
		$url = admin_url( 'tools.php?page=nguyentu-smart-core' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">Trạng thái</a>' );
		return $links;
	}

	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập.', 'nguyentu-smart-core' ) );
		}

		$b       = ntsc_business();
		$owner   = NTSC_Schema::seo_owner();
		$allowed = ntsc_site_allowed();
		$mode    = '' !== $owner
			? 'Schema do ' . $owner . ' quản lý; Smart Core không tạo graph cạnh tranh.'
			: 'Chưa phát hiện SEO plugin: Smart Core chỉ in Organization + WebSite khi trang đủ điều kiện.';
		$theme   = function_exists( 'nt_yoast_enrich_organization' )
			? 'Theme Nguyễn Tú đang bổ sung LocalBusiness vào Yoast; Smart Core nhường quyền.'
			: 'Schema fallback của Smart Core sẵn sàng khi cần.';

		echo '<div class="wrap"><h1>Nguyễn Tú Smart Core</h1>';
		echo '<p>Phiên bản ' . esc_html( NTSC_VERSION ) . ' — chỉ xem trạng thái, không có bước cấu hình.</p>';
		if ( ! $allowed ) {
			echo '<div class="notice notice-warning inline"><p>Plugin không xuất thông tin doanh nghiệp vì tên miền hiện tại không phải website Nguyễn Tú đã xác minh.</p></div>';
		}
		echo '<table class="widefat striped" style="max-width:850px"><tbody>';
		self::row( 'Thương hiệu', (string) ( $b['brand'] ?? '' ) );
		self::row( 'Số điện thoại', (string) ( $b['phone_display'] ?? '' ) );
		self::row( 'Địa chỉ', trim( (string) ( $b['street'] ?? '' ) . ', ' . (string) ( $b['ward'] ?? '' ) . ', ' . (string) ( $b['district'] ?? '' ) . ', ' . (string) ( $b['city'] ?? '' ), ', ' ) );
		self::row( 'Giờ mở cửa', (string) ( $b['open'] ?? '' ) . '–' . (string) ( $b['close'] ?? '' ) . ' hằng ngày' );
		self::row( 'Nguồn dữ liệu', function_exists( 'nt_business' ) ? 'Đọc trực tiếp từ theme Nguyễn Tú' : 'Dữ liệu chuẩn + tùy chọn WordPress cũ nếu có' );
		self::row( 'Schema', $mode );
		self::row( 'Tương thích', $theme );
		self::row( 'Giá thuê', count( ntsc_reference_prices() ) . ' nhóm giá tham khảo; không tự tạo Product/Offer schema' );
		echo '</tbody></table>';
		echo '<p>Không thay đổi URL, canonical, robots, sitemap, title, bài viết hay bảng giá hiện có.</p>';
		echo '</div>';
	}

	private static function row( string $label, string $value ): void {
		echo '<tr><th scope="row" style="width:180px">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
	}
}
