# SEO before / after diff

> **UNVERIFIED — no live baseline.** This report does not claim parity with production.

Reason: The build container's egress policy returns HTTP 403 at CONNECT for thuexemaynguyentu.com (see docs/AUDIT.md section 1). No live request was made, so no live SEO field is recorded here and none may be assumed.

## What is still required

1. Run `node tools/seo/snapshot.mjs --base https://thuexemaynguyentu.com --urls reports/seo-urls.csv --out reports/seo-before.json` from a network that can reach the live site (or the manual GitHub Action `seo-snapshot-live`).
2. Run the same command against the staging copy to produce `reports/seo-after.json`.
3. Re-run `node tools/seo/diff.mjs reports/seo-before.json reports/seo-after.json`.

Until then the only SEO evidence is the absolute-rule check of the sandbox (`reports/seo-rules-after.md`), which tests the contract (one canonical, one H1, no duplicate schema ...), not equality with production.

