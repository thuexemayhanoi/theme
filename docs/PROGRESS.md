# PROGRESS — Nguyễn Tú Editorial Commerce (theme độc lập v1)

> Nhánh `feat/nguyentu-independent-theme-v1` · Repo `thuexemayhanoi/theme`
> **LIVE_UNCHANGED = TRUE** — không có thao tác nào chạm vào site thật `https://thuexemaynguyentu.com/`:
> không kích hoạt theme, không đổi plugin, không ghi DB/option, không đổi menu/term/slug/media, không bật webhook, không merge lên production.
> Mọi kiểm thử chạy trên **sandbox dùng một lần** (WordPress 7.1.3 + WooCommerce 11.2.1 + Yoast SEO 28.7 + MariaDB, dữ liệu mô phỏng có đúng ID của bản audit).

Tệp này được viết lại ở mỗi mốc. Bảng cổng chi tiết (PASS / FAIL / NOT_TESTED) do máy sinh: `reports/release-gates.md`. Không số liệu nào ở đây được ước đoán.

## 1. Kết luận ngắn (cập nhật 2026-10-18)

| Mục | Trạng thái |
| --- | --- |
| Cổng phát hành | **PASS 32 · FAIL 0 · NOT_TESTED 5** (`reports/release-gates.md`) |
| Sẵn sàng go-live? | **Chưa.** 4 cổng chặn là NOT_TESTED vì cần thứ môi trường build không có: site thật (bị chặn 403), nội dung thật của trang #562, plugin Moto QuickCall và MotoAI thật |
| Điểm "98/100 làm đẹp", "99/100 SEO" | **Không đo được, không tuyên bố.** Có số đo thật (Lighthouse lab, axe, hơn 300 kiểm thử tự động) ở các báo cáo dưới đây |
| PR | https://github.com/thuexemayhanoi/theme/pull/1 (chi tiết ở mục 8) |

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
| PHPUnit unit (thuần, không WordPress) | PASS — 81 test | `tools/qa/run-all.sh` |
| PHPUnit integration trên WordPress thật | PASS — 62 test, 890 assertion | `reports/integration-results.md` |
| Node test công cụ SEO và cổng QA | PASS — 30 test | `tests/node` |
| Menu 36 mục, 26 category, product_cat, featured_item 15+4 | PASS | cổng `menu-36`, `categories-26`, `product-terms`, `featured-items` |
| Vòng ghi-lại/phát-lại `featured_item` (bật/tắt mô phỏng theme cũ) | PASS — 9 bước | `reports/compat-roundtrip.md` |
| Luật SEO tuyệt đối trên 86 URL sandbox | **P0 = 0** (43 URL P1: Yoast của sandbox chưa có mô tả meta) | `reports/seo-rules-after.md` |
| Kiểm tra liên kết nội bộ | 236 đích, 0 lỗi non-2xx | `reports/internal-links.csv` |
| Playwright (Chromium thật) 360/390/430/768/1280/1440 px | PASS — layout, menu, blog, woo, seo-head, contact (bật/tắt mô phỏng plugin), search/404 | `reports/e2e-results.md` |
| axe-core WCAG 2.2 A/AA ở 390 và 1280 px, 10 route | PASS — không lỗi critical/serious (widget plugin bên thứ ba được loại ra, có ghi chú) | `reports/e2e-results.md` |
| Lighthouse 12.8 mobile (lab, máy chủ cục bộ) | trang chủ 87, bài 91, danh mục 93, sản phẩm 79; a11y 100, best-practices 100 | `reports/performance.md` |
| Cùng phép đo với tùy chọn "Script jQuery của WooCommerce" bật | trang chủ 97, bài 98, danh mục 99 | `reports/performance.md` |
| Schema JSON-LD | một `@graph`, một LocalBusiness, không trùng `@id`, không rating/review | `reports/structured-data.md` |
| Log PHP/WordPress của sandbox (WP_DEBUG_LOG) trong cả lượt chạy | 0 cảnh báo, thông báo hay deprecated (không riêng code theme/plugin: toàn bộ log trống) | `reports/php-runtime-log.md` |
| Ảnh chụp 360/768/1440 (theme một mình, và 3 trang có mô phỏng QuickCall + MotoAI) | 42 ảnh JPEG | `reports/screenshots/` |

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

## 4b. Vòng review đầu tiên của bot Codex trên PR #1 (9 phát hiện, đều đúng, đã sửa)

Bot review commit `2182d45`. Từng phát hiện được đối chiếu với mã trước khi sửa; mỗi bản sửa có test **thất bại trên mã cũ** (đã thử bằng cách khôi phục tạm mã cũ) và đạt trên mã mới.

| Phát hiện | Kiểm chứng | Sửa | Test chứng minh |
| --- | --- | --- | --- |
| P1 `run-all.sh`: job `static` xanh dù cổng hỏng | Tái hiện: thêm tệp PHP lỗi cú pháp → `php-lint` FAIL nhưng script thoát 0 | `step` ghi nhớ lỗi, `release-gates.mjs` thoát 1 khi có cổng FAIL, script thoát 1 | Trước: thoát 0. Sau: thoát 1; khi khỏe thoát 0 |
| P1 `run-sandbox.sh`: job `sandbox` xanh dù cổng hỏng | Đọc mã: chỉ `echo` khi PHPUnit/Playwright lỗi, bộ tổng hợp luôn thoát 0. Thêm: kết quả cổng được commit sẵn nên bước chết giữa chừng để lại PASS cũ | Mỗi bước lỗi vào danh sách, cuối cùng thoát 1 (không dừng giữa chừng để mọi báo cáo vẫn được ghi); lỗi hạ tầng (DB, đồng bộ, kích hoạt) dừng ngay; kết quả cũ của cổng bị xóa trước khi chạy, bước không báo cáo = NOT_TESTED | Có cổng FAIL ghi sẵn: thoát 1; khỏe: thoát 0 |
| P1 `junit-gates.mjs`: test bị bỏ qua tính là PASS | Đọc mã | Test bị bỏ qua không tính; cổng chỉ toàn test bị bỏ qua = NOT_TESTED (`tools/qa/lib/junit.mjs`) | 6 node test |
| P2 `header.php`: menu di động riêng xếp chồng dưới menu chính | Đọc CSS: `.nt-nav__mobile-only` chỉ ẩn ở desktop | `nav.nt-nav--split`; dưới 1024 px ngăn kéo chỉ hiện menu di động, desktop giữ menu chính | 4 e2e trên stylesheet thật; 2 test thất bại trên CSS cũ |
| P2 `front-page.php`: dải thông tin nhanh không bao giờ hiện khi nội dung có H1 | Đọc mã | `nt_home_facts_placement()`: "Luôn hiện" in dải dưới nội dung, "Luôn ẩn" tắt, **"Tự động" cố ý không thêm gì** cạnh hero có sẵn (giờ mở cửa đã có ở mục địa chỉ). Không bao giờ thêm H1 | 12 unit + e2e ba chế độ; e2e thất bại trên mẫu cũ |
| P2 `inc/related.php`: tầng "chủ đề cha" không thấy bài ở danh mục con | `category__in` chỉ khớp gán trực tiếp; dữ liệu sandbox trả 11 thay vì 12 bài | Tầng cha dùng `tax_query` có `include_children` | Test tích hợp theo dữ liệu; mã cũ 11 ≠ 12 |
| P2 `inc/lib/html.php`: id mục lục sinh ra có thể trùng id tự đặt ở phía sau | Tái hiện bằng unit test | Giữ chỗ mọi id có sẵn (mọi phần tử) rồi mới sinh id thiếu | 2 unit test thất bại trên mã cũ |
| P2 `single-featured_item.php`: mất breadcrumb khi nội dung rộng có H1 riêng | Đọc mã, so với `page.php` | `nt_pagehead_mode()` / `nt_content_is_wide()` dùng chung cho cả hai template | 4 unit (4 tổ hợp) + e2e xuất bản một mục thử rồi xóa; e2e thất bại trên mẫu cũ |
| P2 e2e bảng: test không thể thất bại (`found \|\| true`, không bao giờ mở bài) | Đọc mã: không có `page.goto` | Xuất bản một bài có bảng 12 cột, mở ở 390 px, kiểm tra vùng cuộn, không tràn ngang, cuộn bằng bàn phím; xóa bài sau khi chạy | Thất bại khi cố ý phá CSS của vùng cuộn |

Cùng đợt: CI chỉ chạy một lần mỗi lần push (trước đây nhánh có PR chạy hai lần: sự kiện push và pull_request).

## 5. Chưa kiểm tra / giới hạn / blocker (không được coi là PASS)

1. **Site thật bị chặn từ môi trường build** (HTTP 403 tại CONNECT cho `thuexemaynguyentu.com`; không vượt chính sách). Mọi trường SEO "trước" là `UNVERIFIED` (`reports/seo-before.json`). Chạy workflow `seo-snapshot-live` hoặc `tools/seo/snapshot.mjs` từ mạng được phép rồi `diff.mjs` để có P0 thật.
2. **Nội dung thật của trang #562** (≈40 KB shortcode) không đọc được khi không đăng nhập: cầu nối được xây từ cú pháp công khai; độ phủ thật phải đo bằng `wp nguyentu legacy-scan` trên bản sao DB (cổng `flatsome-real-content`).
3. **Moto QuickCall, MotoAI, Contact Form 7, GTranslate, LiteSpeed Cache, Yoast Premium** không có trong môi trường build: chỉ có bản mô phỏng bố cục (QuickCall/MotoAI) và CSS dự phòng (CF7). Cổng `quickcall-real`, `motoai-real`, `yoast-premium` là NOT_TESTED.
4. **Slug rewrite thật của `featured_item`** không có trong baseline (chỉ có URL term): plugin ghi lại cấu hình runtime thật thay vì đoán; cơ chế đã được chứng minh bằng mô phỏng, giá trị thật phải `verify --probe` trên staging.
5. **Core Web Vitals thật** (CrUX/RUM) chỉ có sau khi chạy trên site thật; số lab ở `reports/performance.md` không thay thế.
6. Gói ngôn ngữ `vi` không cài được trong sandbox, nên nhãn WooCommerce hiện tiếng Anh ở ảnh chụp; trên site thật hiển thị theo gói ngôn ngữ đã cài.
7. Widget mặc định của WordPress (Archives, Categories) được chuyển vào chân trang khi đổi theme; staging cần dọn (có trong checklist).

## 5b. Việc còn lại (theo thứ tự ưu tiên)

1. Ảnh chụp SEO "trước" thật + `diff.mjs` (mục 7) — mở khóa cổng `seo-diff-live`.
2. `wp nguyentu legacy-scan` trên bản sao DB production; bổ sung tag nào đang bị STRIPPED nếu quan trọng (cổng `flatsome-real-content`).
3. Thử Moto QuickCall và MotoAI thật trên staging (cổng `quickcall-real`, `motoai-real`); kiểm tra GTranslate và Contact Form 7 (đã có CSS dự phòng).
4. Quyết định bật tùy chọn *Script jQuery của WooCommerce* sau khi kiểm tra WPCode/plugin trên staging (trang chủ Lighthouse lab 87 → 97).
5. Ứng viên tối ưu tiếp, **chưa làm** vì đụng template WooCommerce: nạp trễ script thư viện ảnh sản phẩm (FlexSlider/zoom/PhotoSwipe, ~70 KB) để trang sản phẩm đạt mức như các trang khác.
6. Thay `screenshot.png` bằng ảnh chụp từ staging khi đã có nội dung thật.

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

- **2026-10-17 → 18:** dựng theme, plugin tương thích, bộ công cụ SEO/QA, sandbox WordPress thật. Lần chạy thật đầu tiên tìm ra 11 nhóm lỗi (mục 4).
- **2026-10-18, PR #1 (https://github.com/thuexemayhanoi/theme/pull/1):** CI GitHub trên runner sạch, commit `2182d45`. Log của job `sandbox` ghi `e2e-layout`, `e2e-menu`, `e2e-contact`, `e2e-woo`, `e2e-blog`, `e2e-seo-head`, `a11y-axe`, `e2e-search-404`, `php-runtime-log` đều PASS, và kết luận chỉ còn 4 cổng chặn NOT_TESTED, tức mọi cổng chặn khác (tích hợp, menu, danh mục, featured_item, luật SEO, liên kết) là PASS. **Nhưng** khi đó cả hai script thoát 0 dù cổng hỏng (phát hiện P1 của Codex), nên "job xanh" chưa phải bằng chứng; bằng chứng là các dòng PASS trong log. Với job `static` chưa có bằng chứng tương đương: lần chạy tới (script đã sửa) là lần đầu có ý nghĩa.
- **2026-10-18, sau review của Codex:** 9 phát hiện đều đúng và đã sửa (mục 4b). Chạy lại toàn bộ trên sandbox với script đã sửa: thoát 0, "mọi bước thành công và không cổng nào FAIL", PASS 32 · FAIL 0 · NOT_TESTED 5 (cùng 4 cổng chặn như cũ).

Chi tiết từng commit nằm trong lịch sử nhánh; PR do chủ site duyệt.


## 2026-10-19 — Permanent Footer SEO HULK V1 (PR #5)

- **Repository/branch:** thuexemayhanoi/theme / `feat/nguyentu-permanent-footer-seo-hulk-v1`. PR: https://github.com/thuexemayhanoi/theme/pull/5 (draft until P0 live parity is independently verified). **LIVE_UNCHANGED by this PR.**
- **Audit:** public homepage footer had 46 anchor instances / 44 unique URLs. Home and some other routes served differing footer versions, possibly cache/deployer inconsistency; current baseline is not a reliable deployment marker.
- **P0 discovered, not caused by footer:** `/bo-suu-tap/thue-ngay/` and `/bo-suu-tap/thue-tuan/` return 404 on current live, despite WP-generated `/featured_item_category/...` currently returning 200. Preserve old URLs; do not disguise the issue by editing permalinks in footer. Owner separately authorizes any rewrite/redirect repair.
- **Implemented:** permanent 4-column/footer-only PHP renderer and compact CSS; 9 service links, 7 indexable Hub links, 5 informational pages; all URLs resolved by verified WP IDs, with publish/public guards and deduplication, separate NAP vs contact-action modes; conservative hourly-consultation tagline; dynamic copyright year and deposit notice.
- **Live URL inventory:** `reports/footer-live-before-2026-10-19.csv` (46 observed anchors) and `reports/footer-curated-urls-2026-10-19.csv` (21 intended destinations returned HTTP 200; seven Hub URLs observed index/follow).
- **QA:** `tests/integration/FooterTest.php`, `tests/e2e/footer.spec.mjs` cover first-party public links, five viewport widths, semantic headings, NAP, no full menu tree, home/page/category/product/collection. GitHub PR CI result is the proof, not these statements. Final live post-deploy screenshots, Yoast diff, real QuickCall/MotoAI overlap, and legacy URL protection remain **UNVERIFIED / P0** until staging verification.
- **Scope guard:** no change to any other repository, WordPress posts/pages/categories, SEO metadata, permalink settings or workflows. Production deployment not requested/attempted by tooling.
- **Rollback:** revert the PR merge commit if subsequently approved; preserve prior theme and Hostinger backup. Detailed audit/release gates: `docs/FOOTER-SEO-HULK-V1.md`.


## 2026-10-19 — Legacy URL compatibility repair (PR #8)

- Merge `3edc3ba`; standalone plugin v1.1.0 released. Complete evidence and resume steps: `docs/LEGACY-URL-REPAIR.md`.
- Green CI: 72 integration tests, 53 recovery HTTP checks with zero failures, both partial-registration cases, 222 valid internal targets, browser/WooCommerce/SEO suites.
- Production predeployment backup stored in `nguyentu_legacy_url_predeploy_20261019` (39 legacy rules + original registration snapshot). No production rewrite flush or content/term/SEO edits.
- **Live plugin remains active v1.0.0; the old URL still returns 404.** Custom ZIP installation is pending because the connected installers only accept WordPress.org packages. Theme merge alone does not update the separate plugin. Do not claim live completion.
