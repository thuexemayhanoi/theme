# Ma trận tương thích shortcode cũ (Flatsome UX)

Trang #562 (trang chủ), #310, #372, #424, #432, #494 chứa shortcode Flatsome. Khi tắt Flatsome chúng sẽ hiện thô.
`inc/legacy/` là **cầu nối hiển thị**, không phải bản sao UX Builder và không dùng mã độc quyền của Flatsome:
nó đọc cú pháp shortcode công khai rồi sinh HTML ngữ nghĩa với class `nt-lg-*` (xem `assets/css/legacy.css`).

## Nguyên tắc

1. **Chỉ đăng ký tag còn trống.** Nếu Flatsome (hoặc plugin khác) đã đăng ký tag đó thì cầu nối không làm gì → rollback sang Flatsome an toàn.
2. **Không sửa nội dung lưu trữ.** Mọi thứ xảy ra lúc render.
3. **Không bao giờ lộ shortcode thô.** Tag chưa hỗ trợ bị bỏ khỏi đầu ra, *giữ nội dung bên trong*, thay bằng chú thích HTML
   `<!-- nt-legacy: [tag] is not supported ... -->` để biết vị trí cần xử lý.
4. **Giữ nguyên H1 và cấp heading.** `[title tag_name="h2"]` ra `<h2>`; mặc định `h3` như markup gốc. Nếu nội dung đã có `<h1>` thì theme không in H1 thứ hai.
5. **Giá trị CSS qua danh sách trắng** (`inc/lib/css.php`): chỉ độ dài, màu, vị trí hợp lệ; mọi thứ khác bị bỏ.
6. **wpautop chạy sau shortcode** cho nội dung có dùng cầu nối, để không sinh `<p>`/`<br>` thừa trong row/col.

## Tag được render

| Tag | Kết quả HTML | Thuộc tính được hỗ trợ | Ghi chú |
| --- | --- | --- | --- |
| `[section]` | `<section class="nt-lg-section">` + nền `<img>` + lớp phủ | `bg`, `bg_color`, `bg_overlay`, `bg_pos`, `dark`, `padding`, `height`, `class`, `id`, `visibility` | Nền là `<img>` có `srcset`, `alt=""` (trang trí) |
| `[row]`, `[row_inner]` | Flex row | `style` (collapse/small/large/xlarge), `width`, `v_align`, `h_align`, `padding`, `class`, `id`, `visibility` | 12 cột bằng CSS `calc()` |
| `[col]`, `[col_inner]` | `div.nt-lg-col` | `span`, `span__md`, `span__sm`, `align`, `valign`, `padding`, `margin`, `bg_color`, `bg_radius`, `color`, `max_width`, `class`, `id`, `visibility` | Điểm ngắt Flatsome: <550, 550–849, ≥850 |
| `[gap]` | `div.nt-lg-gap` (aria-hidden) | `height`, `height__md`, `height__sm` | |
| `[divider]` | `<hr class="nt-lg-divider">` | `width`, `height`, `margin`, `align`, `color` | |
| `[title]` | `<h3>` (hoặc `tag_name`) | `text`, `sub_text`, `style`, `size`, `tag_name`, `link`, `link_text`, `color` | Giữ cấp heading |
| `[ux_banner]` | `div.nt-lg-banner` | `height` (+`__md`,`__sm`), `bg`, `bg_color`, `bg_overlay`, `bg_pos`, `link`, `target` | Ảnh banner đầu trang là LCP: `loading=eager`, `fetchpriority=high` |
| `[text_box]` | Khối đặt trong banner | `width`, `position_x/y` (+`__sm`), `text_align`, `text_color`, `padding` | Vị trí % → start/center/end (khối cao hơn sẽ làm banner cao hơn, không tràn) |
| `[ux_text]` | `div.nt-lg-text` | `font_size`, `text_align`, `text_color`, `line_height`, `width` | |
| `[ux_image]` | `<figure>` + `wp_get_attachment_image` | `id`, `image_size`, `width`, `link`, `target`, `caption`, `margin` | Alt lấy từ thư viện media, không ghi đè |
| `[ux_image_box]` | Thẻ ảnh + chữ | `img`, `img_width`, `image_size`, `image_radius`, `style` (default/overlay/shade...), `link`, `text_align`, `text_padding`, `text_color` | Một liên kết bao cả thẻ; nếu chữ đã có liên kết thì chỉ ảnh là liên kết (tránh `<a>` lồng nhau) |
| `[ux_gallery]` | Lưới ảnh | `ids`, `columns`, `image_size` | Tối đa 24 ảnh |
| `[ux_video]` | Click-to-load (không iframe cho tới khi bấm) | `url` (YouTube/Vimeo) | Có liên kết thường cho no-JS |
| `[ux_slider]` | Dải cuộn scroll-snap | — | Mọi slide nằm trong DOM, không cần JS |
| `[map]` | Thẻ chữ + nút "Mở Google Maps" | — | Không nạp script bên thứ ba |
| `[button]` | `a.nt-btn` | `text`, `link`, `target`, `rel`, `style`, `color`, `size`, `radius`, `expand`, `icon`, `icon_pos`, `class` | `rel` trong nội dung được giữ nguyên; `javascript:` bị loại |
| `[message_box]` | Khối nền | `bg_color`, `padding`, `text_color` | |
| `[ux_html]` | HTML tác giả | — | Cùng mức tin cậy với `post_content` |
| `[accordion]`, `[accordion-item]` | `<details>/<summary>` | `title`, `open` | Nội dung đóng vẫn nằm trong HTML |
| `[tabgroup]`, `[tab]` | Các mục xếp chồng, hiện đủ | `title` | Không ẩn nội dung của tab |
| `[scroll_to]` | `<span id>` làm điểm neo | `link` | |
| `[share]`, `[search]`, `[lightbox]`, `[follow]` | Liên kết/biểu mẫu thường | — | Không script, không pixel |
| `[ux_products]` | Lưới thẻ sản phẩm WooCommerce | `products`, `columns`, `columns__sm`, `cat`, `ids`, `orderby`, `order`, `show`, `offset` | Cần WooCommerce; trần 24 |
| `[ux_product_categories]` | Lưới danh mục | `ids`, `number`, `parent`, `columns`, `hide_empty`, `orderby` | |
| `[blog_posts]` | Lưới bài viết | `posts`, `columns`, `cat`, `orderby`, `order`, `offset` | Chỉ bài đã xuất bản |
| `[ux_portfolio]` | Lưới `featured_item` | `cat`, `posts`, `columns`, `orderby`, `order` | Cần plugin `nguyentu-legacy-compat` (hoặc đăng ký cũ) |

Class tiện ích cũ trong HTML tác giả (`text-center`, `uppercase`, `lead`, `is-large`, `hide-for-small`...) được hỗ trợ ở mức tối thiểu
trong phạm vi `.nt-legacy`.

## Tag bị dọn (nội dung giữ lại)

`ux_hotspot`, `ux_countdown`, `ux_lottie`, `ux_banner_grid`, `col_grid`, `ux_instagram_feed`, `ux_stack`, `ux_menu`, `ux_menu_link`,
`ux_menu_title`, `ux_sidebar`, `ux_pages`, `ux_payment_icons`, `ux_logo`, `logo`, `product_flip`, `featured_box`, `team_member`, `testimonial`,
`page_header`, `ux_breadcrumbs`, `ux_price_table`, `ux_page_title`, `ux_bestseller`, `ux_dynamic_link`, `ux_dynamic_text` và mọi tag `ux_*` chưa biết.
Chúng cần JavaScript/layout riêng của Flatsome. Nếu staging cho thấy trang quan trọng dùng chúng, xử lý bằng cách dựng lại bằng block
Gutenberg trên **bản sao staging** rồi quyết định có áp dụng cho trang thật hay không (không nằm trong phạm vi tự động).

## Ánh xạ theo trang

| Trang | Dự kiến chứa | Cách hiển thị |
| --- | --- | --- |
| #562 `/` | banner + H1, hàng/cột, image box danh mục, `[ux_products]`, nút gọi/Zalo | `front-page.php`: nội dung được render nguyên vẹn; mục native chỉ thêm khi nội dung chưa có (xem `docs/HOME-LAYOUT.md`) |
| #310 `/gioi-thieu/` | section, row/col, ảnh | `page.php` (khổ rộng khi có section/banner/row) |
| #372 `/lien-he/` | NAP, nút, `[map]`, có thể có Contact Form 7 | `page.php`; thẻ NAP chuẩn chỉ thêm khi trang chưa có điện thoại + địa chỉ + bản đồ |
| #424, #432 | row/col, tiêu đề | `page.php`; nếu nội dung không có H1, theme in H1 là tiêu đề trang |
| #494 `/bo-suu-tap/` | `[ux_portfolio]` | Lưới 15 mục, cần `featured_item` |

## Đo độ phủ thật trên staging (bắt buộc trước go-live)

```bash
wp nguyentu legacy-scan --post-type=page,featured_item,product --format=markdown   # chỉ đọc
```

Kết quả liệt kê từng tag, trạng thái (RENDERED / STRIPPED / OTHER), số lần dùng, ID bài và danh sách thuộc tính thật.
Dán vào mục "Kết quả đo trên staging" bên dưới và so ảnh chụp trước/sau.

### Kết quả đo trên staging

_Chưa đo — cần bản sao DB production trên staging (cổng `flatsome-real-content` = NOT_TESTED)._
