import assert from 'node:assert/strict';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { chromium } from '@playwright/test';
import { verifyLiveTermAliases } from './homepage-legacy-parity.mjs';
import { validateLedger, expectedFor, diffMeta, describeDiffs } from './lib/static-baseline.mjs';

// The archived baseline is never edited. Renamed menu/footer aliases are accepted only after a live
// 301 + 200 + self-canonical probe; WordPress-managed copy changes only through the evidence ledger.
const baseline = JSON.parse(readFileSync('reports/static-pages/before-2026-10-21.json', 'utf8'));
const ledger = JSON.parse(readFileSync('reports/static-pages/approved-changes-2026-10-22.json', 'utf8'));
validateLedger(ledger, baseline.pages);
const output = 'reports/static-pages/live';
mkdirSync(output, { recursive: true });
const proof = { started: new Date().toISOString(), commit: process.env.GITHUB_SHA, engine: 'Chromium viewport emulation; native Safari not tested', ledger: ledger.changes.map(c => `${c.id}/${c.field}`), pages: [], errors: [] };
const browser = await chromium.launch();
try {
  const probe = await browser.newContext();
  let ready = false;
  for (let attempt = 0; attempt < 18; attempt++) {
    const response = await probe.request.get(baseline.pages.find(x => x.id === 372).url);
    if (response.ok() && (await response.text()).includes('data-nt-page-body="20261021"')) { ready = true; break; }
    await new Promise(resolve => setTimeout(resolve, 10000));
  }
  assert.ok(ready, 'Static-page release has not reached WordPress');
  const legacy = await verifyLiveTermAliases(probe.request, baseline.site);
  proof.legacyAliasEvidence = legacy.evidence;
  console.log('Legacy menu aliases verified live: HTTP 301 -> 200 + self canonical (' + legacy.evidence.length + ' URLs)');
  await probe.close();
  for (const [width, height] of [[390, 844], [768, 1024], [1440, 900]]) {
    const context = await browser.newContext({ viewport: { width, height }, locale: 'vi-VN', timezoneId: 'Asia/Ho_Chi_Minh' });
    const page = await context.newPage();
    for (const prior of baseline.pages) {
      const expected = expectedFor(prior, ledger);
      const record = { id: prior.id, url: prior.url, width, checks: [], failures: [], pageErrors: [] };
      proof.pages.push(record);
      const onError = error => record.pageErrors.push(String(error));
      page.on('pageerror', onError);
      // Every group is recorded separately so one live run shows all problems, not just the first.
      const check = async (name, fn) => {
        try {
          await fn();
          record.checks.push(name);
        } catch (error) {
          const message = String(error?.message ?? error);
          record.failures.push({ check: name, error: message });
          proof.errors.push(`${prior.id} ${width} [${name}]: ${message}`);
        }
      };
      try {
        const response = await page.goto(prior.url, { waitUntil: 'domcontentloaded' });
        await check('HTTP 200', () => assert.equal(response.status(), 200));
        const meta = await page.evaluate(() => ({
          title: document.title,
          description: document.querySelector('meta[name="description"]')?.content ?? '',
          canonical: [...document.querySelectorAll('link[rel="canonical"]')].map(el => el.href),
          robots: document.querySelector('meta[name="robots"]')?.content ?? '',
          h1: [...document.querySelectorAll('main h1')].map(el => el.textContent.trim()),
          h1Document: document.querySelectorAll('h1').length,
          h2: [...document.querySelectorAll('main h2')].map(el => el.textContent.trim().slice(0, 100)),
          nav: [...document.querySelectorAll('#nt-nav a[href]')].map(el => el.getAttribute('href')),
          footer: [...document.querySelectorAll('#site-footer a[href]')].map(el => el.getAttribute('href')),
          images: document.querySelectorAll('main img:not(form img)').length,
          marker: !!document.querySelector('[data-nt-page-body="20261021"]'),
          rawShortcodes: /\[\/?(?:ux_\w+|section|row|col|contact-form-7)(?=[\s\]/])/.test(document.querySelector('main').innerText),
        }));
        record.meta = meta;
        await check('title/description/canonical/robots/H1 and menu/footer URLs match the baseline', () => {
          const diffs = diffMeta(expected, meta, legacy.replacements);
          record.diffs = diffs;
          assert.deepEqual(diffs, [], describeDiffs(prior.id, width, diffs));
        });
        await check('exactly one H1 in the document', () => {
          assert.equal(meta.h1.length, 1, 'main H1 count');
          assert.equal(meta.h1Document, 1, 'document H1 count');
        });
        await check('no raw shortcodes', () => assert.equal(meta.rawShortcodes, false));
        if ([562, 3040].includes(prior.id)) {
          await check('Homepage/shop excluded; photos retained', () => {
            assert.equal(meta.marker, false, 'Homepage/shop must stay outside the static-page body');
            assert.ok(meta.images > 0, 'Homepage/shop photos must remain');
          });
        } else {
          await check('static-page body marker present, no decorative body images', () => {
            assert.equal(meta.marker, true);
            assert.equal(meta.images, 0, 'No decorative body images');
          });
          for (const mode of ['light', 'dark']) {
            await check(`${mode}: no overflow, side gutters, text/button contrast >= 4.5`, async () => {
              if ((await page.locator('html').getAttribute('data-nt-color-mode')) !== mode) await page.locator('[data-nt-theme-toggle]').click();
              // Buttons animate their colors for 150ms. Measure the settled UI,
              // rather than the previous mode's text on the next mode's background.
              await page.locator('main').evaluate(async main => {
                await Promise.all(main.getAnimations({ subtree: true })
                  .filter(animation => animation instanceof CSSTransition)
                  .map(animation => animation.finished.catch(() => {})));
              });
              const metrics = await page.evaluate(() => {
                const root = document.querySelector('.nt-service-page') ?? document.querySelector('.nt-document-page');
                const rgb = value => value.match(/[\d.]+/g)?.slice(0, 3).map(Number).map(n => n / 255) ?? [];
                const lum = values => values.map(x => x <= .04045 ? x / 12.92 : ((x + .055) / 1.055) ** 2.4).reduce((s, x, i) => s + x * [.2126, .7152, .0722][i], 0);
                const contrast = el => {
                  let parent = el;
                  while (parent && !/^rgb\(/.test(getComputedStyle(parent).backgroundColor)) parent = parent.parentElement;
                  const bg = lum(rgb(getComputedStyle(parent ?? document.body).backgroundColor));
                  const fg = lum(rgb(getComputedStyle(el).color));
                  return (Math.max(bg, fg) + .05) / (Math.min(bg, fg) + .05);
                };
                const rect = root.getBoundingClientRect();
                const card = root.matches('.nt-document-page') ? root : root.querySelector('.nt-service-page__intro');
                return { overflow: document.documentElement.scrollWidth > innerWidth + 1, left: rect.left, right: rect.right,
                  cardPadding: parseFloat(getComputedStyle(card).paddingLeft), cardBorder: parseFloat(getComputedStyle(card).borderTopWidth),
                  lowContrast: [...root.querySelectorAll('h1,h2,h3,h4,p,li,label,.nt-btn')].filter(el => el.getBoundingClientRect().height > 0 && contrast(el) < 4.5).map(el => ({ text: el.innerText.slice(0, 80), contrast: contrast(el) })) };
              });
              assert.equal(metrics.overflow, false, `${prior.id} ${mode}: horizontal overflow`);
              assert.ok(metrics.left >= 12 && metrics.right <= width - 12, 'Readable side gutters');
              // LiteSpeed combines stylesheets and removes their original link IDs.
              // Verify the resulting card geometry, rather than a particular <link>.
              assert.ok(metrics.cardPadding >= 18 && metrics.cardBorder >= 1, 'Page card styles applied');
              assert.deepEqual(metrics.lowContrast, [], `${prior.id} ${mode}: contrast`);
              await page.screenshot({ path: `${output}/${prior.id}-${width}-${mode}.png`, fullPage: width === 390 && [372, 494].includes(prior.id) });
            });
          }
          if (prior.id === 372) {
            await check('CF7 form and hidden fields retained; fields editable; no submission', async () => {
              assert.equal(await page.locator('main .nt-legacy').count(), 0, 'no legacy wrapper');
              // PR #40 embeds exactly one Google map (template-parts/pages/contact.php). Any other frame,
              // or a second one, is a regression; the map keeps its title, lazy loading and referrer policy.
              const frames = await page.locator('main iframe').evaluateAll(els => els.map(el => ({ src: el.src, title: el.title, loading: el.loading, referrerPolicy: el.referrerPolicy })));
              assert.equal(frames.length, 1, 'exactly one iframe, the native Google map: ' + JSON.stringify(frames));
              assert.match(frames[0].src, /^https:\/\/maps\.google\.com\/maps\?q=[^&]+&hl=vi&z=16&output=embed$/, 'map embed URL');
              assert.equal(frames[0].loading, 'lazy', 'map iframe is lazy loaded');
              assert.ok(frames[0].title.length > 10, 'map iframe has an accessible title');
              assert.equal(frames[0].referrerPolicy, 'strict-origin-when-cross-origin', 'map iframe referrer policy');
              assert.equal(await page.locator('main .wpcf7 form').count(), 1, 'one Contact Form 7 form');
              assert.ok(await page.locator('main .wpcf7 input[type="hidden"]').count() > 0, 'CF7 hidden fields');
              await page.locator('main .wpcf7 input[name="your-name"]').fill('Kiểm tra giao diện');
              await page.locator('main .wpcf7 input[name="your-email"]').fill('ui-check@example.invalid');
              await page.locator('main .wpcf7 input[name="your-name"]').fill('');
              await page.locator('main .wpcf7 input[name="your-email"]').fill('');
            });
          }
        }
        await check('no page JavaScript exceptions', () => assert.deepEqual(record.pageErrors, []));
      } catch (error) {
        const message = String(error?.message ?? error);
        record.failures.push({ check: 'navigation', error: message });
        proof.errors.push(`${prior.id} ${width} [navigation]: ${message}`);
      } finally {
        if (record.failures.length) await page.screenshot({ path: `${output}/FAIL-${prior.id}-${width}.png` }).catch(() => {});
        page.off('pageerror', onError);
      }
      const h1 = record.meta ? JSON.stringify(record.meta.h1) : 'n/a';
      console.log(`${record.failures.length ? 'FAIL' : 'OK  '} ${String(prior.id).padEnd(4)} @${String(width).padEnd(4)} ${prior.url} h1=${h1} checks=${record.checks.length} failures=${record.failures.length}`);
    }
    await context.close();
  }
} finally {
  await browser.close();
  proof.finished = new Date().toISOString();
  proof.status = proof.errors.length ? 'FAIL' : 'PASS';
  writeFileSync(`${output}/proof.json`, JSON.stringify(proof, null, 2) + '\n');
}
const shop = proof.pages.find(p => p.id === 3040 && p.meta);
if (shop) console.log('Shop main H2 headings (information only):', JSON.stringify(shop.meta.h2.slice(0, 10)));
const failed = proof.pages.filter(p => p.failures.length);
// Identical failures at several viewports are printed once, with the widths they occurred at.
const merged = new Map();
for (const record of failed) {
  for (const failure of record.failures) {
    const key = `${record.id}\0${failure.check}\0${failure.error.replace(/ @\d+/g, ' @*')}`;
    const entry = merged.get(key) ?? { id: record.id, check: failure.check, error: failure.error, widths: [] };
    entry.widths.push(record.width);
    merged.set(key, entry);
  }
}
if (merged.size) console.log('FAILURE DETAILS (identical failures across viewports are merged)');
for (const entry of merged.values()) {
  console.log(`- page ${entry.id} [${entry.check}] widths ${entry.widths.join(', ')}\n${entry.error.split('\n').map(line => '    ' + line).join('\n')}`);
  console.log(`::error title=Static pages ${entry.id}::${entry.check}: ${entry.error.split('\n')[0]}`);
}
console.log(JSON.stringify({ status: proof.status, pageVisits: proof.pages.length, failedVisits: failed.length, errors: proof.errors.length, distinctFailures: merged.size }, null, 2));
assert.equal(proof.errors.length, 0, `${proof.errors.length} failed check(s); full details are in the log lines above and reports/static-pages/live/proof.json`);
