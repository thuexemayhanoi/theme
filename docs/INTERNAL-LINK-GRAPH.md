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
- `tools/seo/internal-link-graph.mjs`: read-only GET audit of every URL in the site's Yoast sitemaps. Since 2026-10-22 it has no 1,000-URL cap, works in resumable batches and classifies the run as COMPLETE, PARTIAL or FAILED (see *Whole-site audit v2* below). Safeguards are unchanged in spirit: at most **three** concurrent requests, at least **300 ms** pause per worker, bounded retries, no arbitrary recursive discovery, no user data, no login, no plugin execution.
- `tools/seo/crawl-lib.mjs`: sitemap discovery (nested indexes), robots.txt rules, retry/backoff, redirect chains, request ordering and checkpoint files; all network access is injected so it is tested offline.
- `tests/node/internal-link-graph.test.mjs`: known fixtures guard deduplication, authority checks, link placement types, orphan vs contextual-only classification, noindex handling and CSV safety.
- `.github/workflows/internal-link-graph.yml`: read-only PR/manual workflow, outputs downloadable artifact; not scheduled, avoids stressing shared hosting on each blog publication.

Run locally with Node 22 + `npm ci`:

```sh
node --test tests/node/internal-link-graph.test.mjs tests/node/internal-link-graph-crawl.test.mjs
node tools/seo/internal-link-graph.mjs --base https://thuexemaynguyentu.com --out reports/internal-link-graph --concurrency 2 --delay 500 --batch-size 100 --max-minutes 40
node tools/seo/internal-link-graph.mjs --base https://thuexemaynguyentu.com --out reports/internal-link-graph --resume
```

The output directory contains `summary.json`, `report.md`, `urls.csv`, `issues.csv`, `errors.csv`, `redirects.csv`, `canonical-groups.csv`, `orphans.csv`, `nodes.csv`, `edges.csv`, `opportunities.csv`, `excluded.json`, `outside-sitemap-targets.json` and a `checkpoint/` folder. Edges include anchor, placement (header/footer/breadcrumb/related/content/main), inferred silo, and whether a target belongs to the current sitemap. All output is in the CI artifact or the local `reports/` directory, **not** persisted in WordPress.

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

## First live graph preflight (2026-10-21, partial)

The initial audited GitHub Action [run 39611680584](https://github.com/thuexemayhanoi/theme/actions/runs/39611680584) completed with **300 HTTP-200, indexable, self-canonical** sitemap pages. It discovered **803** published sitemap URLs from **9 child sitemaps**, with **503 URLs unscanned** in that run. Within the first 300 lexicographically selected URLs, it recorded 11,958 distinct source-target edges and 48 **partial-sample** zero-inbound candidates. **None of those 48 may be claimed as confirmed site-wide orphan posts.** The next run uses a 1,000-URL cap with identical 2-concurrent / 500-ms safeguards to cover all 803 currently published sitemap destinations.

A real WordPress post (2026-10-21) showed Yoast breadcrumb `Home → current post` while its actual category link appeared in `.nt-article__chips`; the graph now reads that published category chip before setting `silo`. This is direct category evidence, not guesswork.

Read-only validation of `/featured_item_tag/thue-xe-may-theo-ngay/` found `noindex, follow`, while `/featured_item/` returned `index, follow` with self-canonical. Avoid automatically linking all legacy tag archives; a genuine indexable archive must first be compared against `/bo-suu-tap/` for intent overlap.

## Whole-site audit v2 (2026-10-22)

Upgrade of the existing tool and `.github/workflows/internal-link-graph.yml`; there is no second system. Everything is read-only: GET requests, no cookies, no writes, no edits of Yoast metadata or content.

**Discovery.** `/sitemap_index.xml` first, then the `Sitemap:` lines of `robots.txt`, then `/wp-sitemap.xml`. Nested indexes are walked (depth 3, 200 files at most); each child sitemap type is recorded (post, page, product, category, product_cat, featured_item, taxonomy and author archives, …) with its kind (content, taxonomy, archive). Duplicate entries and off-site URLs are ignored; the homepage is added when the sitemap omits it.

**Per-URL checks** (`urls.csv`, findings in `issues.csv`): HTTP status of the sitemap URL, the whole redirect chain (hops, loops, off-site targets, destination status), `noindex` from the robots meta tag **and** the `X-Robots-Tag` header, `robots.txt` blocking, canonical (missing, multiple, off-site, not self, several sitemap URLs folding into the same target), H1 count, title, and the internal links of every indexable self-canonical page.

**Batches, politeness and retry.** `--batch-size` URLs per batch with a pause between batches; progress is saved after each batch. 429/503 answers pause every worker. A network failure, timeout or 408/425/429/5xx is retried with exponential backoff and jitter (honouring `Retry-After`, never above 60 s). A URL that never gets a definitive answer is **unchecked**, not a finding: it is listed in `errors.csv` and requested again on resume. 404, 410 and redirects are definitive answers. Partial runs sample every sitemap type (requests are interleaved by type, not alphabetical).

**Checkpoint and resume.** `checkpoint/results.jsonl` holds one record per URL (internal links included); `--resume` re-reads the sitemaps, skips every URL with a definitive answer and requests only the unchecked or errored ones, so a site of any size can be finished over several runs. In GitHub Actions pass the earlier run's id as `resume_run_id`; the artifact `seo-internal-link-graph-evidence` of that run is downloaded first. `--limit N` caps the URLs requested in one run, `--max-minutes M` stops starting new URLs after M minutes (the run then saves its checkpoint), `--types post,page,…` audits only some sitemap types, `--strict` turns PARTIAL into exit code 2. A checkpoint of another site is refused.

**Status is based only on URLs really checked.** `COMPLETE`: every discovered URL has a definitive answer and every sitemap file loaded. `PARTIAL`: anything in between (budget stop, unchecked or errored URLs, a failed sitemap file). With `--types`, COMPLETE only means the selected types are done: `scope` says so and orphans stay candidates. `FAILED`: no usable sitemap, or not one URL checked (exit code 1). `summary.json` states `urlsDiscovered`, `urlsChecked`, `urlsErrored`, `urlsPending` and the coverage; the report is also the GitHub job summary.

**Orphans, defined carefully** (`orphans.csv`). Only a page that answers 200, is indexable and is its own canonical is in the denominator. With zero inbound links from another such page it is `ORPHAN_CONFIRMED` after a COMPLETE whole-site audit and otherwise `ORPHAN_CANDIDATE`. Pages that are not expected to receive links are classified instead of counted: `NOT_APPLICABLE_TAXONOMY_NOINDEX` (noindex tag or author archives), `NOT_APPLICABLE_NOINDEX`, `NOT_APPLICABLE_ROBOTS_BLOCKED`, `NOT_APPLICABLE_REDIRECT`, `NOT_APPLICABLE_NON_SELF_CANONICAL`, `NOT_APPLICABLE_HTTP_ERROR` and `NOT_CHECKED`. Links from unlisted pages or other sites are unknown, so even a confirmed orphan is a prompt for review, never a reason to add links automatically.

**Running it.** Start `Read-only SEO Internal Link Graph` by hand (`limit` 0 audits every unchecked URL). Pull requests that change the tool only run a 120-URL sample. No schedule is configured on purpose; if one is wanted later, keep it monthly or sparser. Real-run results are recorded in `reports/seo-audit-2026-10-22.md` only after a run has completed.
