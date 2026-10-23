# Assistant: six quick questions, contact and address answers, follow-ups, reading suggestions (23 October 2026)

Request: GitHub Issue #62 ("OWNER APPROVED: Chatbot 6 chips (Liên hệ/Địa chỉ) + fix intent/price/context/source quality"). The owner approved the fix inside the Issue and a scoped exception to the P0 Owner Lock for `inc/assist.php`, `assets/js/assist.js`, `assets/css/assist.css` and their tests. Quick Call, SEO output, WordPress content, `color-mode.css`, `inc/enqueue.php` and `functions.php` are not touched.

Status of this record: **candidate, written before the merge**. Merge, deploy and the live check are recorded in `docs/PROGRESS.md` after they happen; nothing below claims them.

## What a visitor gets now

| Before (main `e0ab823`) | After |
| --- | --- |
| 4 chips: Bảng giá, Đặt cọc, Thủ tục, Xe điện | 6 chips: the same four with the same questions, plus **Liên hệ** (`Số điện thoại và Zalo liên hệ cửa hàng?`) and **Địa chỉ** (`Cửa hàng Nguyễn Tú ở đâu? Chỉ đường?`) |
| Contact wording (`hotline`, `sdt`, `so dien thoai`, `gọi cho cửa hàng`, `đặt xe`, `how can I contact you`) is not understood; the answer is "no source found" | Answered from `inc/business.php`: phone/Zalo 0942 467 674, opening hours 09:00–21:00 every day, buttons **Gọi điện** and **Nhắn Zalo** |
| Address answered without a map button, English `where is your shop` not understood | Address 112 Nguyễn Văn Cừ, Bồ Đề, Long Biên, Hà Nội + hours, button **Chỉ đường** to the canonical Maps profile (`nt_maps_url()`) |
| `Giá Vision thuê tháng và cọc bao nhiêu?` answered with the deposit only | One answer with the month price **and** the deposit |
| `Tháng thì sao?` after `Giá Vision?` labelled as a search; `Wave thì sao?` / `Air Blade?` after it found nothing | Follow-ups follow the vehicle, the old period is dropped when the vehicle changes |
| Electric bike: `Giá tham khảo Xe máy điện / Xe đạp điện: theo ngày Liên hệ, theo tuần Liên hệ…`; 50cc week: `…theo tuần: Báo giá riêng.` | The shop quotes these: the answer says so and offers call and Zalo; no figure is invented |
| `xe 50cc có cần bằng lái không` returned unrelated articles | Only the official text (55/VBHN-VPQH, articles named), classes separated by speed and engine size, vehicle-specific answer by the shop, product page #669 cited |
| `xe tay ga 50cc gọi là gì` got the vehicle overview; `xe chở được bao nhiêu người` got a price | Neither: they are article questions |
| Article suggestions not labelled | At most three public, indexable pages, labelled `gợi ý đọc, không phải câu trả lời chính thức` |

## Evidence

### Reproduced on `main`, same tool, same questions

`tools/qa/assist-live.mjs` asks 34 questions (one is a three-turn conversation) of a running site and opens the chat in Chromium. It was run against the code of `main@e0ab823` and against this branch on the same local harness (the theme's real PHP, CSS and JS; WordPress itself and the page corpus are stand-ins, so this is **not** a live measurement):

| | main `e0ab823` | this branch |
| --- | --- | --- |
| REST cases passing | **4 of 34** | **34 of 34** |
| Browser runs passing (390 px light and dark, 360, 768, 1024, 1440 px) | **0 of 7** (4 chips) | **7 of 7** |

The live "before" run (workflow `Assistant live QA`, label `before`, SHA-256 of the served `assist.js`/`assist.css`, SEO fingerprint and Quick Call menu hash) and the live "after" run are recorded in `docs/PROGRESS.md`.

### Layout, measured in Chromium (repository CSS and JS, real PHP for the markup and the answers)

Ten viewports in light and dark mode, then the software keyboard, hostile answers, errors and timeouts:

- **Chips**: 3 × 2 grid, every chip 44 px high and at least 103 px wide at 320–1440 px (portrait); text never clipped; no overlap with the input, send, close or clear button; no horizontal scroll; the chat adds no layout shift (0.000 at all 20 runs); Quick Call steps aside while the chat is open.
- **Answer area before the first answer**: 226 px at 360×800, 257 px at 390×844, 170 px at 320×568. On a landscape phone the chips become one scrolling row (36 px) and the panel takes the free height: 85 px at 844×390, 87 px at 667×375, 65 px at 568×320 (on `main` the answer area was about 26 px at 844×390 and the chips overflowed the panel at 568×320).
- **Software keyboard** (viewport shrunk while the input is focused; Chromium cannot show a real keyboard): chips hide, input, send and close stay inside the visible height at 390×844, 360×800, 320×568 and 844×390, the answer area keeps 356, 329, 149 and 60 px, chips return when the input loses focus.
- **Contrast** (WCAG AA, 4.5:1): chips and answer buttons pass in light and dark. The probe found one defect of the new buttons: white text on the dark-mode accent `#ef7763` is **2.80:1**; the **Gọi điện** answer button now uses `#c93a22` in dark mode (**5.11:1**), set in `assist.css` only.
- **Hostile answers**: `<img onerror>`, `<script>`, `javascript:` and `data:` links, off-site sources, look-alike hosts (`zalo.me.evil.example`, `maps.google.com.evil.example`), credentials in a URL, unknown button types, `null` and strings in `actions`: nothing runs, no foreign link or button is created, the answer is shown as text.
- **Errors**: network failure, HTTP 500, invalid JSON, empty answer, and a request that never answers (given up after 12.0 s): a calm fallback that points to phone and Zalo, send button enabled again, status line hidden.
- **Memory**: the browser stores only `{model, period}` (no question text); the clear button removes it and the next answer no longer uses the old vehicle.
- **Keyboard**: Tab reaches the six chips in order, then input, send, clear, the Zalo link and close (focus is trapped inside the dialog); Enter and Space send a chip; Escape closes and returns focus to the Quick Call launcher; the focus ring is 3 px.

### Tests added

| Suite | New | Runs |
| --- | --- | --- |
| PHPUnit unit (`tests/unit/AssistTest.php`, pure PHP) | **146** (243 in the suite, 2,711 assertions) | workspace and CI `static` job |
| Node (`tests/node/assist-cases.test.mjs`) | 10 | workspace and CI `static` job |
| PHPUnit integration on real WordPress (`NativeAssistTest.php`) | 15 | CI `sandbox` job only |
| Playwright (`native-assist.spec.mjs`) | 21 | CI `sandbox` job; also run in the workspace against the local harness |

The unit cases include 55 question phrasings for contact and address (Vietnamese with and without marks, abbreviations, English, typos), 21 look-alikes that must **not** trigger them (parking, refuelling, another destination, "cân bằng"), price and deposit combinations, four conversations (price → month → deposit, vehicle switch, address between price questions, period before vehicle), quote-only models, licence, stock, delivery, hostile input, the buttons and the ranking (the two PR #49 tests unchanged). Mutation check: breaking the contact words, the amount test, the period reset or the Maps URL makes the suite fail each time.

Local results on this branch: `php -l` clean, PHPCS 0 errors, PHPUnit unit 243/243, ESLint clean, Node tests all pass, CSS gate, contrast gate (33 pairs) and byte budgets pass, `native-assist` e2e 38 passed and 1 skipped (the legacy-simulator test) against the local harness. **Not run in the workspace** (no WordPress sandbox here): the integration suite and the WordPress e2e: CI does.

### Budgets

`assist.js` 19.6 KB raw / 6.5 KB gzip (cap raised from 19 / 6.2 to 20 / 6.7) and `assist.css` 10.3 KB / 2.9 KB gzip (cap raised from 9 / 3 to 10.5 / 3). The growth is the second chip row, the answer buttons and the short-screen rules; both files load only with the assistant. The critical path (`main.css` gzip + fonts) is unchanged at 42.5 KB of 60.

## Decisions on the other PRs

| PR | Decision | Evidence |
| --- | --- | --- |
| **#49** (bounded BM25, price and deposit) | **Ported, then superseded.** `nt_assist_bm25_rerank()` is the same code; its two tests are in `AssistTest.php` unchanged; the combined price+deposit answer is rebuilt on `nt_price_rows()`/`nt_policy()` with quote-only cells, context and refund handling. Do not merge #49 as well: it conflicts with `inc/assist.php` by design. | `inc/assist.php`, `tests/unit/AssistTest.php` |
| **#9** (cite FAQ/contact pages) | **Superseded** by V2 and by the verified-source mapping in this change (contact #372, FAQ #484, 50cc #669 only for a 50cc licence question). Not merged. | `docs/NATIVE-CHATBOT-CONTEXT-V2.md` |
| **#2** (autopilot, broad) | **Unchanged.** Widgets in it would duplicate the native Quick Call and Assistant; parts not on `main` (admin-configured closing hours, topic index) should return as small PRs from `main` after the owner agrees. | `reports/pr-triage-2026-10-22.md` |

## Observed, not changed

- The existing **Quick Call** "Gọi điện" button is white on `#ef7763` in dark mode: **2.80:1** (measured with the repository CSS; light mode 4.85:1). It is under the P0 freeze and outside Issue #62: ask first. A fix like the one used for the answer button is one rule.
- The offline fallback ("Chưa tra cứu được lúc này…") names phone and Zalo but shows no button; the panel's footer still has the Zalo link. Adding buttons there would need the phone number in the page markup; left as is.
- The article search passes accent-free words to `WP_Query` (as on `main`). Whether MySQL matches `đ` with `d` depends on the live collation; the live run of the `search-pin` question shows it.

## Not tested

Native Safari or an iPhone (Chromium viewport emulation and a modelled keyboard only); real devices; Lighthouse and field Core Web Vitals on the live site; the live site before and after the deploy (recorded separately); page-cache behaviour after the deploy.

## Rollback

Revert the merge commit of the Issue #62 pull request (theme files, tests, tools, docs; nothing is stored in WordPress). Old REST keys and the browser memory format are unchanged, so no cleanup is needed.
