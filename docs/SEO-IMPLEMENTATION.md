# SEO HULK — ánh xạ yêu cầu → mã → kiểm thử

Mỗi dòng trỏ tới nơi triển khai và nơi kiểm tra. Trạng thái PASS / FAIL / NOT_TESTED nằm trong `reports/release-gates.md` (sinh tự động).
Không có điểm số hay thứ hạng nào được tuyên bố ở đây.

| Yêu cầu SEO-HULK | Triển khai | Kiểm tra |
| --- | --- | --- |
| §0 Yoast sở hữu metadata, không nhân đôi | `inc/seo.php` (không in canonical/description/OG/JSON-LD khi có plugin SEO) | `StructureTest::test_theme_never_prints_its_own_structured_data...`, `...canonical_and_meta_description...`; `SeoCoexistenceTest` |
| §1 Danh sách URL vàng + chụp trước/sau | `reports/seo-urls.csv`, `tools/seo/{snapshot,discover,diff}.mjs` | `tests/node/*.test.mjs`; `reports/seo-diff.md` (UNVERIFIED nếu không có baseline thật) |
| §1 404 phải là 404 | Không có redirect/soft-404 trong theme; `404.php` | Quy tắc `status` + `robots-noindex` (`tools/seo/check.mjs`), e2e `404` |
| §2 HTML5 landmark, skip link, một `<main>`, một H1 | `header.php`, `footer.php`, mọi template; `nt_should_print_h1()` | Quy tắc `main-one`, `h1-one`, `landmarks` |
| §2 Menu 36 mục truy cập được | `class-nt-walker-nav.php`, `assets/js/theme.js` | `MenuTest`, e2e menu (bàn phím, `aria-expanded`, Esc, bẫy focus) |
| §2 Category: H1 theo term, mô tả thật, breadcrumb, phân trang | `category.php`, `archive-header.php` | `TaxonomyTest`; e2e |
| §2 Bài viết: mục lục chỉ từ heading thật, bài liên quan, trước/sau | `content.php`, `related.php`, `single.php` | `HtmlTest`, `ThemeTest::test_related_posts...` |
| §3 Schema do Yoast; chỉ bổ sung thuộc tính LocalBusiness đã xác minh | `inc/schema.php` (filter `wpseo_schema_organization`, không ghi đè, không tạo `@id` mới) | `SeoCoexistenceTest`; quy tắc `jsonld-*` |
| §3 Không bịa AggregateRating/review/offer/FAQ | Không có mã tạo chúng; `StructureTest` cấm từ khóa | Quy tắc `jsonld-no-invented-ratings` |
| §3 WooCommerce giữ Product/Offer gốc | `inc/woocommerce.php` chỉ hook, không ghi đè template | `WooCommerceTest` |
| §4 Không đổi `blog_public`, robots, sitemap, canonical | Không có mã ghi các mục này | `diff.mjs`: `robots.txt`, `sitemap_index.xml` |
| §4 Không redirect/slug/taxonomy base mới | Không có | `ContentTest` (slug, permalink), `compare` của plugin |
| §5 Menu từ `wp_nav_menu()`; breadcrumb tương thích Yoast | `menus.php`, `nt_breadcrumbs()` (dùng Yoast khi bật, không có schema thứ hai khi tắt) | `MenuTest`, `SeoCoexistenceTest::test_breadcrumb_trail...` |
| §5 `reports/internal-links.csv` | `tools/seo/links-report.mjs` từ snapshot | `reports/internal-links.csv` (phạm vi: danh sách URL của snapshot) |
| §6 CWV: LCP, CLS, INP | CSS 6,7 KB gzip, font tự host + fallback metric, ảnh có kích thước, LCP `fetchpriority=high`, không jQuery, JS 2,8 KB gzip | `tools/qa/budgets.mjs`, Lighthouse (nếu chạy), e2e |
| §6 Không cache cá nhân hóa | Không in nội dung theo người dùng (trừ liên kết giỏ hàng chỉ khi có hàng) | Checklist staging |
| §7 Brand/NAP chuẩn, Call/Zalo/Maps, không tuyên bố chưa xác minh | `inc/business.php`, `nt_cta_buttons()` | `BusinessTest`, `StructureTest::test_banned_marketing_claims...`, quy tắc `nap` |
| §7 Không hai thanh liên hệ chồng nhau | `nt_show_cta_bar()` | e2e contact (mô phỏng QuickCall/MotoAI); plugin thật = NOT_TESTED |
| §8 Test chạy được, không "checklist trang trí" | `tests/unit`, `tests/integration`, `tests/node`, `tests/e2e` | `tools/qa/run-all.sh`, `tools/qa/run-sandbox.sh` |
| §9 Không tự kích hoạt/ghi production | Không có mã làm việc đó; workflow live chỉ `workflow_dispatch` + GET | Cổng `live-unchanged`, `secret-scan` |

## Những gì theme KHÔNG làm (có chủ ý)

- Không in `<title>`, meta description, canonical, robots, Open Graph, Twitter, JSON-LD, hreflang.
- Không thêm `nofollow` toàn cục, không noindex hàng loạt, không đổi slug/taxonomy base.
- Không `add_theme_support('yoast-seo-breadcrumbs')` (sẽ bật BreadcrumbList nếu chủ site chưa bật).
- Không FAQPage schema (FAQ chỉ hiển thị; rich result không được đảm bảo).
- Không tải script bên thứ ba cho bản đồ/video cho tới khi người dùng bấm.
