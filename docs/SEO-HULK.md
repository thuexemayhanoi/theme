# SEO HULK — NGUYỄN TÚ THEME TECHNICAL SEO & AEO/GEO ACCEPTANCE CONTRACT
Version: 1.0 — 2026-10-17

> **P0 BUILD REQUIREMENT, NOT AN OPTIONAL POLISH TASK.** This project's "SEO HULK" is a nickname for comprehensive, measurable **technical SEO engineering**. It is not a plugin, not a claim of ranking guarantees, and must not invent SEO scores, traffic, snippets, rankings or schema. Existing published SEO configuration has priority over newly invented defaults. Read README.md, CLAUDE.md and docs/wordpress-baseline-2026-10-17.json.

## 0. The non-negotiable SEO mission

Develop a custom standalone WordPress theme that keeps the existing `https://thuexemaynguyentu.com/` identity, indexing, rich-data trust and business-conversion journeys intact when transitioning away from Flatsome + Child.

* SEO baseline belongs to the **CURRENT live WordPress + Yoast SEO**. Read canonical, robots, titles, descriptions, JSON-LD, heading structure, navigation, internal links, hreflang (if any), meta/social tags, sitemaps and status codes **before writing replacement code**.
* Theme controls *semantic frontend structure and performance*. Yoast SEO controls its own SEO metadata, canonical, indexability, Open Graph, Twitter/X metadata, XML sitemaps, breadcrumbs/JSON-LD as configured. **Never inject duplicates** or disable Yoast or rewrite its metadata; only provide integration hooks and semantic containers.
* Theme migration must not change `/%postname%/`, category URLs under `/blog/...`, WooCommerce `/san-pham/...`, `/danh-muc-san-pham/...`, featured-item `/bo-suu-tap/...`, protected money pages, homepage `/` (page ID 562), any indexed content's canonical or URL.
* No claim that a theme can *guarantee top rankings*; Search Console/analytics must verify indexing and traffic after rollout. Good technical SEO supports crawlability and visitor experience but cannot replace substantive content and trust.

## 1. Golden URL inventory and zero-regression snapshot

Record pre-migration response status, canonical href, meta robots, `<title>`, Yoast SEO description, top-level H1 count/text, meta viewport, schema graph `@type`, internal link targets, response headers/redirect chains, structured content and main business CTAs.

**Minimum URLs:**
- `/` (home page ID 562) — H1 and core rent-a-bike intent, address, Call/Zalo/Maps, hero, prices, product sections.
- `/blog/` (category ID 69, NOT posts-index WP Page); `/blog/xe-dien/`; `/blog/phap-ly-kinh-nghiem/`; `/blog/diem-den/`; 2+ nested category children from snapshot.
- `/gioi-thieu/`, `/lien-he/`, `/faq/`, `/shop/`, `/bo-suu-tap/` and three nested featured-item term URLs; one `/san-pham/{slug}/`; one published blog post; one paid-intent money page; `/404-invalid-nonexistent/`.
- `/robots.txt`, `/sitemap_index.xml` and relevant Yoast child sitemaps if exposed; legitimate RSS/search pages; structured post navigation; indexed source links from menu.
- Refresh real URL list from WP inventory to avoid imagined URLs. HTTP 200 and semantic parity required on all public verified URLs; 404 must remain 404, not 200 with empty content.

Export `reports/seo-before.json`, `reports/seo-after.json`, `reports/seo-diff.md`, and `reports/seo-urls.csv` (actual before/after data, not fabricated). If live comparison cannot be performed, flag **UNVERIFIED** with required manual tests instead of claiming PASS.

### Regression severity:
- **P0 BLOCK deployment:** existing public URL becomes 404/redirected/noindex/canonical drift, important text/H1 lost, product/order/CTA broken, menu category tree dropped, Yoast/structured data duplicated, robots/sitemap blocked, Flash of raw Flatsome shortcode, critical accessibility defect.
- **P1 fix before go-live where feasible:** missing breadcrumbs, incorrect internal-link targets, overlong page response, horizontal overflow, weak content discovery, image dimension/alt gap, unnecessary JS/CSS.
- **P2 enhancements:** UI refinements, more powerful related content, nonessential image optimization, editorial niceties.

## 2. Semantic HTML, accessibility and mobile-first content

Use HTML5 `header`, `nav`, `main`, `article`, `section`, `aside`, `footer`; one `main` landmark; skip link; meaningful heading hierarchy; ideally one genuine H1 per route, no theme-generated extra H1 for editor content with one already. Preserve homepage source H1 from page #562 when building custom frontend; **never silently overwrite SEO H1**. Keep core textual selling propositions discoverable as HTML, not exclusively canvas/JS or images.

Responsive/mobile-first accessible navigation with all 36 menu items and ancestry, keyboard-usable expandable submenus, `aria-expanded`, labels, escape/focus handling, contrast and no horizontal overflow. Desktop, mobile and tablet must display the same critical content and CTA paths. Search UI should have proper query form, semantic results and pagination.

For category archives: term-based H1; real `term_description`; breadcrumb ancestry and canonical; server-side query of correct posts; proper pagination URLs; empty categories remain manageable but **no invented content or new categories**. For single posts: visible publication metadata when appropriate, H2/H3 TOC only on actual headings, previous/next in existing cluster, related internal posts, correct author attribution, image alt, no fabricated review/comments. Do not auto-insert random marketing anchors into neutral safety guides.

## 3. Yoast SEO — one source of truth for metadata and schema

- Keep `wp_head()`, `wp_footer()`, `wp_body_open()`, `language_attributes()`, `body_class()`, `wp_title`/title-tag theme support, proper WP hooks and standard query/template hierarchy.
- Yoast currently installed and active on target; use its hooks/output rather than emit competing theme-defined title, meta description, canonical, robots, Open Graph, Twitter/X and BreadcrumbList tags. Detect and regression-test **exactly one canonical** for indexable pages and one expected title/robots authority.
- Schema: preserve Yoast `@graph` WebSite, WebPage, Article, BreadcrumbList, Organization or LocalBusiness if it exists. Only add verified, applicable missing properties through documented Yoast filters after inspecting what exists; **never produce competing graphs of same entities**. Valid `LocalBusiness` requires verified NAP: business "Cho Thuê Xe Máy Nguyễn Tú", phone `0942467674`, address **112 Nguyễn Văn Cừ, Bồ Đề, Long Biên, Hà Nội**, opening hours only if currently verified. Use real map/profile IDs. Do not invent `AggregateRating`, review counts, ratings, offers, inventory, FAQ or service area coverage.
- WooCommerce: use its legitimate Product/Offer data where provided. Do not fake prices, reviews, `inStock` or product claims. Preserve WooCommerce hooks/template compatibility and Yoast WooCommerce if installed.
- FAQPage structured data only when the actual visible FAQ and eligibility justify it; Google rich-result eligibility is not guaranteed. Avoid HowTo markup solely for rich results.
- AEO/GEO: useful direct answer in first content section where already supported by content, clear factual NAP, consistent entity details, concise headings and FAQ when genuinely useful; never fabricate first-hand claims or automated testimonials. Theme must support content, not rewrite writer drafts.

## 4. Crawling, indexing and sitemap safety

- Production's `blog_public` is currently 1. Do not change site visibility or switch posts to `noindex` en masse.
- Preserve `robots.txt`, Yoast `/sitemap_index.xml`, child sitemaps, existing canonical setup and pagination strategy. Search and 404 templates should honor Yoast/WordPress robots policies; avoid indexing internal search results.
- No forced auto redirects, slug rewrite, slug transliteration, changing taxonomy base, global nofollow/noreferrer, bulk category changes or new URL patterns.
- Avoid canonical-to-homeplace and duplicate `rel=canonical`. A dynamically generated archive must use native queried term/object canonical, not hard-coded URL.
- Hreflang only on valid, bidirectionally verified translations and equivalent localized URLs. GTranslate presence alone does not authorize synthetic hreflang. Do not create thousands of crawlable filter/query pages.
- Preserve legitimate index/follow behavior of current published URLs; don't impose blanket noindex on archives. Audit thin archives and tag archives, report recommendations rather than changing production settings automatically.

## 5. Internal link graph and controlled discovery

- Render primary + mobile menu from **WP menu #67 via `wp_nav_menu()`** and correct parent structures. Theme should register menu locations compatible with existing `primary`, `primary_mobile`, `footer`, `top_bar_nav`.
- Render breadcrumb compatible with Yoast (prefer its function when enabled) for posts, hierarchical categories, products and marketing pages; no 2nd schema graph.
- Build optional lightweight related posts via taxonomy/term data and indexed published posts only (no draft, no broken link), cap list per page, prevent N+1 queries; cache computed results where useful.
- Relationship: *supporting article → category hub/pillar → commercial page only when relevant*. Do not force homepage anchors in each paragraph, inject sitewide exact-match keyword blocks, spam 36 menu links into page bodies, generate circular irrelevant links or link to unpublished pages.
- Technical deliverable: `reports/internal-links.csv` with source URL, target URL, anchor, published status, category/silo, detected breakage, recommendation. Can begin with a sampled URL inventory in staging if full crawl costly, label coverage.

## 6. Core Web Vitals — measured targets, not invented scores

Aim at **LCP ≤ 2.5s**, **INP ≤ 200ms**, **CLS ≤ 0.1** for real-world **75th-percentile** mobile visits where measurable. CrUX/RUM may be unavailable on low-traffic pages; use lab Lighthouse as lab evidence, not field success.

Implementation principles:
- Minimal dependencies; avoid jQuery when not needed, giant frameworks, render-blocking libraries, remote font critical path. Keep theme CSS/JS modular and load only on relevant templates; use `wp_enqueue_scripts` with file version/cache busting.
- Render important content server-side with PHP. Header, H1, primary CTA and content render without hydration. Don't replace semantic content with client-only JS.
- Featured hero image: correctly sized `srcset`, `sizes`, explicit width/height, nonlazy LCP image, optional `fetchpriority=high` only where justified. Other images lazy-load; WebP/AVIF preferred where real pipeline provides them; descriptive but natural ALT. Optimize embedded video/map to click-to-load when appropriate. Do not break WooCommerce galleries.
- Avoid layout shift: dimensions for logo/media, reserve menu, sticky header, chatbot and QuickCall positions; avoid duplicate fixed contact bars on mobile.
- Cache-compatible with currently active **LiteSpeed Cache** and product-cart sessions (never cache personal cart/checkout HTML). No blind cache plugin disabling.
- Keep lighthouse results, HTML bytes and network payload in `reports/performance.md`; test home, article, archive and WooCommerce product on 360/390/768/1440 widths, throttled mobile where available. Record limits of the test environment.

## 7. Brand, trust and local discovery

The active local business uses **Nguyễn Tú** branding, not Nguyễn Hà. Verified core business data: phone `0942 467 674`; address `112 Nguyễn Văn Cừ, Bồ Đề, Long Biên, Hà Nội`. Compare current live header/footer/contact/map and Google Business Profile if publicly available; do not overwrite with guessed details.

Keep customer conversion visible: Call `tel:0942467674`, Zalo `https://zalo.me/0942467674`, actual verified Google Maps URL, WhatsApp only if available, contact page link, booking information and rental conditions. No claims such as "free delivery", "no deposit", "24/7", "brand-new fleet", "hundreds of 5-star reviews" unless independently verified.

Preserve image metadata and existing alt captions; the theme must not swap media IDs or overwrite WP Media Library. Existing quick-call / MotoAI Chat widgets should remain functional without duplicate sticky bars or script conflicts.

## 8. SEO-HULK acceptance tests: build, test, diff, fix

Claude must **write runnable checks**, not a decorative checklist. Minimum:

1. PHPUnit/WP test: register nav menus, templates, hooks, theme supports, Yoast coexistence, featured-item compatibility and WooCommerce template detection.
2. Integration tests on staging/disposable WordPress: imported menu 67 contains all 36 and same parent IDs; post category snapshot 26 remains; product-cat and featured-item taxonomy term IDs + paths; 15 featured-item pages maintain URLs; public content is not mutated.
3. Snapshot tests: homepage page 562, representative single/article/category/product/featured-item routes. Compare status, canonical, robots, H1, Yoast title/meta, links, schema @types and essential content.
4. Front-end tests mobile/tablet/desktop for menu keyboard expansion, nested archives, sticky header/CTA, chatbot+QuickCall overlap, WooCommerce product/add to cart, cart/checkout where enabled, and raw shortcode leakage.
5. Unit tests for SEO renderer and WordPress functions. Verify no unexpected duplicated canonical or schema; no CSS/JS breaks, no missing main landmarks. Link checker with known public URLs.
6. P0 release gate: **no green deployment** without test evidence + rollback plan. Report explicitly each `PASS` / `FAIL` / `NOT_TESTED`; do not call no test a PASS. If the host/browser doesn't support full staging, stop before production switch and provide exact manual verification steps.

Suggested reports:
`reports/seo-before.json`, `reports/seo-after.json`, `reports/seo-diff.md`, `reports/seo-urls.csv`, `reports/internal-links.csv`, `reports/structured-data.md`, `reports/performance.md`, `reports/release-gates.md`. Use `docs/PROGRESS.md` with commits, branch, PR and remaining blockers.

## 9. Deployment permissions

Claude can research, implement files/tests and push to a feature branch. **No unattended activation, domain change, canonical/robots change, Yoast option write, WordPress DB mutation, theme/plugin uninstall, production webhook activation or live merge.** Owner approves the actual live theme switch after testing. Retain Flatsome Child + Parent installed for rollback.

**Definition of Done:** `SEO HULK` is complete only when migration preserves prior SEO signals, technical implementation passes supported tests, the theme and compatibility plugin deliver functional public URLs, the performance measurements are documented honestly, and go-live has an owner-approved safe plan. Keyword rankings and SEO traffic are observed later, not guaranteed by the theme.
