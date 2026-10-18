# Performance report (lab measurements)

Measured 2026-10-17T19:11:58.171Z with 141.0.7390.37 against `http://127.0.0.1:8088`.

> **Read this first.** These are LAB measurements on a local PHP built-in server inside a container: no page cache, no gzip/brotli, no CDN, no HTTP/2, shared CPU. They show relative cost and catch regressions; they are **not** field data (CrUX/RUM), **not** a Lighthouse score claim and **not** a ranking promise. Real Core Web Vitals need real traffic (75th percentile mobile) after go-live. Thresholds quoted: LCP <= 2.5 s, INP <= 200 ms, CLS <= 0.1.

## mobile 390px, 4x CPU, 1.6 Mbps / 150 ms RTT (Lighthouse "slow 4G" style)

| Page | LCP ms (element) | CLS | Interaction ms* | Requests | HTML KB | Total KB (raw) | Total KB (est. gzip) | CSS KB gz | JS KB gz | Fonts KB | Images KB | 3rd-party |
| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| home | 2776 (img.nt-lg-bg__img) needs improvement | 0 good | 48 | 31 | 94.6 | 598.8 | 273.7 | 32.8 | 50.3 | 34.9 | 139.7 | 0 |
| post | 2620 (p.wp-block-paragraph) needs improvement | 0 good | 56 | 21 | 81.7 | 462.4 | 167.5 | 26.8 | 51.6 | 34.9 | 37.7 | 0 |
| category | 2632 (img.nt-card__img) needs improvement | 0 good | 64 | 18 | 68.8 | 444.9 | 161.5 | 26.3 | 50.3 | 34.9 | 37.7 | 0 |
| product | 2544 (img.wp-post-image) needs improvement | 0 good | 80 | 30 | 73.7 | 603.3 | 254.6 | 30.3 | 77.9 | 34.9 | 96.6 | 0 |
| contact | 2284 (p) good | 0 good | 56 | 17 | 56.7 | 398.7 | 123.4 | 27.3 | 50.3 | 34.9 | 0.0 | 0 |

## desktop 1440px, no throttling

| Page | LCP ms (element) | CLS | Interaction ms* | Requests | HTML KB | Total KB (raw) | Total KB (est. gzip) | CSS KB gz | JS KB gz | Fonts KB | Images KB | 3rd-party |
| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| home | 392 (img.nt-lg-bg__img) good | 0 good | 48 | 31 | 90.9 | 603.0 | 281.1 | 32.8 | 50.3 | 34.9 | 147.6 | 0 |
| post | 352 (img.nt-article__img) good | 0 good | 40 | 21 | 81.7 | 449.8 | 154.9 | 26.8 | 51.6 | 34.9 | 25.1 | 0 |
| category | 276 (img.nt-card__img) good | 0 good | 56 | 19 | 68.8 | 447.2 | 163.8 | 26.3 | 50.3 | 34.9 | 40.0 | 0 |
| product | 328 (img.wp-post-image) good | 0 good | 40 | 30 | 73.7 | 590.1 | 241.3 | 30.3 | 77.9 | 34.9 | 83.4 | 0 |
| contact | 260 (span) good | 0 good | 48 | 17 | 56.7 | 398.7 | 123.4 | 27.3 | 50.3 | 34.9 | 0.0 | 0 |

\* Interaction = longest event-handling duration of the first menu/search click observed with PerformanceObserver (`event` entries). It is an INP-style lab proxy, not INP.

## Lighthouse

**NOT_TESTED** in this run (no Lighthouse summary was supplied).

## Budgets enforced in CI

See `tools/qa/budgets.mjs`: main.css <= 34 KB raw / 9 KB gzip, theme.js <= 14 KB / 4.5 KB gzip, fonts <= 40 KB, critical path (main.css gzip + two fonts) <= 60 KB.

## Limits of this environment

- Single run per page and profile; variance between runs is not measured.
- Local loopback network: CDP throttling approximates, but does not reproduce, a mobile network.
- Production adds LiteSpeed Cache, brotli, HTTP/2/3, real images (the sandbox uses generated JPEGs) and third-party plugins (MotoAI, QuickCall, GTranslate, CF7, Site Kit) whose cost is not included here.

