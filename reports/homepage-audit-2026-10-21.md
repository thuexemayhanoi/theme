# Kiểm toán trang chủ Nguyễn Tú — 21/10/2026 (giờ Việt Nam)

Website: https://thuexemaynguyentu.com/  
Repository duy nhất thay đổi: https://github.com/thuexemayhanoi/theme  
Checkpoint Git: `27bc96b5a66140df1ead75c884170e462b259d44`, nhánh `checkpoint/homepage-audit-2026-10-21`.  
Bản sửa mã: `0120acbdc6531be8ef220d58c3d12955f3532eeb`, PR https://github.com/thuexemayhanoi/theme/pull/34.

## Phạm vi và bằng chứng

- Browser Use đã tải và kiểm tra trang thật ở 1440×900, 768×1024, 390×844. Đây là giả lập kích thước bằng trình duyệt cloud, không phải iPhone/iPad Safari thật.
- Đã đọc mã nguồn GitHub, HTML trang chủ, nguồn plugin Smart Core/YITH đang cài, thông tin kết nối WordPress; không chỉnh DB, bài, sản phẩm, menu, danh mục hay SEO metadata.
- Lighthouse mobile: Performance 69/100, Accessibility 100/100, Best Practices 96/100, SEO 100/100. LCP 5,3 giây; CLS 0; TBT 0 ms; FCP 3,5 giây; Speed Index 5,6 giây. Không có dữ liệu CrUX/INP người dùng thật.
- SEO scan: 25/26 kiểm tra tự động đạt; 40 đích liên kết được kiểm tra có thể truy cập, 0 link chết trong mẫu. Không phải kiểm toàn bộ 153 liên kết. Cảnh báo 14 liên kết ảnh không có tên là các liên kết trang trí có `aria-hidden=true`, `tabindex=-1` và liên kết tiêu đề tương ứng; Lighthouse không phát hiện lỗi accessibility.
- AIOScan: 100/100, 21/21 phép kiểm tra truy cập/đọc. Không chứng minh doanh nghiệp được AI đề xuất.
- Cloud Browser Use hết credits khi mở lượt kiểm tra bổ sung. Không thử lại hoặc tự mua credits.

## Lỗi và xử lý

| Mã | Mức | Bằng chứng / nguyên nhân | Sửa trong theme |
| --- | --- | --- | --- |
| F01 | P1 | Plugin Smart Core trên WordPress vẫn là calculator cũ: Wave 7 ngày 1.050.000đ, 30 ngày 4.500.000đ. Nó chỉ có data-day-rate và nhân giá ngày. File plugin mới ở GitHub không tự cập nhật khi triển khai theme. | Component calculator native lấy cùng `nt_price_rows()` với bảng giá, dùng giá tuần/tháng riêng; tách tiền cọc; loại callback/JS cũ tại vị trí này. |
| F02 | P2 | Calculator cũ dùng nền/chữ trắng cố định, không theo biến màu tối. | CSS component theo nền/chữ theme; input 16px, grid co vừa màn hình 320px. |
| F03 | P2 | Đèn mở cửa dùng màu Zalo xanh lam. Bộ hẹn 30 giây có thể cập nhật muộn so với biên giờ. | Xanh lá riêng cho trạng thái mở; giữ đỏ đóng; hẹn theo biên phút, múi giờ Asia/Ho_Chi_Minh. |
| F04 | P2 | Nhãn nhóm điều hướng footer là H2. | Đổi sang đoạn văn có ID/aria-labelledby, giữ nguyên lớp CSS và URL. |
| F05 | P2 | Native homepage không có nút yêu thích, nhưng YITH vẫn tải React/Lapilli và gọi wishlist endpoint. Browser thấy request hủy khi reload. | Chỉ dequeue entry bundle/style YITH trên editorial homepage không có footer widget; giữ đăng ký/dependency và trang sản phẩm. Không tắt plugin hay xóa wishlist. |

Chưa ghi nhận P0 trong phần đã kiểm tra. F01 là lỗi kinh doanh cần sửa trước phát hành.

## Bảng giá cần đạt sau sửa

| Xe | 1 ngày | 3 ngày | 7 ngày | 30 ngày | Cọc riêng |
| --- | --- | --- | --- | --- | --- |
| Honda Wave | 150.000đ | 450.000đ | 700.000đ | 900.000–1.200.000đ | 2–3 triệu |
| Honda Vision | 200.000đ | 600.000đ | 1.000.000đ | 1.800.000–2.000.000đ | 4–5 triệu |
| Honda Air Blade | 200.000đ | 600.000đ | 1.000.000đ | 1.500.000đ | 3 triệu |
| Honda Click / Yamaha Mio | 150.000đ | 450.000đ | 700.000đ | 900.000–1.200.000đ | 2 triệu |
| Xe tay ga 50cc | 200.000đ | 600.000đ | Liên hệ | Liên hệ | 4 triệu |
| Xe máy điện / Xe đạp điện | Liên hệ | Liên hệ | Liên hệ | Liên hệ | Theo mẫu xe |

0, âm, chữ, số thập phân, trống hoặc quá 30 ngày: thông báo chọn 1–30 ngày. Kỳ 8–29 ngày cần báo giá riêng. Không cộng cọc vào tiền thuê.

## Kết quả từng khu vực

- Header/hero/search/body/footer: render được ở cả ba kích thước đã kiểm; không tràn ngang. Hero giữ hai CTA Gọi/Chỉ đường và bốn lối tắt có xe 50cc; không sửa H1 hoặc URL.
- Menu: mở/đóng, mở cấp con hoạt động trong baseline. Giữ mục con đang mở khi đóng/mở drawer là một trạng thái quan sát được, chưa đủ cơ sở coi là lỗi điều hướng.
- Dữ liệu WordPress tăng: query featured/hub/latest có `posts_per_page` giới hạn và `no_found_rows`; danh sách bài loại ID đã hiển thị để tránh lặp. Không tải toàn bộ hàng nghìn bài vào DOM.
- Sản phẩm: 8 ảnh có alt, width/height, lazy loading; bài thiếu featured image dùng placeholder. Không tự tạo ảnh hay chỉnh Media Library.
- Footer NAP: đúng 112 Nguyễn Văn Cừ, Bồ Đề, Long Biên, Hà Nội; 0942 467 674; nguyentuantu8x@gmail.com; 09:00–21:00. Back to Top hoạt động trong baseline.
- Quick Call/chat: mở/đóng được trong baseline. Báo cáo browser dựa trên hình chữ nhật giao nhau chưa kiểm computed visibility; CSS hiện có đã ẩn Quick Call khi body có `nt-chat-is-open`. Không coi geometry của phần tử ẩn là lỗi và không vá CSS theo giả định.
- Native iOS safe area, dock, thanh địa chỉ, bàn phím thật: chưa có thiết bị thật để kiểm. Bộ E2E có mô phỏng VisualViewport/rotation; phải phân biệt mô phỏng với Safari thật.

## Chatbot thật: kiểm bằng endpoint nội bộ, không thêm AI API

1. Hỏi `Honda Vision giá bao nhiêu một tuần?`: trả lời 1.000.000đ/tuần, kèm lưu ý xác nhận giá; nguồn `/faq/`; context Honda Vision/week.
2. Hỏi tiếp `Tiền cọc bao nhiêu?` với context đã trả: cọc Vision 4–5 triệu; nguồn `/faq/`.
3. Mã server chỉ đọc nội dung publish, không mật khẩu; lọc noindex; tìm tối đa 12 bài và trả tối đa 3 nguồn. Câu hỏi 2–180 ký tự; context model được đối chiếu bảng giá.
4. UI dùng textContent, kiểm cùng origin cho nguồn, timeout 12 giây, hủy phản hồi đang chờ khi xóa ghi nhớ.
5. Cache chủ đề tối đa 512 ký tự/24 giờ; cache nguồn tối đa 8.000 ký tự/6 giờ/5 nhóm, mỗi nhóm tối đa 3 nguồn; log chỉ giữ 13 phần tử, không lưu lịch sử chat.

## SEO, AEO/GEO và an toàn URL

- Title: Thuê Xe Máy Hà Nội – Theo Ngày, Tháng | Nguyễn Tú.
- Meta description: Thuê xe máy Hà Nội tại Nguyễn Tú với xe số, xe ga 50cc–125cc. Thuê theo ngày, tuần hoặc tháng; thủ tục đặt cọc nhanh, hỗ trợ giao xe theo thỏa thuận.
- Canonical đúng `https://thuexemaynguyentu.com/`; robots index/follow; một main và H1.
- JSON-LD có 2 block parse được: Yoast graph và LocalBusiness. Không thêm Organization/FAQ schema thứ hai chỉ để tăng điểm.
- `/robots.txt` đọc được: không chặn trang chủ, chặn wp-admin và REST; có Sitemap trỏ đúng Yoast index.
- `/sitemap_index.xml` đọc được, có 9 sitemap con (post/page/product/featured_item/category/product_cat/pa_dong-xe/pa_loai-xe/featured_item_category).
- Yoast vẫn có Article metadata từ nội dung homepage cũ và Person có email/ngày sinh. Chưa sửa profile/SEO options ngoài phạm vi repository; cần rà soát riêng về độ phù hợp, không tự công bố đó là lỗi schema cú pháp.
- Không đổi title/meta/canonical/robots, permalink, menu URL hay cấu trúc taxonomy.

## Điểm có bằng chứng

| Hạng mục | Điểm / trạng thái |
| --- | --- |
| UI/UX | Chưa chấm tổng hợp; baseline 3 viewport đã quan sát |
| Responsive | Chưa chấm tổng hợp; native Safari chưa kiểm |
| Technical SEO | Lighthouse SEO 100/100 là kiểm tự động cơ bản; không thay thế điểm technical toàn diện |
| Performance | Mobile baseline 69/100; chưa đo sau triển khai |
| Accessibility | Lighthouse tự động 100/100; không phải xác nhận WCAG thủ công đầy đủ |
| Logic & Functional QA | Native JS 9/9 ca hành vi đạt; WordPress/E2E đang chạy |
| Tổng QA | Chưa đủ bằng chứng để công bố >=90 |

## Kiểm thử và phát hành

- Exact native JS chạy trong host V8 với DOM stubs: 9 ca đạt.
- CI static tại code commit 0120acbdc6531be8ef220d58c3d12955f3532eeb: PHP lint, PHPCS, PHPUnit unit, ESLint, Node tests, CSS parsing, asset budget, contrast đều đạt; 32 PASS, 0 FAIL, 5 NOT_TESTED theo báo cáo gate cũ.
- WordPress sandbox/integration/E2E: đang chạy tại https://github.com/thuexemayhanoi/theme/actions/runs/39468818357.
- Các gate migration Flatsome/MotoAI/QuickCall cũ trong repo không tự biến thành PASS; hiện website đã chạy native theme/widgets nên cần đọc đúng phạm vi kiểm tra.
- Trạng thái hiện tại: chưa merge/publish; LIVE_UNCHANGED = TRUE ở thời điểm lập bản này.

## Tệp mã thay đổi

- template-parts/home/prices.php
- template-parts/home/calculator.php
- assets/js/rental.js
- assets/css/home.css
- assets/css/color-mode.css
- assets/js/theme.js
- inc/enqueue.php
- template-parts/footer/columns.php
- tests/node/native-rental-behavior.test.mjs
- tests/e2e/native-rental.spec.mjs
- tests/e2e/header-controls.spec.mjs
- tests/e2e/footer.spec.mjs
- tests/integration/HomeAssetsTest.php
- docs/PROGRESS.md

Rollback: hoàn nguyên PR/merge qua Git, triển khai lại checkpoint; không cần phục hồi DB vì không ghi dữ liệu nội dung/cấu hình.


Kiểm bổ sung: mã giờ mở cửa chạy nguyên bản với Date giả lập qua bốn mốc 08:59:59 / 09:00:00 / 20:59:59 / 21:00:00 cho kết quả đúng, chuyển ở biên phút +20ms khi timer hoạt động. Đã bổ sung native-rental vào lệnh E2E sandbox để các ca mới thực sự được chạy; clock test dùng đồng hồ chạy, không dùng setFixedTime cho phép thử thời gian tiến lên. Trạng thái phát hành sẽ được cập nhật sau kiểm thử.
