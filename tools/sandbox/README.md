# Nguyễn Tú WordPress sandbox (disposable, local)

A realistic, throw-away WordPress for testing the **`nguyentu-editorial-commerce`** theme and the
companion plugin **`nguyentu-legacy-compat`** for real: PHPUnit-style integration against a real DB,
WP-CLI checks, Playwright screenshots. It reproduces the 2026-10-17 audit of
`thuexemaynguyentu.com` (`docs/wordpress-baseline-2026-10-17.json`) with **exact IDs**.

* Real software: WordPress **7.1.3** (same as production), **WooCommerce 11.2.1** (official release zip),
  **Yoast SEO 28.7 free** (official release zip), **MariaDB 10.11.14** (real server, started by hand, no systemd),
  **WP-CLI 2.12.0**, PHP 8.3.6 built-in server.
* Everything lives outside the repo, in `$SANDBOX_DIR` (default `${TMPDIR:-/tmp}/nguyentu-sandbox`; every script honours the
  environment variable, e.g. `SANDBOX_DIR=/path/to/sandbox ./serve.sh start`). The repo only contains these scripts (`tools/sandbox/**`).
* Not touched: production, the repo outside `tools/sandbox/`, git state. No credentials in the scripts: the DB password and the
  wp-admin password (user `admin`) are generated at install time and kept in `$SANDBOX_DIR/etc/db-password` and `$SANDBOX_DIR/etc/admin-password`.

## Quick start

```bash
cd /home/user/theme/tools/sandbox
./setup.sh            # WordPress + WooCommerce + Yoast + MariaDB (about 20 s warm, see Timings)
./seed.sh             # fixtures with exact IDs + Yoast reindex + snapshot "seeded" (about 70 s)
./verify-seed.sh      # PASS/FAIL table, exit 1 on any FAIL (about 17 s)
./serve.sh start      # http://127.0.0.1:8088   wp-admin: admin / $(cat $SANDBOX_DIR/etc/admin-password)
```

State left by the build session: installed, seeded, snapshot `seeded` saved, MariaDB running, PHP server **stopped** (this install lives in the scratchpad: pass `SANDBOX_DIR=<scratchpad>/sandbox`).

## Everyday commands

| Task | Command |
|---|---|
| start / stop / restart / status | `./serve.sh start` · `stop` · `restart` · `status` (start waits until the site answers) |
| faster pages (opcache, still sees edits at once) | `OPCACHE=1 ./serve.sh restart`  (about 0.2 s/page instead of 0.7 s) |
| logs | `./serve.sh logs [-f] [N]`  (access log, `php-error.log`, `wp-debug.log`) |
| copy theme + companion plugin + simulators into WordPress | `./sync.sh`  (0.25 s when nothing changed) |
| activate your theme / plugin | `./wp.sh theme activate nguyentu-editorial-commerce` · `./wp.sh plugin activate nguyentu-legacy-compat` |
| WP-CLI | `./wp.sh <args>`  = `php wp-cli.phar --path=<wp> --allow-root --url=http://127.0.0.1:8088 <args>` (memory_limit 512M; starts MariaDB if it is down) |
| reset to the seeded state | `./snapshot.sh restore seeded`  (under 1 s; DB **and** uploads; activation state is part of the DB, so re-activate theme/plugin afterwards) |
| take your own snapshot | `./snapshot.sh save mytest` · `list` · `delete mytest` |
| full re-seed from the pristine install | `./seed.sh`  (restores snapshot `pristine` first) |
| start from nothing (keeps the downloads cache) | `./setup.sh --reinstall && ./seed.sh` |
| DB shell / connection data for test runners | `./db.sh shell` · `eval "$(./db.sh env)"` (exports `NT_DB_HOST/PORT/NAME/USER/PASSWORD/SOCKET`, `NT_DB_TEST_NAME`) |
| MariaDB | `./db.sh start|stop|status` |
| QuickCall + MotoAI simulators on / off | `./activate-sims.sh` · `./activate-sims.sh --off` |
| featured_item simulator on / off | `SIM_FLATSOME=0 ./sync.sh` (off: mu-plugin removed, rewrite rules flushed) · `SIM_FLATSOME=1 ./sync.sh` (on, default). The env var is read on every `sync.sh`, so `export SIM_FLATSOME=0` for a whole session. |
| old-theme menu locations for another stylesheet | `./assign-legacy-locations.sh <stylesheet>` (seed.sh only runs it for `flatsome-child`) |
| uploads for a theme with custom image sizes | `./wp.sh media regenerate --yes` |

`wp-cron` is off (`DISABLE_WP_CRON`); run `./wp.sh cron event run --due-now` when needed, or start with
`NT_SANDBOX_WP_CRON=1 ./serve.sh start`. Outbound HTTP from PHP is blocked except loopback.

## Recipes

**Playwright** (`baseURL: 'http://127.0.0.1:8088'`; use `127.0.0.1`, not `localhost`: WordPress redirects the host).
Four PHP workers serve in parallel. To render a page with a core theme while your theme is active, send
`X-Sandbox-Theme: twentytwentyfive` (no DB change). The pages carry **both** simulator widgets, so overlap tests have something to hit:
`#moto-quickcall-sim` (fixed bottom bar, z-index 9999, 56px) and `#motoai-chat-bubble-sim` (fixed bottom-right 56px bubble, z-index 99999, `#motoai-chat-panel-sim` opens on click).

**PHPUnit against a real DB**: `eval "$(./db.sh env)"`; an empty database `nguyentu_sandbox_tests` exists and the sandbox DB user may use it
(WordPress' test suite wipes its DB, never point it at `nguyentu_sandbox`). The WP test library itself is **not** installed
(it is downloaded from develop.svn.wordpress.org, which is blocked here).

**Integration checks over HTTP + WP-CLI**: `./verify-seed.sh` is a template (`php/verify-seed.php` for the DB side,
`curl` for the HTTP side). `$SANDBOX_DIR/seed-manifest.json` lists every seeded ID (posts with their categories, thumbnails, markup type, attachments, pages, menu items).

**Menu locations**: the seed assigns nothing to the active theme. `theme_mods_flatsome-child` holds the old state
(`primary`, `primary_mobile` -> 67, `footer`, `top_bar_nav` -> 66). Activating your theme triggers WordPress' own
location migration; check it with `./wp.sh menu location list`.

## What the seed contains (exact IDs from the baseline)

| Area | Content |
|---|---|
| Permalinks | `/%postname%/`; WooCommerce `product_base=/san-pham`, `category_base=danh-muc-san-pham`; **category base stripped** with Yoast's own `stripcategorybase` option, so `/blog/`, `/blog/xe-dien/`, `/blog/xe-dien/xe-dap-dien/` resolve like production; `/category/blog/` 301-redirects to `/blog/`. |
| Categories | 26 terms with exact `term_id`/name/slug/parent (`&amp;` stored decoded as `&`); `term_taxonomy_id == term_id`; per-category direct counts equal the baseline (sum 161) |
| Posts | 160 published, IDs **10001-10160** (the baseline has no blog post IDs), one author `nguyentu-editor` ("Nguyễn Tú"), dates over the last 12 months (anchor 2026-10-17), 600-1500 words, 3-5 H2 + some H3, a list in every post, a table in 32, a quote in 40, manual excerpts in 53, 107 in block markup and 53 in classic HTML, 40 with a 1600x900 featured image (attachments 9200-9239). Exactly one post has two categories (157 + 156). All synthetic and marked as such. |
| Pages | 10 with exact IDs/slugs/titles/parents (562 front page `/`, 310 > 424, 432, 372, 484, 494, 3040 shop, 326, 449). Content from `tests/fixtures/pages/<ID>.html` when present (tokens `{{HERO_ID}}`, `{{PRODUCT_IMG:<slug>}}` replaced, otherwise verbatim), else a placeholder. Sandbox-only extras: **9001 cart, 9002 checkout, 9003 my-account** with the classic shortcodes. |
| Products | 13 simple products, exact IDs; leaf categories only (71: 4, 72: 6, 73: 3; displayed counts incl. children 70: 13); VND without decimals (`150.000 ₫`); prices on honda-wave 150000, honda-vision 200000, honda-air-blade 200000, honda-click 150000, yamaha-mio-classico 150000, xe-ga-50cc 200000, the other 7 have **no price**; in stock, image + descriptions. WooCommerce's own default `Uncategorized` product category is already term **15** on a fresh install; it is the default. |
| featured_item | 15 posts, exact IDs, each in term 77 and one of 78/79/80; terms 77-80 (counts 15/5/5/5). Registered by the simulator: `/bo-suu-tap/<term>/`, `/bo-suu-tap/<comma-separated-terms>/<slug>/`. |
| Menus | nav_menu 67 "Main chính" with 36 `nav_menu_item` posts (exact IDs, `menu_order` = position, parent IDs, type/object/object_id, custom links keep their production absolute URLs); nav_menu 66 "Secondary" is empty. Children come before their parent in `menu_order` for some items, exactly as in the baseline. |
| Media | hero `9100` (dusk sky, sun and road, no text), products `9101-9113`, posts `9200-9239`; real attachments with generated sizes. The 123 production attachments are not reproduced. |
| Site | `WPLANG=vi` (`<html lang="vi">`, no language pack, so UI strings stay English), `Asia/Ho_Chi_Minh`, `blog_public=1`, `WP_ENVIRONMENT_TYPE=local`, `WP_DEBUG`/`WP_DEBUG_LOG` on (log in `$SANDBOX_DIR/logs/wp-debug.log`, display off), currency VND. |
| Yoast | real plugin, indexables built for everything; custom titles/descriptions on pages (descriptions start `[Sandbox]`) and some posts; Organization name set; XML sitemaps at `/sitemap_index.xml`. |

Simulators and harness glue are described in `fixtures/README.md`.

## Environment variables (all optional)

`SANDBOX_DIR` (`${TMPDIR:-/tmp}/nguyentu-sandbox`) `WP_ROOT` `PORT` (8088) `DB_PORT` (3307) `DB_NAME` `DB_USER` `DB_SOCKET` `THEME_SLUG` `PLUGIN_SLUG`
`SIM_FLATSOME` (1) `ADMIN_USER` `ADMIN_PASS` (overrides the generated password) `WP_VERSION` `WC_VERSION` `YOAST_VERSION` `WPCLI_VERSION` (each may be `latest`)
`YOAST_SOURCE=release|dist` `PHP_CLI_SERVER_WORKERS` (4) `OPCACHE` (0) `NT_SANDBOX_WP_CRON` `NT_SANDBOX_KEEP_EMOJI`
`VERIFY_THEME` (twentytwentyfive) `NT_PAGES_FIXTURE_DIR` `NT_BASELINE_JSON` `NT_SEED_ANCHOR` `SYNC_EXTRA_EXCLUDES` `SNAPSHOT_UPLOADS` (1)

## Timings (this machine, 4 cores, measured)

All numbers were measured on this 4-core machine with the scripts as shipped.

| Step | Time |
|---|---|
| `setup.sh` from an empty `SANDBOX_DIR` (cold: everything downloaded from GitHub) | **25 s** (downloads 9 s, MariaDB init + start 2 s, plugin install + activation 6 s, WordPress install 1 s) |
| `setup.sh --reinstall` (downloads cached) | 17-20 s |
| `setup.sh` on an already installed and seeded site (no-op, re-syncs the admin password) | 7 s |
| `seed.sh` (restore pristine < 1 s, `seed.php` 25-27 s, Yoast reindex 34 s, simulators 5 s, flush 1-2 s, snapshot < 1 s) | **68-70 s** |
| `verify-seed.sh` (starts its own server with `OPCACHE=1`; 4 requests in parallel) | 16 s |
| `snapshot.sh restore seeded` (DB + 12 MB of uploads) | 0.7-1 s |
| `sync.sh` unchanged / when toggling the featured_item simulator (includes a rewrite flush) | 0.25 s / 1.8 s |
| `serve.sh start` | < 1 s |
| page generation, opcache off (default) / `OPCACHE=1` | about 0.7 s / 0.2 s |
| `node smoke-playwright.cjs` (11 pages x 3 viewports, opcache off) | 53 s |

Disk after a full install: downloads 236 MB, WordPress 258 MB, MariaDB datadir 174 MB, snapshots 15 MB.

## Decisions and deviations from the brief

* **Yoast comes from the official release zip** (`github.com/Yoast/wordpress-seo/releases/download/28.7/wordpress-seo-28.7.zip`),
  not from a clone of `Yoast-dist/wordpress-seo`: that repository has no `vendor/autoload.php` (Composer generates it),
  so the plugin silently does nothing (no options, no tables). The dist route still works as a fallback
  (`YOAST_SOURCE=dist`: clone + `composer dump-autoload --classmap-authoritative`); it was tested.
* **Real MariaDB** (10.11.14 from apt), not the SQLite integration plugin. Started by `db.sh` with `--no-defaults`, TCP `127.0.0.1:3307`
  plus a unix socket (under `/tmp` when `$SANDBOX_DIR` is too long for an AF_UNIX path).
* **WP-CLI 2.12.0** is the pinned release asset (sha512 verified), not the `wp-cli/builds` phar.
* **`WPLANG=vi` is written past `sanitize_option()`**: WordPress silently refuses a language that is not installed, and no language pack can be downloaded.
  Result: `<html lang="vi">`, English UI strings, `d/m/Y` dates.
* **Yoast persists indexables although `WP_ENVIRONMENT_TYPE=local`** (a filter in the sandbox mu-plugin); stock Yoast would not, and `wp yoast index` would refuse to run.
* WooCommerce's own default `Uncategorized` product category is **already term 15** on a fresh install, so nothing had to be moved; the seed only asserts that there is exactly one.
* Category names that are `&amp;` in the baseline JSON are stored decoded (`Đánh Giá & So Sánh Xe`), as requested.
* `DISABLE_WP_CRON` is on, outbound PHP HTTP is blocked, WordPress.org update checks are answered locally, mail is never sent, and WordPress's emoji polyfill is removed
  (see `fixtures/README.md`). These make runs hermetic but differ slightly from production HTML.
* Additions beyond the brief: `lib.sh`, `router.php`, `php/*.php` helpers, `smoke-playwright.cjs`, an Organization logo (attachment 9120, so Yoast emits an `Organization` node),
  the `X-Sandbox-Theme` header override, `OPCACHE=1`, auto-start of MariaDB by `wp.sh` / `serve.sh` / `snapshot.sh`, an empty second database for a PHPUnit suite.
* `sync.sh` excludes the brief's list **plus everything the repo's `.gitignore` ignores** (`/dist`, `*.zip`, caches ...). Tracked dev files such as `composer.json` or `package.json` are still copied.
* The wp-admin password is **generated** at install time and kept in `$SANDBOX_DIR/etc/admin-password` (`ADMIN_PASS` overrides). `setup.sh` and `snapshot.sh restore` re-apply it, so an older install that was created with a fixed password is brought in line the next time one of them runs.
* `seed.sh` also works with the featured_item simulator off (`SIM_FLATSOME=0`): terms and relationships are then written directly; the URL checks of `verify-seed.sh` for `/bo-suu-tap/<term>/` and legacy single paths correctly FAIL in that state.

## Known limits

* **Synthetic content.** Blog posts, product/featured_item texts, placeholder pages and all images are generated filler (marked `_nt_sandbox_synthetic` / "Nội dung mô phỏng").
  Production has 123 attachments, the sandbox 55-56. No real prices other than the six given in the brief. Twelve blog fixtures use the audited homepage Matrix IDs (476, 1060, 895, 829, 847, 841, 862, 836, 857, 1062, 823, 887); remaining blog IDs are invented (10013-10160). All blog text and category assignments remain synthetic.
* **Simulators, not the real plugins.** The QuickCall bar and the MotoAI bubble follow only what the brief states (position, size, z-index, links). The Flatsome registration is reduced to the brief's facts
  (CPT `featured_item`, taxonomy `featured_item_category`, slugs); its real `register_post_type` arguments (labels, `has_archive`, capabilities ...) were not read from production.
* **Not installed / not reproduced:** Yoast SEO Premium, Contact Form 7, GTranslate, LiteSpeed Cache, WPCode, Site Kit, WPVibe, YLabs agent, VibeSEO, real Flatsome code, production's Yoast settings (only a company name + generated logo, `stripcategorybase`, and per-page titles/descriptions are set), the 123 attachments, comments, orders, users other than admin and `nguyentu-editor`.
* **Default theme is a block theme.** While `twentytwentyfive` is active, Appearance > Menus (`nav-menus.php`) answers "Your theme does not support navigation menus" (it works once a classic theme that registers locations is active),
  and the first front-end requests create two `wp_navigation` posts (WordPress converts menu 67 for the Navigation block). Pages that carry legacy UX shortcodes show them raw under this theme: that is the situation your theme must fix.
* **Server:** PHP built-in server: HTTP/1.1 without keep-alive tuning, no gzip, no Range requests, no `.htaccess`/LiteSpeed behaviour, 4 workers. Use exactly `http://127.0.0.1:<PORT>` (WordPress redirects other hosts).
  Performance numbers measured here (Lighthouse, TTFB) say nothing about production.
* **No internet from PHP or from the browser.** Gravatar (disabled), Google Fonts, oEmbed, s.w.org emoji, WordPress.org APIs and language packs do not load.
* The WordPress PHPUnit test library is **not** installed (it comes from develop.svn.wordpress.org, blocked here); an empty `nguyentu_sandbox_tests` database is provided for a suite you bootstrap yourself.
* `featured_item` single URLs are `/bo-suu-tap/<comma-separated-terms>/<slug>/` (simulator default) and the existing `/featured_item/` archive is preserved (`has_archive=true`).
* The sitemap lists the 3 sandbox pages (cart, checkout, my-account) too; production has no such pages. Yoast's `category-sitemap.xml` only lists non-empty categories.
* Restoring a snapshot also restores plugin/theme activation (it is DB state): re-activate your theme and companion plugin afterwards, and remember that the rewrite rules stored in the snapshot match the simulator state at snapshot time (`SIM_FLATSOME=1`).
* `verify-seed.sh` and `smoke-playwright.cjs` use the active default theme or `X-Sandbox-Theme`; they are harness checks, not theme tests.
