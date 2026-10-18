# Trang chủ: nội dung trước, mục native chỉ thêm khi thiếu

Trang chủ là Page #562 với ~40 KB shortcode Flatsome. Yêu cầu P0: **không mất chữ, H1, liên kết, ảnh** của trang đang có thứ hạng, nhưng vẫn có giao diện
đẹp, rõ giá, rõ liên hệ. Cách giải quyết: render đầy đủ nội dung hiện có, rồi chỉ **bổ sung** các mục mà nội dung chưa có.

## Thứ tự trong `front-page.php`

1. **Hero.** Nếu nội dung đã có `<h1>` (thường trong `[ux_banner]`) thì dùng nguyên văn, theme **không** in H1 nào khác.
   Chỉ khi nội dung hoàn toàn không có H1, theme in hero native với H1 = tiêu đề trang (không viết lại từ khóa).
2. **Nội dung trang #562** qua cầu nối shortcode (nguyên văn, đã làm sạch shortcode thô).
3. Các mục native, mỗi mục ở chế độ **Tự động** (mặc định) / Luôn hiện / Luôn ẩn trong *Giao diện › Nguyễn Tú*:

| Mục | Hiện khi (Tự động) | Dữ liệu |
| --- | --- | --- |
| Lựa chọn ngày / tuần / tháng | nội dung chưa liên kết tới cả `/bo-suu-tap/thue-ngay` và `/thue-tuan` | Giá "từ" tính từ bảng giá; liên kết chỉ khi term tồn tại |
| Danh mục xe | nội dung chưa có ≥ 2 liên kết `/danh-muc-san-pham/` | `product_cat` thật |
| Sản phẩm | nội dung chưa có `[ux_products]` / `[products]` | `wc_get_products` |
| Bảng giá tham khảo | nội dung chưa có ≥ 3 mức giá | Bảng giá chuẩn (sửa được trong cài đặt) + chính sách cọc |
| Quy trình thuê xe | nội dung chưa nhắc "thủ tục / quy trình / các bước" | Chỉ câu chữ từ chính sách đã công bố |
| Địa chỉ, giờ mở cửa, bản đồ | nội dung chưa có điện thoại + địa chỉ + liên kết/khung bản đồ | NAP chuẩn; bản đồ chỉ tải khi bấm |
| Câu hỏi thường gặp | nội dung chưa có accordion / "FAQ" | Chỉ dữ kiện đã xác nhận; **không** xuất FAQPage schema |
| Bài viết mới | nội dung chưa có `[blog_posts]` | 6 bài mới nhất đã xuất bản |

Mục ở chế độ "Luôn hiện" sẽ hiển thị dù nội dung đã có; "Luôn ẩn" tắt hẳn.

**Dải thông tin nhanh** (giờ mở cửa, loại xe, lời hứa xác nhận giá và cọc) nằm trong hero native. Khi nội dung trang tự có H1 và hero riêng:
"Tự động" **không** thêm gì (giờ mở cửa đã có ở mục địa chỉ), "Luôn hiện" in dải này ngay dưới nội dung trang, "Luôn ẩn" tắt hẳn.
Dải này không bao giờ thêm H1 (`nt_home_facts_placement()`, có unit test và e2e).

## Quy tắc nội dung

- Không có tuyên bố chưa xác minh: không "miễn phí giao xe", không "không cần cọc", không "24/7", không số đánh giá/sao.
- Giá luôn kèm câu "giá tham khảo, cần xác nhận trước khi đặt xe"; xe điện không có giá công bố hiển thị "Liên hệ".
- Mọi liên kết nội bộ trỏ tới đối tượng **đã tồn tại** (term, page, product); không tạo liên kết tới thứ chưa xuất bản.
- Chỉ **một** thanh liên hệ cố định trên mobile: của theme (khi không có Moto QuickCall) hoặc của QuickCall.

## Cách chọn trên staging

Mở staging, so ảnh 360/768/1440 giữa trang cũ và mới. Nếu một mục native làm lặp nội dung hiện có, đặt mục đó ở "Luôn ẩn".
Nếu muốn trang chủ hoàn toàn native, chuyển các mục sang "Luôn hiện" và chỉnh nội dung #562 **trên bản sao staging** (không phải sửa production trong phạm vi dự án này).
