# Internal Link Graph — audit checkpoint (2026-10-21)

> **Scope:** independent native WordPress theme `thuexemayhanoi/theme`. Read-only diagnosis and QA tooling. Production content, Yoast settings, taxonomy/permalinks, menus, templates, Quick Call and Nguyễn Tú Assistant are **not modified**. `AGENTS.md` P0 owner freeze applies.

## Verified before this change

- Post-merge baseline: GitHub `main` at `2851cefc98f21bd7880d5ed107bf38ada8d0fb68`, CI [PASS](https://github.com/thuexemayhanoi/theme/actions/runs/39604939073); Homepage Live QA [PASS](https://github.com/thuexemayhanoi/theme/actions/runs/39604939051).
- SiteGuru indexed-crawl analysis, fetched **2026-10-21**, reports **227 pages checked**, **16 orphan candidates** and **0 broken internal-link targets**. A crawler's last successful collection date is not confirmed from the report payload, so **do not label these current LIVE findings until independently re-crawled**.
- SiteGuru's *entire* orphan list consists of **one `/featured_item/` archive and 15 `/featured_item_tag/{slug}/` archives**. These are **not 16 normal posts**. Their purpose, indexability, sitemap inclusion and canonical need verification before deciding whether links are desirable. Do NOT add artificial links, doorway archives or keyword-stuffed navigation.
- SiteGuru flagged 16 internal redirect targets, primarily `/author/taihanoi82gmail-com/page/{N}/` returning 301 to homepage **in the collected crawl**, plus `/gioi-thieu` to trailing-slash URL and `/blog/chia-se/page/1/` to its archive root. These are **crawler evidence, not yet current live confirmed**. Avoid editing author pagination or Yoast until status, user role/author URL, response Location and canonical have been re-verified.
- Existing `inc/related.php` already uses two bounded tax queries with post-cache invalidation and no background full-site scan. `template-parts/content/related.php` renders up to four related posts. Keep these unchanged until a specific failure is proven.
- Existing `tools/seo/links-report.mjs` consumes the sampled snapshot and labels its coverage; that is useful for baseline regression, but **is not an all-published-URL graph**.

## Added audit tooling — no theme runtime changes

- `tools/seo/internal-link-graph-lib.mjs`: HTML link extraction and region/anchor classification, unique inbound vs contextual inbound, source/target silo, missing incoming link candidates.
- `tools/seo/internal-link-graph.mjs`: bounded read-only GET crawl driven by the site's Yoast sitemap index. Defaults: maximum **250** URLs (CI chooses **300**), at most **two concurrent** HTTP connections and **500 ms pause per worker**; no arbitrary recursive discovery, no user data, no login, no plugin execution. URL cap / failed request → report `complete=false`, never claim a definitive orphan count.
- `tests/node/internal-link-graph.test.mjs`: known fixtures guard deduplication, authority checks, link placement types, orphan vs contextual-only classification, noindex handling and CSV safety.
- `.github/workflows/internal-link-graph.yml`: read-only PR/manual workflow, outputs downloadable artifact; not scheduled, avoids stressing shared hosting on each blog publication.

Run locally with Node 22 + `npm ci`:

```sh
node --test tests/node/internal-link-graph.test.mjs
node tools/seo/internal-link-graph.mjs --base https://thuexemaynguyentu.com --out reports/internal-link-graph --limit 300 --concurrency 2 --delay 500
```

The generated artifact contains `summary.json`, `nodes.csv`, `edges.csv`, `opportunities.csv`, `excluded.json` and `outside-sitemap-targets.json`. Edges include anchor, placement (header/footer/breadcrumb/related/content/main), inferred silo, and whether a target belongs to the current sitemap. All output is in the CI artifact or local `reports/` directory, **not** persisted in WordPress.

## Interpretation rules

1. **Only** HTTP-200, indexable and self-canonical sitemap destinations count as normal graph nodes. Noindex, redirects and noncanonical URLs appear in excluded evidence, not in the orphan denominator.
2. `orphanCandidates` counts zero incoming source pages **within the audited sitemap graph**; it does **not** include links from unsampled archives or other sites, and it is explicitly `PARTIAL` when any URLs remain unscanned.
3. `noContextualInbound` means a destination has at least one incoming link (e.g. header/footer) but no incoming article/related/main link. This is a prioritization signal, not proof of an SEO problem.
4. Silo inference uses actual category/breadcrumb paths, not fabricated category membership from post titles. `unclassified` is normal where no reliable breadcrumb exists.
5. Orphan archives and author pagination must be reviewed **before any edits**: preserve SEO URLs, Yoast canonical, robots, schema and published content; do not auto-create sitewide links.
6. Reuse current related-posts, category hubs and breadcrumbs first. Any future changes to shared templates must be on a new branch, independently regression-tested and inspected for P0 freeze impact.

## Follow-up decisions after graph artifact

- Cross-check the current sitemap URL counts and crawl statuses against WordPress/Yoast; differentiate absent-from-sitemap vs legitimate indexable URLs and redirects.
- Prioritize commercially important published posts with **zero contextual incoming links** and a verified relevant category hub, not fake taxonomy tag archives.
- Investigate author pagination redirects read-only; fixes can be outside the theme, and must not be guessed.
- Present a small, source-backed link opportunity matrix (`supporting post → pillar/hub → rental commercial page`) before touching old post bodies. No bulk insertions.
