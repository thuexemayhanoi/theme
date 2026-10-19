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
5. **Answer generator:** deterministic facts read from `nt_business()`, `nt_policy()`, `nt_price_rows()` when verified, else curated WordPress links and handoff to Call/Zalo. No fabricated availability, hourly price, sale, discounts or guaranteed booking. Mode is visibly described as **tra cứu**, NOT generative AI.
6. **One widget only:** existing Moto QuickCall takes precedence; theme native quickcall suppressed while old plugin active. Existing MotoAI similarly takes precedence; theme assistant suppressed while old plugin active. When QuickCall migrates first but MotoAI remains, the native contact menu opens the existing MotoAI bubble for AI Chat; no second chatbot. Neither `nt-ctabar` nor native launcher doubles the active plugin. Default Maps link OFF; other buttons ON. Controls: WP Admin > Appearance > Nguyễn Tú > Quick Call và trợ lý tư vấn.

## Plugin backup, licensing, and transition gate

**BACKUP NOT VERIFIED / NO OLD PLUGIN DEACTIVATED AS PART OF CODE RELEASE.**
The installed QuickCall plugin has no explicit license marker in its main PHP header. Source reads are not a byte-exact portable archive (secrets may be masked); a bundle built from them would NOT be a validated restore ZIP. Do not pretend a ZIP exists.

Before production deactivation, the owner/admin must create a **private restore archive** via Hostinger hPanel File Manager / Backup tools:

1. Ensure a fresh Hostinger backup of today's site exists (yesterday's backup may predate recent changes).
2. In Hostinger file manager, privately archive **only** `public_html/wp-content/plugins/moto-quickcall-flatsome-safe-v4/` into a ZIP stored **outside public_html** or download it directly. Do not publish that ZIP in GitHub or a public uploads directory; this is a recovery copy of an existing installed plugin, not a license for redistribution.
3. Back up the setting `mqcps_v4_settings` if it exists through an authorized export route (currently read API returned option not found, so do not fabricate its content). Remove tokens/private user data from any sharing/exported logs.
4. Validate ZIP extraction / plugin file integrity on staging; confirm the plugin can be restored in the Plugins admin when required.
5. In a staging clone, **disable Moto QuickCall only**, verify exactly one native QuickCall and working Call/Zalo/WhatsApp/Chat/Maps across 360/390/768/1024/1440, preserving Footer and existing MotoAI. Re-enable on regression.
6. To move to the **native** assistant too, separately deactivate MotoAI on staging only after testing the public REST, real source links and verified prices. The separate production switch deserves explicit owner review.
7. Only when backup + cutover QA is complete, authorized production admin deactivates the corresponding old plugin(s). **Never uninstall/delete** without a further explicit owner instruction. Roll back by reactivating each old plugin, or restoring from private ZIP/Hostinger backup.
8. After any cutover, purge LiteSpeed Cache once and verify public HTML, CSS/JS, 5 screen widths and REST behavior (active chat endpoint only). Do not repeatedly push to trigger deploys.

## Tests and evidence

- `tests/integration/NativeAssistTest.php`: priority of legacy plugin, exactly-one launcher, business links, trusted fact synthesis, public-only sources, REST input validation and plugin gating.
- `tests/e2e/native-assist.spec.mjs`: five widths, Escape/open/close, real sandbox REST and accessible local sources.
- `tests/e2e/contact.spec.mjs` and `tools/qa/run-sandbox.sh` execute with legacy plugin simulators ON then OFF. These are **simulation results**, not real plugin equivalence.
- Existing QA: canonical/robots, SEO schema, WooCommerce, header, footer and menu unchanged.
- **UNVERIFIED until cutover:** actual production plugin ZIP/restore, real plugins simultaneously running with their exact configuration, production disabled-plugin transition, field Core Web Vitals and live non-duplication after deactivation.

### Notes on future scale

At tens of thousands of articles, native MySQL `WP_Query` search can become slow. Keep this release on bounded, on-demand queries (maximum four results) and later move search processing into a lightweight companion plugin with a maintained full-text index and publication hooks. No API key or paid LLM model is required for the deterministic assistant. No pretend-generative behaviour.
