# Homepage WordPress images — live audit (22 October 2026)

Question: do the 17 WordPress images of the homepage load, sit where the design puts them, keep their space (no layout shift), carry sensible alt text and loading attributes — and do visitors see them?

The 17 are the 14 post thumbnails `01-…` to `14-…` made earlier and the three images added by PR #54: the hero `15-thue-xe-may-ha-noi-nguyen-tu-hero` (attachment 4990) and the two service visuals `16-gioi-thieu-dich-vu-thue-xe-may-nguyen-tu` (4987) and `17-giao-nhan-xe-may-nguyen-tu-minh-hoa` (4978).

## Method (read-only)

`tools/qa/homepage-images-live.mjs` in Chromium at 390 × 844, 768 × 1024 and 1440 × 900, on two renders of the same page:

- **served** — `https://thuexemaynguyentu.com/` exactly as visitors get it;
- **origin** — the same page with a unique query string (`?nt-origin=…`), which the page cache does not hold, so it shows the current theme and WordPress data.

Rules are in `tools/qa/lib/homepage-images.mjs` (11 unit tests). `tools/qa/homepage-image-probe.mjs` adds a handful of public GETs: the two renders, the stylesheets, and the public media endpoint for 4990, 4987 and 4978. Run: [39855485819](https://github.com/thuexemayhanoi/theme/actions/runs/39855485819) (commit `2b9f191`, 21:13–21:15 UTC), conclusion success; audit status **`PASS_ORIGIN_STALE_PAGE_CACHE`**. Earlier runs that shaped the rules: 39854007583, 39854464501, 39854822260.

Nothing was written to WordPress. The cache-busted requests are ordinary GETs; LiteSpeed keeps a short-lived page-cache entry for each (the 768 and 1440 px origin renders hit the entry made by the 390 px one, so they show the same fresh page). "Natural" sizes in the run log are density-corrected by the browser (the file's width divided by its `srcset` density), so a `-510x287` file in a 390 px slot reports about 390; the file named in the tables is the one the browser chose.

## Verdict

| Question | Result |
| --- | --- |
| Do the three PR #54 images exist and work? | **Yes.** Attachments 4990, 4987, 4978 answer HTTP 200 on the public media endpoint as `image/webp` with alt text; the theme's stylesheet served by the site contains the hero rule (`hasHeroRule=true`). |
| Does the origin render show them? | **Yes**, at all three widths: 1 hero (eager) and 2 service visuals (lazy), 16:9, distinct files. |
| Do visitors see them now? | **No.** The served homepage (`x-litespeed-cache: hit`, `max-age 604800`) is an older cache entry: no `nt-home-mag__hero-visual`, no `nt-home-mag__visuals` in its HTML, 23 `<img>` against 15 in the origin render. Probe verdict `STALE_PAGE_CACHE`. |
| Does anything shift or load badly? | **No.** In all six renders every image loaded (`complete`, natural size > 0), none overflows the viewport, **layout shift 0** and **0 caused by images**, no duplicate file in a render. |
| What is the LCP element? | The lead paragraph (`P.nt-home-mag__lead`), never an image: 1.50–1.53 s on cached pages, 1.50 s on the origin at 768/1440 px, **3.06 s on the first (cold) origin request at 390 px**. These are GitHub-runner timings, informational, not field data. |

**Action (WORDPRESS_ACTION_REQUIRED):** purge the LiteSpeed page cache of the homepage (LiteSpeed Cache → Toolbox → Purge, or purge the single URL). The theme is deployed; this repository cannot and must not purge production caches. The purge will also show everything else that changed since the cached copy (next section), not only the three images.

## What the three new images look like (origin render)

| Image | Role | 390 px | 768 px | 1440 px | Loading | Alt (as served) |
| --- | --- | --- | --- | --- | --- | --- |
| 15 hero (4990) | first screen (top 427 / 399 / 551 px) | 356 × 200 (file `-510x287`) | 705 × 396 (`-768x432`) | 632 × 355 (`-1400x788`) | eager | "Các loại xe máy và xe tay ga bên cầu Long Biên lúc hoàng hôn, hình minh họa AI" |
| 16 service visual (4987) | below the fold (top 6,599 / 5,216 / 4,300 px) | 356 × 200 | 342 × 192 | 585 × 329 (`-711x400`) | lazy | "Quầy tư vấn thuê xe và dãy xe tay ga, ảnh minh họa không phải cửa hàng thật" |
| 17 service visual (4978) | same row as 16 | 356 × 200 | 342 × 192 | 585 × 329 (`-711x400`) | lazy | "Khách nhận chìa khóa và mũ bảo hiểm bên xe tay ga, hình minh họa AI" |

All three carry `width`/`height` 760 × 428 (a 16:9 box reserved before the file arrives, hence no shift), the displayed ratio is 16:9 and the hero is at least 240 px wide on every viewport. The alt texts are short, descriptive and say honestly that the pictures are illustrations; none repeats a keyword. **Decision: keep all three as they are.** No re-upload, no alt change.

## The 14 post thumbnails (01–14)

- In the served (cached) page all 14 are present and load at every width, with unique files and descriptive alt text; one of them (the first featured card) is eager and below the first screen at 390 px — a warning, not a failure, because the LCP is text.
- In the **origin** page only three of them appear (01, 08, 09). The reason is data, not a fault of the images: the homepage queries pick the newest posts, and the newest posts published since the cached copy have **no featured image**. The card inventory of the origin render: featured grid 4 cards, 0 with image; topic rows 6 cards, 3 with image; latest 4 cards, 0 with image — **11 of 14 article cards are text-only**, the other eight cards (products and categories) have their usual images.
- Consequence: after the cache purge visitors will see text-only cards where the cached page has pictures. The audit's hard rules still pass (no overflow, no layout shift), but a human has not looked at the result: the screenshots are in the run's artifact `homepage-live-evidence`, which this sandbox cannot download (host blocked). **Owner decision (WORDPRESS_ACTION_REQUIRED, optional):** give the 11 newest posts a featured image like the `01–14` series, or accept text-only cards. The theme prints no `<img>` for a post without a thumbnail; this repository does not change content.

## Notes, not defects

- **`fetchpriority`.** The template asks for `fetchpriority="high"` on the hero (`template-parts/home/editorial.php`), but the HTML the server sends has no such attribute. The raw hero tag carries `data-lazyloaded`, `data-src`, `data-srcset` and `data-sizes` next to `loading="eager"`: LiteSpeed Cache's image lazy-load rewrite is applied to the hero too. Measured effect: none visible (text LCP, shift 0), so nothing was changed. If the owner wants the hero loaded without the lazy-load script, exclude it in LiteSpeed Cache → Page Optimization → Media → Lazy Load Image Excludes (optional; measure before and after).
- **Product images** (8 per render) are existing WooCommerce thumbnails of 247 × 296 shown at 169 × 127 (390 px) up to 277 × 208 (1440 px): cropped and up to about 12 % enlarged on desktop. Not part of the 17, not changed by PR #54, no action proposed without a visible problem.
- **Cached copy at 390 px**: first featured image eager at 942 px, below the first screen (warning only).

## Not tested

Real iPhone/Android devices and Safari (Chromium viewport emulation only); Lighthouse and field Core Web Vitals; human review of the screenshots; behaviour after the cache is purged (it needs the purge first — re-run *Homepage live QA* afterwards; the audit then reports `PASS` instead of `PASS_ORIGIN_STALE_PAGE_CACHE`).
