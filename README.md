# NGUYỄN TÚ — INDEPENDENT WORDPRESS THEME FACTORY

> **P0 OWNER FREEZE (2026-10-21): QUICK CALL + NGUYỄN TÚ ASSISTANT — KEEP CURRENT LIVE BEHAVIOR.** Read [AGENTS.md](AGENTS.md) **before any work**. The owner explicitly forbids changes, optimization, refactors, replacement, disabling or plugin swaps for these two working features unless asked and approved for the **specific change** first. Read-only audit is allowed; preserve them during every other UI/SEO/performance update. This rule overrides older checklist items asking to build/optimize Quick Call or chatbot. **Current production has an independent theme and native contact+assistant; older 2026-10-17 migration notes below are historical baselines, not a live checklist.**

> **SEO HULK — BẮT BUỘC, ƯU TIÊN P0:** Đọc và thực hiện toàn bộ [docs/SEO-HULK.md](docs/SEO-HULK.md) cùng README và CLAUDE.md. Giao diện đẹp thôi chưa đủ: bảo vệ canonical, robots, Yoast metadata/JSON-LD, sitemap, H1, category hierarchy, WooCommerce, Local SEO, internal links, Core Web Vitals và mọi URL cũ bằng báo cáo diff + kiểm thử. Không tự thay SEO cũ, không tạo schema trùng, không bịa score/ranking, không deploy live khi chưa đạt P0.


> Chủ dự án: website chính https://thuexemaynguyentu.com/ (không phải blog.rentbikehanoi.com). Tài liệu nguồn cho Claude/ChatGPT Work làm theme độc lập thay Flatsome + Flatsome Child. Viết mã thật, kiểm thử, commit và push; KHÔNG chuyển theme sản xuất trong đêm nếu chưa có thử nghiệm trên staging và phê duyệt go-live.

## 1. Mục tiêu

Xây **Nguyễn Tú Editorial Commerce** — theme WordPress độc lập, không là child theme, không phụ thuộc runtime/shortcode/licensed files của Flatsome; thiết kế hiện đại và đẹp như ý tưởng WordPress Editorial trước đó nhưng mang thương hiệu riêng **Nguyễn Tú**. Ưu tiên mobile, hiệu năng, hiển thị giá và sản phẩm, liên hệ đặt xe, blog có category cha/con, website thương mại dịch vụ thật. Mọi sửa đổi mã nằm ở GitHub để sau này có thể triển khai bằng Deployer for Git + webhook, KHÔNG dùng WPVibe draft theme làm kho mã chính.

**Quy tắc số 1: ĐỔI LỚP HIỂN THỊ, KHÔNG XÓA/CHUYỂN DỮ LIỆU.** Bài, page, WooCommerce product, category, tag, media, menu, post_meta, Yoast metadata, CPT featured_item, taxonomy featured_item_category, slug, URL, nội dung người dùng, WP writer ledger và workflow đều giữ nguyên. Không thay site URL/permalink, không xóa FlatSome/child, không sửa database sản xuất. Chỉ thực hiện theme switch sau acceptance test đạt và chủ website đồng ý riêng.

## 2. Kiểm kê đã xác minh ngày 2026-10-17

- URL: https://thuexemaynguyentu.com/
- WordPress 7.1.3; PHP 8.2.34; theme active: `Flatsome Child` 3.0 (stylesheet `flatsome-child`, parent `flatsome` 3.18.7).
- Homepage là **WP Page ID 562**, permalink gốc hiển thị bằng `/`; `show_on_front=page`; `page_for_posts=0`; permalinks bài viết `/%postname%/`.
- Đã có **160 published posts, 10 published pages, 13 published WooCommerce products, 15 published Flatsome featured_item, 123 media attachments** tại thời điểm kiểm kê (tăng dần do writer); 26 category bài viết; 36 mục của menu chính.
- Menu 67 **Main chính**: gán ở cả `primary` và `primary_mobile`; **36 items nhiều cấp**, PHẢI giữ đúng `menu_item_parent`, `object_id`, vị trí, URL, nhãn; không thiết kế lại bằng menu dữ liệu tĩnh. Menu 66 **Secondary**: đang trống, được gán ở `footer` và `top_bar_nav`.
- Có parent/child categories thật; **không flatten cây taxonomy**, không tạo lại category mới có slug trùng. Blog category 69 -> 127 Chia Sẻ, 126 Điểm Đến, 156 Đánh Giá & So Sánh Xe, 157 Xe Điện, 158 Kỹ Thuật & Bảo Dưỡng, 159 Pháp Lý & Kinh Nghiệm, 162 Hướng dẫn thuê xe, với các category con.
- WooCommerce taxonomy product_cat: term 70 **Loại Xe** -> term 71 **Xe Số**, 72 **Xe Ga**, 73 **Xe Điện**. WooCommerce phải tiếp tục hoạt động (shop, product archive, product detail, cart/checkout nếu dùng).
- **RỦI RO P0:** 15 mục `featured_item` + taxonomy `featured_item_category` có thể được đăng ký bởi Flatsome. Terms **77 Bảng Giá** -> **78 Thuê Ngày**, **79 Thuê Tuần**, **80 Thuê Tháng**. Menu 67 chứa link dạng `/bo-suu-tap/bang-gia/`, `/bo-suu-tap/thue-ngay/`, `/bo-suu-tap/thue-tuan/`, `/bo-suu-tap/thue-thang/`. Khi bỏ Flatsome, có nguy cơ CPT/taxonomy và rewrite mất khiến 404. Phải xây **một plugin companion nhỏ độc lập theme** để đăng ký/đọc lại cùng CPT/taxonomy, dùng đúng tên và rewrite cho URL sẵn có (chỉ khi xác minh rewrite thực tế), và dựng archive/single frontend không phụ thuộc Flatsome. Không tạo bản ghi bản sao, không reset ID.
- **RỦI RO P0:** WP Page #562 sử dụng nhiều shortcodes Flatsome `[ux_banner]`, `[row]`, `[col]`, `[section]`, `[ux_products]`, `[ux_image]`, `[ux_image_box]`, `[button]` ... raw `post_content` khoảng **40.656 bytes**. Các trang 310, 372, 424, 432, 494 cũng chứa `[ux_...]`. Nếu tắt Flatsome, shortcode sẽ hiển thị thô hoặc không render. **Không được chỉ đổi giao diện rồi tuyên bố đã xong.** Thiết kế các template native trong theme mới để trình bày cùng dữ liệu; với nội dung legacy shortcode, lập compatibility matrix và giải pháp hiển thị thực tế trong staging. Không viết shortcode engine giả mạo toàn bộ UX Builder; không xóa, thay thế, hoặc ghi đè raw page content sản xuất. Dựng front-page template tương đương đủ thông tin, sản phẩm, link, CTA, metadata; page template có cách render sạch, đánh dấu vị trí còn chưa tương thích, không được xuất bản nếu có shortcode thô.
- Plugin đang dùng: WooCommerce, Yoast SEO + Premium, LiteSpeed Cache, Contact Form 7, GTranslate, MotoAI Chat Widget, Moto QuickCall Pro+ Social (tên có “Flatsome Safe v4”), WPCode Lite, Site Kit, WPVibe, YLabs WP Agent, VibeSEO. Kiểm tra plugin QuickCall có phụ thuộc CSS/hook Flatsome và tránh tạo 2 thanh liên hệ chồng nhau. Không thay plugin, cấu hình, REST auth, secrets.
- Blog writer riêng: repo `thuexemayhanoi/wp-writer` đang phục vụ `thuexemaynguyentu.com`; **không chỉnh workflow writer** trong dự án theme này. Đăng mới phải vẫn hiển thị đúng category, H1, Yoast, ảnh, breadcrumb, bài liên quan.

**File đính kèm kiểm kê:** `docs/wordpress-baseline-2026-10-17.json` lưu ID, parent và menu hiện có. Không dùng snapshot làm nguồn để overwrite DB; trước go-live phải đối chiếu snapshot mới hơn vì post count thay đổi liên tục.

## 3. Trang và đường dẫn không thể mất

- Home `/` (Page #562), `/gioi-thieu/` (#310), `/gioi-thieu/ve-chung-toi/` (#424), `/gioi-thieu/su-menh-tam-nhin/` (#432), `/lien-he/` (#372), `/faq/` (#484), `/bo-suu-tap/` (#494), `/shop/` (#3040), `/chinh-sach-bao-mat/` (#326), `/dieu-khoan-su-dung/` (#449).
- Blog root `/blog/` **là category term #69, không phải trang Page hay posts_index**; `page_for_posts=0`. Tạo archive category/blog đúng, không đổi rewrite.
- Sản phẩm `/san-pham/{slug}/`, product categories `/danh-muc-san-pham/loai-xe/{xe-so|xe-ga|xe-dien}/`; blog category tree `/blog/.../`; các bài viết cũ `/{postname}/`; bảng giá featured terms `/bo-suu-tap/...` phải còn.
- Website có nhiều money pages/posts lên hạng; không thay title/meta/canonical/slug/H1/trích đoạn SEO đã tồn tại khi migrate theme. Giữ nguyên schema của Yoast, tránh duplicate schema từ theme; 1 H1 phù hợp cho từng template.

## 4. Giao diện và tính năng phải hoàn thành

1. Giao diện thương mại + tạp chí chuẩn tiếng Việt, phong cách hiện đại gọn gàng: trắng/kem nhẹ, xanh navy, accent xanh hoặc đỏ tiết chế. Theme không cần giống Flatsome, nhưng phải đẹp, dễ đọc và tăng lượt gọi/nhắn.
2. Header sticky nhẹ, logo/site title, **36-menu multi-level đầy đủ** trên desktop/tablet/mobile; menu mobile accessible, dropdown cấp sâu không đè màn hình; phân nhóm nhưng **không thay dữ liệu menu**. Các vị trí `primary`, `primary_mobile`, `footer`, `top_bar_nav` đăng ký để giữ mapping; sau activate nếu mapping trống cần mapping menu 67/66 theo kế hoạch kiểm chứng.
3. Home custom sections: hero có H1 và NAP/điện thoại, CTA Call/Zalo/Maps; các lựa chọn thuê ngày/tuần/tháng; bảng giá rõ có thể quản trị; category sản phẩm xe số/ga/điện; WooCommerce products; uy tín/thủ tục/địa chỉ; FAQ và bài blog; internal links về money pages theo ngữ cảnh. Chỉ dùng facts thực tế và links đã tồn tại, không tự bịa giá/ưu đãi.
4. Archive blog phân cấp cha/con; category title, description, breadcrumb, featured image nếu có, phân trang; single post TOC H2/H3 tùy chọn, author/date, bài liên quan đúng topic, previous/next, chia sẻ, schema do Yoast; search có nút kính lúp, không lộ bản nháp; 404 hữu ích.
5. WooCommerce đầy đủ templates/hook/style: shop, product categories, single product, product gallery, price, stock, add-to-cart, cart, checkout/account nếu các đường này bật. Không làm đứt tracking/shop logic.
6. CPT featured_item và taxonomy featured_item_category còn đủ **15 item / 4 terms** và URL được xác minh. Nếu cần plugin companion `plugins/nguyentu-legacy-compat/` chứa registration + templates bridge tách khỏi theme. Không di chuyển bài/term. Nếu không đạt, **BLOCK go-live**.
7. QuickCall hiện có ưu tiên giữ nguyên plugin, không tạo duplicate CTA cố định; chatbot MotoAI giữ, Contact Form 7 / GTranslate chạy; tránh JS/CSS conflicts.
8. Responsive 360/390/430 mobile, 768 tablet, 1280/1440 desktop; ảnh có size/lazy loading, font được phép, không phụ thuộc CDN bắt buộc, không overflow ngang; WCAG keyboard focus, màu chữ có độ tương phản.
9. Theme dùng WordPress APIs đúng chuẩn: escaping, sanitization, nonces khi có form, template hierarchy, hooks WooCommerce, Gutenberg-friendly, PHP 8.2+, WordPress 7.1+, không thêm chức năng xuất bản/bất kỳ AI API key nào vào theme. Dữ liệu nằm ở WordPress, không nhét dữ liệu bài viết hàng loạt vào code.

## 5. Quy trình GitHub làm qua đêm

- **P0 — Audit:** Đọc README và audit JSON, soát thực tế `thuexemaynguyentu.com` read-only; WPVibe/WP Agent nếu đã kết nối; bằng chứng cụ thể cho homepage shortcode, menu sâu, category, featured_item, WooCommerce, Yoast, snippets/QuickCall; lưu docs/AUDIT.md và danh sách URLs cần test.
- **P1 — Build:** Tạo theme root theo định dạng sẵn sàng để Deployer for Git cài trực tiếp **(style.css, functions.php, index.php, front-page.php, single.php, page.php, home.php, archive.php, category.php, search.php, 404.php, header.php, footer.php, woocommerce templates/hooks...)**; không đặt theme trong thư mục con nếu repo chính là đường dẫn plugin triển khai. Plugin companion đặt riêng `plugins/nguyentu-legacy-compat` và hướng dẫn ZIP riêng, không giả vờ Deployer tự cài companion.
- **P2 — Compatibility:** Xây xử lý archive featured; kiểm chứng homepage/page mà không phụ thuộc Flatsome; báo cáo mapping từng shortcode / trang. Preserve menu term IDs, product category and URLs.
- **P3 — Test:** Lint PHP/CSS/JS, automated WordPress coding & integration, test dữ liệu import vào disposable WordPress/staging, Playwright screenshots (nếu có browser), link checker, Lighthouse (không claim score thiếu đo). Đưa bằng chứng ảnh và report ở `reports/`. So sánh 3 viewport, kiểm WooCommerce/cart, 26 blog terms, 36 menu items, 15 featured items và 10 page nội dung.
- **P4 — Release:** Commit/push liên tục các checkpoint lên GitHub trong branch `feat/nguyentu-independent-theme-v1` hoặc PR để duyệt; GitHub Actions tự kiểm tra. Hoàn thành gần nhất có thể trong phiên, không dừng ở lập kế hoạch. PR mô tả trạng thái PASS/BLOCKED và cách test. **KHÔNG merge hoặc activate trên live website vào ban đêm**; chủ quyết định riêng sau khi xem preview, bằng chứng, kế hoạch rollback. Không được xóa Flatsome, Flatsome Child hay plugin cũ cho đến khi chứng minh chuyển đổi tương đương.

### Acceptance gate cho phép đề xuất go-live

- Không có 404 trên tập URL trọng yếu; 160 bài, 10 page, 13 products, 15 featured items, 26 categories, 36 menu items và ancestry gốc còn truy cập được; counts theo dữ liệu mới nhất, không phải baseline cố định.
- Không còn **Flatsome shortcodes thô** xuất hiện trên frontend của các trang public; home giữ thông điệp/NAP/giá/CTA/links quan trọng; Blog category tree đúng; WooCommerce đúng; MotoAI/QuickCall không vỡ.
- Yoast title/meta/canonical/robots và JSON-LD trước/sau tương đồng; không có duplicate H1/schema, đổi slug, permalink hay bài bị noindex.
- UI trên 3 thiết bị không lỗi; không broken image, console errors; accessible menu; không ghi DB ngoài dữ liệu staging.
- Nếu bất kỳ mục P0 nào BLOCKED: không tự phát hành. Ghi rõ root cause và hướng xử lý, có thể đề xuất companion plugin hoặc backup/migration, nhưng giữ live nguyên trạng.

## 6. Triển khai và rollback — sau khi chủ duyệt

- Snapshot Hostinger gồm files + database; xuất WordPress menu/category/CPT/URL/SEO options snapshot; giữ `flatsome` và `flatsome-child` cài nguyên vẹn.
- Cài companion plugin và test rewrite trên staging trước; tạo ZIP mới hoặc Deployer for Git cho theme root; webhook là luồng update **sau khi xác minh push->deploy thật**. Không nhầm HTTP ping 200 với tự động triển khai.
- Kích hoạt theme mới trên staging và chạy acceptance checklist. Chỉ sau owner signoff mới thay theme live (bước có rủi ro), gán lại menu location bằng ID 67/66 nếu bị mất, flush rewrite 1 lần có giám sát, purge cache, kiểm SEO và functional URLs.
- Rollback: activate `flatsome-child`, vẫn giữ parent `flatsome`; khôi phục đúng menu assignments/options nếu đổi; clear cache, kiểm URL/home/cart. Restore Hostinger snapshot nếu DB đã bị ảnh hưởng ngoài dự tính.
- Tuyệt đối không đưa credentials, Application Password, webhook secret, OAuth token, .env vào repo công khai.

## 7. Tiến độ và báo cáo

Mỗi phiên Claude ghi `docs/PROGRESS.md`: làm gì, đã kiểm thử gì, blockers, commit/PR links, còn mấy việc, xác nhận rõ **LIVE_UNCHANGED** hay không. Nếu hết quota, hãy push checkpoint có thể tiếp tục và chỉ dẫn command resume. Không báo “hoàn tất” khi mới tạo skeleton. Các kiểm thử tính năng thực tế và tương thích Flatsome là tiêu chuẩn cuối cùng.
