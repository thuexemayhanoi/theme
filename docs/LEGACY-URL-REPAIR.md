# Legacy URL repair — 2026-10-19

Scope: only `thuexemayhanoi/theme` and the standalone `nguyentu-legacy-compat` plugin on `thuexemaynguyentu.com`.
The owner explicitly authorized audit, repair, testing, commit/push and deployment in this task.

## Evidence and root cause

- Authenticated inventory: all 15 published `featured_item` objects and terms 77–80 still exist; the parent/child relationships and five items per period are intact.
- All 19 pre-switch URLs agree with Yoast indexables last updated before the theme change. Search Console also confirms the old `/bo-suu-tap/` paths. Private Search Console metrics are not included in this repository.
- The saved runtime registration arguments have CPT base `featured_item` and taxonomy base `featured_item_category`. The saved effective rewrite rules instead use `/bo-suu-tap/{term}/` and `/bo-suu-tap/{comma-separated-terms}/{post-slug}/`. The old theme's permastruct/link filtering was not replayed by v1.0.
- The v1.0 replay guard also returned when **either** registration existed, leaving partial registrations unrecovered. The original sandbox assumed standard CPT single URLs, so it missed the production discrepancy.
- The read-only HTTP regression run before repair reproduced failures across the old canonical URLs and their replacement aliases. URL selection follows SEO evidence, not whichever replacement currently answers 200.

## Repair

- v1.1 generates the original URL structure from existing post/term objects, with term IDs providing stable ordering. The public regression fixture independently lists the exact 19 expected paths.
- Add explicit scoped routes and validate category segments against the queried post's real taxonomy assignments. Keep missing objects/invalid groups as 404, disable core guessing only inside these legacy prefixes.
- Redirect only resolved public legacy singles/terms to their corresponding canonical URL with 301; preserve paging/feed/embed endpoints and tracking parameters. `/featured_item/` remains its existing archive; `/bo-suu-tap/` remains Page 494.
- Core permalink APIs now return the same URLs to menus, footer, content cards and Yoast sitemap generation. No Yoast canonical filter or metadata write is added.
- Restore only missing registrations and leave an existing registration object intact. With another component providing both registrations, the plugin remains dormant.
- No automatic rewrite flush. Before recovery, preserve the original registration snapshot and current rewrite rules in the additive, non-autoloaded option `nguyentu_legacy_compat_backup_v110`. Never overwrite this backup.
- Quote comma-containing paths properly in CLI CSV exports and read them using CSV parsing in QA.

## Validation and deployment

- `tools/qa/legacy-recovery.sh` tests the actual mismatched registration fixture without the old theme, all 19 original URLs, 19 one-hop aliases, canonical tags, sitemaps, control URLs and actual 404 behavior, plus both partial-registration cases.
- `tools/qa/compat-roundtrip.sh` verifies capture/replay/dormancy using an updated simulator matching the effective legacy paths. It is a simulation, not proprietary old-theme code.
- Run the repository CI suites before release. The release workflow builds a standalone ZIP from a tested `legacy-compat-v*` branch; it does not install or activate anything by itself.
- Update the already active plugin through the authenticated WordPress plugin installer. Check the backup exists, flush rewrite rules once with supervision, purge page cache and run `tools/seo/legacy-live-audit.py --out <report> --before <baseline> --verify`.
- Recheck all post IDs/slugs/status/content hashes and all four terms. Any live-deployment claim requires the actual after report; CI alone is insufficient.

## Rollback

Restore the prior plugin package from the pre-release commit and the `rewrite_rules` value in `nguyentu_legacy_compat_backup_v110`; purge page cache. The original `nguyentu_legacy_compat` option is never overwritten by v1.1. No content/term/SEO restoration should be necessary because this repair does not write those records. Keep the Hostinger backup available. Do not reactivate the old theme as part of this repair.

## Verified result and checkpoint

- Merged PR: https://github.com/thuexemayhanoi/theme/pull/8, merge commit `3edc3ba4043462b72cf76366becff198d95d2481`.
- Green CI: https://github.com/thuexemayhanoi/theme/actions/runs/39234961183.
- WordPress integration: 72 tests / 1,014 assertions; recovery fixture: 19 exact canonical URLs / 19 aliases / 53 HTTP cases, zero failures. Both partial-registration cases pass. All 222 internal-link targets return 2xx. Browser suites include WooCommerce, menu, layout and SEO head checks.
- The existing fixture-only report has 43 missing-description P1 warnings; no P0 SEO failures. Five pre-existing whole-theme manual release gates remain NOT_TESTED. This is not a claim that an entire production theme audit has been completed.
- Standalone release: https://github.com/thuexemayhanoi/theme/releases/tag/legacy-compat-v1.1.0 (17,937-byte ZIP).
- Predeployment production backup: `nguyentu_legacy_url_predeploy_20261019` contains the original registration snapshot and 39 legacy rewrite entries. It excludes unrelated component rules. No production rewrite flush has been performed in this task.
- **PRODUCTION PLUGIN IS STILL 1.0.0.** Authenticated verification after the merge confirmed it active at its original path. `/bo-suu-tap/thue-ngay/` still returns 404 and its `/featured_item_category/` alias still returns 200. A theme deployment does not install a separately packaged plugin.
- The connected plugin installers support WordPress.org packages, so installing the custom ZIP remains a separate administrator step. No post/page/term/menu/price/Yoast data was edited; only the additive backup option was created.

## Resume after installing the package

1. Install the released ZIP as an update to `nguyentu-legacy-compat` and retain its activation state.
2. Verify version 1.1.0, backup `nguyentu_legacy_compat_backup_v110`, and the original 15 objects / four terms.
3. Flush rewrite rules once if required, then purge page/object cache.
4. Run `python tools/seo/legacy-live-audit.py --out reports/legacy-live-after-2026-10-19.json --before reports/legacy-live-before-2026-10-19.json --verify` against the real site.
5. Compare old alias-page title/description/robots with restored canonical pages; verify existing Yoast redirect history, a product URL and pagination/feed variants. Update this checkpoint only with actual live results.

Status: **CODE_MERGED / PACKAGE_RELEASED / LIVE_PLUGIN_UPDATE_PENDING**. Do not report the live 404s fixed before installation and after-verification.
