# Nguyễn Tú — hướng dẫn bảo trì cho mọi AI

Repo duy nhất: `thuexemayhanoi/theme`. Website: `https://thuexemaynguyentu.com/`.
Đọc README → tài liệu này → `docs/AUTOPILOT.md` → `docs/ARCHITECTURE.md`; dùng `docs/PROGRESS.md` làm checkpoint, không dùng lịch sử hội thoại làm nguồn mã.

## Hợp đồng hiện hành

- Chủ website đã cho phép triển khai kỹ thuật trong phạm vi MASTER WORK A–Z ngày 18/10/2026. Theme `theme` đã active; `main` có webhook production. Những đoạn trong tài liệu cũ nói "chưa đổi Flatsome" là lịch sử.
- Làm trên nhánh kỹ thuật. Kiểm thử rồi mới merge. Không phát hành khi còn lỗi P0, không ghi đè thay đổi đồng thời trên main. Không yêu cầu chủ duyệt lại các việc đã được ủy quyền.
- Không đổi title, H1, meta, canonical, slug, permalink, menu, taxonomy hoặc nội dung hàng loạt. Khóa tiêu đề trang chủ WPCode #3999 phải giữ. Không sửa repo writer.
- Dùng `nt_business()`, `nt_price_rows()`, `nt_hours_state()` làm nguồn chung; không chép NAP/giá/giờ vào từng trang. Ngày nghỉ và ngoại lệ sửa ở Giao diện → Nguyễn Tú.
- Token chung: main.css; bổ sung sáng/tối và widget: autopilot.css. Không tạo CSS theo từng URL. 360/390/768/1440 px phải không tràn ngang.
- Yoast sở hữu metadata và graph của nó. Không thêm graph cạnh tranh. Schema ngoài Yoast phải có kiểm kê, không bịa Offer/InStock/rating hoặc giờ.
- Trợ lý hiện là **quy tắc + tìm kiếm từ khóa có từ đồng nghĩa**, không phải LLM hoặc vector search. Không API trả phí, không lưu/gửi câu hỏi ra ngoài. Không xác nhận đặt xe.
- `_nt_knowledge` và `_nt_link_graph` là chỉ mục có thể dựng lại, không phải nguồn nội dung. Chỉ public/publish/không mật khẩu. Save cập nhật từng đối tượng; cron backfill 20 đối tượng/lượt. Không crawl toàn site mỗi câu hỏi.
- Quick Call do plugin cũ sở hữu cho tới khi bản native đã được thử. Sau khi xác minh native, có thể tắt đúng plugin cũ, giữ nguyên file/options để khôi phục. Khi plugin MotoAI bật, native chat tự ẩn.
- Mở rộng từng module độc lập qua WordPress hooks/filters. Không phụ thuộc Claude, ChatGPT, Work hay một connector cụ thể.

## Kiểm chứng

`composer install && npm ci`; `bash tools/qa/run-all.sh`; `bash tools/sandbox/setup.sh && bash tools/sandbox/seed.sh`; `bash tools/qa/run-sandbox.sh`.
Không dùng dữ liệu production cho test tạo/xóa bài. Các test tích hợp từ chối host không phải loopback.
Kiểm tra PHP, WPCS, unit, REST/giờ/giá/chỉ mục, e2e dark/auto/contact/chat, SEO snapshot/diff trước và sau; ghi rõ giới hạn coverage. Test skipped hoặc chưa chạy không phải PASS.

## Triển khai và phục hồi

Push nhánh → PR/CI → đối chiếu main → merge bản đạt kiểm thử → xác minh mã HTML, assets và REST trên site thật → purge cache khi cần → đối chiếu metadata/URL/widget. Rollback bằng revert commit rồi webhook, hoặc kích hoạt theme dự phòng nếu lỗi nghiêm trọng. Không xóa Flatsome/plugin cũ. Xem `docs/ROLLBACK.md`.
