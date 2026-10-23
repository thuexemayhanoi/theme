# Final WooCommerce / Theme QA — 2026-10-23 (Vietnam)

**Repository:** `thuexemayhanoi/theme`. **WordPress:** `https://thuexemaynguyentu.com/`.

**Scope:** Audit real shop, 13 products, category archives, responsive Light/Dark, performance; fix only evidenced defects and preserve original image/SEO/WordPress content. Existing experiment branch `feat/home-curated-17-media-fallback-20261023` is explicitly **OUT OF SCOPE** and **NOT MERGED**. The 17 uploaded WordPress images and naturally changing latest posts remain untouched.

## Evidence obtained from WPVibe (read-only)

- Active theme is `theme` (Nguyễn Tú Editorial Commerce v1.0.0), WordPress 7.1.3 / PHP 8.2.34. Deployed Git SHA was **not authenticated**; live assertions must never infer deployment from theme version.
- WordPress REST returned exactly **13 published WooCommerce products**. Each has a nonzero Featured Image reference.
- Rendered live HTML: all 13 individual product pages expose exactly one main H1, product-gallery image markup and a phone + Zalo contact link. This is **DOM/source confirmation**, not physical iPhone Safari QA. No cart/checkout, payment, or contact form submitted.
- Shop `/shop/`: 13 products. All 13 archive action links render visible `Đọc tiếp` but WooCommerce default `aria-label="Đọc thêm về …"`, a concrete WCAG 2.5.3 Label-in-Name mismatch. Fixed by scoped `woocommerce_loop_add_to_cart_args` filter: prepend the existing *visible* product action string to the accessible name while retaining product name and preserving href/cart/stock/price semantics. Woo source supports this filter.
- Existing CI on `main@78a2a1289050` passed at run [39869930996](https://github.com/thuexemayhanoi/theme/actions/runs/39869930996). Existing dark-mode correction #57 already merged. This new PR adds specific label-name e2e and integration assertions, plus a separate bounded read-only production test.

## Lighthouse lab checkpoint (WPVibe, before this patch)

| Route | Device | Performance | A11y | Best Practices | SEO | LCP | CLS |
|---|---|---:|---:|---:|---:|---|---|
| `/` | Mobile | 82 | 100 | 100 | 100 | 4.3s | 0 |
| `/shop/` | Mobile | 73 | 96 | 96 | 100 | 2.8s | **0.512** |
| `/shop/` | Desktop | 100 | 100 | 96 | 100 | 0.5s | 0 |
| `/san-pham/honda-vision/` | Mobile | 79 | 97 | 96 | 100 | 4.6s | 0 |
| `/danh-muc-san-pham/loai-xe/xe-dien/` | Mobile | 77 | 96 | 96 | 100 | 4.5s | 0 |

- These are Lighthouse **lab** snapshots, not reproducible before/after averages; CrUX real-user field coverage is unavailable.
- Lighthouse flags small touch targets and console errors on Woo mobile. Their **specific selectors, source scripts and reproduction are not yet known**. Do NOT apply speculative CSS/JS or weaken security/cache settings.
- Shop-mobile CLS 0.512 is important. **The source of shifting has not been proven**. The new `tools/qa/woo-final-live.mjs` records PerformanceObserver layout-shift sources, console messages, touch target candidates and Chromium geometry at 390/768/1440 across light/dark on four bounded pages. Analyze evidence before any CSS/LiteSpeed change.
- Live HTML uses LiteSpeed SVG `src` placeholders with true image URLs in `data-src`; this does not itself prove a rendering defect or LCP cause.

## Release and owner-lock rules

- No existing product image or owner-approved pricing policy changes. No WordPress database updates, media copies, bulk post operations, taxonomy edits, or destructive actions. No change to H1/H2/H3, menu, Yoast, canonical, sitemap or 301 aliases.
- **AGENTS.md P0 Owner Lock**: Quick Call and native Nguyễn Tú Assistant untouched. Shared perf changes that would affect them are prohibited without separate owner approval.
- PR first; CI and WordPress sandbox must pass before a merge. Recheck served live after merge; if not verified, mark MERGED_NOT_LIVE_VERIFIED.
- Native iOS Safari / real-device tests, Google Search Console, CrUX field CWV and audited deploy SHA remain **NOT_TESTED**.
- If all release gates pass, this is intended as the **last Woo-focused release before freezing `/theme` for SEO**. Open owner-locked/historical PRs must not be merged just to get to zero open PRs. Record any future audit findings as issues, not opportunistic runtime changes.

**Rollback:** revert this one PR; no WordPress data rollback needed.
