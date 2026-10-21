> **CURRENT-PRODUCTION WARNING (2026-10-21):** The original guide below documents the *completed 17–19 Oct migration*, not the active production state. WordPress now runs the independent `theme` with Legacy Compat v1.1.0 active. **Do not repeat activation, plugin capture or rewrite flush as a routine deploy.** For current status and theme-only rollback, read [CURRENT-PRODUCTION.md](CURRENT-PRODUCTION.md) and the owner freeze in [AGENTS.md](../AGENTS.md). Any new release must use a verified GitHub commit, backup and production post-deploy QA. 

# Triển khai theme Nguyễn Tú Editorial Commerce

> **Không có bước nào dưới đây được thực hiện trong phiên build.** Production vẫn là Flatsome Child (xem `docs/PROGRESS.md`, `LIVE_UNCHANGED = TRUE`).
> Mọi bước ghi lên website thật cần chủ website đồng ý riêng sau khi xem staging.

## 0. Điều kiện để *đề xuất* go-live

`reports/release-gates.md` phải hiển thị **mọi cổng "Blocking" = PASS**. Hiện tại các cổng cần quyền truy cập site thật hoặc plugin trả phí
(`seo-diff-live`, `flatsome-real-content`, `quickcall-real`, `motoai-real`) là **NOT_TESTED** — chúng chỉ chuyển sang PASS khi có người chạy
đúng các bước trong `docs/STAGING-CHECKLIST.md`.

## 1. Chuẩn bị (trên production, chỉ đọc + sao lưu)

1. Snapshot Hostinger gồm **files + database**.
2. Xuất để đối chiếu (đều chỉ đọc):
   ```bash
   wp menu item list 67 --format=json > menu-67-before.json
   wp nguyentu-compat export-urls > featured-before.csv        # sau khi cài plugin ở bước 2 (nếu chưa, bỏ qua và làm trên staging)
   node tools/seo/snapshot.mjs --base https://thuexemaynguyentu.com --urls reports/seo-urls.full.csv --out reports/seo-before.json
   ```
   (chạy snapshot từ máy có mạng, hoặc workflow GitHub **seo-snapshot-live** — chỉ `workflow_dispatch`, chỉ GET).
3. Giữ nguyên `flatsome` và `flatsome-child` đã cài. Không xóa gì.

## 2. Cài plugin tương thích **trước** khi đổi theme (thứ tự quan trọng)

`plugins/nguyentu-legacy-compat/` là plugin riêng (Deployer for Git **không** tự cài plugin này).

```bash
bash tools/build-plugin-zip.sh          # tạo dist/nguyentu-legacy-compat.zip
```

1. Cài ZIP trên **staging** (không phải production) rồi **kích hoạt khi Flatsome Child vẫn đang chạy**.
   Lúc này plugin đang ngủ đông và **ghi lại** cấu hình đăng ký `featured_item` / `featured_item_category` của Flatsome vào option `nguyentu_legacy_compat`
   (đây là thao tác ghi duy nhất của plugin, chỉ vào option của chính nó).
2. `wp nguyentu-compat status` → `registered_by_other_component: yes`, `snapshot_source: runtime`.
3. `wp nguyentu-compat export-urls > featured-before.csv` (15 mục + 4 term).
4. Nếu `snapshot_source` là `defaults` thì **dừng**: plugin chưa thấy đăng ký thật; không được go-live.

## 3. Cài theme trên staging

Cách A — Deployer for Git: trỏ repo `thuexemayhanoi/theme`, nhánh đã duyệt, thư mục đích `wp-content/themes/nguyentu-editorial-commerce`.
Cách B — ZIP: `bash tools/build-theme-zip.sh` rồi tải lên.
Webhook chỉ bật **sau khi** xác minh push → deploy chạy thật (đừng nhầm HTTP 200 của webhook với việc triển khai).

> Thư mục `tools/`, `tests/`, `docs/`, `reports/`, `plugins/` đi kèm repo nhưng có `.htaccess` chặn truy cập web trực tiếp
> và mọi tệp PHP có `defined('ABSPATH') || exit;`.

## 4. Kích hoạt trên staging và chạy nghiệm thu

```bash
wp theme activate nguyentu-editorial-commerce
wp rewrite flush                       # đúng MỘT lần, có giám sát
wp nguyentu status                     # kiểm tra Yoast, Woo, featured item, vị trí menu
wp nguyentu-compat status              # replay_active: yes (Flatsome đã rời)
wp nguyentu-compat verify --probe       # 15 mục + 4 term resolve đúng và trả 200
wp nguyentu-compat compare featured-before.csv   # không MISSING / CHANGED
wp nguyentu legacy-scan --post-type=page,featured_item,product --format=markdown
```

Khi kích hoạt, theme chỉ **điền các vị trí menu còn trống** (`primary`, `primary_mobile` → menu #67; `footer`, `top_bar_nav` → menu #66),
không ghi đè gán sẵn có, và ghi nhật ký vào option `nt_activation_log`. Đặt `define('NT_DISABLE_MENU_AUTOMAP', true);` để tắt.

Sau đó chạy toàn bộ `docs/STAGING-CHECKLIST.md` (so ảnh 360/768/1440, SEO diff, QuickCall/MotoAI thật, đo shortcode thật).

## 5. Go-live (chỉ sau khi chủ website ký duyệt)

1. Sao lưu lần nữa (files + DB).
2. Cài plugin tương thích (đã kích hoạt khi Flatsome còn chạy, bước 2) và theme lên production như staging.
3. `wp theme activate nguyentu-editorial-commerce` → `wp rewrite flush` (một lần) → xóa cache LiteSpeed.
4. Chạy lại bước 4 trên production, rồi `node tools/seo/snapshot.mjs ... --out reports/seo-after.json` và
   `node tools/seo/diff.mjs reports/seo-before.json reports/seo-after.json`. **Mọi P0 phải bằng 0.**
5. Theo dõi Search Console (Coverage, Pages, Sitemaps) và nhật ký lỗi 404 trong 2 tuần. Thứ hạng không được cam kết bởi theme.

## 6. Cache

- LiteSpeed Cache: **không** tắt plugin. Giỏ hàng, thanh toán, tài khoản không được cache (mặc định của WooCommerce + LiteSpeed). Theme không in nội dung cá nhân hóa
  ngoài liên kết giỏ hàng chỉ hiện khi giỏ có hàng.
- Sau khi đổi theme: purge toàn bộ cache một lần.

## 7. Cấu hình tùy chọn (Giao diện › Nguyễn Tú)

Thông tin doanh nghiệp (mặc định = dữ liệu chuẩn), bảng giá tham khảo, bật/tắt từng mục trang chủ (mặc định "Tự động"), thanh liên hệ mobile,
mục lục, giỏ hàng trên header, LocalBusiness trong schema Yoast. Không có khóa API, không có chức năng xuất bản.
