# Internal Link Graph — validated 2026-10-21 snapshot and editorial priority matrix

> **Read-only QA evidence, not a ranking promise.** Repository `thuexemayhanoi/theme` only. No WordPress content or plugin configuration changed in this analysis. Do not change Quick Call/Nguyễn Tú Assistant or add unrelated links.

## Scope and reproducibility

- Graph collection: 2026-10-21 at 14:12 UTC from GitHub Actions [run 39613049346](https://github.com/thuexemayhanoi/theme/actions/runs/39613049346); [evidence ZIP (14-day artifact retention)](https://github.com/thuexemayhanoi/theme/actions/runs/39613049346/artifacts/12352192899).
- Source: nine reachable Yoast child sitemaps; 803 distinct current sitemap paths, all 803 GET-attempted. 802 pages gave 200, indexable and exactly one self-canonical. One request returned `fetch failed`; **`complete=false`** in the saved analysis. Follow-up read via WPVibe returned the target's title, `index, follow` meta and self-canonical, but **did not validate original probe's HTTP response code**.
- One transient/failed URL: `/xe-so-vao-so-bi-keu-lach-cach-yamaha-neu-nam-nguyen-nhan-tu-la-bo-can-so-banh-rang-va-nhot/`. Recheck this URL from the same read-only GitHub runner on the next audit. Do **not** change its permalink or canonical.
- Unique internal source→target pairs within scanned valid pages: **45,826**; total extracted link placements including distinct anchor/region: **69,583**. 82 targets lie outside the *listed sitemap inventory*, including author pages and pagination URLs; these are **not automatically broken links**.
- Only one zero-inbound **candidate within the 802-page sitemap graph**: `/uncategorized/` (default WordPress category). There are **no confirmed orphan posts among the 802 accepted sitemap nodes**. Because one page fetch failed and unsitemapped pagination/archives are outside this graph, avoid absolute whole-site orphan claims.
- Three destinations have inbound but no `content`/`main`/`related` incoming placements: `/blog/` (801 incoming source pages), `/blog/huong-dan-thue-xe/su-dung-xe-thue-50cc/` (801), and `/featured_item/` (15). Do not link to them mechanically merely to clear a tool warning. A global navigation link is not equivalent to a meaningful supporting-article link.
- All inspected link counts derive from the downloadable `nodes.csv` and `edges.csv` artifact, not guessed from slug/keyword strings.

## Commercial pillar/hub targets already published

| Intent / destination | Unique incoming page sources | Contextual incoming sources | Keep |
| --- | ---: | ---: | --- |
| `/thue-xe-may-50cc-ha-noi/` | 13 | 12 | Established commercial 50cc entry, prioritize its intent |
| `/san-pham/xe-ga-50cc/` | 801 | 13 | WooCommerce product destination |
| `/cho-thue-xe-dien-ha-noi/` | 16 | 16 | Established electric rental landing page |
| `/san-pham/xe-dap-dien/` | 801 | 21 | WooCommerce product destination |
| `/san-pham/xe-may-dien/` | 801 | 11 | WooCommerce product destination |
| `/danh-muc-san-pham/loai-xe/xe-dien/` | 801 | 5 | Electric product category hub |

The high 801 count is largely global navigation/footer exposure. It does not mean 801 contextual recommendations. Never create duplicate links that already exist.

SiteGuru's cached GSC report for **2026-09-19 through 2026-10-18**, obtained 2026-10-21, gives 39 clicks for `/thue-xe-may-50cc-ha-noi/` (down 48.7% against prior period), 26 clicks for `/san-pham/xe-dap-dien/` (down 39.5%), and 12 for electric product category (up 100%). This **does not establish link scarcity as the cause of traffic changes**. SiteGuru found zero keyword-cannibalization cases in this 30-day period under its traffic significance threshold; other forms of overlap may exist.

## Editorial link matrix (REVIEW, no automatic writes)

Only insert a link if a relevant paragraph already discusses the same user intent; use a natural descriptive anchor, confirm product/service availability rather than implying a specific rental model is stocked, avoid exact-match spam and duplicate anchors, and check canonical/HTTP before publishing.

| Candidate source published URL | Proposed destination | Evidence and action |
| --- | --- | --- |
| `/giay-phep-xe-may-50cc-giay-to-bao-hiem-thuong-nham/` | `/thue-xe-may-50cc-ha-noi/` | Current graph has no direct contextual link to this commercial 50cc entry. **REVIEW** only if an existing paragraph addresses renting a legal 50cc vehicle; legal article's main intent should remain legal guidance |
| `/honda-cub-50cc-co-phu-hop-di-ha-noi-dai-ngay-va-quang-duong-hon-hop/` | `/thue-xe-may-50cc-ha-noi/` | Current graph has no direct contextual link to this rental guide. **REVIEW** if article actually discusses choosing a suitable 50cc for Hanoi; do not imply specific Cub inventory without verifying |
| `/yamaha-neos-co-gi-dang-chu-y-danh-gia-cho-nhu-cau-di-trong-pho/` | `/cho-thue-xe-dien-ha-noi/` | No contextual link to general rental landing page; **REVIEW** only under a natural comparison/choosing section, without implying Yamaha NEO's is in stock |
| `/xe-dap-dien-vinfast-drgnfly-pin-thao-roi-nut-ga-ngon-cai-va-quang-duong-thuc-te-ra-sao/` | `/san-pham/xe-dap-dien/` | No contextual link to generic electric bicycle rental product; **REVIEW** if it naturally answers 'where can I rent a suitable e-bike?' and carefully separate product models |

**Already linked — DON'T DUPLICATE:**

- `/chi-phi-thue-xe-may-50cc-tai-ha-noi/` links to both `/thue-xe-may-50cc-ha-noi/` and `/san-pham/xe-ga-50cc/`. The commercial 50cc article also links back to the cost explainer.
- `/so-sanh-gia-thue-xe-may-dien-va-xe-dap-dien-tai-ha-noi/` links to products and `/cho-thue-xe-dien-ha-noi/`. Its commercial hub also links to the comparison and the electric-bike product.
- The category pages and related-post component already generate many relevant links; do not clone them in the post body merely for extra counts.

## Risks and acceptance criteria for any future content change

1. Confirm with WordPress that the precise source article is still published and which term(s) it belongs to. Verify destination canonical, page indexability and topic fit, not only the slug.
2. Read source paragraph/context and its existing anchors to avoid duplicates; preserve the author's original meaning, especially on legal and safety topics.
3. For an approved limited content edit, use match-once WordPress content patch, validate exact HTML and outgoing target after edit. Do not change title, H1, Yoast meta, URL, taxonomy or permalink.
4. Do not auto-edit hundreds of posts, update AI Writer repositories or introduce a runtime sitewide link-injection module.
5. Keep `/uncategorized/` intact until the three default-category posts are reviewed and the indexability/URL consequence of changing taxonomy is approved separately. Do not auto-noindex it.
6. SiteGuru's earlier 16 orphan URL list was one `/featured_item/` and 15 `/featured_item_tag/` archives. At least one sampled tag URL has `noindex, follow`; current complete-sitemap sampled graph also found `/featured_item/` receives 15 inbound links. Do not auto-link legacy archives or treat old crawl as current.
7. Monitor performance and Search Console later; internal link additions alone do not guarantee ranking improvements.

## Protected code / rollback

No change to `inc/assist.php`, `inc/business.php`, `assets/css/assist.css`, `assets/js/assist.js`, shared enqueue/settings, Quick Call or chatbot. This is a documentation-only checkpoint. For a documentation regression, revert this Markdown file; no WordPress data or deployment state needs restoring.
