# Nguyễn Tú grounded assistant V2 — context + lightweight memory

**Scope:** `thuexemayhanoi/theme` only, with `motoopen/chothuexemayhanoi` inspected READ ONLY. No source copied wholesale; no Motoopen contact data or unverified prices reused. This is a deterministic on-site information assistant **not a generative AI model**. No paid AI API and no open-source model used.

## What was learned from MotoAI V39

MotoAI V39 implements local NLP, term ranking (BM25), extractive sentences, user topic memory, source manifests and a phone dock-offset heuristic, with a 24h refresh and broad 80-page/site browser crawling budget. These ideas inspired the new version, but it **does not copy the V39 module or crawl from a visitor's device**. Motoopen's contact address and price statements are for a different entity and are not facts for Nguyễn Tú.

## New verified architecture

1. `inc/assist.php`: Normalize Vietnamese for intent and short vehicle names, recognize price/deposit/paperwork/delivery/directions/live availability/general queries. Only quote `inc/business.php` current price rows and business policy. Do not infer hourly prices, availability or promotional guarantees.
2. Prices, deposits, paperwork, electric and vehicle-overview questions use the **published, non-passworded WordPress FAQ post ID 484**, live location/delivery uses the **published contact page ID 372**. `get_permalink()` follows legitimate WordPress URL changes. Both IDs were confirmed on production read-only on 2026-10-19. Suppress draft/password/noindex sources.
3. Open-ended technical/content questions use one bounded WordPress `WP_Query` (max 12 examined, 3 links returned). Rank **title relevance first** with filtered Vietnamese terms, and reject results without adequate topic overlap. Updates are immediate with WordPress publishing; no new indexer, cron, full-site crawl or database table.
4. Optional context `{model,period}` is validated server-side against actual `nt_price_rows()`, not against arbitrary client prices. A follow-up `“Tháng thì sao?”` after `“Giá Honda Vision?”` can use the confirmed Vision month row; an unknown model never generates a price.
5. Client in `assets/js/assist.js` saves only confirmed vehicle name and rental period for up to **24 hours** (well under 512 characters); public related-link cards for generic discovery only, max **5 topics, 3 sources each, 8000 serialized characters, 6 hours**. No chat messages, passport numbers, names, emails, phone numbers or arbitrary raw questions are written to localStorage. Cache is fallback-only when offline: primary answers always query current WordPress content. The clear button deletes both entries; failed/denied storage gracefully disables memory.
6. Source links are same-origin and inserted as safe text nodes. Status line uses `role=status` and `aria-busy`; answer sources labeled plainly; chat DOM retains one greeting + 12 messages for low memory usage. Keyboard focus returns to visible QuickCall launcher after the panel closes. Buttons and theme styling remain consistent; no third-party JS.
7. Refuse to present live inventory/special deals as verified. Hand off to Call/Zalo where current human confirmation is necessary.

## Performance and safety

- One bounded query *only for non-curated questions*; owner facts/FAQ links avoid an expensive broad text search for price/deposit.
- The visitor never downloads or indexes the WordPress sitemap or hundreds of posts. Frontend contact + assistant bundles have explicit raw/gzip budgets in `tools/qa/budgets.mjs`.
- User messages are sent to the website's own read-only WordPress REST route to answer them, not to a paid/third-party AI service. Do not confuse this with a promise that no HTTP request ever occurs.
- No changes to SEO metadata, canonical, schema, NAP, products, QuickCall links, Footer or WordPress posts.
- Opt-out: the visitor can press **Xóa ghi nhớ**; disabling browser storage also works, while server answers continue.
- Prior open PR #9 addresses related citation quality on an older base: V2 intentionally subsumes that fix; do not merge PR #9 into main afterwards without review.

## QA release requirements

- PHP integration confirms only published relevant citation IDs, model follow-ups and rejects arbitrary browser model text, no inventory claims.
- Playwright verifies FAQ price citations; 390px reload/continuation, clear button, focus return; existing 360/390/768/1024/1440 contact + dock tests remain.
- PHP/JS lint, SEO/Yoast/woocommerce, CSS and bundles must PASS on exact source SHA before merge.
- After merge, verify page loads and chatbot REST question `Giá thuê xe máy bao nhiêu?` only cites `/faq/`, plus follow-up `Tháng thì sao?` if browser test permitted. Purge cache just once if needed.
- Rollback by reverting **only** this release commit (older V1 chatbot and QuickCall remain). If CI is red or a site test is unavailable, state FAIL/UNVERIFIED, do not claim deployment successful.
