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

Status: implementation and validation in progress; live plugin unchanged until the tested ZIP is installed.
