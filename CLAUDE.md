# CLAUDE.md — Nguyễn Tú WordPress independent theme

This repository is the **new primary-site theme** for https://thuexemaynguyentu.com/, NOT the blog.rentbikehanoi.com theme. Read README.md in full, then docs/wordpress-baseline-2026-10-17.json before doing anything. Every fact and ID in the snapshot is from a read-only audit, dated 2026-10-17; refresh observations when tools allow. Do not assume a current static snapshot is authoritative for a moving publisher.

## Working contract

- Be a senior WordPress theme architect, migration engineer, PHP/WooCommerce developer, QA engineer and mobile UX lead. **Implement code and tests**, commit/push checkpoints, update docs/PROGRESS.md, finish with a PR that the owner can review. Never stop at a plan.
- Create a feature branch for all new code: feat/nguyentu-independent-theme-v1. Do not rewrite published posts/pages, content, taxonomies, menus, media, SEO metadata, slugs or production DB. Production is live with customer traffic.
- Do **not** activate or uninstall themes/plugins on live WordPress, and do not merge-to-production or enable a deployer webhook. The owner will authorize those operations after a staging verification. Ask only if a genuinely indispensable credential/approval is required, otherwise implement mock/staging-compatible code.
- Root must be a valid self-contained WordPress theme (style.css header, index.php, functions.php and all required templates). Keep plugin-only legacy CPT/taxonomy compatibility under plugins/nguyentu-legacy-compat/, with separate ZIP/install instructions. Don't copy proprietary Flatsome code or depend on its runtime.
- Critical migration: WP page #562 contains Flatsome UX shortcodes; 15 featured_item published content entries with taxonomy featured_item_category terms 77–80; 36 hierarchical menu items on menu 67; 26 blog categories; 13 Woo products. Preserve URLs/layout semantics/functionality, not literal Flatsome internals.
- Frontend must show no raw UX shortcodes and retain accurate NAP/contact options, Yoast SEO output, WooCommerce cart functions, MotoAI chatbot and existing QuickCall plugin if compatible. If blocked, report honestly; never claim PASS from static lint alone.
- Run lint, unit and integration tests, and screenshots across 360, 768 and 1440 px where tools are available. Compare protected URLs; do not fabricate test results or page-load scores. Put findings into reports/ and document rollback.
- At the end of the session, provide PR link, commits, working features, failing tests, outstanding tasks, how to preview on staging, and next one-step continuation. Always record LIVE_UNCHANGED in docs/PROGRESS.md if true.
