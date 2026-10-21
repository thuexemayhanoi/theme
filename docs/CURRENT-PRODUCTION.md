# Current production baseline — 2026-10-21

> **CURRENT STATE, not a deployment instruction.** Source: read-only WP Agent snapshot of `https://thuexemaynguyentu.com/`, GitHub `main` inspection, and public requests on 2026-10-21. Re-check before every release. `docs/PROGRESS.md`, `docs/DEPLOY.md`, `docs/ROLLBACK.md` and reports dated 2026-10-17–19 contain historical **pre-migration** instructions and must not be treated as the production state.

## Verified production inventory

- WordPress: **7.1.3**; PHP: **8.2.34**; production theme: **Nguyễn Tú Editorial Commerce**, stylesheet/template `theme`, version **1.0.0** — **already active**. Do not run the old Flatsome-to-theme activation sequence again.
- Published WP pages: **10**, including front page **562**, shop **3040**, and eight static pages **310, 424, 432, 372, 484, 326, 449, 494**. Published blog categories reported: **27**. No bulk content changes have been authorized in this theme workstream.
- Active: Yoast SEO + Yoast Premium, WooCommerce, LiteSpeed Cache, Contact Form 7, Deployer for Git, Nguyễn Tú Legacy Compat **v1.1.0**, Nguyễn Tú Smart Core **v1.0.1**. WP Agent reported those plugins active; do not deactivate, reinstall, update or flush their settings as part of a theme audit.
- Publicly accessible in the read-only check: `/robots.txt`, `/sitemap_index.xml`, `/bo-suu-tap/`; the sitemap index advertises post, page, product, featured_item, category and taxonomy sitemaps. This **does not prove** every child URL resolves correctly.
- GitHub `main` checkpoint at this read: `490ef3be5fd835d3e20ca2c6a0a4f9e67cdad3a6`. Production's deployed theme **commit SHA has not been independently established**; matching version number alone is not proof that deployment is synchronized.
- PR [#39](https://github.com/thuexemayhanoi/theme/pull/39) is **MERGED**, merge commit `9fb395dddfc608d1cb22cf752ba7b971c995ac9f`; only `AGENTS.md`, `README.md`, `CLAUDE.md` changed.

## P0 — Owner freeze

Native **Quick Call** and **Nguyễn Tú Assistant** are frozen. `AGENTS.md` governs. Read-only observation is allowed, but **no changes to protected code, CSS/JS, settings, cache behavior, widget positions or dependencies** and no indirect behavior changes without separate feature-specific owner consent. Do not infer approval from general theme/SEO work.

## Release and rollback — current rules

1. Record `main` SHA, the actual production deploy revision (if available), and backups of **files + database** before a change.
2. Only change this repository on a branch; compare against current `main`, perform CI, inspect SEO URL/canonical/robots/H1/menu diffs and frozen-feature regressions.
3. Merge only if release gates pass; verify the **actual** WordPress deployment, relevant live pages and cache. Mark unavailable checks `NOT_TESTED` or `BLOCKED`.
4. **First rollback path for a theme-only regression is the last verified good theme commit/release in Deployer for Git**, then confirm live behavior. Returning to `flatsome-child` is **historical migration contingency**, not the default rollback for today's native production. A theme rollback does not automatically roll back plugin or database changes.
5. Do not blindly run `wp theme activate`, `wp rewrite flush`, change permalinks, disable plugins, or purge caches merely to complete documentation.

## Current audit checkpoint

- **DONE (read-only verified):** production uses native theme; active plugin inventory; PR #39 merged docs-only; public robots/sitemap/featured archive respond with content.
- **EXISTING (code discovered, not newly implemented):** category/blog/shop/product/featured templates; breadcrumbs, TOC, related post queries; SEO audit tools; static + integration + E2E CI workflows.
- **AUDIT_PENDING:** complete route-by-route live status, real-device iOS/Safari, deployment revision parity, measured Lighthouse/CrUX, per-URL canonical/robots/schema, internal-link graph freshness, 404/broken destinations, form delivery and protected-feature behavior. Prior recorded lab/sandbox PASS is not current production PASS.
- **NO CHANGE MADE TO RUNTIME:** this checkpoint is documentation only. No WordPress content, setting, plugin, theme runtime file, SEO metadata, slug, URL or cache has been edited.

Next: update the live golden-URL inventory, obtain exact production revision and rerun release gates before choosing a narrowly scoped runtime fix. Do not install AgentMarkup JavaScript adapters into this PHP WordPress theme: final HTML belongs to WordPress/Yoast, not a Vite/Next/Astro build.
