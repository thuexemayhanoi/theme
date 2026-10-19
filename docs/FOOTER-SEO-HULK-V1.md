# PERMANENT FOOTER SEO HULK V1 — 2026-10-19

**Scope:** `thuexemayhanoi/theme` only. Production theme/site and all WordPress content **unchanged by this PR**. PR #5 is a draft feature branch per README/CLAUDE.md; main/production is not silently activated.

## 1. Read-only audit and evidence

Inspected README.md, CLAUDE.md, docs/SEO-HULK.md, docs/wordpress-baseline-2026-10-17.json, footer.php, template-parts/footer/{columns,contact}.php, inc/{business,settings,menus,seo,schema}.php, the live rendered HTML of the homepage/contact/price archive/blog category and automated test files. Initial live HTML was retrieved through a fresh public scrape on 2026-10-19 (maxAge 0, source https://thuexemaynguyentu.com/).

- **Old homepage footer:** 46 rendered anchors pointing at **44 unique URLs**; includes most of the 36-item multilevel primary navigation. Full list, including duplicate anchors: `reports/footer-live-before-2026-10-19.csv`.
- **Live inconsistencies:** at collection/contact/category URLs, public HTML was observed with an earlier four-column footer implementation and on one collection page with optional WooCommerce widgets (Latest/Best Selling/Top Rated). Homepage had a different footer revision. These differences indicate a deployment/cache consistency issue needing review; do not infer root cause without checking WordPress active theme and cache.
- **Status check of intended target URLs:** all **21 selected** internal destinations returned **200** in the public web scrape, recorded in `reports/footer-curated-urls-2026-10-19.csv`. All **seven** hub archives also reported **index, follow**. These observations were taken before deploying the PR, and do not prove that the post-deploy render is correct.
- **P0 existing rewrite drift:** `/bo-suu-tap/thue-ngay/` and `/bo-suu-tap/thue-tuan/` returned **404**, whereas currently generated WP term links `/featured_item_category/thue-ngay/` and `/featured_item_category/thue-tuan/` returned **200**. Legacy permalink inventory expects `/bo-suu-tap/...`. No rewrites, redirects, canonical, Yoast metadata, or taxonomy data were modified here. This P0 must be independently reconciled before stating SEO parity; fix outside the Footer-only change with separate authorization.

## 2. Long-lived architecture

- Four stable columns: (1) brand/verified NAP/tagline/social; (2) 9 selected money/service links; (3) 7 topical Hub links; (4) 5 published information pages + existing CTA buttons.
- Single curated manifest at `inc/footer.php` specifies existing WordPress **term/post IDs** only, not invented URLs. Terms use `get_term_link`, public pages/products use `get_permalink`. Unpublished, missing, non-public and foreign-host targets are filtered out, as are repeated URL and navigation anchor text.
- Adding 1,000+ posts or descendant categories does **not** add new footer links. Links naturally update if WordPress changes a published object's permalink. Built-in WP object caching is used; no transient or manual invalidation needed.
- `inc/business.php` remains the only NAP source (no separately hard-coded address); footer brand column shows it once. The last column renders only `nt_cta_buttons()`, preserving existing QuickCall plugin detection without adding a second floating bar.
- The footer-only tagline specifically says hourly **consultation**, not a guaranteed hourly rental plan. Site tagline and Yoast title/meta are untouched.
- Footer is server-rendered with semantic `section`/`nav`/`ul`/`li`/`a`/`address`, **no H1**, three informational H2 group headings; no hiding links/keyword stuffing, and no additional JSON-LD.
- No JavaScript added, theme color system preserved, responsive 1 column (<640px), 2 columns (640–1023px), 4 columns (>=1024px). Fixed mobile QuickCall and chatbot CSS untouched. Only CSS scoped to footer changed.
- The footer bottom copyright year continues using `wp_date('Y')`, with neutral booking/deposit confirmation copy.

## 3. QA matrix / release gates

| Check | Status | Evidence |
|---|---|---|
| Curated IDs, no URL invention, no draft link | IMPLEMENTED / CI REQUIRED | `inc/footer.php` + WP integration/E2E |
| 21 candidate internal URLs return 200 on live site before deployment | PASS, scoped to 2026-10-19 observations | `reports/footer-curated-urls-2026-10-19.csv` |
| Hub index, follow (all 7) | PASS, scoped to current public HTML | Web scrape metadata on 2026-10-19 |
| No raw menu dump, duplicate nav URLs/anchors | CI REQUIRED | `tests/e2e/footer.spec.mjs` |
| 360, 390, 768, 1024, 1440 widths | CI REQUIRED | `tests/e2e/footer.spec.mjs`, not live screenshots |
| Home/about/contact/category/product/collection share footer | CI REQUIRED | WordPress sandbox Playwright |
| NAP, three headings, social, actions and no duplicate schema | CI REQUIRED / live UNVERIFIED | PHP template + SEO-head regression tests |
| Old /bo-suu-tap/ term URL preservation | **P0 FAIL (pre-existing observed)** | Two old URLs 404 as above |
| Real plugin QuickCall + MotoAI overlapping at footer | **UNVERIFIED** | Needs production-equivalent staging/mobile |
| Live pre/post Yoast canonical/title/meta/JSON-LD diff | **UNVERIFIED** | Needs fresh before/after snapshot of identical routes |
| Core Web Vitals real-user measurements | **UNVERIFIED** | Requires field collection |
| Post-merge deployment/version/cache parity | **UNVERIFIED** | Needs owner-approved deploy and fresh HTML |

These labels are not a claim of final production acceptance. See CI for current technical status. Do not merge solely because tests pass while the legacy URL P0 or live comparison is still outstanding.

## 4. Release, rollback, maintenance

1. Inspect PR #5 and its test logs; use a staging copy of production, with backup first.
2. Compare HTML, NAP, Yoast canonical/meta/schema, and the main categories, products, posts, and featured archives before/after. Confirm the main menu, QuickCall and MotoAI remain intact.
3. Only with owner approval, merge/deploy through the existing Deployer for Git integration. Confirm the active theme points at expected commit and purge the correct LiteSpeed/CDN caches. Do not treat a successful webhook ping as proof of deploy.
4. If regression occurs, revert the PR merge commit in GitHub or switch the active site to its verified prior theme via hosting backup; do not delete products, pages, menus, posts, taxonomies or SEO settings.
5. New articles/categories need no footer edits. To intentionally replace one of the 21 target hubs or business pages, update its ID/label in the single manifest only after verifying its published destination.
