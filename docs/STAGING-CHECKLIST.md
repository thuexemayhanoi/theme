# Checklist nghiệm thu trên staging (dành cho chủ website)

Các mục dưới đây là phần **không thể kiểm tra trong môi trường build** (không truy cập được site thật, không có plugin trả phí/riêng).
Đánh dấu từng dòng PASS / FAIL kèm ngày và người kiểm. Chưa làm = NOT_TESTED (không phải PASS).

## A. Chuẩn bị staging

- [ ] Staging là **bản sao** (files + DB) của production, chặn index (`Discourage search engines`) và chặn gửi email/thanh toán thật.
- [ ] Cài plugin `nguyentu-legacy-compat` và kích hoạt **khi Flatsome Child còn chạy**; `wp nguyentu-compat status` hiển thị `snapshot_source: runtime`.
- [ ] `wp nguyentu-compat export-urls > featured-before.csv` (15 mục + 4 term).
- [ ] Chụp SEO "trước": `node tools/seo/snapshot.mjs --base https://thuexemaynguyentu.com --urls reports/seo-urls.full.csv --out reports/seo-before.json`
      (tạo `seo-urls.full.csv` bằng `tools/seo/discover.mjs`). Hoặc chạy workflow **seo-snapshot-live**.

## B. Sau khi kích hoạt theme mới trên staging

- [ ] `wp rewrite flush` đúng một lần.
- [ ] `wp nguyentu-compat verify --probe` → "Mọi URL featured_item và term resolve đúng"; `compare featured-before.csv` → không MISSING/CHANGED.
- [ ] `wp nguyentu status` không có dòng ATTENTION đáng ngại (Yoast hoạt động; 4 vị trí menu đã gán).
- [ ] Menu: đủ 36 mục, đúng cha–con, đúng thứ tự (so `wp menu item list 67`).
- [ ] `node tools/seo/snapshot.mjs --base <staging> ...` → `diff.mjs before after` → **P0 = 0** (xem `reports/seo-diff.md`).
- [ ] `wp nguyentu legacy-scan --format=markdown` → điền kết quả vào `docs/LEGACY-COMPAT.md`; không tag quan trọng nào ở trạng thái STRIPPED.
- [ ] **Widget**: vào *Giao diện › Widget*. WordPress tự chuyển widget của theme cũ sang hai vùng của theme mới
      (*Cột bên bài viết*, *Chân trang mở rộng*) theo thứ tự. Gỡ những widget không muốn hiện (danh sách Archives/Categories mặc định, v.v.).
- [ ] **Breadcrumb bài viết**: theme dùng breadcrumb của Yoast và không đổi cài đặt Yoast. Nếu trên site thật bài viết hiển thị
      "Trang chủ › Blog › Chuyên mục › Bài", kiểm tra Yoast *Cài đặt › Nâng cao › Breadcrumbs › Taxonomy to show in breadcrumbs for Posts = Categories*
      và so sánh với `BreadcrumbList` trong ảnh chụp "trước" (không đổi cấu hình khi chưa duyệt).
- [ ] **Ngôn ngữ**: nhãn WooCommerce (Thêm vào giỏ, Mô tả, Sản phẩm liên quan…) hiển thị tiếng Việt khi gói ngôn ngữ `vi` đã cài (môi trường build không có gói này).

## C. Trang và URL (mở bằng trình duyệt, 360 / 768 / 1440 px)

- [ ] `/` — giữ H1, NAP, nút Gọi/Zalo/Maps, giá, sản phẩm, không có chữ `[ux_...]`.
- [ ] `/gioi-thieu/`, `/gioi-thieu/ve-chung-toi/`, `/gioi-thieu/su-menh-tam-nhin/`, `/lien-he/`, `/faq/`, `/bo-suu-tap/`, `/shop/`, `/chinh-sach-bao-mat/`, `/dieu-khoan-su-dung/`.
- [ ] Hai bảng giá mẫu cho mỗi kỳ: `/bo-suu-tap/thue-ngay/`, `/thue-tuan/`, `/thue-thang/`, `/bang-gia/` và 3–4 `featured_item` bất kỳ.
- [ ] `/blog/`, `/blog/xe-dien/`, `/blog/xe-dien/xe-dap-dien/`, một bài viết (mục lục, bài liên quan, bài trước/sau), tìm kiếm có kết quả và không kết quả.
- [ ] Một sản phẩm có giá (thêm vào giỏ → giỏ hàng → thanh toán tải được) và một sản phẩm không giá (hiện "Liên hệ báo giá" + nút gọi/Zalo).
- [ ] `/404-invalid-nonexistent/` trả **404** (kiểm tra bằng `curl -I`).

## D. Plugin thật (không có trong môi trường build)

- [ ] **Moto QuickCall Pro+ Social**: chỉ có **một** thanh liên hệ cố định trên mobile; không chồng lên nút chat; nút gọi/Zalo hoạt động.
      (Theme tự ẩn thanh của mình khi thấy thư mục plugin bắt đầu bằng `moto-quickcall`.)
- [ ] **MotoAI Chat Widget**: bong bóng hiện, mở/đóng được, không che nội dung cuối trang hoặc nút gọi.
- [ ] **Contact Form 7** trên `/lien-he/`: gửi thử (staging) thành công, có thông báo.
- [ ] **GTranslate**: bộ chuyển ngôn ngữ hiển thị, không tạo hreflang giả (không bật thêm cấu hình mới).
- [ ] **LiteSpeed Cache**: purge xong, trang khách không bị cache giỏ hàng/thanh toán.
- [ ] **Yoast SEO Premium**: breadcrumb (nếu bật), redirect manager (nếu có), schema của bài viết và sản phẩm không trùng.
- [ ] **Site Kit / WPCode**: không có đoạn JSON-LD LocalBusiness thứ hai (xem `reports/seo-rules-after.md`, quy tắc `jsonld-one-local-business`).

## E. SEO và hiệu năng

- [ ] Search Console: so sánh Coverage/Pages trước và sau trong 14 ngày.
- [ ] Lighthouse mobile (home, một bài, một category, một sản phẩm) — ghi số đo thật và giới hạn của môi trường vào `reports/performance.md`.
- [ ] Core Web Vitals thực tế (CrUX/RUM) chỉ đánh giá sau khi có đủ lượng truy cập.
- [ ] **Tùy chọn hiệu năng** *Giao diện › Nguyễn Tú › Script jQuery của WooCommerce* (mặc định TẮT, WooCommerce nguyên trạng).
      Bật trên staging rồi mở Console ở trang chủ, blog, `/lien-he/`: không được có lỗi `jQuery is not defined`; MotoAI, Moto QuickCall,
      Contact Form 7, GTranslate và mọi đoạn WPCode vẫn chạy. Đo lab trong sandbox (không phải số liệu thật): trang chủ Lighthouse mobile 87 → 97,
      bài viết 92 → 98 (`reports/performance.md`). Theo dõi nguồn đơn hàng của Woo không bị ảnh hưởng (script `sourcebuster`/`order-attribution` được giữ).

## F. Ký duyệt

| Hạng mục | Người kiểm | Ngày | Kết quả |
| --- | --- | --- | --- |
| Giao diện 3 thiết bị | | | |
| SEO diff (P0 = 0) | | | |
| Plugin thật (D) | | | |
| Kế hoạch rollback đã đọc | | | |
| **Chủ website đồng ý go-live** | | | |
