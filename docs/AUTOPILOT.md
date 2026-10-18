# Autopilot A–Z — cấu trúc và vận hành

## Cấu trúc tính năng

| Chức năng | Mã | Nguồn dữ liệu / mở rộng |
|---|---|---|
| Template, menu, bảng, shortcode cũ | templates, inc/legacy, main/home/blog/woo/legacy.css | WordPress APIs; không sửa dữ liệu gốc |
| NAP, giá, liên hệ | inc/business.php, inc/settings.php | nguyentu_options; dữ liệu chuẩn dự phòng |
| Lịch hoạt động | inc/lib/hours.php, inc/hours.php | giờ mặc định + ngày nghỉ ISO 1–7 + ngoại lệ theo ngày |
| Sáng/tối/tự động | inc/autopilot.php, assets/css/autopilot.css, assets/js/autopilot.js | CSS variables; localStorage; thiết bị chỉ quyết định màu |
| Liên hệ thu gọn | template-parts/autopilot/widgets.php | tel/Zalo chuẩn; WhatsApp chỉ khi được nhập xác minh |
| Trợ lý | inc/assistant.php, widgets, autopilot.js | quy tắc thương mại ưu tiên + tìm kiếm WordPress + từ đồng nghĩa |
| Chỉ mục và đồ thị link | inc/assistant.php | postmeta _nt_knowledge, _nt_link_graph; cron nt_knowledge_batch |
| Liên kết chủ đề/bài liên quan | inc/related.php, nt_cluster_navigation | taxonomy thật, publish only, query có giới hạn |
| Metadata, breadcrumb, sitemap | inc/seo.php, inc/schema.php + Yoast | không tạo thẻ SEO thứ hai |

## Đồng hồ và cache

GET `/wp-json/nguyentu/v1/hours` trả server_time, next_change, trạng thái và múi giờ Asia/Ho_Chi_Minh; no-store. HTML có fallback từ server; JS lấy lại ngay khi tải, khi tab trở lại và tối đa mỗi 60 giây; tại ranh giới sẽ lấy lại theo khoảng thời gian server cung cấp, không dùng giờ thiết bị. Khi lỗi mạng không tự khẳng định cửa hàng đang mở. Lịch ngoại lệ `YYYY-MM-DD | closed` hoặc `YYYY-MM-DD | 10:00-18:00`. Không hỗ trợ giờ qua đêm. `[nguyentu_hours]` dùng cùng component trên nội dung biên tập khi cần.

## Trợ lý: giới hạn trung thực

POST `/wp-json/nguyentu/v1/assistant` nhận question (1–300 ký tự), context ngắn tùy chọn (chỉ ý định/mẫu xe), trả answer, sources, hours, context, mode. Mode là local-rules-and-search. Giá lấy cấu hình đã xác minh; không dùng bài viết để ghi đè giá thương mại. Hỗ trợ câu hỏi phổ biến Việt/Anh và theo tiếp câu hỏi giá theo kỳ thuê; câu ngoài phạm vi chuyển liên hệ. Tìm kiếm từ khóa và mở rộng đồng nghĩa, **chưa có embedding/vector search hoặc LLM**. Chỉ trả liên kết trang xuất bản, không tự tổng hợp cam kết từ bài viết.

20 yêu cầu/phút/IP qua transient băm có salt; bỏ qua forwarded-IP chưa tin cậy. Hạn mức này chống spam cơ bản, không thay WAF; nhiều người dùng cùng NAT có thể chung giới hạn. Không lưu hội thoại, không external AI calls, không SDK nặng. Nâng cấp về sau bằng thay provider sau `nt_assistant_answer()` và giữ giao diện REST, phải có sự cho phép trước khi gửi dữ liệu ngoài.

## Chỉ mục, dữ liệu và phục hồi

Save cập nhật một object. Draft/private/password-protected xóa chỉ mục. Backfill cron 20/lượt, lưu nt_knowledge_cursor, kết thúc ghi version. WP-Cron cần lượt truy cập hoặc cron hosting; nếu hosting tắt WP-Cron thì phải cấu hình cron chuẩn. Không tự chạy task có phí. `_nt_link_graph` lưu tối đa 100 outgoing link/nguồn và anchor; metadata cập nhật tăng dần, không ghi link tự động vào nội dung. Báo cáo đầy đủ từ crawler trong tools/seo/links-report.mjs, phạm vi URL phải được ghi rõ.

Dựng lại index: trên staging/CLI thực: `wp option update nt_knowledge_cursor 0` và `wp option update nt_knowledge_index_version 0`; lượt init sau lên lịch lại. Không xóa bài hoặc taxonomy. Đổi theme dọn lịch cron; metadata có thể giữ để khôi phục. Có thể tắt trợ lý ở Giao diện → Nguyễn Tú mà không ảnh hưởng nội dung.

## QA và triển khai

Đọc AGENTS.md. Các bài kiểm tra bổ sung: HoursTest, AutopilotTest, autopilot.test.mjs, autopilot.spec.mjs. Kiểm tra 360/390/768/1440, Light/Dark/Auto, lưu lựa chọn, bàn phím, đóng/mở panel, giá/không bịa, giờ chuyển ngày/nghỉ, API input/rate, index draft/password, không widget trùng. Dùng bản preview/staging trước main; preview token không được commit vào repo.


## Bảo toàn URL và dọn schema cũ

`inc/featured.php` áp lại các rule `/bo-suu-tap/` đã được companion capture, cả khi WordPress đang giữ rewrite cache cũ; không flush DB mỗi lượt truy cập, không đổi term/slug/menu. Link term lấy đúng permastruct đã có bằng chứng. Alias kỹ thuật cũ vẫn truy cập được.

`inc/schema.php` lọc duy nhất graph Header WPCode có ID `#nguyen-tu-website` đã xác minh là graph trùng; không xóa option và không đụng script khác. LocalBusiness được thêm vào chính graph Yoast nếu chưa có Organization/LocalBusiness, với dữ liệu cấu hình chung. Không tạo Offer/InStock hoặc rating giả. Ngày nghỉ và ngoại lệ được phản ánh trong giờ schema. Tắt theme/rollback sẽ phục hồi nguyên mã chèn cũ do dữ liệu không bị xóa.

Báo cáo links-report.mjs thêm số outgoing/incoming trong mẫu crawl và hàng trang chưa có incoming trong mẫu, đề xuất xem xét thủ công theo chủ đề. Không coi mẫu crawl là bản đồ đầy đủ mọi URL.
