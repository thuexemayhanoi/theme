# Nguyễn Tú Native Quick Call + Knowledge Assistant — staged migration (2026-10-19)

## Scope, source and truth labels

- **Repository changed:** `thuexemayhanoi/theme` only. Reference repository `/wordpress-theme` was READ ONLY.
- **Real production inventory:** WordPress 7.1.3 / PHP 8.2.34 / active stylesheet `theme`. Plugins: `moto-quickcall-flatsome-safe-v4/moto-quickcall-flatsome-safe-v4.php` (Moto QuickCall Pro+ Social v4.0.0) and `motoai-chat-widget/motoai-chat-widget.php` (MotoAI Chat Widget v1.0.3), both active at audit. Source examined read-only with WPVibe.
- **Old QuickCall:** Floating button at left / `wp_footer` priority 99, plus Bootstrap Icons from jsdelivr; options stored under `mqcps_v4_settings`. A read-only attempt to fetch that key returned **not found**, so present layout appears to rely on plugin defaults. Main plugin entry lists no explicit license. Do **not** copy or redistribute the existing plugin into this repository or create a redistributable ZIP without confirmed permission.
- **Old MotoAI:** Separate browser crawler/learning cache in `localStorage`, built-in static price table in `motoai.js`. Some prices in that script are not verified against the owner's current `inc/business.php`. The new assistant does **not** import/propagate the unverified prices or client crawler.
- **Reference architecture (read only):** `thuexemayhanoi/wordpress-theme` and its companion search subsystem; inspiration only, no copied code, no writes.
- **Old site's content, SEO/Yoast metadata, canonical, permalinks, menus, posts, categories, WooCommerce data:** unchanged.

## Architecture

1. `inc/assist.php`: one centrally configurable native QuickCall, grounded fact templates, public WP search and read-only REST endpoint `POST /wp-json/nguyentu/v1/assist`.
2. `assets/css/assist.css`: self-contained navy / orange / Zalo blue / WhatsApp green; accessible touch targets, safe-area aware, responsive at 360, 390, 768, 1024, 1440; no external font/CSS/icon dependencies.
3. `assets/js/assist.js`: keyboard-closeable launcher and chat form, focus handling, Esc, server-provided sources as safe DOM text links. No third-party AI API, cookies, localStorage, chat persistence or analytics. No JavaScript framework.
4. **Search engine:** one bounded WordPress `WP_Query` for *published, non-password-protected* posts, pages and WooCommerce products on every visitor request. No frozen index: published content appears without reindexing, deleted/unpublished content falls out naturally. Index is the WordPress DB/search itself. For 10k+ posts consider a separate companion full-text search implementation later, not an unbounded client crawler.
5. **Answer generator / source precision:** direct policy answers cite only the *published* FAQ, contact or relevant 50cc/electric WordPress object by ID, not loosely matched search results. Generic discovery questions search published content dynamically. Deterministic facts read from `nt_business()`, `nt_policy()`, `nt_price_rows()` when verified, else curated WordPress links and handoff to Call/Zalo. No fabricated availability, hourly price, sale, discounts or guaranteed booking. Mode is visibly described as **tra cứu**, NOT generative AI.
6. **One widget only:** existing Moto QuickCall takes precedence; theme native quickcall suppressed while old plugin active. Existing MotoAI similarly takes precedence; theme assistant suppressed while old plugin active. When QuickCall migrates first but MotoAI remains, the native contact menu opens the existing MotoAI bubble for AI Chat; no second chatbot. Neither `nt-ctabar` nor native launcher doubles the active plugin. Default Maps link OFF; other buttons ON. Controls: WP Admin > Appearance > Nguyễn Tú > Quick Call và trợ lý tư vấn.

## Plugin backup and already completed owner removal (verified 2026-10-19)

**The owner reports Moto QuickCall and MotoAI were disabled and deleted on production before this rollout.** WPVibe site info and the native WordPress plugin listing independently confirm that **neither plugin is installed**; the public HTML also contains no `moto-quickcall` or `motoai` output. The active theme's older two-button Gọi/Zalo fallback remains until this PR deploys, so visitor contact was not cut off.

There is **no installed copy to ZIP now**. We did **not** uninstall or deactivate either plugin and must not claim that a portable QuickCall ZIP was produced or restored. The owner reports a Hostinger backup from yesterday; its exact plugin contents and restoration quality have **not** been verified. For a private restoration, inspect/download the actual archived `wp-content/plugins/moto-quickcall-flatsome-safe-v4/` from the hosting backup, without copying secrets into GitHub. If absent from that archive, only a valid, licensed original distribution can recover it.

When this PR's WordPress sandbox tests pass, release the new theme via the already configured Deployer for Git workflow **once**. Use the single active theme directory. No separate plugin switch is necessary because the previous plugins are no longer installed. Following delivery, confirm the new native QuickCall replaces the old theme's fallback bar; check Gọi/Zalo/WhatsApp/Chat at 360, 390, 768, 1024 and 1440 px, source retrieval, and no overlap with footer, header, mobile browser toolbar or any still-injected WPCode widgets.

On failure, roll back to the earlier theme commit and restore the previous **two-button theme fallback**. The deleted plugins do not reappear just by reverting the theme; their recovery depends on the host backup or separately licensed plugin packages.

**Never claim an actual AI language model is connected**: this version answers by bounded WordPress search and verified business templates without an API. Future paid AI integrations would require independent approval, privacy/security review and budget.


## Tests and evidence

- `tests/integration/NativeAssistTest.php`: priority of legacy plugin, exactly-one launcher, business links, trusted fact synthesis, public-only sources, REST input validation and plugin gating.
- `tests/e2e/native-assist.spec.mjs`: five widths, Escape/open/close, real sandbox REST and accessible local sources.
- `tests/e2e/contact.spec.mjs` and `tools/qa/run-sandbox.sh` execute with legacy plugin simulators ON then OFF. These are **simulation results**, not real plugin equivalence.
- Existing QA: canonical/robots, SEO schema, WooCommerce, header, footer and menu unchanged.
- **UNVERIFIED until live deployment:** plugin recovery ZIP/restore from Hostinger backup, field Core Web Vitals and no-overlap / correct native bar after publishing. Legacy simulator tests remain useful for regression only; real plugins have already been removed by owner.

### Notes on future scale

At tens of thousands of articles, native MySQL `WP_Query` search can become slow. Keep this release on bounded, on-demand queries (maximum four results) and later move search processing into a lightweight companion plugin with a maintained full-text index and publication hooks. No API key or paid LLM model is required for the deterministic assistant. No pretend-generative behaviour.
