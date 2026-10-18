# Kiểm toán thực tế 18/10/2026 — Autopilot A–Z

Nguồn: repo main 3df2bff, WPVibe đọc WordPress thật, HTML trang chủ, bản preview cùng dữ liệu thật. Không dùng snapshot 17/10 làm dữ liệu hiện hành.

## Đã xác minh

- Active theme `theme`, Nguyễn Tú Editorial Commerce 1.0.0; Flatsome/Child inactive, vẫn giữ để phục hồi. Deployer for Git, WooCommerce 11.2.1, Yoast 28.7 + Premium 20.12, legacy compatibility active.
- Moto QuickCall 4.0 active, MotoAI Chat 1.0.3 inactive. Plugin QuickCall tự sinh WhatsApp khi để trống và tải Bootstrap Icons ngoài site. Bản thay thế không tự suy đoán WhatsApp.
- primary/primary_mobile cùng menu Main chính; footer/top_bar_nav cùng Secondary. 26 category cha/con vẫn có; các danh mục rỗng hiện hữu không xóa hoặc tạo lại.
- WordPress có 10 page công khai, homepage #562; bài mới đang tiếp tục đăng từ hệ thống writer riêng. Không chỉnh repo khác.
- Home trước: 1 H1 `Thuê Xe Máy Hà Nội – Nguyễn Tú`; title `Thuê Xe Máy Hà Nội – Theo Ngày, Tháng | Nguyễn Tú`; canonical homepage; robots index/follow. Khóa title WPCode #3999 đang publish, phải giữ.
- Sitemap Yoast trả XML có post/page/product/featured_item/category/product_cat; robots không chặn toàn site, chặn REST và đường nội bộ Woo như cũ.
- Search Console property chính kết nối quyền siteOwner, truy cập miễn phí giới hạn 3 ngày. Chưa suy ra ranking, traffic hay điểm SEO từ việc kết nối.

## Nguyên nhân và xử lý

| Phát hiện | Nguyên nhân | Hướng xử lý |
|---|---|---|
| Chưa có lịch đóng/mở động | chỉ in chuỗi giờ cố định ở template | clock server no-store dùng chung; lịch nghỉ/ngoại lệ trong settings |
| Chưa có Light/Dark/Auto | token chỉ có bảng màu sáng | đổi token + xử lý các nền/chữ cố định trong component |
| Chưa có trợ lý native | MotoAI inactive | native rule/search; không giả là LLM |
| QuickCall phụ thuộc plugin + icon CDN | plugin cũ render footer | thay bằng component nhẹ sau kiểm chứng, giữ plugin để phục hồi |
| Schema trùng và sai giờ/còn xe | option WPCode `ihaf_insert_header` có WebSite/WebPage riêng, 08:00–21:30, Offer InStock cố định | sao lưu, hợp nhất phần LocalBusiness thật; giữ Yoast, bỏ Offer/stock chưa xác minh |
| Giờ cũ trong home content | một đoạn tại dòng 754 còn 08:00–21:30 | đối chiếu bản gốc, sửa đúng đoạn business giờ, không sửa title/H1/meta |
| Tài liệu còn bảo chưa active theme | checkpoint giai đoạn migration 17/10 | AGENTS + hợp đồng hiện hành, tài liệu module rõ ràng |

## Kiểm thử hiện tại

PHP syntax: toàn bộ module/template mới đạt local và validation trên WPVibe preview.
PHPUnit unit: 85 test, 1935 assertion PASS (PHP 8.3 + intl).
Node tests: 31 PASS; ESLint, CSS parser, asset budgets PASS.
REST preview: hours từ server trả Asia/Ho_Chi_Minh; Vision tháng trả đúng 1.800.000–2.000.000đ.
Chưa dùng kết quả này để khẳng định integration/e2e/production đã đạt. Xem checkpoint và kết quả CI sau cùng.
