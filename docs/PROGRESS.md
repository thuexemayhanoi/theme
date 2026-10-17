# PROGRESS — Nguyễn Tú Editorial Commerce (independent theme v1)

> Branch: `feat/nguyentu-independent-theme-v1` · Repo: `thuexemayhanoi/theme`
> **LIVE_UNCHANGED = TRUE** — nothing in this project has touched the production site
> `https://thuexemaynguyentu.com/`: no theme activation, no plugin change, no DB write,
> no option write, no menu/term/slug change, no webhook enabled, no merge to production.

This file is rewritten at every checkpoint. Newest session first.

## Session 1 — 2026-10-17 (autonomous build)

### Environment facts that shape the work

| Fact | Consequence |
| --- | --- |
| Egress policy of the build container returns **HTTP 403 on CONNECT** for `thuexemaynguyentu.com`, `wordpress.org`, `downloads.wordpress.org` | **Live read-only audit could not be performed from this session.** All live SEO "before" fields are **UNVERIFIED** (see `reports/seo-before.json`). Tooling to capture them (`tools/seo/`) and a manual GitHub Action (`seo-snapshot-live.yml`, `workflow_dispatch` only) are provided so the owner/CI can capture the real baseline. I did **not** try to bypass the policy. |
| Package-manager and GitHub git/raw hosts are reachable | A real WordPress 7.1.3 + MariaDB + WooCommerce + Yoast sandbox can be built (`tools/sandbox/`), so integration/e2e evidence is real, but runs on **synthetic fixtures derived from `docs/wordpress-baseline-2026-10-17.json`**, not on production content. |
| Yoast SEO **Premium**, MotoAI, Moto QuickCall, GTranslate, LiteSpeed, Flatsome are not obtainable here | Premium-only behaviour, MotoAI/QuickCall real CSS/JS and Flatsome real shortcodes are **NOT_TESTED**; simulators exist only to exercise layout/overlap logic and are labelled as simulations. |

### Status

See `reports/release-gates.md` for the authoritative per-gate PASS / FAIL / NOT_TESTED table
(generated from test output, never hand-edited).

### Resume commands

```bash
git fetch origin feat/nguyentu-independent-theme-v1 && git checkout feat/nguyentu-independent-theme-v1
bash tools/sandbox/setup.sh      # once; installs WP 7.1.3 + MariaDB + Woo + Yoast in the scratchpad
bash tools/sandbox/seed.sh       # seed baseline-shaped fixtures
bash tools/sandbox/sync.sh && bash tools/sandbox/serve.sh start
npm ci && npx playwright test    # e2e (browsers are preinstalled in the container)
```

(The scripts above are created progressively during this session; the checklist below records which exist.)

### Checklist (updated as work lands)

- [x] Branch created, repository scaffolding
- [ ] Theme core (`functions.php`, `inc/`)
- [ ] Templates + design system
- [ ] Legacy shortcode bridge + compatibility matrix
- [ ] Companion plugin `plugins/nguyentu-legacy-compat/`
- [ ] Automated tests (unit, integration, e2e, contrast, budgets)
- [ ] SEO snapshot/diff tooling + reports
- [ ] CI workflows, deploy + rollback docs
- [ ] Screenshots 360/768/1440, performance report
- [ ] Pull request opened

### Blockers (honest list)

1. Live baseline capture is blocked by the container network policy (host `thuexemaynguyentu.com`).
   Needed from owner: allow the host for this environment **or** run the `seo-snapshot-live` workflow.
2. Real raw `post_content` of page #562 (≈40 KB of Flatsome shortcodes) is not readable without
   authenticated access. The bridge is built from the documented UX shortcode syntax and the
   shortcode names listed in README; exact coverage must be re-measured on staging with
   `wp nguyentu legacy-scan` (provided).
3. Featured-item single-URL rewrite slug is unknown (only term URLs `/bo-suu-tap/...` are in the
   baseline). The companion plugin therefore uses **capture-then-replay** of the live registration
   instead of guessing, and ships a `verify` command that compares against the real sitemap URLs.
