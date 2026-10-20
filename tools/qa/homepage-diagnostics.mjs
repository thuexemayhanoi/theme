import assert from 'node:assert/strict';
import { chromium } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { mkdirSync, writeFileSync } from 'node:fs';

const out = 'reports/homepage-diagnostics';
mkdirSync(out, { recursive: true });
const proof = { started: new Date().toISOString(), code: process.env.GITHUB_SHA, url: 'https://thuexemaynguyentu.com/', scope: 'Chromium only; two named axe rules and console/HTTP diagnostics, not a complete accessibility certification', devices: [] };
const browser = await chromium.launch();
try {
  for (const viewport of [{ width: 1365, height: 768 }, { width: 768, height: 1024 }, { width: 390, height: 844 }]) {
    const context = await browser.newContext({ viewport, locale: 'vi-VN' });
    const page = await context.newPage();
    const result = { viewport, console: [], uncaught: [], failedRequests: [], httpErrors: [], phases: [] };
    proof.devices.push(result);
    let closing = false;
    page.on('console', msg => {
      if (['error', 'warning'].includes(msg.type())) result.console.push({ type: msg.type(), text: msg.text(), location: msg.location(), closing });
    });
    page.on('pageerror', err => result.uncaught.push(String(err)));
    page.on('requestfailed', req => result.failedRequests.push({ url: req.url(), error: req.failure()?.errorText, closing }));
    page.on('response', res => {
      if (res.status() >= 400) result.httpErrors.push({ url: res.url(), status: res.status() });
    });
    const inspect = async phase => {
      const axe = await new AxeBuilder({ page })
        .withRules(['target-size', 'label-content-name-mismatch'])
        .analyze();
      const check = { phase, violations: axe.violations, incomplete: axe.incomplete, passes: axe.passes.map(item => ({ id: item.id, count: item.nodes.length })) };
      result.phases.push(check);
      await page.screenshot({ path: out + '/' + phase + '-' + viewport.width + '.png', fullPage: phase === 'light' });
      assert.deepEqual(check.violations, [], phase + ' accessibility violations');
      assert.deepEqual(check.incomplete, [], phase + ' needs manual rule review');
      assert.deepEqual(result.uncaught, [], phase + ' uncaught JavaScript');
      assert.deepEqual(result.console.filter(msg => msg.type === 'error'), [], phase + ' browser console errors');
      assert.deepEqual(result.httpErrors, [], phase + ' HTTP errors');
    };
    result.status = (await page.goto(proof.url, { waitUntil: 'domcontentloaded' })).status();
    assert.equal(result.status, 200);
    await page.waitForFunction(() => document.querySelector('[data-nt-rental-result]')?.textContent.includes('150.000'));
    await page.waitForTimeout(6000);
    await inspect('light');
    await page.locator('[data-nt-theme-toggle]').click();
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => document.documentElement.getAttribute('data-nt-color-mode') === 'dark');
    await inspect('dark-reload');
    await page.locator('[data-nt-theme-toggle]').click();
    await page.locator('[data-nt-search-toggle]').click();
    await inspect('search');
    await page.locator('[data-nt-search-toggle]').click();
    if (viewport.width < 1240) {
      await page.locator('[data-nt-menu-toggle]').click();
      await inspect('drawer');
      await page.locator('[data-nt-menu-close]').click();
    }
    await page.locator('[data-nt-qc-launch]').click();
    await inspect('quickcall');
    await page.locator('[data-nt-chat-open]').first().click();
    await inspect('chat');
    await page.locator('[data-nt-chat-close]').click();
    result.status = 'PASS';
    // Preserve shutdown-related network failures separately from failures during interaction.
    closing = true;
    await context.close();
    console.log(JSON.stringify(result, null, 2));
  }
  proof.status = 'PASS';
} catch (err) {
  proof.status = 'FAIL';
  proof.error = String(err.stack || err);
  process.exitCode = 1;
} finally {
  proof.finished = new Date().toISOString();
  writeFileSync(out + '/proof.json', JSON.stringify(proof, null, 2));
  console.log(JSON.stringify({ status: proof.status, error: proof.error, finished: proof.finished }));
  await browser.close();
}
