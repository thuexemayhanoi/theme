# Sandbox fixtures: simulators and harness glue

Everything in this directory exists **only** for the disposable WordPress sandbox
(`tools/sandbox/`). None of it is theme or plugin code, none of it may be deployed.
`sync.sh` copies it into the sandbox WordPress; nothing here is ever written to production.

| File | Kind | Why it exists | Installed when |
|---|---|---|---|
| `mu-plugins/nt-sim-flatsome-registration.php` | **SIMULATION** | Re-creates the legacy registration that the Flatsome theme performs on production: CPT `featured_item` and taxonomy `featured_item_category`. Needed so the 15 `featured_item` posts, terms 77-80 and the URLs `/bo-suu-tap/...` and `/featured_item/<slug>/` behave like production, and so the companion plugin's capture-then-replay can be tested by toggling it off. | `sync.sh`, only when `SIM_FLATSOME=1` (default). With `SIM_FLATSOME=0` the file is removed and rewrite rules are flushed. |
| `plugins/moto-quickcall-flatsome-safe-v4/moto-quickcall-sim.php` | **SIMULATION** | Stand-in for the production "Moto QuickCall Pro+ Social (Flatsome Safe v4)" plugin: a `position:fixed` bottom bar (z-index 9999, 56px) with Call `tel:0942467674` and Zalo `https://zalo.me/0942467674`, printed in `wp_footer`. Used to test layout overlap and "no duplicate sticky bar" behaviour. | `sync.sh` copies it; `activate-sims.sh` (run by `seed.sh`) activates it. `activate-sims.sh --off` deactivates. |
| `plugins/motoai-chat-widget/motoai-chat-widget-sim.php` | **SIMULATION** | Stand-in for the production "MotoAI Chat Widget": a fixed 56px bubble at the bottom-right (z-index 99999) with a click-to-open panel. It overlaps the QuickCall bar on purpose. No network calls, no AI. | same as above |
| `mu-plugins/nt-sandbox-infra.php` | **test harness** (not a simulation of production) | Keeps the sandbox hermetic and production-like for Yoast. See the list below. | `sync.sh`, always |

## What `nt-sandbox-infra.php` does

1. Forces Yoast SEO to persist **indexables** although `WP_ENVIRONMENT_TYPE=local`
   (Yoast skips this on non-production environments; production persists them).
   This is what makes `wp yoast index --reindex` work and gives the `wp_yoast_indexable` rows
   that the live site has.
2. Disables Yoast's "installation successful" redirect on the first wp-admin visit.
3. **Per-request theme override**: request header `X-Sandbox-Theme: <stylesheet>` renders that
   request with another installed theme without touching the active theme in the database
   (`verify-seed.sh` uses it to check URLs under `twentytwentyfive`). Only when
   `WP_ENVIRONMENT_TYPE` is `local`; the value is sanitised and must be an installed theme directory.
   Handy for A/B screenshots: `page.setExtraHTTPHeaders({'X-Sandbox-Theme': 'twentytwentyfive'})`.
4. Never sends mail: `wp_mail()` is short-circuited and appended to `$SANDBOX_DIR/logs/mail.log`
   (WooCommerce order e-mails during checkout tests do not fail).
5. Answers WordPress.org update checks locally ("nothing to update"); outbound PHP HTTP is blocked
   anyway (`WP_HTTP_BLOCK_EXTERNAL`), this just removes the warnings.
6. Silences the WP 6.7+ "translation loading triggered too early" notice for third-party text domains
   (WooCommerce under `WPLANG=vi`). It stays **enabled for the `nguyentu` text domain**, so your
   own early `__()` calls are still reported in `logs/wp-debug.log`.
7. Removes WordPress's emoji detection script and styles. Headless Chromium has no colour-emoji font,
   so the polyfill would request SVGs from `s.w.org` (blocked) on every page containing an emoji and
   log a failed request / console error. Start the server with `NT_SANDBOX_KEEP_EMOJI=1` to keep
   the WordPress default. Note: this makes the sandbox HTML differ slightly from production
   (no inline emoji script).

## Seeded data that is synthetic

* Blog posts 10001-10160, product descriptions, `featured_item` texts and the placeholder page
  texts are synthetic Vietnamese filler (`php/seed-data.php`). Every blog post carries the post meta
  `_nt_sandbox_synthetic=1` and ends with a visible "Nội dung mô phỏng ..." notice.
* Images are generated with PHP GD at seed time (gradient + label, hero = dusk sky/sun/road without text).
* Yoast meta descriptions written by the seed start with `[Sandbox]`.
* Page fixtures are **yours**: `tests/fixtures/pages/<ID>.html` is used verbatim as `post_content`
  after the tokens `{{HERO_ID}}` (attachment 9100) and `{{PRODUCT_IMG:<product-slug>}}` are replaced.
  An unknown token makes `seed.sh` fail.

## Things that are NOT simulated

Contact Form 7, GTranslate, LiteSpeed Cache, WPCode, Site Kit, Yoast SEO **Premium**, WPVibe,
YLabs agent, VibeSEO, the writer workflow and any real Flatsome/Flatsome-child code. The legacy
state of `flatsome-child` exists only as the `theme_mods_flatsome-child` option
(`assign-legacy-locations.sh`).
