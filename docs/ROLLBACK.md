# Rollback

Mục tiêu: trở về trạng thái cũ trong vài phút mà không mất dữ liệu. Theme mới **không ghi** bài viết, term, menu, media hay SEO nên không có gì
trong nội dung cần "hoàn tác"; thay đổi duy nhất trong DB là các option/theme_mod nhỏ liệt kê bên dưới.

## Những gì theme/plugin mới có thể ghi (toàn bộ)

| Ghi gì | Khi nào | Ảnh hưởng khi rollback |
| --- | --- | --- |
| `theme_mods_nguyentu-editorial-commerce` → `nav_menu_locations` | Khi kích hoạt theme (chỉ điền vị trí trống) | Vô hại: là theme_mod của riêng theme mới, theme cũ không đọc |
| option `nt_activation_log` | Khi kích hoạt theme | Có thể xóa |
| option `nguyentu_options` | Khi lưu trang cài đặt (Giao diện › Nguyễn Tú) | Có thể xóa |
| option `nguyentu_legacy_compat` | Khi kích hoạt plugin tương thích hoặc bấm "Ghi lại cấu hình" | Có thể xóa cùng plugin (`uninstall.php`) |
| `rewrite_rules` | Một lần `wp rewrite flush` có giám sát sau khi đổi theme | Flush lại sau khi rollback |

## Các bước (theo thứ tự)

1. **Kích hoạt lại theme cũ:**
   ```bash
   wp theme activate flatsome-child      # parent flatsome vẫn được giữ nguyên cài đặt
   ```
2. **Gán lại menu nếu cần** (Flatsome dùng theme_mod của chính nó nên thường còn nguyên). Nếu trống:
   `wp menu location assign 67 primary && wp menu location assign 67 primary_mobile && wp menu location assign 66 footer && wp menu location assign 66 top_bar_nav`
3. `wp rewrite flush` (một lần) rồi purge cache LiteSpeed.
4. Kiểm tra: `/`, `/blog/`, `/shop/`, một `/san-pham/...`, `/bo-suu-tap/bang-gia/`, một bảng giá `featured_item`, giỏ hàng.
5. **Plugin tương thích:** để nguyên (nó tự ngủ đông khi Flatsome đăng ký lại `featured_item`) hoặc tắt:
   `wp plugin deactivate nguyentu-legacy-compat`. Không cần xóa.
6. Nếu database bị ảnh hưởng ngoài dự kiến: khôi phục **snapshot Hostinger** (files + DB) đã chụp ở bước chuẩn bị.

## Vì sao rollback an toàn

- Flatsome và Flatsome Child không bị xóa/thay đổi; cầu nối shortcode chỉ đăng ký tag **chưa có ai đăng ký**, nên khi Flatsome hoạt động trở lại
  nó không làm gì.
- Plugin tương thích chỉ đăng ký `featured_item` khi chưa tồn tại.
- Không có redirect, không đổi slug/permalink/canonical/robots, không đổi `blog_public`.

## Dấu hiệu cần rollback ngay

- Bất kỳ URL trọng yếu nào trả 404/5xx hoặc bị redirect.
- `tools/seo/diff.mjs` báo P0 (canonical lệch, noindex xuất hiện, H1/title/description đổi, schema mất/trùng, shortcode thô).
- Giỏ hàng/thanh toán lỗi, hoặc menu header trống.
