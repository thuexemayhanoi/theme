> Cập nhật 18/10: các module Autopilot và đường mở rộng nằm trong [AUTOPILOT.md](AUTOPILOT.md); hợp đồng hiện hành ở [../AGENTS.md](../AGENTS.md). Chỉ mục postmeta/cron là ngoại lệ có chủ ý đối với mô tả "chỉ trình bày" cũ; không sửa bản ghi nội dung hoặc SEO metadata.

# Kiến trúc theme Nguyễn Tú Editorial Commerce

Theme **độc lập** (không `Template:` cha, không gọi hàm/tệp Flatsome — có test chặn). Thư mục gốc repo = thư mục theme để Deployer for Git cài trực tiếp.
Theme chỉ là **lớp hiển thị**: không ghi bài viết, term, menu, media, metadata SEO; không có khóa API; không có chức năng xuất bản.

## Cấu trúc

```
style.css · theme.json · functions.php        Khai báo theme, token cho editor, bootstrap (PHP >= 8.2)
index/front-page/home/page/single/archive/    Template hierarchy chuẩn của WordPress
category/search/404/header/footer/comments
single-featured_item · taxonomy-featured_item_category · archive-featured_item   Bảng giá (đăng ký do plugin tương thích)
template-parts/{header,footer,content,home,featured}/
inc/
  lib/            Hàm thuần (text, html, css, prices) — kiểm thử đơn vị không cần WordPress
  business.php    Dữ liệu chuẩn NAP/giờ/giá/chính sách + tùy chọn (không bịa số liệu)
  settings.php    Giao diện › Nguyễn Tú (Settings API, nonce, sanitize)
  setup.php       Theme supports, 7 vị trí menu giữ slug cũ, widget areas, dọn <head>
  enqueue.php     CSS/JS có điều kiện, version = mtime, preload font, không jQuery
  menus.php       Render menu #67 y nguyên bằng wp_nav_menu(), fallback theo ID, điền vị trí trống khi kích hoạt
  class-nt-walker-nav.php   Walker truy cập được (disclosure button, aria-expanded, mega panel cho nhánh 3 cấp)
  seo.php         Nhường Yoast; breadcrumb; bảo đảm 1 H1; fallback tối thiểu CHỈ khi không có plugin SEO
  schema.php      Bổ sung thuộc tính LocalBusiness còn thiếu vào nút Organization của Yoast
  content.php     id tiêu đề (mục lục), bọc bảng cuộn ngang, heuristic "nội dung đã có mục này"
  related.php     Bài liên quan cùng chủ đề, có trần và cache, không N+1
  home.php, featured.php, woocommerce.php, template-parts helpers
  legacy/         Cầu nối shortcode Flatsome (xem docs/LEGACY-COMPAT.md)
  cli.php         WP-CLI chỉ đọc: status, menu-snapshot, legacy-scan
assets/css|js|fonts|icons   main/home/blog/woo/legacy CSS, theme.js (deferred), Plus Jakarta Sans (OFL), icon Tabler (MIT) nhúng sẵn
plugins/nguyentu-legacy-compat/   Plugin riêng giữ featured_item (capture-then-replay)
tests/ tools/ docs/ reports/ .github/   Kiểm thử, công cụ, tài liệu, báo cáo, CI (không chạy trên production; có .htaccess chặn)
```

## Quyết định thiết kế và lý do

| Quyết định | Lý do |
| --- | --- |
| Yoast là nguồn duy nhất của title/meta/canonical/robots/OG/schema | `docs/SEO-HULK.md` §3; test tĩnh cấm theme in JSON-LD/OG và chỉ cho phép một `<head>` dự phòng khi *không* có plugin SEO |
| Menu render bằng `wp_nav_menu()` + walker, không sao chép dữ liệu | Giữ đúng `menu_item_parent`, `object_id`, thứ tự, URL (README §2); test so từng mục với baseline |
| Một bản DOM của menu (header = drawer trên mobile) | Desktop/mobile cùng liên kết; không nhân đôi 36 mục |
| Dùng lại các cỡ ảnh WordPress đã có, không `add_image_size` | Không cần tạo lại thumbnail, không đụng thư viện media |
| WooCommerce chỉ qua hook/support, không ghi đè template | Cập nhật Woo/Yoast Woo/cổng thanh toán không vỡ |
| Trang chủ "nội dung trước, mục native chỉ thêm khi thiếu" | Không mất chữ SEO (P0) và không lặp nội dung (`docs/HOME-LAYOUT.md`) |
| Cầu nối shortcode đăng ký "tag còn trống" | Rollback sang Flatsome không xung đột |
| `featured_item` do plugin riêng, theme chỉ hiển thị | Đổi theme không làm mất URL `/bo-suu-tap/...`; plugin ghi lại cấu hình thật thay vì đoán |
| Không jQuery, JS 8,8 KB, một tệp `defer` | Chỉ nâng cấp tiệm tiến; trang vẫn dùng được khi tắt JS |
| Font tự host + fallback cùng metric | Không CDN, giảm CLS |

## Luồng dữ liệu trang chủ

`front-page.php` → `nt_get_rendered_content()` (the_content + cầu nối) → nếu có `<h1>` thì giữ, nếu không in hero native →
nội dung → từng mục native qua `nt_section_enabled()` (chế độ auto/on/off + `nt_content_covers()`).

## Giới hạn đã biết

- Cầu nối không thay thế UX Builder; các tag động/JS của Flatsome bị dọn (nội dung giữ lại).
- Chưa kiểm tra với plugin thật MotoAI/Moto QuickCall/Yoast Premium/GTranslate (không có trong môi trường build).
- Số đo Core Web Vitals thực tế cần dữ liệu người dùng thật; báo cáo hiệu năng chỉ là số đo phòng thí nghiệm kèm giới hạn.
