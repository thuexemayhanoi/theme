<?php
/**
 * Plugin Name:       Nguyễn Tú Legacy Compat (featured_item)
 * Plugin URI:        https://thuexemaynguyentu.com/
 * Description:       Giữ nguyên post type "featured_item" và taxonomy "featured_item_category" (15 bảng giá, 4 term, URL /bo-suu-tap/...) khi theme Flatsome được thay bằng theme độc lập. Ghi lại cấu hình đăng ký đang chạy rồi đăng ký lại y hệt; không tạo, sửa hay xóa bản ghi nào.
 * Version:           1.0.0
 * Requires at least: 7.1
 * Requires PHP:      8.2
 * Author:            Nguyễn Tú
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nguyentu-legacy-compat
 *
 * HOW IT WORKS (capture, then replay)
 *  1. While the old theme is still active it registers `featured_item` / `featured_item_category`.
 *     Activating this plugin at that moment stores the exact registration arguments (labels,
 *     rewrite slugs, supports, REST flags ...) in ONE option of its own.
 *  2. After the old theme is gone, on `init` (priority 99) the plugin registers the same post
 *     type and taxonomy from that snapshot, but only if nobody else has registered them.
 *  3. With Flatsome active again (rollback) nothing happens: the plugin sees the types already
 *     exist and stays dormant.
 *
 * It never writes posts, terms, menus, media or SEO data. Its only database write is its own
 * option `nguyentu_legacy_compat`. Rewrite rules are flushed by an administrator through the
 * normal WordPress flow (Settings > Permalinks > Save, or `wp rewrite flush`) once, supervised.
 *
 * @package NguyenTuLegacyCompat
 */

defined( 'ABSPATH' ) || exit;

define( 'NT_COMPAT_VERSION', '1.0.0' );
define( 'NT_COMPAT_FILE', __FILE__ );
define( 'NT_COMPAT_DIR', plugin_dir_path( __FILE__ ) );
define( 'NT_COMPAT_OPTION', 'nguyentu_legacy_compat' );

require_once NT_COMPAT_DIR . 'includes/class-registry.php';
require_once NT_COMPAT_DIR . 'includes/class-templates.php';
require_once NT_COMPAT_DIR . 'includes/class-admin.php';
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once NT_COMPAT_DIR . 'includes/class-cli.php';
}

register_activation_hook( __FILE__, array( 'NT_Compat_Registry', 'on_activate' ) );

add_action( 'init', array( 'NT_Compat_Registry', 'replay' ), 99 );
NT_Compat_Templates::hooks();
NT_Compat_Admin::hooks();
