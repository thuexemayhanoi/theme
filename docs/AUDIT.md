# AUDIT P0 — hiện trạng và bằng chứng

Ngày lập: 2026-10-17 · Phạm vi: theme độc lập thay Flatsome + Flatsome Child cho https://thuexemaynguyentu.com/

> **LIVE_UNCHANGED = TRUE.** Không có thao tác ghi nào lên website thật trong toàn bộ phiên làm việc.

## 1. Nguồn dữ liệu và mức tin cậy

| Nguồn | Cung cấp | Mức tin cậy |
| --- | --- | --- |
| `README.md`, `CLAUDE.md`, `docs/SEO-HULK.md` (chủ dự án) | Yêu cầu, ràng buộc, cổng nghiệm thu | Hợp đồng công việc |
| `docs/wordpress-baseline-2026-10-17.json` | ID, parent, menu, term, tiêu đề, số lượng (kiểm kê chỉ đọc của chủ dự án, 2026-10-17) | Cao cho ID/cấu trúc. **Không** chứa permalink bài viết, metadata Yoast, schema, nội dung thô trang #562 |
| "Dữ liệu chuẩn" của chủ cửa hàng (NAP, giờ mở cửa, bảng giá tham khảo, chính sách cọc) | Nội dung hiển thị thật | Cao, đã được chủ cửa hàng xác nhận |
| Website thật | Canonical, robots, title, description, JSON-LD, sitemap, HTML | **KHÔNG TRUY CẬP ĐƯỢC** từ môi trường build |
| WPVibe / WP Agent | Kiểm kê trực tiếp | Không có trong phiên |

### Vì sao không kiểm tra được website thật

Chính sách mạng của container trả về `HTTP 403` ở bước `CONNECT` cho `thuexemaynguyentu.com`, `wordpress.org` và `downloads.wordpress.org`
(xem `curl -sS $HTTPS_PROXY/__agentproxy/status`, mục `recentRelayFailures`). Tôi **không** tìm cách vượt chính sách này. Hệ quả:

- Mọi trường SEO "trước" (canonical, robots, title, description, JSON-LD, H1, liên kết nội bộ, sitemap, robots.txt) là **UNVERIFIED**:
  `reports/seo-before.json` ghi rõ `status: UNVERIFIED` và lý do.
- Cổng `seo-diff-live` trong `reports/release-gates.md` là **NOT_TESTED**, kèm cách chạy thủ công
  (`tools/seo/snapshot.mjs` hoặc workflow `seo-snapshot-live`, chỉ chạy tay, chỉ GET).
- Nội dung thô của trang #562 (~40.656 byte shortcode Flatsome) không đọc được. Cầu nối shortcode xây theo cú pháp công khai và danh sách
  tag trong README; độ phủ thật phải đo trên staging bằng `wp nguyentu legacy-scan`.

## 2. Những gì đã xác minh từ baseline (số liệu tính lại bằng script)

- WordPress 7.1.3, PHP 8.2.34, theme đang dùng `flatsome-child` 3.0 (parent `flatsome` 3.18.7). Permalink `/%postname%/`.
- Trang chủ = Page #562 (`show_on_front=page`, `page_for_posts=0`). `/blog/` là **category #69**, không phải Page.
- **Menu #67 "Main chính": 36 mục, 3 cấp** — 6 mục gốc (Trang Chủ, Giới Thiệu, Blog, Loại Xe, Bảng Giá, Liên Hệ), 15 mục cấp 2, 15 mục cấp 3.
  Loại mục: 16 `custom`, 15 `taxonomy`, 5 `post_type`. Nhánh Blog có 7 con và 15 cháu (nhánh sâu nhất → hiển thị dạng mega panel).
  Gán ở `primary` và `primary_mobile`. Menu #66 "Secondary" **trống**, gán ở `footer` và `top_bar_nav`.
- **26 category bài viết**: 2 gốc (Blog #69, Uncategorized #1), 7 cấp 1, 17 cấp 2. Tổng `count` = 161 (160 bài, đúng 1 bài thuộc 2 category).
- `product_cat`: 70 Loại Xe → 71 Xe Số (4), 72 Xe Ga (6), 73 Xe Điện (3); 13 sản phẩm.
- `featured_item_category`: 77 Bảng Giá → 78 Thuê Ngày, 79 Thuê Tuần, 80 Thuê Tháng (mỗi nhóm 5 mục, cả 15 mục cũng thuộc #77). URL term `/bo-suu-tap/{slug}/`
  → taxonomy có rewrite slug `bo-suu-tap` và **không** hierarchical trong URL.
- Plugin cần kiểm tra: WooCommerce, Yoast SEO + Premium, LiteSpeed Cache, Contact Form 7, GTranslate, MotoAI Chat Widget,
  Moto QuickCall Pro+ Social ("Flatsome Safe v4"), WPCode Lite, Site Kit.

## 3. Chưa xác minh (không được đoán)

| Mục | Vì sao quan trọng | Cách giải quyết trong dự án |
| --- | --- | --- |
| Permalink của 15 `featured_item` (single) | Là money page đang có thứ hạng; baseline chỉ có `post_name` | Plugin `nguyentu-legacy-compat` **ghi lại** cấu hình đăng ký đang chạy rồi đăng ký lại y hệt; lệnh `wp nguyentu-compat export-urls / compare / verify` chứng minh URL trước = sau |
| Canonical / robots / title / description / schema hiện tại | Yoast đang sở hữu; mọi lệch là P0 | Theme không in lại các thẻ này; `tools/seo` chụp và so sánh khi có quyền truy cập |
| Cấu hình breadcrumb Yoast, Local SEO, schema từ WPCode | Tránh schema trùng | Theme chỉ **bổ sung thuộc tính còn thiếu** vào nút Organization của Yoast (không tạo thực thể thứ hai); cổng `jsonld-one-local-business` kiểm tra |
| CSS/JS của MotoAI và QuickCall | Có thể phụ thuộc Flatsome | Theme không vẽ thanh liên hệ cố định thứ hai khi QuickCall hoạt động; kiểm tra bằng bản mô phỏng bố cục; plugin thật = NOT_TESTED |
| Nội dung thô trang #562, #310, #372, #424, #432, #494 | Nguy cơ lộ shortcode thô | Cầu nối + `legacy-scan` trên staging |
| Page template đã chọn cho từng trang (Flatsome `page-blank`...) | Template không tồn tại sẽ rơi về `page.php` | Chấp nhận (an toàn); ghi trong checklist staging để so ảnh chụp |

## 4. Danh sách URL trọng yếu

`reports/seo-urls.csv` — sinh bằng `node tools/seo/make-urls-csv.mjs` từ baseline (59 đường dẫn): trang chủ, 26 category, 10 page
(shop và các trang README), 4 term bảng giá, 4 product_cat, 13 sản phẩm, trang tìm kiếm, `/404-invalid-nonexistent/` (phải là 404).
Không có trong baseline nên **không liệt kê**: 160 bài viết và 15 `featured_item` single. Với site thật, `tools/seo/discover.mjs` lấy chúng từ
sitemap (`post-sitemap.xml`, `featured_item-sitemap.xml`).

## 5. Sổ rủi ro P0

| # | Rủi ro | Giảm thiểu | Nơi triển khai | Bằng chứng |
| --- | --- | --- | --- | --- |
| 1 | Shortcode Flatsome hiện thô khi tắt Flatsome | Cầu nối render + dọn tag lạ, giữ nội dung | `inc/legacy/` | Gate `legacy-render`, `no-raw-shortcodes` |
| 2 | `featured_item` mất đăng ký → 404 `/bo-suu-tap/...` | Plugin capture-then-replay | `plugins/nguyentu-legacy-compat/` | Gate `featured-items` |
| 3 | Mất gán menu → header trống | Locations cùng slug, điền vị trí trống khi kích hoạt, fallback theo ID 67/66 | `inc/menus.php` | Gate `menu-36` |
| 4 | Trùng/thiếu thẻ SEO | Theme không in canonical/meta/OG/JSON-LD khi có plugin SEO | `inc/seo.php`, `inc/schema.php` | `StructureTest`, gate `seo-rules-sandbox` |
| 5 | H1 đôi hoặc mất H1 | Dựng nội dung trước, chỉ in H1 khi nội dung chưa có | `nt_should_print_h1()` | Gate `seo-rules-sandbox` (`h1-one`) |
| 6 | Hai thanh liên hệ chồng nhau | Thanh của theme chỉ hiện khi không có QuickCall | `nt_show_cta_bar()` | Gate `e2e-contact` |
| 7 | CLS/LCP | Font tự host + fallback metric, ảnh có kích thước, LCP `fetchpriority`, không jQuery | `assets/`, `inc/enqueue.php` | Gate `budgets`, `lighthouse` |
| 8 | Tuyên bố sai (giá, đánh giá, 24/7...) | Chỉ dùng dữ liệu chuẩn, test cấm cụm từ | `inc/business.php` | `StructureTest::test_banned_marketing_claims...` |
