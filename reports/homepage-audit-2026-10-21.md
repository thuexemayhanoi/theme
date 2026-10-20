# Báo cáo kiểm toán trang chủ Nguyễn Tú — 21/10/2026

Website: https://thuexemaynguyentu.com/  
Repository sửa duy nhất: https://github.com/thuexemayhanoi/theme

## Kết quả phát hành và bảo vệ dữ liệu

**Đã phát hành lên WordPress thật và xác minh bằng Chromium ở 390×844, 768×1024, 1440×900.**

- Checkpoint trước sửa: `27bc96b5a66140df1ead75c884170e462b259d44`, nhánh `checkpoint/homepage-audit-2026-10-21`.
- PR đã merge: https://github.com/thuexemayhanoi/theme/pull/34, merge commit `376a0a5d7d07c5ee644593a30d5f07f5aa319bf4`.
- Commit mã cuối: `a8b7db20cd407f1b8397b774d84795179cc85492` — sửa điều kiện widget footer đã ẩn khi loại bundle wishlist.
- Live QA PASS lúc **01:13:24 ngày 21/10/2026, giờ Việt Nam**: https://github.com/thuexemayhanoi/theme/actions/runs/39472417529.
- Ảnh chụp website thật và proof.json: https://github.com/thuexemayhanoi/theme/actions/runs/39472417529/artifacts/12288575069.
- Không ghi bài, trang, sản phẩm, danh mục, menu hay SEO metadata; không thay slug/permalink/canonical/robots. Đích của **42 liên kết menu và 31 liên kết footer** trùng baseline ở cả ba viewport.
- LiteSpeed Cache và object cache đã purge thành công sau phát hành. Lượt đầu còn HTML cache cũ; lượt xác minh sau purge đã PASS.
- LIVE_UNCHANGED = FALSE: chỉ cập nhật theme và làm mới cache trong phạm vi chủ website cho phép.

## Các số đo có bằng chứng

Đây là **Lighthouse lab**, không phải dữ liệu Core Web Vitals của người dùng thật. Chỉ so trước/sau mobile; chưa có baseline desktop cùng điều kiện.

| Chỉ số | Mobile trước | Mobile sau | Desktop sau |
| --- | ---: | ---: | ---: |
| Performance | 69/100 | **94/100** | **100/100** |
| Accessibility | 100/100 | **100/100** | **97/100** |
| Best Practices | 96/100 | **100/100** | **96/100** |
| SEO cơ bản | 100/100 | **100/100** | **100/100** |
| LCP | 5,3 s | **1,8 s** | **0,4 s** |
| CLS | 0 | **0** | **0** |
| TBT | 0 ms | 90 ms | 40 ms |
| FCP | 3,5 s | 1,5 s | 0,4 s |
| Speed Index | 5,6 s | 5,9 s | 0,4 s |

Lighthouse có dao động giữa lần đo; không quy toàn bộ chênh lệch cho một thay đổi. Mobile Speed Index không cải thiện trong lượt đo này. Không có CrUX/INP thực tế để kết luận toàn bộ Core Web Vitals đã đạt.

| Hạng mục QA theo yêu cầu | Điểm / kết luận |
| --- | --- |
| UI/UX /100 | Chưa gán điểm thẩm mỹ tổng hợp; thao tác đã kiểm ở 3 viewport PASS |
| Responsive /100 | Chưa gán điểm toàn diện; các kiểm tra Chromium/sandbox PASS, Safari thật chưa kiểm |
| Technical SEO /100 | Lighthouse SEO cơ bản 100/100 mobile và desktop; không coi là điểm technical toàn site |
| Performance /100 | Mobile 94, desktop 100 |
| Accessibility /100 | Tự động: mobile 100, desktop 97; chưa xác nhận WCAG thủ công đầy đủ |
| Logic & Functional QA /100 | PASS các ca đã chạy; số lượng và phạm vi ghi ở phần kiểm thử |
| Tổng QA /100 | **Chưa chấm điểm tổng hợp**, không tự công bố >=90 khi thiếu bằng chứng cho mọi hạng mục |

AIOScan: **100/100, 21/21 checks** về khả năng AI truy cập và đọc nội dung. Không chứng minh AI đang đề xuất doanh nghiệp: https://aioscan.com/scan/thuexemaynguyentu.com.

## Lỗi phát hiện và đã sửa

| Mã | Mức | Nguyên nhân có bằng chứng | Kết quả |
| --- | --- | --- | --- |
| F01 | P1 | Plugin Smart Core đang cài chỉ nhân giá ngày: Wave 7 ngày 1.050.000đ, 30 ngày 4.500.000đ. File plugin mới trong repo không tự được cài khi triển khai theme. | Calculator native trong theme dùng cùng nt_price_rows() với bảng giá; dùng giá tuần/tháng đã công bố; tiền cọc hiển thị riêng. Live test cả 6 nhóm xe PASS. |
| F02 | P2 | Calculator cũ có nền/chữ trắng cố định. | Component theo biến màu theme, giữ tương phản dark mode và grid co vừa 320px trong sandbox. Live dark/reload PASS ở 3 viewport. |
| F03 | P2 | Đèn mở cửa dùng xanh lam và timer 30 giây có thể cập nhật muộn ở biên giờ. | Màu xanh lá riêng khi mở, đỏ khi đóng; Intl Asia/Ho_Chi_Minh; hẹn biên phút +20ms. Hai mốc 09:00/21:00 qua E2E. |
| F04 | P2 | Nhãn nhóm điều hướng footer dùng H2. | Đổi thành đoạn văn có ID/aria-labelledby; giữ lớp CSS, nhãn và URL. |
| F05 | P2 | YITH tải wishlist/React/Lapilli dù native homepage không có nút wishlist. Widget footer cũ vẫn được gán nhưng bị ẩn bởi filter. | Điều kiện asset khớp chính điều kiện footer thực sự hiển thị; bỏ entry wishlist ở trang chủ. Không tắt plugin; giữ trang sản phẩm và widget footer khi được bật. Live xác nhận entry wishlist không tải. |

F01 đã đóng sau kiểm thử live. Chưa ghi nhận P0/P1 còn lại **trong phạm vi trang chủ và các kiểm tra đã thực hiện**; đây không phải tuyên bố toàn bộ website không còn lỗi.

## Bảng giá calculator sau sửa

| Xe | 1 ngày | 3 ngày | 7 ngày | 30 ngày | Cọc riêng |
| --- | --- | --- | --- | --- | --- |
| Honda Wave | 150.000đ | 450.000đ | 700.000đ | 900.000–1.200.000đ | 2–3 triệu |
| Honda Vision | 200.000đ | 600.000đ | 1.000.000đ | 1.800.000–2.000.000đ | 4–5 triệu |
| Honda Air Blade | 200.000đ | 600.000đ | 1.000.000đ | 1.500.000đ | 3 triệu |
| Honda Click / Yamaha Mio | 150.000đ | 450.000đ | 700.000đ | 900.000–1.200.000đ | 2 triệu |
| Xe tay ga 50cc | 200.000đ | 600.000đ | Liên hệ | Liên hệ | 4 triệu |
| Xe máy điện / Xe đạp điện | Liên hệ | Liên hệ | Liên hệ | Liên hệ | Theo mẫu xe |

- Từ 1–6 ngày: dùng giá ngày nếu đã công bố.
- Đúng 7 / 30 ngày: dùng giá tuần / tháng, giữ khoảng giá khi có.
- Từ 8–29 ngày: liên hệ xác nhận gói, không tự ghép hoặc suy diễn giá.
- 0, âm, thập phân, chữ, trống, số quá lớn: báo chọn 1–30 ngày.
- Không cộng cọc vào tiền thuê và không hứa xe còn sẵn.

## Kiểm thử chức năng và hồi quy

**CI đầy đủ trước phát hành:** https://github.com/thuexemayhanoi/theme/actions/runs/39470386562  
Code được kiểm: `8c3d52e30eb88b8038eb5abe47bba7662c2c3b87`.

- PHP lint, PHPCS, PHPUnit unit, ESLint, Node tests, CSS parsing, asset budgets, contrast: PASS.
- Tích hợp: **86 tests, 1.243 assertions**, PASS.
- Trình duyệt: **198 ca khi bật simulator + 26 ca native khi tắt simulator = 224 ca PASS**. Ca không áp dụng được skip theo hai chế độ; không tính skip là PASS.
- Các lượt chụp ảnh: 33 + 9 ca PASS. Ảnh sandbox: https://github.com/thuexemayhanoi/theme/actions/runs/39470386562/artifacts/12287477778.
- Bao gồm menu, layout, footer, search/404, WooCommerce, blog, SEO head, axe accessibility, calculator, dark mode, hours.
- Native chatbot/Quick Call: né dock giả lập ở 360/390/768/1024/1440; nhường modal; xoay màn hình; VisualViewport bàn phím mô phỏng; xóa bộ nhớ khi request đang chờ; lưu chủ đề và dẫn nguồn: PASS.
- Bounded query và loại trùng bài đã được rà soát trong mã: giới hạn posts_per_page, no_found_rows, post__not_in; không đưa hàng nghìn bài vào DOM. Không tạo bài giả để thử tải.

**Auto-repair F05 sau phát hành:** `a8b7db20cd407f1b8397b774d84795179cc85492`. Static checks PASS; live QA sau purge PASS.  
**CI bản sửa cuối:** https://github.com/thuexemayhanoi/theme/actions/runs/39472417682 — **PASS**: static + WordPress sandbox; **87 tests / 1.244 assertions**, 224 ca giao diện/chức năng và các ca chụp ảnh đều đạt. Artifact bản cuối: https://github.com/thuexemayhanoi/theme/actions/runs/39472417682/artifacts/12289030766.

Checker SEO sandbox trước phát hành còn 43 cảnh báo P1 về thiếu meta description trong dữ liệu fixture của nhiều archive/collection; P0 = 0. Không chuyển cảnh báo của fixture thành kết luận website thật, không sửa hàng loạt metadata để làm xanh báo cáo.

## Xác minh website thật

Lượt Chromium sau cùng hoàn tất lúc 2026-10-20T18:13:24.930Z; commit a8b7db20cd407f1b8397b774d84795179cc85492; cả ba viewport PASS:

1. HTTP 200; một main và H1; title, description, canonical và toàn bộ URL menu/footer khớp baseline.
2. Không tràn ngang; chỉ một calculator; không tải JS calculator plugin cũ hoặc entry wishlist.
3. Chuyển dark/light; dark calculator có nền/chữ đúng; lựa chọn giao diện giữ sau tải lại.
4. Kiểm đủ 6 nhóm xe ở 1/3/7/30 ngày, tiền cọc riêng và các đầu vào sai.
5. Menu mở/đóng nhiều lần ở mobile/tablet và khôi phục trạng thái cuộn.
6. Đèn giờ Hà Nội đúng trạng thái đóng lúc 01:13 dù timezone của browser đặt America/Los_Angeles.
7. Chatbot: Vision một tuần 1.000.000đ; hỏi tiếp cọc 4–5 triệu; nguồn FAQ. Quick Call thực sự bị ẩn khi chat mở, không suy luận lỗi chỉ từ hình chữ nhật giao nhau.
8. Đóng chat và Back To Top hoạt động; không phát hiện uncaught JavaScript error trong lượt này.
9. Có 12 ảnh website thật: home light/dark, chatbot, footer cho mỗi viewport. Một số request Google Analytics bị hủy khi reload/đóng context được giữ trong proof.json; không che hoặc coi đó là lỗi ứng dụng đã sửa.

## SEO, AEO/GEO, NAP và nguồn dữ liệu

- H1 hiện hành: Thuê Xe Máy Hà Nội – Nguyễn Tú.
- Title giữ nguyên: Thuê Xe Máy Hà Nội – Theo Ngày, Tháng | Nguyễn Tú.
- Description giữ nguyên: Thuê xe máy Hà Nội tại Nguyễn Tú với xe số, xe ga 50cc–125cc. Thuê theo ngày, tuần hoặc tháng; thủ tục đặt cọc nhanh, hỗ trợ giao xe theo thỏa thuận.
- Canonical duy nhất đúng homepage; robots index/follow; JSON-LD có 2 block parse được, gồm graph Yoast và LocalBusiness. Không thêm Organization/FAQ graph chỉ để tăng điểm.
- robots.txt đọc được, không chặn homepage; có Sitemap trỏ Yoast sitemap_index.xml. Sitemap index đọc được, gồm 9 sitemap con.
- Mẫu scan SEO live: 40 đích liên kết truy cập được, 0 link chết trong mẫu; không phải kiểm toàn bộ website.
- 14 liên kết ảnh trang trí bị một checker báo không tên nhưng có aria-hidden/tabindex=-1 và liên kết tiêu đề tương ứng; không coi cảnh báo này là lỗi người đọc màn hình đã được chứng minh.
- NAP: 112 Nguyễn Văn Cừ, Bồ Đề, Long Biên, Hà Nội; 0942 467 674; nguyentuantu8x@gmail.com; 09:00–21:00. Bảng giá và cọc khớp dữ liệu chủ website.
- Mã chatbot dùng nội dung publish không mật khẩu, lọc noindex, tìm tối đa 12 bài và trả tối đa 3 nguồn. Không thêm API AI bên ngoài.
- Hiển thị bằng textContent, lọc nguồn cùng origin; timeout 12 giây, hủy phản hồi đang chờ khi xóa ghi nhớ.
- Cache chủ đề <=512 ký tự / 24 giờ; cache nguồn <=8.000 ký tự / 6 giờ / 5 nhóm; log tối đa 13 phần tử. Không lưu lịch sử chat.

## Những điểm còn tồn tại hoặc chưa xác minh

| Mức | Nội dung | Trạng thái |
| --- | --- | --- |
| P2 | Desktop Lighthouse target-size | Còn cảnh báo kích thước/khoảng cách vùng bấm; chưa đủ selector bằng chứng để vá an toàn |
| P2 | Desktop label-content-name-mismatch | Còn cảnh báo tên truy cập không khớp nhãn nhìn thấy; chưa sửa theo suy đoán |
| P2 | Desktop errors-in-console | Best Practices 96; live không có uncaught error, có request Analytics bị hủy khi điều hướng; chưa kết luận cùng nguyên nhân |
| P2 | CSS/JS dư | Sau sửa vẫn còn Lighthouse ước tính 18 KiB CSS / 95 KiB JS ở mobile |
| P2 | Yoast Article/Person cũ | Homepage còn Article metadata từ nội dung cũ và Person có email/ngày sinh; không sửa profile/SEO options ngoài repository |
| Chưa kiểm | Safari iPhone/iPad thật, Home Indicator, thanh địa chỉ và bàn phím hệ điều hành | Chỉ có giả lập Chromium/VisualViewport; không tự ghi PASS thiết bị thật |
| Chưa đo | INP/CrUX thực tế | Không đủ dữ liệu người dùng thật |
| Giới hạn | Ảnh baseline Browser Use | Có quan sát đầu phiên; không có liên kết artifact baseline bền vững. Ảnh sau triển khai có artifact GitHub ở trên |

## Tệp thay đổi

Mã website:
- template-parts/home/prices.php
- template-parts/home/calculator.php
- assets/js/rental.js
- assets/css/home.css
- assets/css/color-mode.css
- assets/js/theme.js
- inc/enqueue.php
- template-parts/footer/columns.php

Kiểm thử / bằng chứng:
- tests/node/native-rental-behavior.test.mjs
- tests/e2e/native-rental.spec.mjs
- tests/e2e/header-controls.spec.mjs
- tests/e2e/footer.spec.mjs
- tests/integration/HomeAssetsTest.php
- tools/qa/run-sandbox.sh
- tools/qa/homepage-live.mjs
- .github/workflows/homepage-live-qa.yml
- reports/homepage-baseline-2026-10-21.json
- reports/homepage-audit-2026-10-21.md
- reports/homepage-audit-results-2026-10-21.json
- docs/PROGRESS.md

Workflow live QA dùng Playwright sẵn có, chỉ duyệt frontend và endpoint tra cứu nội bộ, lưu screenshot/proof; không có quyền sửa nội dung hoặc cấu hình WordPress. Baseline URL cần được rà soát lại nếu chủ website chủ động thay cấu trúc điều hướng sau này.

Rollback: hoàn nguyên các commit runtime về checkpoint bằng Git và triển khai lại; không cần phục hồi DB vì không ghi nội dung/cấu hình doanh nghiệp.
