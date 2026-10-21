import assert from 'node:assert/strict';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { chromium } from '@playwright/test';

const baseline = JSON.parse(readFileSync('reports/static-pages/before-2026-10-21.json', 'utf8'));
const output = 'reports/static-pages/live';
mkdirSync(output, { recursive: true });
const proof = { started: new Date().toISOString(), commit: process.env.GITHUB_SHA, engine: 'Chromium viewport emulation; native Safari not tested', pages: [], errors: [] };
const browser = await chromium.launch();
try {
  const probe = await browser.newContext();
  let ready = false;
  for (let attempt = 0; attempt < 18; attempt++) {
    const response = await probe.request.get(baseline.pages.find(x => x.id === 372).url);
    if (response.ok() && (await response.text()).includes('data-nt-page-body="20261021"')) { ready = true; break; }
    await new Promise(resolve => setTimeout(resolve, 10000));
  }
  await probe.close();
  assert.ok(ready, 'Static-page release has not reached WordPress');
  for (const [width, height] of [[390, 844], [768, 1024], [1440, 900]]) {
    const context = await browser.newContext({ viewport: { width, height }, locale: 'vi-VN', timezoneId: 'Asia/Ho_Chi_Minh' });
    const page = await context.newPage();
    for (const prior of baseline.pages) {
      const record = { id: prior.id, url: prior.url, width, checks: [], pageErrors: [] };
      proof.pages.push(record);
      const onError = error => record.pageErrors.push(String(error));
      page.on('pageerror', onError);
      try {
        const response = await page.goto(prior.url, { waitUntil: 'domcontentloaded' });
        assert.equal(response.status(), 200);
        const meta = await page.evaluate(() => ({
          title: document.title,
          description: document.querySelector('meta[name="description"]')?.content ?? '',
          canonical: [...document.querySelectorAll('link[rel="canonical"]')].map(el => el.href),
          robots: document.querySelector('meta[name="robots"]')?.content ?? '',
          h1: [...document.querySelectorAll('main h1')].map(el => el.textContent.trim()),
          nav: [...document.querySelectorAll('#nt-nav a[href]')].map(el => el.getAttribute('href')),
          footer: [...document.querySelectorAll('#site-footer a[href]')].map(el => el.getAttribute('href')),
          images: document.querySelectorAll('main img:not(form img)').length,
          marker: !!document.querySelector('[data-nt-page-body="20261021"]'),
          pageStyles: !!document.querySelector('#nt-pages-css'),
          rawShortcodes: /\[\/?(?:ux_\w+|section|row|col|contact-form-7)(?=[\s\]/])/.test(document.querySelector('main').innerText),
        }));
        for (const key of ['title', 'description', 'canonical', 'robots', 'h1', 'nav', 'footer']) {
          assert.deepEqual(meta[key], prior[key], `${prior.id}: ${key} changed`);
        }
        assert.equal(meta.rawShortcodes, false);
        record.checks.push('200, title/meta/canonical/robots/H1 and navigation/footer URLs preserved');
        if ([562, 3040].includes(prior.id)) {
          assert.equal(meta.marker, false, 'Homepage/shop must stay outside the static-page body');
          assert.equal(meta.pageStyles, false);
          assert.ok(meta.images > 0, 'Homepage/shop photos must remain');
          record.checks.push('Homepage/shop excluded; photos retained');
          continue;
        }
        assert.equal(meta.h1.length, 1);
        assert.equal(meta.marker, true);
        assert.equal(meta.images, 0, 'No decorative body images');
        assert.equal(meta.pageStyles, true);
        for (const mode of ['light', 'dark']) {
          if ((await page.locator('html').getAttribute('data-nt-color-mode')) !== mode) await page.locator('[data-nt-theme-toggle]').click();
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
            return { overflow: document.documentElement.scrollWidth > innerWidth + 1, left: rect.left, right: rect.right,
              lowContrast: [...root.querySelectorAll('h1,h2,h3,h4,p,li,label,.nt-btn')].filter(el => el.getBoundingClientRect().height > 0 && contrast(el) < 4.5).map(el => ({ text: el.innerText.slice(0, 80), contrast: contrast(el) })) };
          });
          assert.equal(metrics.overflow, false, `${prior.id} ${mode}: horizontal overflow`);
          assert.ok(metrics.left >= 12 && metrics.right <= width - 12, 'Readable side gutters');
          assert.deepEqual(metrics.lowContrast, [], `${prior.id} ${mode}: contrast`);
          await page.screenshot({ path: `${output}/${prior.id}-${width}-${mode}.png`, fullPage: width === 390 && [372, 494].includes(prior.id) });
          record.checks.push(`${mode}: no overflow, side gutters, text/button contrast >= 4.5`);
        }
        if (prior.id === 372) {
          assert.equal(await page.locator('main .nt-legacy').count(), 0);
          assert.equal(await page.locator('main iframe').count(), 0);
          assert.equal(await page.locator('main .wpcf7 form').count(), 1);
          assert.ok(await page.locator('main .wpcf7 input[type="hidden"]').count() > 0);
          await page.locator('main .wpcf7 input[name="your-name"]').fill('Kiểm tra giao diện');
          await page.locator('main .wpcf7 input[name="your-email"]').fill('ui-check@example.invalid');
          await page.locator('main .wpcf7 input[name="your-name"]').fill('');
          await page.locator('main .wpcf7 input[name="your-email"]').fill('');
          record.checks.push('CF7 form and hidden fields retained; fields editable; no submission');
        }
        assert.deepEqual(record.pageErrors, []);
        record.meta = meta;
      } catch (error) {
        record.error = String(error);
        proof.errors.push(`${prior.id} ${width}: ${error}`);
        await page.screenshot({ path: `${output}/FAIL-${prior.id}-${width}.png` }).catch(() => {});
      } finally {
        page.off('pageerror', onError);
      }
    }
    await context.close();
  }
} finally {
  await browser.close();
  proof.finished = new Date().toISOString();
  writeFileSync(`${output}/proof.json`, JSON.stringify(proof, null, 2) + '\n');
}
console.log(JSON.stringify({ pages: proof.pages.length, errors: proof.errors }, null, 2));
assert.deepEqual(proof.errors, []);
