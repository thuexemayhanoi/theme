<?php
/**
 * Read-only status screen (Tools > NT Legacy Compat) with one write action: store the snapshot.
 *
 * @package NguyenTuLegacyCompat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin screen.
 */
final class NT_Compat_Admin {

	/**
	 * Register hooks.
	 */
	public static function hooks(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_nt_compat_capture', array( __CLASS__, 'handle_capture' ) );
	}

	/**
	 * Add the Tools page.
	 */
	public static function menu(): void {
		add_management_page( 'NT Legacy Compat', 'NT Legacy Compat', 'manage_options', 'nguyentu-legacy-compat', array( __CLASS__, 'render' ) );
	}

	/**
	 * Handle the "capture now" button (nonce + capability checked).
	 */
	public static function handle_capture(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'nguyentu-legacy-compat' ), 403 );
		}
		check_admin_referer( 'nt_compat_capture' );
		$result = NT_Compat_Registry::capture( ! empty( $_POST['force'] ) );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => 'nguyentu-legacy-compat',
					'nt_msg' => $result['message'],
					'nt_ok'  => $result['ok'] ? '1' : '0',
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	/**
	 * Count published featured items and terms.
	 *
	 * @return array{items:int,terms:int}
	 */
	private static function counts(): array {
		$items = 0;
		$terms = 0;
		if ( post_type_exists( NT_Compat_Registry::CPT ) ) {
			$counts = wp_count_posts( NT_Compat_Registry::CPT );
			$items  = (int) ( $counts->publish ?? 0 );
		}
		if ( taxonomy_exists( NT_Compat_Registry::TAX ) ) {
			$found = get_terms(
				array(
					'taxonomy'   => NT_Compat_Registry::TAX,
					'hide_empty' => false,
					'fields'     => 'count',
				)
			);
			$terms = is_numeric( $found ) ? (int) $found : 0;
		}
		return array(
			'items' => $items,
			'terms' => $terms,
		);
	}

	/**
	 * Render the page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$snap   = NT_Compat_Registry::snapshot();
		$counts = self::counts();
		$def    = $snap['definition'] ?? array();
		$pt_rw  = $def['post_type']['args']['rewrite'] ?? array();
		$tx_rw  = $def['taxonomy']['args']['rewrite'] ?? array();
		$source = (string) ( $snap['source'] ?? '' );
		$rules  = NT_Compat_Registry::stored_rules();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$msg = isset( $_GET['nt_msg'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['nt_msg'] ) ) : '';
		$ok  = isset( $_GET['nt_ok'] ) && '1' === $_GET['nt_ok'];
		// phpcs:enable
		?>
		<div class="wrap">
			<h1>NT Legacy Compat — featured_item</h1>
			<?php if ( '' !== $msg ) : ?>
				<div class="notice notice-<?php echo $ok ? 'success' : 'warning'; ?>"><p><?php echo esc_html( $msg ); ?></p></div>
			<?php endif; ?>
			<?php if ( 'defaults' === $source ) : ?>
				<div class="notice notice-warning"><p><strong>Đang dùng cấu hình mặc định, chưa xác minh với site thật.</strong> Kích hoạt plugin khi theme cũ còn hoạt động rồi bấm "Ghi lại cấu hình", sau đó chạy <code>wp nguyentu-compat verify</code>.</p></div>
			<?php elseif ( NT_Compat_Registry::registered_by_other() && '' === $source ) : ?>
				<div class="notice notice-warning"><p>Theme/plugin khác đang đăng ký featured_item nhưng chưa có bản ghi. Bấm "Ghi lại cấu hình" ngay bây giờ, trước khi đổi theme.</p></div>
			<?php endif; ?>

			<table class="widefat striped" style="max-width:860px">
				<tbody>
					<tr><td>Đăng ký bởi thành phần khác (theme cũ)</td><td><?php echo NT_Compat_Registry::registered_by_other() ? 'Có' : 'Không'; ?></td></tr>
					<tr><td>Plugin này đang đăng ký (replay)</td><td><?php echo NT_Compat_Registry::replay_active() ? 'Có' : 'Không (ngủ đông)'; ?></td></tr>
					<tr><td>Bản ghi cấu hình</td><td><?php echo '' === $source ? 'Chưa có' : esc_html( $source . ' · ' . ( $snap['captured_at'] ?? '' ) ); ?></td></tr>
					<tr><td>Slug rewrite featured_item</td><td><code><?php echo esc_html( is_array( $pt_rw ) ? (string) ( $pt_rw['slug'] ?? '' ) : '—' ); ?></code></td></tr>
					<tr><td>Slug rewrite featured_item_category</td><td><code><?php echo esc_html( is_array( $tx_rw ) ? (string) ( $tx_rw['slug'] ?? '' ) : '—' ); ?></code> (hierarchical URL: <?php echo is_array( $tx_rw ) && ! empty( $tx_rw['hierarchical'] ) ? 'có' : 'không'; ?>)</td></tr>
					<tr><td>Số featured_item đã xuất bản</td><td><?php echo esc_html( (string) $counts['items'] ); ?> (kỳ vọng: 15)</td></tr>
					<tr><td>Số term featured_item_category</td><td><?php echo esc_html( (string) $counts['terms'] ); ?> (kỳ vọng: 4)</td></tr>
					<tr><td>Rule rewrite đang lưu liên quan</td><td><?php echo esc_html( (string) count( $rules ) ); ?> <?php echo 0 === count( $rules ) ? '— nếu đã đổi theme, hãy chạy "wp rewrite flush" MỘT lần có giám sát' : ''; ?></td></tr>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px">
				<input type="hidden" name="action" value="nt_compat_capture">
				<?php wp_nonce_field( 'nt_compat_capture' ); ?>
				<label><input type="checkbox" name="force" value="1"> Ghi đè bản ghi hiện có</label>
				<?php submit_button( 'Ghi lại cấu hình đăng ký', 'primary', 'submit', false ); ?>
			</form>
			<p class="description">Chỉ ghi vào option <code><?php echo esc_html( NT_COMPAT_OPTION ); ?></code>. Không tạo, sửa hay xóa bài viết, term, menu hoặc SEO.</p>
		</div>
		<?php
	}
}
