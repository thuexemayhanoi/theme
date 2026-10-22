# Post contents list — position after the first H2 (22 October 2026)

Question: can the contents list of a single post sit right after the first H2 of the text instead of directly under the H1, the meta line and the featured image — without changing headings, anchors, URL, canonical or SEO data, without a second list, and without breaking the layout on phones, tablets and desktop in light and dark mode?

Target reading order: H1, meta line, featured image, opening paragraph, first H2, contents list, rest of the text.

## What changed (PR #56, `8d77b6d`)

| File | Change |
| --- | --- |
| `inc/lib/html.php` | `nt_html_toc_placement()` scans the rendered body with tag nesting and returns a label and a byte offset; `nt_html_insert_at()` inserts a string at that offset. The body is never rewritten by the scan. |
| `single.php` | Posts only (`is_singular( 'post' )`): the collapsible list is rendered once and inserted into the printed body at the offset. The reading time still counts the body without the list. |
| `template-parts/content/toc.php` | A `placement` argument (whitelisted) printed as `data-nt-toc-placement` on the inline `<details>`. |
| `assets/css/blog.css` | Three scoped rules so the list keeps its look inside the text (`.nt-prose .nt-toc--inline`, `.nt-toc__list`, `.nt-toc__item`). |

Tests and tools (not part of the theme at runtime): `tests/unit/HtmlTest.php`, `tests/node/toc-structure.test.mjs`, the e2e describe `post contents list position` in `tests/e2e/blog.spec.mjs`, and the read-only live check `tools/qa/toc-live.mjs` with its workflow `.github/workflows/toc-live-qa.yml`.

Unchanged: the sticky sidebar list and its scroll-spy (`assets/js/theme.js`), heading ids and anchors (`nt_filter_the_content`), the three-heading minimum, Yoast output, Quick Call, the Assistant, `inc/enqueue.php`, `functions.php`, `inc/settings.php`.

## Placement rules

| Label | When | Where |
| --- | --- | --- |
| `after-first-h2` | the first non-empty `<h2>` is a direct child of the body | right after its closing tag |
| `after-intro` | no such H2 (none, or inside a group, columns, quote, list item, `<details>`, link, figure) | after the last top-level block that ends before the first `<h2>`/`<h3>` (usually the opening paragraph) |
| `before-content` | no safe place at all | in front of the text, where it used to be |

The scanner treats comments, `script`, `style`, `template`, `noscript`, `iframe`, `textarea` and `title` as opaque, closes an open `<p>` at the next block tag as a browser does, and gives up (fallback) on an unterminated comment.

## Verdict

| Question | Result |
| --- | --- |
| Is the list right after the first H2? | **Yes.** 150 of 150 sampled real posts get `after-first-h2` with 0 mismatches against an independent HTML5 parse (baseline run [39861293208](https://github.com/thuexemayhanoi/theme/actions/runs/39861293208), 150 of 1,000 sitemap posts). On the deployed site 24 of 24 sampled posts show it on the served page and 24 of 24 origin renders place it there (run [39862850910](https://github.com/thuexemayhanoi/theme/actions/runs/39862850910), `main@8d77b6d`, verdict PASS). |
| Headings, anchors, canonical unchanged? | **Yes.** The list of headings (level, text, order) is identical between the served and the origin render in every sampled post, and the e2e compares it with the fixture post; canonical is identical; the function returns an offset and never edits the body. |
| One list only, never inside a heading? | **Yes.** One inline and one sticky variant exist, exactly one is visible at 390, 768, 1024, 1180 and 1440 px; none sits inside an `h1`–`h4` and no heading sits inside the list. |
| Open/close, anchors, accessibility? | **Yes.** Click, Enter and Space toggle it; a link scrolls its heading below the sticky header and sets the hash; axe (WCAG 2 A/AA) on the text is clean; summary and link contrast at least 4.5:1 in light and dark; no horizontal overflow. |
| Fallbacks? | **Yes.** A post without an H2 and a post whose first H2 sits in a group both get the list after the opening paragraph, as a direct child of the text; fewer than three headings: no list; pages, products, categories and the home page: none. |
| Devices and modes | Chromium at 390 (iPhone), 768 (iPad portrait), 1024 (iPad landscape), 1180 (iPad Air landscape) and 1440 px (desktop), light and dark. Real Safari: **NOT_TESTED**. |

Tests: PHPUnit unit suite 97 (12 new), 11 new Node tests (`tests/node/toc-structure.test.mjs`), 14 new WordPress e2e tests (CI run [39861293168](https://github.com/thuexemayhanoi/theme/actions/runs/39861293168): e2e-blog 21, a11y-axe 20, integration 93). The sticky list (1100 px and wider) was measured with the repository's stylesheets at 1180 and 1440 px: worst text contrast 6.32:1 in light and 9.22:1 in dark mode.

## Decision for the owner

From 1100 px the sidebar keeps its sticky list (with scroll-spy) and the in-text list is hidden; below 1100 px the in-text list is shown. So on a wide desktop the list is still beside the text, not in it. Showing the in-text list on desktop too would remove the sticky list. That is a product choice, not made here.

## Found on the way

In dark mode the H1 of posts, the blog index and the shop measured 1.02:1 on the live site (baseline run 39861293208; not caused by the list). Fixed separately in PR #57 (`d8c5942`), see `docs/PROGRESS.md`.

## Rollback

Revert the merge commit `8d77b6d`. Nothing is stored in WordPress for this feature, so there is nothing else to restore; the old place of the list returns with the revert.

## Not tested

Real iPhone/iPad Safari, Lighthouse and field Core Web Vitals on the live site, Search Console. The live checks are read-only (`tools/qa/toc-live.mjs`, workflow `Post contents list live QA`); the sandbox that runs e2e is disposable and never touches production.
