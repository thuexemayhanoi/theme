> **LATEST PRODUCTION STATUS — 2026-10-21:** This file retains an earlier build/staging record, including an obsolete `LIVE_UNCHANGED` assertion from before the theme was deployed. It is **historical, not current production evidence**. Read [CURRENT-PRODUCTION.md](CURRENT-PRODUCTION.md) and the P0 owner freeze in [AGENTS.md](../AGENTS.md) before acting. Do not rerun the previous migration or treat the old `NOT_TESTED` checks as results from the current live installation.

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

## 2026-10-20 — Compact menu and footer labels

- Owner requested short labels of 2–3 words throughout menu/footer, with every existing link preserved. `inc/navigation-labels.php` supplies one display-only mapping for the shared walker and footer resolver; unknown labels pass through. No database, permalink, category, title/H1, metadata or link target writes.
- Footer group headings shortened to “Thuê xe”, “Cẩm nang”, “Thông tin”; casing made consistent. Stored menu order, hierarchy and object IDs remain authoritative.
- Local validation: ESLint, 48 Node tests, CSS parsing, asset budgets and 33 contrast checks passed. PHP is unavailable locally, so PHP lint, standards and unit/integration checks run in GitHub CI. Existing footer assertions updated; menu regression compares all rendered hrefs and stored values.
- Captured production menu/footer hrefs and homepage SEO before deployment. LIVE_UNCHANGED at this checkpoint; deploy through the existing main webhook after CI, then purge cache and compare the same links and SEO on live.
- Rollback: revert this change through the existing deployment path; no database restoration is needed.


## 2026-10-21 — Homepage audit, baseline and native estimate repair
- User explicitly authorized audit → fix → QA → publish in /theme only. No URL, content, menu target or SEO option mutations.
- Git checkpoint: checkpoint/homepage-audit-2026-10-21 at 27bc96b5a66140df1ead75c884170e462b259d44.
- Live baseline: mobile Lighthouse Performance 69, Accessibility 100, Best Practices 96, SEO 100; LCP 5.3 s, CLS 0, TBT 0 ms. No field INP data.
- P1 reproduced: installed Smart Core calculator multiplies daily rates for 7/30 days (Wave 1,050,000 / 4,500,000 VND); repo's newer plugin is not installed by a theme deploy.
- Repair in theme: native component consumes nt_price_rows() for day/week/month/deposit. Known plugin hook removed only when rendering the native price component; plugin shortcode remains available.
- P2: native estimate dark surface, opening dot green, minute-aligned hours refresh, footer navigation labels use paragraphs.
- Exact native JavaScript executed with DOM stubs in host V8: 9 behavioral tests passed. Full WordPress/PHP/browser CI still pending at this checkpoint.
- LIVE_UNCHANGED = TRUE at branch creation. Do not infer deployment from a successful Git push.


## 2026-10-21 — Homepage audit release verified
- Runtime deployed: a8b7db20cd407f1b8397b774d84795179cc85492; PR #34 merge 376a0a5d7d07c5ee644593a30d5f07f5aa319bf4.
- Auto-repair: assigned but hidden footer widgets must not retain YITH wishlist assets; match the existing nguyentu_enable_footer_extra display filter.
- Final full CI PASS: https://github.com/thuexemayhanoi/theme/actions/runs/39472417682 — 87 integration tests / 1,244 assertions; 224 browser tests; 42 screenshot cases. Static checks PASS.
- Final live Chromium QA PASS: https://github.com/thuexemayhanoi/theme/actions/runs/39472417529 at 390/768/1440 widths. Menu 42/footer 31 URLs and metadata unchanged; pricing, dark reload, hours, drawer, chat context/QuickCall and Back to Top verified.
- Live image/proof artifact: https://github.com/thuexemayhanoi/theme/actions/runs/39472417529/artifacts/12288575069.
- Lighthouse after: mobile Performance 94, Accessibility 100, Best Practices 100, SEO 100; LCP 1.8 s, CLS 0. Desktop Performance 100, Accessibility 97, Best Practices 96, SEO 100; LCP 0.4 s, CLS 0.
- Remaining: desktop target-size/name-label warnings and Lighthouse console flag, unused assets, older Yoast Person/Article fields. Native iPhone/iPad Safari and real keyboard untested; no CrUX/INP field data. No fabricated overall QA score.
- Full report: reports/homepage-audit-2026-10-21.md; machine-readable evidence: reports/homepage-audit-results-2026-10-21.json.
- LIVE_UNCHANGED = FALSE. Only repository theme code and authorized cache purge changed production; no content/taxonomy/menu destination/SEO option writes.


## 2026-10-21 — Follow-up: inspect open UI before closing desktop P2 findings
- Checkpoint: checkpoint/homepage-p2-2026-10-21 at 20811b6537a379533cfff452f56210d0682481f7.
- Initial public-page diagnostics at 1365/390 passed the two named axe rules and had no console/HTTP errors. Inspecting the opened chatbot then reproduced serious label-content-name-mismatch on button[data-nt-chat-clear].
- Reproduction: https://github.com/thuexemayhanoi/theme/actions/runs/39488703698. Visible text "Xóa ghi nhớ" differed from aria-label "Xóa bộ nhớ tư vấn trên thiết bị".
- Minimal candidate fix: aria-label now starts with the exact visible text. No chat logic, stored data, URLs or SEO metadata changes.
- Add opened-panel axe checks to all five native assistant viewport tests and public live QA. Diagnostic workflow remains read-only and records light/dark/search/drawer/QuickCall/chat states.
- Full CI and deployed verification are pending for this candidate. LIVE_UNCHANGED = TRUE at this checkpoint.


## 2026-10-21 — iPhone inner-page legacy defects: 1 URL per cycle
- Source: six real Safari iPhone screenshots supplied by the owner. Five distinct pages: /lien-he/ (#372), /faq/ (#484), /gioi-thieu/ (#310), /gioi-thieu/ve-chung-toi/ (#424), /gioi-thieu/su-menh-tam-nhin/ (#432).
- Observed defects: original Flatsome shortcode styles rendered inside the native theme, creating inline black/white backgrounds that conflict with light/dark tokens; excessive legacy gaps, mobile type, oversized or unrelated artwork; illegible CTA labels.
- First isolated change, **/lien-he/** only: branch fix/legacy-contact-iphone-2026-10-21. CSS scoped to #post-372 in assets/css/legacy.css, normalizes surface/text contrast, padding and title on phones/tablets/desktops, and hides the **second archival Moto Open map only in this contact layout** without deleting WP content/media. Contact Form 7, valid primary map, phone/Zalo, permalink, SEO and global theme remain intact.
- Added tests/e2e/contact.spec.mjs regression for light/dark computed contrast, overflow, safe padding and extraneous embedded map at 390/768/1440.
- State: **PENDING QA/CI and merge** at this checkpoint; not yet verified on the live site. Do not claim PASS before checking PR CI and real WordPress.
- Next URLs: FAQ #484 (unrelated giant legacy image), introduction #310 (giant old banner), about-us #424 (black text on dark and oversized typography), mission #432 (white/light panel contrast and banner). Each URL requires its own scoped fix, CI and live verification; do not modify original WordPress post bodies, SEO fields, slugs or media library.
- Automation: hourly, process one page per run, consult this checkpoint, stop when all five pass and owner-facing Safari findings are resolved or explicitly marked as requiring human device verification.


## 2026-10-21 — Contact page #372: correct map selector and fixture regression
- Continued existing PR #36 on branch `fix/legacy-contact-iphone-2026-10-21`; no new PR or URL created.
- Root cause of the previous six `e2e-contact` failures: WordPress sandbox fixture `[map]` did not render either of the two production `.nt-lg-html` map iframes; assertion expected two. The production page has a primary Nguyễn Tú embed and an archived Moto Open embed.
- CSS candidate changed from `.nt-lg-html:last-child` (could hide the only valid map) to `.nt-lg-html + .nt-lg-html:last-child`, which hides only a later adjacent embed in the same map column.
- E2E now reconstructs two wrappers **only when sandbox has no iframe**, requires primary visible / archived hidden, and removes the archived wrapper to verify the remaining map stays visible. Preserves 390/768/1440 light/dark contrast, overflow and padding checks.
- Status: candidate commits 9acc1a32, cb483a8d, 448db4ee pushed; full CI/merge/live verification **NOT YET VERIFIED** at checkpoint. Continue only when both CI jobs PASS, then purge LiteSpeed and test published `/lien-he/`. Keep original WordPress post, Contact Form 7, permalink and SEO untouched.

### 2026-10-21 — Static-page body consistency (owner-authorized release)

- Current WP inventory confirmed: 10 published Pages. Scope is eight informational pages (310, 424, 432, 372, 484, 326, 449, 494); homepage 562 and shop 3040 are excluded.
- Fresh public baseline: `reports/static-pages/before-2026-10-21.json`, including exact titles, descriptions, canonicals, robots, H1s and header/footer destinations.
- Root causes: contact and collection still render UX Builder presentation; FAQ has a decorative full-width image; document-page spacing was only defined in blog.css, which static pages do not enqueue.
- Candidate: shared pages.css reading frame; native contact/collection presentation with central business data and real term links; static-page-only decorative image removal preserving functional form images/SVG icons. Contact Form 7 uses the original saved shortcode once. An editor-authored replacement body takes priority over native legacy templates.
- Stored content, media, Yoast metadata, parent/child relationships, permalinks and all other templates are unchanged. Rollback: revert this release's theme files; no database restore needed.
- Local checks: ESLint, CSS parsing, asset budgets and 57 Node tests passed. PHP and real WordPress/browser gates are delegated to repository CI because this workspace has no PHP runtime. Browser UI verification on the public site is required after deployment; native Safari remains untested.
- LIVE_UNCHANGED = TRUE at candidate checkpoint. Owner explicitly requested testing, push and WordPress publication in this session; no new approval is required once gates pass.

### 2026-10-21 — Static pages published and verified on WordPress

- PR #37 merged as `ff8575ef27602f0f91c169d14d8b31a2a8bb9a20`. Runtime PHP/CSS is unchanged by the later live-QA timing fix `f37498c65a063e9f0b0c525a54c9ff87b1688689`.
- Full repository CI passed on the release: runs 39542482605 (branch) and 39542852690 (merged main), including the 91-test WordPress integration suite, browser/accessibility checks, SEO rules and legacy URL recovery.
- WordPress theme files arrived through the existing deployment path, but LiteSpeed initially served old page HTML. The eight already-public Pages (310, 424, 432, 372, 484, 326, 449, 494) were republished via WP Agent, refreshing modified timestamps and page caches. Their post_content, media, URLs, parent relationships and Yoast field values were not rewritten.
- Live QA **PASS**: run 39543853063, artifact 12320463429, tested commit `f37498c65a063e9f0b0c525a54c9ff87b1688689`. Thirty page/viewport visits: eight target pages plus home/shop exclusions at 390/768/1440px. Forty-eight light/dark target-page states passed the geometry and 4.5:1 contrast checks. All ten page titles, descriptions, canonical/robots/H1 values and menu/footer URL lists match the fresh baseline. Every target body has no decorative images; one CF7 form and its hidden fields remain.
- Live QA waits for CSS color transitions to finish before measuring contrast; it verifies rendered card geometry because LiteSpeed combines CSS and removes individual stylesheet link IDs. The earlier failures were cache/timing observations, not a reduced contrast threshold.
- Homepage live QA also passed: run 39542852730. Homepage/shop photos remain available and neither uses the new static-page marker.
- `robots.txt` text is identical. Sitemap index and page sitemap keep the same URL sets; republishing legitimately changes last-modified timestamps/order. Evidence: `reports/static-pages/{verified-live.json,global-before.json,global-after.json,live-contact-desktop.png}`.
- Native Safari/iPhone hardware remains NOT_TESTED; viewport checks use Chromium. Form fields were focused and edited with dummy values, then cleared; no contact message was submitted.
- **LIVE_CHANGED = TRUE — RELEASE COMPLETE FOR THE EIGHT-PAGE BODY SCOPE.** Rollback the theme release if needed; the original WordPress content/media is retained. No other repository, post, taxonomy, menu, canonical, robots configuration or Yoast setting was edited.

### 2026-10-23 — Owner-approved SEO Matrix continuation

- Owner approved completing the existing primary-site Excel Matrix. Fresh backup captured all 10 scoped Pages and 28 Posts, with raw bodies and available Yoast fields, before content edits. A fresh public audit found 38/38 HTTP 200 responses, one H1, self canonicals and parseable JSON-LD.
- Separate WordPress content patches repair unsupported delivery/late-fee examples, electric-vehicle specification/comparison tables and local travel claims, and add contextual Matrix links. Only the six explicit ON-PAGE proposals authorize metadata/H1 edits; slugs, canonical and robots remain protected. Excel and rollback evidence are retained with the user deliverables.
- This theme candidate adds contextual links within the existing home price/closing sections and collection body, plus a privacy link immediately before the existing Contact Form 7 shortcode. It does not alter forms, contact configuration, layout, styles, Quick Call or Assistant runtime files.
- Yoast Article is excluded on the nine audited business Pages (310,424,432,372,484,326,449,494,3040), while the prior homepage-only rule is retained. Other pages, posts, products and replacement homepages keep their configured Article behavior. No graph is injected and no SEO option or database value is changed by this filter.
- The previous WooCommerce checkpoint branch and live authenticated read now confirm category 73 already uses an H2 and product 669 already has the reviewed classification text. Do not publish those historical previews again.
- Candidate gates: Node suite, PHP/WordPress integration via repository CI, and the existing read-only 68-visit live Matrix workflow. Actual iPhone Safari and field Core Web Vitals remain NOT_TESTED. Full editorial completion must not be inferred from HTTP checks or the historical 24/38 counter.
- At this checkpoint: theme NOT_MERGED, deployment NOT_VERIFIED. WordPress content LIVE_CHANGED = TRUE. Roll back this candidate by reverting its commit; use exact per-ID content backups for separate editorial changes after checking the newest revision.

### 2026-10-22 — Stability and SEO audit (branch `fix/stability-seo-audit-20261022`, PR #55)

- **LIVE_UNCHANGED = TRUE.** This work adds tools, tests, workflows, reports and docs only; no theme runtime file, no Quick Call or Nguyễn Tú Assistant file and no WordPress content, taxonomy, menu, media, Yoast value, slug or option was written. Requests to the live site were public GETs (pages, sitemaps, `robots.txt`, stylesheets, the public media endpoint) plus the two questions the existing homepage QA puts to the Assistant (POST `/wp-json/nguyentu/v1/assist`, a stateless local lookup: `inc/assist.php` contains no option, transient, post, log or mail write). The contact form was never submitted (dummy values are typed and cleared in the browser only). Cache-busted `?nt-…=` requests create short-lived LiteSpeed page-cache entries and nothing in WordPress. Backup of the starting point: branch `backup/main-c339a4b-20261022` (a tag push was refused by the proxy).
- **Static pages live QA** — failing run [39839840364](https://github.com/thuexemayhanoi/theme/actions/runs/39839840364) (`main@8251fab`) compared today's site with the stale 2026-10-21 snapshot. Cause: the snapshot recorded `/featured_item_category/…` links that Legacy Compat v1.1.0 now answers with 301 → `/bo-suu-tap/…`, and WordPress-managed copy was edited outside this repository (title, description and H1 of page 494; the second H1 of the shop page 3040). No theme commit changed them (`git diff ff8575e..c339a4b`). The fix is in the test, never on the site: renamed links are accepted only after a live 301 → 200 → self-canonical probe, and copy changes only through the ledger `reports/static-pages/approved-changes-2026-10-22.json` (title, description and H1 only; each entry's `from` must equal the archived value; canonical, robots and navigation can never be approved). The archived baseline is untouched. TESTED: run [39852359517](https://github.com/thuexemayhanoi/theme/actions/runs/39852359517) and PR run [39853624254](https://github.com/thuexemayhanoi/theme/actions/runs/39853624254) pass at 390/768/1440 px; 9 unit tests. Owner confirmation of the page 494 edit is recommended (the ledger says why).
- **PR #52 (electric category H1, 50cc legal text)** — verified, not changed: clean merge, fresh live matrix run [39853219861](https://github.com/thuexemayhanoi/theme/actions/runs/39853219861), and the official consolidated law (55/VBHN-VPQH) read on GitHub Actions: run [39854185181](https://github.com/thuexemayhanoi/theme/actions/runs/39854185181) found 10 of 10 provisions verbatim (`reports/legal-source-2026-10-22.md`). Price, deposit, slug, canonical and Yoast untouched. Optional wording on *xe mô tô* (licence, age 18) is a WORDPRESS_ACTION_REQUIRED choice of the owner.
- **Whole-site SEO audit** — `tools/seo/internal-link-graph.mjs` v2 (no URL cap, batches, checkpoint, resume, retry with backoff, COMPLETE/PARTIAL/FAILED by URLs really checked). TESTED: 35 unit/simulated-site tests; live run [39853196970](https://github.com/thuexemayhanoi/theme/actions/runs/39853196970) **COMPLETE, 1,090 of 1,090 URLs**, 0 errored, no finding, all indexable and self-canonical; one confirmed orphan (`/uncategorized/`) and two navigation-only pages; resume proven by run [39856095893](https://github.com/thuexemayhanoi/theme/actions/runs/39856095893) (1,090 already checked, 0 requested). Details and limits: `reports/seo-audit-2026-10-22.md`.
- **Homepage images (17)** — run [39855485819](https://github.com/thuexemayhanoi/theme/actions/runs/39855485819): the origin render passes every image rule at 390/768/1440 px (all loaded, 16:9, alt present, no layout shift, text LCP). The served homepage is an older LiteSpeed cache entry without the three PR #54 images: **STALE_PAGE_CACHE, WORDPRESS_ACTION_REQUIRED (purge the homepage page cache)**; 11 of 14 article cards have no featured image once the cache is fresh. `reports/homepage-images-2026-10-22.md`.
- **Open PR triage** — eight open PRs classified in `reports/pr-triage-2026-10-22.md`; #52 MERGE_READY; #47 and #3 NEEDS_REBASE_OR_FIX; #4 SUPERSEDED; #9, #49, #50, #2 OWNER_APPROVAL_REQUIRED. #49 (Assistant) and every Owner-Lock PR were not merged and not touched.
- **NOT_TESTED:** real iPhone Safari and other devices (Chromium viewport emulation only), Lighthouse on the live site and field Core Web Vitals, Google Search Console coverage, the owner's approvals given outside GitHub, a human look at the homepage after the cache purge. The PHPUnit integration suite, the WordPress browser/accessibility e2e and the SEO rules run only in the repository CI (WordPress sandbox job), not in this workspace.
- **TESTED locally on the merged tree** (`49cc084` + this branch): `php -l` on 131 PHP files, PHPCS on 92 files (300 sniffs, 0 errors, 0 warnings), PHPUnit unit suite 85 tests / 2,155 assertions, ESLint clean, 125 Node tests. Quick Call and the Assistant are covered read-only by the existing homepage live QA (opens the chat, asks the weekly price and the deposit, checks the answers, Quick Call hidden while the chat is open): step *Wait for native component and verify public frontend* of run [39855485819](https://github.com/thuexemayhanoi/theme/actions/runs/39855485819) passed.
- **PR #52 MERGED** (squash, `49cc084`) after its three checks passed on head `d4141b2` (lint/unit/QA gates, WordPress sandbox, 76-case live verification); it holds a rollback record of the owner-approved WooCommerce writes and the extended read-only live check, no theme runtime file. LIVE_VERIFIED for the content it records: the live matrix (run [39854911199](https://github.com/thuexemayhanoi/theme/actions/runs/39854911199)) read both URLs after the owner's writes. CI on `main` for the merge commit: see run [39856969470](https://github.com/thuexemayhanoi/theme/actions/runs/39856969470).
- At this checkpoint: PR #55 NOT_MERGED; deployment of PR #55 NOT_VERIFIED. Rollback: revert the merge commit of PR #55 (tools, tests, workflows, reports, docs only; nothing to restore in WordPress).

### 2026-10-22 — Post contents list after the first H2 (PR #56) and dark-mode readability (PR #57)

- **Status.** PR #55 is MERGED (`d3bfaf2`; its checkpoint line above, "NOT_MERGED", is superseded), PR #56 MERGED (`8d77b6d`), PR #57 MERGED (`d8c5942`). **LIVE_CHANGED = TRUE for the theme** (templates and CSS reached production through the `main` deploy). **LIVE_UNCHANGED = TRUE for WordPress data:** no post, page, taxonomy, menu, media, Yoast value, slug, option or plugin was written. Quick Call, the Assistant, `inc/enqueue.php`, `functions.php` and `inc/settings.php` were not touched. Requests to the live site are public GETs (nothing is submitted).
- **Contents list (PR #56), single posts only.** The collapsible list that phones and tablets (below 1100 px) saw between the featured image and the text now sits in the text right after the first `<h2>`: H1, meta line, featured image, opening paragraph, first H2, contents list, rest of the text. `nt_html_toc_placement()` (`inc/lib/html.php`) scans the rendered body with tag nesting and returns a byte offset; `single.php` inserts the list into the printed body only (the reading time and the sticky list are unaffected). Headings, ids, anchors, text, URL, canonical and Yoast data are untouched; the list is not a heading and never sits inside one.
- **Fallbacks, never a broken layout.** A first H2 inside another block (group, columns, quote, list item, `<details>`, link, figure) is not used: the list then goes before the first section (after the opening paragraph) and, with no safe place, stays in front of the text as before. Posts with fewer than three headings still have no list. Pages, products, categories and the home page never print it; other post types that fall back to `single.php` keep the old place.
- **Wide screens: OWNER DECISION.** From 1100 px the sticky sidebar list and its scroll-spy are unchanged and CSS still shows exactly one of the two variants. Moving the in-text list to desktop as well would remove the sticky list; that is the owner's choice, not made here.
- **TESTED.** PHPUnit unit suite 97 tests (12 new for the placement helper: nesting, implicit `</p>`, comments, script/style/template, unterminated comment, no H2, empty H2); 11 new Node tests (`tests/node/toc-structure.test.mjs`); WordPress e2e on the sandbox, 14 new tests at 390, 768, 1024, 1180 and 1440 px in light and dark mode (run [39861293168](https://github.com/thuexemayhanoi/theme/actions/runs/39861293168), green; e2e-blog 21, a11y-axe 20, integration 93): position and order, one H1, heading levels/text/order identical to the post, exactly one visible list, none inside a heading, open/close by click, Enter and Space, anchor scroll below the sticky header, contrast of the summary and links, axe (WCAG 2 A/AA) on the text, no horizontal overflow, the two fallbacks, fewer than three headings, and pages/products/categories/home. The sticky list (1100 px and wider) was measured with the repository's stylesheets at 1180 and 1440 px: worst text contrast 6.32:1 in light and 9.22:1 in dark mode. Real content: baseline run [39861293208](https://github.com/thuexemayhanoi/theme/actions/runs/39861293208) sampled 150 of 1,000 sitemap posts; the placement function put the list after the first H2 on 150 of 150 with 0 mismatches against an independent HTML5 parse (served pages still had the old layout then, as expected before the deploy).
- **LIVE_VERIFIED (contents list).** Deployed check, run [39862850910](https://github.com/thuexemayhanoi/theme/actions/runs/39862850910) on `main@8d77b6d`: VERDICT PASS. The new template was served after one check; 24 of 24 sampled posts show the new layout on the served page and 24 of 24 origin renders place the list after the first H2; heading lists and canonical identical between served and origin; Chromium at 390, 768, 1024 and 1440 px in light and dark mode. Homepage live QA on the same commit (run [39862851088](https://github.com/thuexemayhanoi/theme/actions/runs/39862851088)) passed, including the Quick Call/Assistant step (read-only) and the homepage images: served = origin at 390/768/1440 px, `stalePageCache` false, so the earlier STALE_PAGE_CACHE finding for the homepage no longer holds. CI on `main@8d77b6d`: run [39862850894](https://github.com/thuexemayhanoi/theme/actions/runs/39862850894) is green (Lint/unit/QA gates and WordPress sandbox).
- **Dark-mode defect found on the way (PR #57).** In dark mode `--nt-navy-900` is a near-black navy meant for backgrounds, yet some text was painted with it: the titles of posts, the blog index, collection pages, the shop and products (1.02:1 on the page; baseline run 39861293208 measured 3 of 3 sampled posts, `/blog/` and `/shop/`, against 16.2:1 in light mode) and the row headers of the home price table, i.e. the vehicle names (1.24:1 and 1.44:1). The contents list itself was fine. One rule in `assets/css/color-mode.css` gives them `--nt-ink`; light mode is unchanged. Measured with the repository's stylesheets in Chromium at 390/768/1440 px: 11.54 to 16.31:1 in dark mode. New e2e assert at least 4.5:1 for these titles and row headers in light and dark mode (shared helper in `tests/e2e/helpers.mjs`). **LIVE_VERIFIED for the titles:** after the deploy of PR #57, run [39867868196](https://github.com/thuexemayhanoi/theme/actions/runs/39867868196) (read-only, origin renders at 390 px, `main@d8c5942`) measured the dark-mode title of 3 sampled posts, `/blog/` and `/shop/` at 16.31:1 (1.02:1 before) with light mode unchanged at 16.2:1 and no "hard to read" warning. The price-table row headers, product pages and collection pages are covered by the CI e2e and the local measurement only; no live dark-mode probe exists for them: live NOT_TESTED. Homepage live QA on `d8c5942` passed (run [39867870908](https://github.com/thuexemayhanoi/theme/actions/runs/39867870908): Quick Call/Assistant step and homepage images; it was dispatched after the push-triggered run 39866942901 was cancelled while stuck installing Chromium on a slow runner). CI on `main@d8c5942`: run [39866942928](https://github.com/thuexemayhanoi/theme/actions/runs/39866942928) green.
- **Not audited / NOT_TESTED.** No full dark-mode contrast sweep of the site (only the uses of that token were audited). Real iPhone/iPad Safari, Lighthouse and field Core Web Vitals on the live site, Search Console. LiteSpeed may keep serving older copies of other pages until its page cache is purged.
- **Rollback.** Revert the merge commit of PR #56 (`single.php`, `template-parts/content/toc.php`, `inc/lib/html.php`, three scoped rules in `assets/css/blog.css`, tests and tools) and/or of PR #57 (one CSS rule and tests). Nothing to restore in WordPress.
- **Next, one step each.** WORDPRESS_ACTION_REQUIRED (optional): purge the LiteSpeed page cache so cached pages reference the new stylesheet version (dark-mode colours). OWNER DECISION: whether the contents list should also move into the text on wide screens. Then run `Post contents list live QA` (manual, mode deployed) and `Homepage live QA` once to see the result as visitors get it.


## 2026-10-23 — Scoped static homepage Matrix curation

Owner authorized completion of the existing homepage body with stable strategic links and an automatic latest-post section. Reused the featured four cards and two topic rows; curated 12 verified published Matrix Posts covering prices, monthly/50cc rental, procedures, five area silos, choice/experience and delivery. Real category navigation, products, prices, stored content and 17-image integration remain. Runtime diff: `template-parts/home/editorial.php` only. Quick Call/Assistant and shared assets unchanged. Added query behaviour and card-order regression checks; sandbox fixture counts remain 160. Local Node 135 PASS / 1 SKIP / 0 failures; changed test ESLint and whitespace checks PASS. PHP and sandbox gates run in PR CI. Native Safari NOT_TESTED. LIVE_UNCHANGED at this checkpoint; see release result for merge/live verification. Details and rollback: `reports/homepage-matrix-curation-2026-10-23.md`.

### 2026-10-23 — Assistant: six quick questions, contact and address answers, follow-ups (Issue #62)

- **Approval and scope.** The owner approved this fix in Issue #62 as a scoped exception to the P0 Owner Lock: `inc/assist.php`, `assets/js/assist.js`, `assets/css/assist.css` and their tests. Quick Call (launcher, links, markup, styles), `color-mode.css`, `inc/enqueue.php`, `functions.php`, Yoast output and all WordPress content, menus, taxonomies, media and options are untouched. No new API, model, plugin, cookie or stored chat text; the assistant stays a rule-based lookup, not generative AI.
- **What changed.** Six chips (the four old ones with their old questions, plus `Liên hệ` and `Địa chỉ`) in a 3 × 2 grid of 44 px targets; contact and address answers from `inc/business.php` with safe `Gọi điện` / `Nhắn Zalo` / `Chỉ đường` buttons (the canonical Maps profile, not the Quick Call share link); questions read as subjects (a combined price + deposit question gets one answer); follow-ups keep or replace the vehicle and drop the period when the vehicle changes; quote-only cells (`Liên hệ`, `Báo giá riêng`) never become numbers; licence and age answers quote only the official text (55/VBHN-VPQH) by vehicle class; article suggestions are at most three, public, indexable and labelled `gợi ý đọc`, re-ranked with the mini BM25 of PR #49. `docs/NATIVE-CHATBOT-CONTEXT-V2.md` describes the rules; `reports/assistant-six-chips-2026-10-23.md` holds the evidence.
- **Reproduced on `main` (same tool, same questions, local harness: the theme's real PHP/CSS/JS, WordPress and the page corpus stand in):** 4 of 34 question cases pass and 0 of 7 browser runs (four chips). Examples: `hotline`, `so dien thoai`, `đặt xe`, `how can I contact you` ask for "no source"; `Giá Vision thuê tháng và cọc bao nhiêu?` answers the deposit only; `Wave thì sao?` after `Giá Vision tháng` finds nothing; the electric bike gets `Giá tham khảo … theo ngày Liên hệ`; `xe tay ga 50cc gọi là gì` gets the vehicle overview and `xe chở được bao nhiêu người` a price. On this branch: 34 of 34 and 7 of 7. This is **not** a live measurement; the live runs of workflow `Assistant live QA` (`before` on the pull request, `after` after the merge) are recorded below when they exist.
- **TESTED in the workspace.** `php -l`, PHPCS (0 errors), PHPUnit unit 243 tests / 2,711 assertions (146 new, the two PR #49 tests unchanged; mutation checks fail as they should), ESLint, Node 146 tests (10 new), CSS gate, contrast gate (33 pairs), byte budgets (`assist.js` 19.6 KB, `assist.css` 10.3 KB; caps raised to 20 / 10.5 KB with the reason in `tools/qa/budgets.mjs`), the `native-assist` Playwright spec against the local harness (38 passed, 1 skipped: the legacy-simulator test) and a Chromium measurement of the open chat at 360, 390, 430, 768, 1024, 1440, 320×568, 844×390, 667×375 and 568×320 in light and dark mode (chips 44 px, no overlap, no horizontal scroll, no layout shift from the chat, software keyboard modelled, hostile answers, errors, a 12 s timeout, memory clear, Tab/Enter/Space/Escape). **Not run in the workspace:** the WordPress integration suite (15 new cases) and the WordPress e2e (21 new tests): the repository CI does.
- **Defect found by the measurement and fixed in this change.** White text on the dark-mode accent `#ef7763` is 2.80:1; the new `Gọi điện` answer button uses `#c93a22` in dark mode (5.11:1), set in `assist.css`. **Observed, not changed (P0 freeze, ask first):** the existing Quick Call `Gọi điện` button has the same 2.80:1 in dark mode (light 4.85:1).
- **Other pull requests.** #49 ported (BM25, combined price and deposit) and therefore superseded; #9 superseded by V2 and by the verified-source mapping; #2 unchanged. None was merged or closed by this work.
- **Not tested.** Native Safari and real iPhones (Chromium emulation, modelled keyboard), Lighthouse and field Core Web Vitals on the live site, the live site before and after the deploy (below), page-cache behaviour after the deploy.
- **At this checkpoint:** pull request NOT_MERGED, deployment NOT_VERIFIED. **LIVE_UNCHANGED = TRUE** for WordPress data and for the theme running on the live site. Rollback: revert the merge commit of the Issue #62 pull request (nothing is stored in WordPress; the REST keys and browser memory format are backward compatible).
- **Next, one step.** CI green on the pull request, merge, then run `Assistant live QA` (label `after`) and compare the evidence with the `before` run: chips 6, buttons, answers, SEO fingerprint and Quick Call menu hash unchanged.
