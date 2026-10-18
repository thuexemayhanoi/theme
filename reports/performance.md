# Performance report (lab measurements)

Measured 2026-10-18T02:03:56.301Z with 141.0.7390.37 against `http://127.0.0.1:8088`.

> **Read this first.** These are LAB measurements on a local PHP built-in server inside a container: no page cache, no gzip/brotli, no CDN, no HTTP/2, shared CPU. They show relative cost and catch regressions; they are **not** field data (CrUX/RUM), **not** a Lighthouse score claim and **not** a ranking promise. Real Core Web Vitals need real traffic (75th percentile mobile) after go-live. Thresholds quoted: LCP <= 2.5 s, INP <= 200 ms, CLS <= 0.1.

## mobile 390px, 4x CPU, 1.6 Mbps / 150 ms RTT (Lighthouse "slow 4G" style)

| Page | LCP ms (element) | CLS | Interaction ms* | Requests | HTML KB | Total KB (raw) | Total KB (est. gzip) | CSS KB gz | JS KB gz | Fonts KB | Images KB | 3rd-party |
| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| home | 2044 (img.nt-lg-bg__img) good | 0 good | 40 | 28 | 94.0 | 479.9 | 257.3 | 16.4 | 50.3 | 34.9 | 139.7 | 0 |
| post | 1936 (p.wp-block-paragraph) good | 0 good | 32 | 18 | 81.1 | 343.5 | 151.1 | 10.5 | 51.6 | 34.9 | 37.7 | 0 |
| category | 1856 (img.nt-card__img) good | 0 good | 40 | 15 | 68.3 | 326.0 | 145.1 | 10.0 | 50.3 | 34.9 | 37.7 | 0 |
| product | 2496 (img.wp-post-image) good | 0 good | 32 | 30 | 73.7 | 603.3 | 254.6 | 30.3 | 77.9 | 34.9 | 96.6 | 0 |
| contact | 1616 (p) good | 0 good | 40 | 14 | 56.1 | 279.8 | 107.0 | 11.0 | 50.3 | 34.9 | 0.0 | 0 |

## desktop 1440px, no throttling

| Page | LCP ms (element) | CLS | Interaction ms* | Requests | HTML KB | Total KB (raw) | Total KB (est. gzip) | CSS KB gz | JS KB gz | Fonts KB | Images KB | 3rd-party |
| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| home | 256 (img.nt-lg-bg__img) good | 0 good | 48 | 28 | 94.0 | 488.8 | 266.2 | 16.4 | 50.3 | 34.9 | 148.5 | 0 |
| post | 212 (img.nt-article__img) good | 0 good | 40 | 18 | 81.1 | 330.9 | 138.5 | 10.5 | 51.6 | 34.9 | 25.1 | 0 |
| category | 212 (img.nt-card__img) good | 0 good | 32 | 16 | 68.3 | 328.3 | 147.4 | 10.0 | 50.3 | 34.9 | 40.0 | 0 |
| product | 220 (img.wp-post-image) good | 0 good | 40 | 30 | 73.7 | 590.1 | 241.3 | 30.3 | 77.9 | 34.9 | 83.4 | 0 |
| contact | 216 (span) good | 0 good | 40 | 14 | 56.1 | 279.8 | 107.0 | 11.0 | 50.3 | 34.9 | 0.0 | 0 |

\* Interaction = longest event-handling duration of the first menu/search click observed with PerformanceObserver (`event` entries). It is an INP-style lab proxy, not INP.

## Lighthouse (mobile preset, simulated throttling): default

| Page | Performance | Accessibility | Best practices | SEO | LCP s | CLS | TBT ms |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| home | 87 | 100 | 100 | 100 | 3.55 | 0 | 0 |
| post | 91 | 100 | 100 | 92 | 3.17 | 0 | 7 |
| category | 93 | 100 | 100 | 92 | 2.87 | 0 | 1 |
| product | 79 | 100 | 100 | 100 | 4.37 | 0 | 0 |

Lighthouse 12.8.2, 2026-10-18T02:05:42.643Z. Scores come from a local, uncompressed server and vary between runs; treat them as a smoke test.

## Lighthouse (mobile preset, simulated throttling): lean: setting "Script jQuery của WooCommerce" = on

| Page | Performance | Accessibility | Best practices | SEO | LCP s | CLS | TBT ms |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| home | 97 | 100 | 100 | 100 | 2.25 | 0 | 5 |
| post | 98 | 100 | 100 | 92 | 2.26 | 0 | 0 |
| category | 99 | 100 | 100 | 92 | 1.96 | 0 | 0 |
| product | 79 | 100 | 100 | 100 | 4.38 | 0 | 13 |

Lighthouse 12.8.2, 2026-10-18T02:06:32.655Z. Scores come from a local, uncompressed server and vary between runs; treat them as a smoke test.

## Findings from the 2026-10-18 runs (what the numbers do and do not say)

**Tóm tắt (VI):** số đo là số đo phòng thí nghiệm trên máy chủ cục bộ, không phải dữ liệu thật và không phải cam kết thứ hạng.
Điểm yếu lớn nhất trên trang chủ/bài viết không nằm ở mã của theme mà ở **jQuery do WooCommerce nạp trong `<head>`** (chặn hiển thị).
Tùy chọn *Script jQuery của WooCommerce* (mặc định TẮT) loại bỏ nó ở trang không-Woo; cần kiểm tra trên staging trước khi bật.

1. **Largest cause on the home page (default settings): render-blocking jQuery.** Lighthouse lists `jquery.min.js` (88 KB raw) and
   `jquery-migrate.min.js` in the head as the two most expensive blocking requests (about 1.5 s and 0.75 s of estimated delay). Nothing in the
   theme depends on jQuery; WooCommerce's frontend scripts do, and WordPress prints jQuery in the head for them. The LCP breakdown on mobile:
   TTFB 13 %, load delay 46 %, load time 4 %, render delay 37 % (the hero image itself downloads in ~140 ms).
2. **Opt-in fix, measured.** With *Script jQuery của WooCommerce = on* the same pages load no jQuery and no Woo script except order attribution
   (`sourcebuster`, `order-attribution`, no jQuery dependency): home 87 → 97, post 91 → 98, category 93 → 99 (Lighthouse mobile, lab), page weight
   432 KiB → 312 KiB on the home page. It stays **off by default** because inline snippets elsewhere on the live site (WPCode, plugin widgets) may assume jQuery exists;
   `docs/STAGING-CHECKLIST.md` lists the check to run before enabling it.
3. **Already done in the theme:** WooCommerce's three stylesheets (about 16 KB gzip, render-blocking) are loaded only on shop, product, cart, checkout,
   account screens and pages that embed Woo shortcodes/blocks (filter `nguyentu_load_woo_assets` forces them everywhere); the hero image is `loading="eager"`
   with `fetchpriority="high"` and real dimensions; fonts are self-hosted with a size-adjusted fallback; CLS is 0 on every measured page.
4. **Items in the Lighthouse opportunity list that belong to this test server, not the theme:** text compression (`php -S` sends no gzip/brotli: "220 KiB"),
   cache lifetimes for static files (no `Cache-Control`: "334 KiB"), and CSS minification (LiteSpeed Cache minifies in production; the theme ships readable CSS).
   They disappear or shrink behind the real server stack; they must be re-measured there.
5. **Product pages (79) stay slower than other templates:** WooCommerce's gallery needs jQuery, FlexSlider, zoom and PhotoSwipe (about 70 KB of JavaScript
   Lighthouse flags as unused until the visitor interacts). Replacing the gallery script is a WooCommerce-template change the theme deliberately does not make
   (updates of Woo/Yoast Woo/payment gateways must keep working). Lazy-loading those scripts is a candidate follow-up, not part of this release.
6. **SEO 92 on post/category pages** is the single audit "Document does not have a meta description": the sandbox's Yoast has no description template for them.
   The text belongs to Yoast; whether the live site has it is decided by the before/after diff (`tools/seo/diff.mjs`), not by the theme.
7. **Not measured here:** field Core Web Vitals (CrUX/RUM), real images, CDN/page cache, third-party plugins (MotoAI, QuickCall, GTranslate, CF7, Site Kit).
   Every number above can only be repeated, not extended to production, until the same run is made on staging.

## Budgets enforced in CI

See `tools/qa/budgets.mjs`: main.css <= 34 KB raw / 9 KB gzip, theme.js <= 14 KB / 4.5 KB gzip, fonts <= 40 KB, critical path (main.css gzip + two fonts) <= 60 KB.

## Limits of this environment

- Single run per page and profile; variance between runs is not measured.
- Local loopback network: CDP throttling approximates, but does not reproduce, a mobile network.
- Production adds LiteSpeed Cache, brotli, HTTP/2/3, real images (the sandbox uses generated JPEGs) and third-party plugins (MotoAI, QuickCall, GTranslate, CF7, Site Kit) whose cost is not included here.

