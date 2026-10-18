# PROGRESS — Nguyễn Tú Editorial Commerce (theme độc lập v1)

> Nhánh `feat/nguyentu-independent-theme-v1` · Repo `thuexemayhanoi/theme`
> **LIVE_UNCHANGED = TRUE** — không có thao tác nào chạm vào site thật `https://thuexemaynguyentu.com/`:
> không kích hoạt theme, không đổi plugin, không ghi DB/option, không đổi menu/term/slug/media, không bật webhook, không merge lên production.
> Mọi kiểm thử chạy trên **sandbox dùng một lần** (WordPress 7.1.3 + WooCommerce 11.2.1 + Yoast SEO 28.7 + MariaDB, dữ liệu mô phỏng có đúng ID của bản audit).

Tệp này được viết lại ở mỗi mốc. Bảng cổng chi tiết (PASS / FAIL / NOT_TESTED) do máy sinh: `reports/release-gates.md`. Không số liệu nào ở đây được ước đoán.

## 1. Kết luận ngắn (cập nhật 2026-10-18)

| Mục | Trạng thái |
| --- | --- |
| Cổng phát hành | **PASS 31 · FAIL 0 · NOT_TESTED 5** (`reports/release-gates.md`) |
| Sẵn sàng go-live? | **Chưa.** 4 cổng chặn là NOT_TESTED vì cần thứ môi trường build không có: site thật (bị chặn 403), nội dung thật của trang #562, plugin Moto QuickCall và MotoAI thật |
| Điểm "98/100 làm đẹp", "99/100 SEO" | **Không đo được, không tuyên bố.** Có số đo thật (Lighthouse lab, axe, 400+ kiểm thử) ở các báo cáo dưới đây |
| PR | Xem mục 8 |

## 2. Đã làm được gì

**Theme độc lập** (không `Template:` cha, không gọi hàm Flatsome — có test chặn): thư mục gốc repo là theme để Deployer for Git cài trực tiếp.

- Trang chủ "nội dung trước": giữ nguyên H1/văn bản/liên kết của trang #562, chỉ **thêm** mục native khi nội dung chưa có (`docs/HOME-LAYOUT.md`).
- Cầu nối shortcode Flatsome (`inc/legacy`): 33 tag render, tag khác bị dọn nhưng giữ nội dung, không bao giờ lộ `[ux_*]` thô (`docs/LEGACY-COMPAT.md`).
- Menu #67 (36 mục, 3 cấp) bằng `wp_nav_menu()` + walker truy cập được; ngăn kéo mobile, mega panel desktop, bàn phím + `aria-expanded`.
- Blog: danh mục phân cấp 26 term, bài viết có mục lục từ tiêu đề thật, bài liên quan có trần và cache, trước/sau, chia sẻ, khung liên hệ.
- WooCommerce chỉ qua hook/support: lưới sản phẩm, trang sản phẩm 2 cột, giỏ hàng, thanh toán 2 cột, tài khoản; sản phẩm chưa có giá hiện "Liên hệ báo giá" + gọi/Zalo (không tự đặt giá).
- Liên hệ: Gọi / Zalo / Chỉ đường từ dữ liệu NAP chuẩn của chủ cửa hàng; đúng **một** thanh cố định trên mobile (tự nhường Moto QuickCall khi có).
- SEO: Yoast là nguồn duy nhất của title/meta/canonical/robots/sitemap/schema; theme chỉ bổ sung thuộc tính LocalBusiness đã xác minh vào nút Organization của Yoast; breadcrumb dùng của Yoast; không FAQPage/rating/review bịa.
- Plugin riêng `plugins/nguyentu-legacy-compat/`: giữ CPT `featured_item` + taxonomy bằng cách **ghi lại rồi phát lại** cấu hình thật (không đoán slug), có `verify`/`compare`/`export-urls`.
- Hiệu năng: không jQuery trong theme (JS 2,8 KB gzip), font tự host, CSS của WooCommerce chỉ nạp ở trang Woo, tùy chọn bỏ jQuery/script Woo khỏi trang không-Woo (mặc định tắt).
- Công cụ SEO có thật: `tools/seo/` (chụp trước/sau, luật tuyệt đối, diff P0/P1/P2, kiểm tra liên kết, báo cáo schema), CI (`.github/workflows`), ZIP cài đặt (`tools/build-*-zip.sh`), tài liệu deploy/rollback/staging.

## 3. Bằng chứng kiểm thử (chạy thật, không chỉ lint)

| Bộ kiểm thử | Kết quả | Xem ở |
| --- | --- | --- |
| PHP lint, PHPCS (WordPress), ESLint, CSS parse, ngân sách byte, tương phản token | PASS | `reports/results/*.json` |
| PHPUnit unit (thuần, không WordPress) | PASS — 68 test | `tools/qa/run-all.sh` |
| PHPUnit integration trên WordPress thật | PASS — 61 test, 882 assertion | `reports/integration-results.md` |
| Node test công cụ SEO | PASS — 24 test | `tests/node` |
| Menu 36 mục, 26 category, product_cat, featured_item 15+4 | PASS | cổng `menu-36`, `categories-26`, `product-terms`, `featured-items` |
| Vòng ghi-lại/phát-lại `featured_item` (bật/tắt mô phỏng theme cũ) | PASS — 9 bước | `reports/compat-roundtrip.md` |
| Luật SEO tuyệt đối trên 86 URL sandbox | **P0 = 0** (43 URL P1: Yoast của sandbox chưa có mô tả meta) | `reports/seo-rules-after.md` |
| Kiểm tra liên kết nội bộ | 236 đích, 0 lỗi non-2xx | `reports/internal-links.csv` |
| Playwright (Chromium thật) 360/390/430/768/1280/1440 px | PASS — layout, menu, blog, woo, seo-head, contact (bật/tắt mô phỏng plugin), search/404 | `reports/e2e-results.md` |
| axe-core WCAG 2.2 A/AA ở 390 và 1280 px, 10 route | PASS — không lỗi critical/serious (widget plugin bên thứ ba được loại ra, có ghi chú) | `reports/e2e-results.md` |
| Lighthouse 12.8 mobile (lab, máy chủ cục bộ) | trang chủ 87, bài 91, danh mục 93, sản phẩm 79; a11y 100, best-practices 100 | `reports/performance.md` |
| Cùng phép đo với tùy chọn "Script jQuery của WooCommerce" bật | trang chủ 97, bài 98, danh mục 99 | `reports/performance.md` |
| Schema JSON-LD | một `@graph`, một LocalBusiness, không trùng `@id`, không rating/review | `reports/structured-data.md` |
| Ảnh chụp 360/768/1440 | 42 ảnh JPEG | `reports/screenshots/` |

Giới hạn khi đọc các số trên: nội dung là mô phỏng, plugin QuickCall/MotoAI/Flatsome là bản mô phỏng bố cục, đo hiệu năng là lab trên `php -S` (không gzip, không cache, không CDN). Không phải dữ liệu thật, không phải cam kết thứ hạng.

## 4. Lỗi tìm được ở lần chạy thật đầu tiên (và đã sửa)

Đây là lần đầu theme chạy trong WordPress thật; kiểm thử tĩnh trước đó (lint, unit) không thấy các lỗi này.

| Lỗi | Hậu quả nếu không sửa | Sửa |
| --- | --- | --- |
| Lần chạy `the_content` lồng nhau (trích dẫn thẻ, widget) lấy mất khung bọc và `wpautop` của lần chạy ngoài | Trang có `[ux_portfolio]`/`[blog_posts]` mất định dạng đoạn văn | Trạng thái là ngăn xếp theo từng lần chạy; test có handler gọi lồng |
| `wpautop` chèn `<br />` và `</p>` thừa vào thẻ sản phẩm/bài viết sinh từ template | Khoảng trắng/HTML sai trong thẻ | Markup sinh sẵn được "đỗ tạm" khi `wpautop` chạy rồi trả lại nguyên vẹn |
| `[ux_image_box link=…]` bọc `<a>` quanh `<div>` → `wpautop` cắt thành liên kết rỗng | Liên kết không tên (axe `link-name`), khối không bấm được | Liên kết phủ + `aria-label` lấy từ tiêu đề |
| Header mobile 360 px: nút menu bị đẩy ra ngoài màn hình | **Không mở được menu trên điện thoại nhỏ** | Nút gọi thành icon dưới 640 px, logo gọn lại |
| Ngăn kéo menu nằm dưới lớp phủ (ngữ cảnh xếp chồng) | Chạm vào liên kết trong ngăn kéo chỉ đóng menu | Lớp phủ nằm trong header; thanh liên hệ hạ z-index |
| Menu desktop mở khi focus bàn phím lướt qua, `aria-expanded` sai | Phải Tab qua ~30 liên kết của mega panel | Mở bằng hover hoặc nút; `aria-expanded` phản ánh đúng nút |
| Float/% width của WooCommerce bóp thẻ sản phẩm, thư viện ảnh, form thanh toán | **Shop, sản phẩm, thanh toán vỡ giao diện** (thẻ rộng ~60 px) dù mọi test DOM vẫn xanh | Reset đúng độ ưu tiên; thêm test hình học; thanh toán 2 cột |
| Chữ hero dính mép trái màn hình (flex-basis ghi đè width) | Trang chủ trông lỗi | `flex: 0 1 auto`; test chữ không chạm mép |
| Tương phản: widget footer, giá Woo (olive), URL kết quả tìm kiếm | Vi phạm WCAG AA | Màu theo token; có test axe |
| Cờ `--http` của plugin trùng cờ toàn cục của WP-CLI | `verify` báo lỗi lạ | Đổi thành `--probe` |
| Test dựa dữ liệu sai (danh mục rỗng, XPath `contains(@class)` khớp lớp con, console 404 của tài liệu 404) | Test đỏ/giả | Sửa test theo dữ liệu thật của baseline |

## 5. Chưa kiểm tra / giới hạn / blocker (không được coi là PASS)

1. **Site thật bị chặn từ môi trường build** (HTTP 403 tại CONNECT cho `thuexemaynguyentu.com`; không vượt chính sách). Mọi trường SEO "trước" là `UNVERIFIED` (`reports/seo-before.json`). Chạy workflow `seo-snapshot-live` hoặc `tools/seo/snapshot.mjs` từ mạng được phép rồi `diff.mjs` để có P0 thật.
2. **Nội dung thật của trang #562** (≈40 KB shortcode) không đọc được khi không đăng nhập: cầu nối được xây từ cú pháp công khai; độ phủ thật phải đo bằng `wp nguyentu legacy-scan` trên bản sao DB (cổng `flatsome-real-content`).
3. **Moto QuickCall, MotoAI, Contact Form 7, GTranslate, LiteSpeed Cache, Yoast Premium** không có trong môi trường build: chỉ có bản mô phỏng bố cục (QuickCall/MotoAI) và CSS dự phòng (CF7). Cổng `quickcall-real`, `motoai-real`, `yoast-premium` là NOT_TESTED.
4. **Slug rewrite thật của `featured_item`** không có trong baseline (chỉ có URL term): plugin ghi lại cấu hình runtime thật thay vì đoán; cơ chế đã được chứng minh bằng mô phỏng, giá trị thật phải `verify --probe` trên staging.
5. **Core Web Vitals thật** (CrUX/RUM) chỉ có sau khi chạy trên site thật; số lab ở `reports/performance.md` không thay thế.
6. Gói ngôn ngữ `vi` không cài được trong sandbox, nên nhãn WooCommerce hiện tiếng Anh ở ảnh chụp; trên site thật hiển thị theo gói ngôn ngữ đã cài.
7. Widget mặc định của WordPress (Archives, Categories) được chuyển vào chân trang khi đổi theme; staging cần dọn (có trong checklist).

## 6. Xem thử trên staging (một lượt)

1. Tạo staging là **bản sao** (files + DB) của production, chặn index và chặn email/thanh toán thật.
2. Cài ZIP `dist/nguyentu-legacy-compat.zip` và kích hoạt **khi Flatsome Child còn chạy**; `wp nguyentu-compat status` phải là `snapshot_source: runtime`.
3. Cài ZIP `dist/nguyentu-editorial-commerce.zip` (hoặc Deployer for Git trỏ vào repo), kích hoạt trên staging; `wp rewrite flush` một lần.
4. Đi theo `docs/STAGING-CHECKLIST.md` (mục B–E): menu 36 mục, `verify --probe`, chụp SEO sau rồi `diff.mjs` (P0 phải bằng 0), 3 thiết bị, plugin thật.
5. Chỉ khi checklist ký xong và chủ site đồng ý mới go-live theo `docs/DEPLOY.md`; rollback trong `docs/ROLLBACK.md` (kích hoạt lại Flatsome Child, bỏ plugin tương thích).

## 7. Lệnh tiếp tục

```bash
git fetch origin feat/nguyentu-independent-theme-v1 && git checkout feat/nguyentu-independent-theme-v1
composer install --prefer-source && npm ci
bash tools/qa/run-all.sh                       # cổng không cần sandbox (8 cổng, ~10 giây)
bash tools/sandbox/setup.sh && bash tools/sandbox/seed.sh   # dựng sandbox (một lần)
bash tools/qa/run-sandbox.sh --reset           # integration + SEO + e2e + ảnh chụp (~12 phút)
```

**Một bước tiếp theo:** chạy `seo-snapshot-live` (hoặc cho phép host `thuexemaynguyentu.com` ra khỏi môi trường build) để có ảnh chụp SEO "trước" thật; đó là cổng chặn duy nhất mà code không tự giải quyết được.

## 8. Nhật ký

Chi tiết từng commit nằm trong lịch sử nhánh; PR do chủ site duyệt.
