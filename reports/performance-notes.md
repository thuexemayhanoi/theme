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
