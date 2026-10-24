import assert from 'node:assert/strict';
import { readFileSync, mkdirSync, writeFileSync } from 'node:fs';
import { chromium } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { assertPreservedLinks, verifyLiveTermAliases } from './homepage-legacy-parity.mjs';

const baseline = JSON.parse(readFileSync('reports/homepage-baseline-2026-10-21.json', 'utf8'));
// Explicit, owner-authorized homepage copy update; preserve the archived navigation/SEO before snapshot.
const expectedSeo = JSON.parse(readFileSync('reports/homepage-seo-expectations-2026-10-24.json', 'utf8'));
const out = 'reports/homepage-live';
mkdirSync(out, { recursive: true });
const proof = { started: new Date().toISOString(), commit: process.env.GITHUB_SHA, engine: 'Chromium emulation; native Safari untested', devices: [], errors: [] };
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
const browser = await chromium.launch();
try {
  const probe = await browser.newContext();
  let ready = false;
  for (let attempt = 0; attempt < 12; attempt++) {
    const response = await probe.request.get(baseline.url);
    if (response.ok() && (await response.text()).includes('data-nt-native-rental')) { ready = true; break; }
    await pause(10000);
  }
  assert.ok(ready, 'Native calculator is not deployed; do not claim live success');
  // The baseline predates the 19 Oct Legacy Compat URL recovery. Only treat the four
  // renamed menu/footer links as equivalent after observing real HTTP 301 redirects
  // and self-canonicals. Do not edit the archived SEO baseline or relax other URL checks.
  const legacy = await verifyLiveTermAliases(probe.request, baseline.url);
  proof.legacyAliasEvidence = legacy.evidence;
  await probe.close();
  const rates = [
    ['Honda Wave', 150000, '700.000đ', '900.000–1.200.000đ', '2–3 triệu'],
    ['Honda Vision', 200000, '1.000.000đ', '1.800.000–2.000.000đ', '4–5 triệu'],
    ['Honda Air Blade', 200000, '1.000.000đ', '1.500.000đ', '3 triệu'],
    ['Honda Click / Yamaha Mio', 150000, '700.000đ', '900.000–1.200.000đ', '2 triệu'],
    ['Xe tay ga 50cc', 200000, '', '', '4 triệu'],
    ['Xe máy điện / Xe đạp điện', 0, '', '', 'Xác nhận theo mẫu xe'],
  ];
  for (const [width, height] of [[390, 844], [768, 1024], [1440, 900]]) {
    // A deliberately different browser timezone must not change the Hanoi opening state.
    const context = await browser.newContext({ viewport: { width, height }, locale: 'vi-VN', timezoneId: 'America/Los_Angeles' });
    const page = await context.newPage();
    const result = { width, height, checks: [], consoleErrors: [], browserConsoleErrors: [], requestFailures: [] };
    proof.devices.push(result);
    page.on('pageerror', err => result.consoleErrors.push(String(err)));
    page.on('console', msg => { if (msg.type() === 'error') result.browserConsoleErrors.push({ text: msg.text(), location: msg.location() }); });
    page.on('requestfailed', req => result.requestFailures.push({ url: req.url(), error: req.failure()?.errorText }));
    const response = await page.goto(baseline.url, { waitUntil: 'domcontentloaded' });
    assert.equal(response.status(), 200);
    await page.waitForFunction(() => document.querySelector('[data-nt-rental-result]')?.textContent.includes('150.000'));
    const meta = await page.evaluate(() => ({
      title: document.title,
      description: document.querySelector('meta[name="description"]')?.content,
      canonical: [...document.querySelectorAll('link[rel="canonical"]')].map(el => el.href),
      h1: [...document.querySelectorAll('main h1')].map(el => el.textContent.trim()),
      main: document.querySelectorAll('main').length,
      nav: [...document.querySelectorAll('#nt-nav a[href]')].map(el => el.getAttribute('href')),
      footer: [...document.querySelectorAll('#site-footer a[href]')].map(el => el.getAttribute('href')),
      overflow: document.documentElement.scrollWidth > innerWidth + 1,
      nativeCount: document.querySelectorAll('[data-nt-native-rental]').length,
      oldScript: !!document.querySelector('#nt-smart-rental-js'),
      unusedWishlist: !!document.querySelector('#yith-wcwl-add-to-wishlist-js'),
    }));
    assert.equal(meta.title, expectedSeo.title);
    assert.equal(meta.description, expectedSeo.description);
    assert.deepEqual(meta.canonical, [baseline.url]);
    assert.deepEqual(meta.h1, [baseline.h1]);
    assert.equal(meta.main, 1);
    assertPreservedLinks(meta.nav, baseline.nav, legacy.replacements);
    assertPreservedLinks(meta.footer, baseline.footer, legacy.replacements);
    assert.equal(meta.overflow, false);
    assert.equal(meta.nativeCount, 1);
    assert.equal(meta.oldScript, false);
    assert.equal(meta.unusedWishlist, false);
    result.meta = meta;
    result.checks.push('SEO, menu/footer URL parity after verified 301 aliases, one calculator, no overflow or unused wishlist entry');
    await page.locator('[data-nt-theme-toggle]').click();
    const mode = await page.locator('html').getAttribute('data-nt-color-mode');
    await page.reload({ waitUntil: 'domcontentloaded' });
    assert.equal(await page.locator('html').getAttribute('data-nt-color-mode'), mode);
    if (mode !== 'dark') await page.locator('[data-nt-theme-toggle]').click();
    assert.equal(await page.locator('[data-nt-native-rental]').evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(27, 41, 64)');
    await page.screenshot({ path: out + '/home-' + width + '-dark.png', fullPage: true });
    await page.locator('[data-nt-theme-toggle]').click();
    result.checks.push('Dark mode, calculator contrast and mode persistence after reload');
    const hours = await page.locator('[data-nt-hours-status]').getAttribute('data-nt-hours-state');
    const hhmm = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Ho_Chi_Minh', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(new Date());
    assert.equal(hours, hhmm >= '09:00' && hhmm < '21:00' ? 'open' : 'closed');
    result.hours = { hanoi: hhmm, state: hours };
    if (width < 1240) {
      for (let n = 0; n < 3; n++) {
        await page.locator('[data-nt-menu-toggle]').click();
        assert.equal(await page.locator('[data-nt-menu-toggle]').getAttribute('aria-expanded'), 'true');
        await page.locator('[data-nt-menu-close]').click();
        assert.equal(await page.locator('[data-nt-menu-toggle]').getAttribute('aria-expanded'), 'false');
        assert.equal(await page.locator('body').evaluate(el => el.classList.contains('nt-lock')), false);
      }
    }
    result.checks.push('Hanoi opening status and drawer close/scroll unlock');
    const vehicle = page.locator('[data-nt-rental-vehicle]');
    const days = page.locator('[data-nt-rental-days]');
    const amount = page.locator('[data-nt-rental-result]');
    for (const [name, day, week, month, deposit] of rates) {
      await vehicle.selectOption(name);
      for (const count of [1, 3, 7, 30]) {
        await days.fill(String(count));
        const text = await amount.textContent();
        const expected = count < 7 ? (day ? new Intl.NumberFormat('vi-VN').format(day * count) + 'đ' : '') : (count === 7 ? week : month);
        assert.ok(expected ? text.includes(expected) : text.includes('cần liên hệ'), name + ': ' + text);
        assert.ok(text.includes(deposit));
      }
    }
    for (const value of ['0', '-1', '1.5', '999999999', '']) {
      await days.fill(value);
      assert.equal(await amount.textContent(), 'Chọn từ 1 đến 30 ngày.');
    }
    await days.evaluate(el => { el.value = 'invalid'; el.dispatchEvent(new Event('input', { bubbles: true })); });
    assert.equal(await amount.textContent(), 'Chọn từ 1 đến 30 ngày.');
    result.checks.push('All six vehicle groups: 1/3/7/30 days, separate deposits and invalid input');
    await page.evaluate(() => scrollTo(0, 0));
    await page.locator('[data-nt-qc-launch]').click();
    await page.locator('[data-nt-chat-open]').first().click();
    assert.equal(await page.locator('#nt-native-quickcall').isVisible(), false);
    await page.locator('#nt-assist-question').fill('Honda Vision giá bao nhiêu một tuần?');
    await page.locator('[data-nt-chat-form] button[type="submit"]').click();
    await page.waitForFunction(() => [...document.querySelectorAll('.nt-assist__message--bot')].some(el => el.textContent.includes('1.000.000')));
    await page.locator('#nt-assist-question').fill('Tiền cọc bao nhiêu?');
    await page.locator('[data-nt-chat-form] button[type="submit"]').click();
    await page.waitForFunction(() => [...document.querySelectorAll('.nt-assist__message--bot')].some(el => el.textContent.includes('4–5 triệu')));
    result.chat = await page.locator('.nt-assist__message--bot').allTextContents();
    const chatAxe = await new AxeBuilder({ page }).withRules(['target-size', 'label-content-name-mismatch']).analyze();
    result.chatAccessibility = { violations: chatAxe.violations, incomplete: chatAxe.incomplete };
    assert.deepEqual(chatAxe.violations, [], 'Open chatbot accessibility');
    assert.deepEqual(chatAxe.incomplete, [], 'Open chatbot rules need manual review');
    await page.screenshot({ path: out + '/chat-' + width + '.png' });
    await page.locator('[data-nt-chat-close]').click();
    assert.equal(await page.locator('#nt-native-quickcall').isVisible(), true);
    await page.evaluate(() => { document.documentElement.style.scrollBehavior = 'auto'; scrollTo(0, document.documentElement.scrollHeight); });
    await page.screenshot({ path: out + '/footer-' + width + '.png' });
    await page.locator('.nt-footer__backtop').click();
    await page.waitForFunction(() => scrollY <= 20);
    result.checks.push('Chat price/context, hidden QuickCall while chat is open, close and Back to Top');
    assert.deepEqual(result.consoleErrors, []);
    assert.deepEqual(result.browserConsoleErrors, []);
    await page.screenshot({ path: out + '/home-' + width + '-light.png', fullPage: true });
    await context.close();
  }
  proof.status = 'PASS';
} catch (err) {
  proof.status = 'FAIL';
  proof.errors.push(String(err.stack || err));
  process.exitCode = 1;
} finally {
  proof.finished = new Date().toISOString();
  writeFileSync(out + '/proof.json', JSON.stringify(proof, null, 2));
  console.log(JSON.stringify(proof, null, 2));
  await browser.close();
}
