# Nguyễn Tú — GEO execution gate (2026-10-22)

**Source matrix:** `Nguyen_Tu_GEO_Master_Matrix_2026-10-22.xlsx` (user-approved). **Scan:** CreceRank `0326f845-b6a4-4893-8b99-43682f226aa2`; 2 prompts × 2 runs × 3 providers; 12/12 completed; overall mention rate **4/12 = 33.3%**. Gemini 3/4, ChatGPT 1/4, Google AI Overviews 0/4. The sample is small; 0/4 does not imply indexing/crawler failure.

## P0 evidence and decisions

| ID | Evidence checked 2026-10-22 | Gate decision |
|---|---|---|
| GEO-01 | Monthly landing post #1060; published; WP Yoast `index,follow,max-snippet:-1`, self-canonical; SiteGuru HTTP 200, GSC **Submitted and indexed**, zero warnings/suggestions; AIOScan 97/100 access/readiness. | **PASS — preserve** title, slug, canonical and existing source citations. No rewrite. |
| GEO-02 | Theme `inc/business.php` verified NAP: **Cho Thuê Xe Máy Nguyễn Tú; 112 Nguyễn Văn Cừ, Bồ Đề, Long Biên, Hà Nội; 0942 467 674; daily 09:00–21:00**; public business profile matches. | Internal NAP passes in tested sources. Correct offsite discrepancies, not the source of truth. |
| GEO-03 | SiteGuru: homepage and monthly post both indexable, indexed and Google canonical matches self; GEO Tool Check robots parser: GPTBot, OAI-SearchBot, Google-Extended unblocked. | **PASS — do NOT** modify robots, noindex, canonical, Yoast, or introduce pseudo “AI schema”/llms.txt. Google AI Overview eligibility does not guarantee citation. |
| GEO-04 | Vinpearl listing displays two outdated/unverified addresses **24D Tạ Hiện** and **112B Nguyễn Văn Cừ**, plus unsupported assertion of **comprehensive accident insurance**. Phone 0942 467 674 and daily price band 150–200k match the reference. | Ask Vinpearl editorial team to update to the verified primary address and remove/verify the insurance claim. Do not claim a second branch. |
| GEO-05 | CreceRank competitor field mismatches labels/domains, including Moto One → `motoopen.github.io`, other brands → `thuha.rentbikehanoi.com` / `global.rentbikehanoi.com`. | Mark as **RELATED/AMBIGUOUS**, exclude from trustworthy independent-competitor comparisons until entity and cited URL align. |
| GEO-14 | Other open PRs already touch assistant and SEO link graph; post #1060 modified 2026-10-21 21:54 local time. | **Freeze overlapping production URLs/code** until Work SEO owner and PR status are reconciled. This PR changes docs only. |

## P1 follow-up and actual gaps

- **GEO-07 fact consistency:** reference prices in `inc/business.php`: Wave 150k/day, 700k/week, 900k–1.2m/month, deposit 2–3m; Vision 200k, 1m, 1.8–2m, deposit 4–5m; Air Blade 200k, 1m, 1.5m, deposit 3m; Click/Mio 150k, 700k, 900k–1.2m, deposit 2m. The public indexed `/faq/` and `blog.thuexemaynguyentu.com` results show older Air Blade **1.4m/month**. Confirm live WordPress body and current overlap before a narrowly scoped revision; avoid site-wide bulk replacement.
- **GEO-06 response utility:** “reliable rental address in Hanoi” receives Gemini 2/2 mentions but ChatGPT/AIO 0/2 each, and no own-site citation for that prompt. Keep homepage as main service-address intent; do not create doorway location pages.
- **GEO-08:** Yoast JSON-LD readback for #1060 contains Article, WebPage, BreadcrumbList and WebSite graph. No evidence yet of missing special AI schema. Verify theme/Smart Core cross-output before touching schema.
- **GEO-09:** WordPress #1060 body has context links to delivery fees, 50cc, contracts, the shop, Honda Vision and Honda Wave despite WP Agent reporting 0/10 internal links — treat that rubric as a false negative, do not inject duplicate links.
- **GEO-10:** Google Business Profile NAP/hours matched; TripAdvisor URL requires additional review (external fetch incomplete). No synthetic reviews.
- **GEO-11:** MOTOGO 66.7% vs Nguyễn Tú 33.3% in this scan. Differentiate by only verified policies, vehicle types, monthly rental and local pickup/delivery, not “top 1” claims.
- **GEO-12:** Keep baseline prompts unchanged. Trial allows 1 owned completed scan; do not enqueue extra analysis without budget check.
- **GEO-13:** Images/mobile experience audit remains pending; do not mass-generate hundreds of featured images.

## External correction action: Vinpearl

Article: https://vinpearl.com/vi/15-dia-chi-thue-xe-may-ha-noi-uy-tin-nhat

Official contact route: https://vinpearl.com/vi/contact and `callcenter@vinpearl.com` (not necessarily the editorial team's direct inbox). Suggested factual correction: use **112 Nguyễn Văn Cừ, Bồ Đề, Long Biên, Hà Nội** as the sole *verified primary location*; phone **0942 467 674**; hours **09:00–21:00** daily. Remove unverified claims about 24D Tạ Hiện, 112B Nguyễn Văn Cừ or comprehensive insurance, unless publisher has independent proof. No outreach has yet been sent.

## Rollout gate for later code/content changes

1. Confirm ownership/freeze for URL against active Work SEO and GitHub PRs.
2. Read latest WordPress raw content and GitHub main before any patch; obtain revision/backup.
3. Make at most a match-once, line-level fix for one verified false fact. No slug, URL, canonical, robots, Yoast, sitemap or global title modifications.
4. Check HTTP 200, self-canonical, GSC indexability, text/links, and actual public readback. If regression, restore original revision.
5. Only after QA and conflict check, merge/deploy; this audit document alone does not authorize speculative production modifications.

## Evidence

- CreceRank scan `0326f845-b6a4-4893-8b99-43682f226aa2` (private result).
- SiteGuru monthly page: https://app.siteguru.co/pages/details/49022338 ; homepage https://app.siteguru.co/pages/details/49022318
- https://developers.google.com/search/docs/appearance/ai-features
- https://vinpearl.com/vi/15-dia-chi-thue-xe-may-ha-noi-uy-tin-nhat
- https://thuexemaynguyentu.com/thue-xe-may-theo-thang-ha-noi-gia-re-xe-ga-xe-so/
- https://thuexemaynguyentu.com/faq/
- https://github.com/thuexemayhanoi/theme/pulls

**Production status:** No WordPress writes. No theme runtime or other repository modified. Document-only audit checkpoint.
