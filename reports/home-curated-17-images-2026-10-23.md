# Nguyễn Tú: keep 17 uploaded homepage illustrations useful as new posts arrive

**Scope:** `thuexemayhanoi/theme` only · read-only WordPress verification 2026-10-23. **PR candidate only** until CI, merge and live deployed checks. This is NOT a bulk post/media update.

## Root cause verified

- Uploads `4978–4994` are all present in WordPress Media Library.
- The three service images `4990` (hero), `4987` (service introduction), `4978` (handover) are explicitly rendered by `template-parts/home/editorial.php`.
- **The other 14 images ALREADY ARE WordPress Featured Images for 14 matching, published posts.** The Media Library `post: null` field does not mean the image is unused: WordPress stores Featured Image association in `_thumbnail_id` on the post itself. Verified with a read-only join of `posts` and `postmeta`.
- The old homepage template queried the four newest posts for the hero magazine cards, three newest per two hubs and four more latest posts. New AI-created posts without thumbnails displaced all 14 image-backed posts. On 23/10, the 20 newest posts sampled all had `featured_media: 0`.

## Curated mapping — 14 existing published posts

| Illustration | WP media ID | WP post ID | Homepage placement |
|---|---:|---:|---|
| 01 Phố thông minh | 4992 | 4898 | Chia Sẻ |
| 02 Metro cuối chuyến | 4984 | 4897 | Bài viết chọn lọc |
| 03 Dây sạc bị sờn | 4985 | 4896 | Bài viết chọn lọc |
| 04 Xe ga 50cc và vali | 4981 | 4895 | Nội dung nổi bật |
| 05 Du lịch xe đạp điện | 4993 | 4892 | Chia Sẻ |
| 06 Xe buýt Nội Bài T1/T2 | 4986 | 4887 | Bài viết chọn lọc |
| 07 Túi ni lông chợ Hà Nội | 4979 | 4827 | Chia Sẻ |
| 08 Dung tích xi lanh | 4982 | 4788 | Đánh Giá & So Sánh Xe |
| 09 Đồng hồ xe máy | 4994 | 4787 | Đánh Giá & So Sánh Xe |
| 10 Smart Key | 4983 | 4786 | Đánh Giá & So Sánh Xe |
| 11 Trạm sạc, lối thoát | 4989 | 4894 | Nội dung nổi bật |
| 12 Đặt cọc USD/VND | 4991 | 4893 | Nội dung nổi bật |
| 13 Xe đạp điện chở hàng | 4988 | 4891 | Bài viết chọn lọc |
| 14 Tủ đổi pin điện | 4980 | 4890 | Nội dung nổi bật |

No re-upload, no duplicate image URLs, no assigning unrelated image to an article. Theme resolves every URL, title, category, post status and image using WordPress data at render time; IDs only select which existing posts can enter the curated list. Unpublished/deleted entire groups fall back to the old bounded post selection (also protects sandbox fixtures). Other category hub links remain live and unchanged.

## Homepage behavior after candidate

1. **Four prominent cards** use their four already published, matching images.
2. **Two three-card topic rows** use their already published, matching images from the correct taxonomy hubs.
3. **Four additional cards** show selected articles with their existing relevant images. Rename this section `Bài viết chọn lọc` rather than mislabel old posts as the newest.
4. **Five genuinely newest posts** remain discoverable via a new compact text-only list, not by replacing curated images.
5. Elsewhere on homepage/blog/archive/related, when a post has no featured image, render a CSS/SVG editorial tile containing the real topic name, not a false `<img>`, stock photo or an invented thumbnail. No media/API expense.
6. The above-fold service hero (`4990`) has WordPress `loading=eager`, `fetchpriority=high` and LiteSpeed's official `data-no-lazy="1"` flag so its URL is not replaced with a JS lazy-load placeholder. Curated card images are not eagerly prioritized above the hero.

**SEO and policy:** preserve post URL, H1-H4, canonical, Yoast, robots, products, existing WordPress image associations and the P0 Owner Lock (Quick Call/Assistant). Latest links use real post permalinks.

## Testing and release requirements

- CI: PHP lint, PHPCS, unit, Node, integration, existing Playwright homepage regression, Light/Dark/viewport/CTA/SEO invariants. New Node tests check the 14 disjoint mappings, fresh-post region and image-free placeholder.
- After merge: verify on **served** page and uncached origin at 390/768/1440: 14 cards map to the expected media/posts, 3 service visuals exist, five recent links update independently, no repeated article links, no layout shift or horizontal scroll. Verify `<img class="nt-home-mag__hero-img">` has an actual URL in `src` rather than LiteSpeed placeholder and LCP on a controlled mobile run.
- Monitor cache/deployment status; if no fresh evidence, mark `MERGED_NOT_LIVE_VERIFIED`. No WordPress cache purge without a scoped authorization/need. Real iOS Safari remains `NOT_TESTED` unless actually run.
- Roll back by reverting this PR (no database restore required).
- **Owner's future editorial choice:** curate different posts by replacing the verified IDs in the theme (not by attaching arbitrary images to new posts). Periodically review time-sensitive legal/transport pieces for currency before long-term pinning.
