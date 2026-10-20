import { chromium } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { mkdirSync, writeFileSync } from 'node:fs';

const out = 'reports/homepage-diagnostics';
mkdirSync(out, { recursive: true });
const proof = { started: new Date().toISOString(), code: process.env.GITHUB_SHA, url: 'https://thuexemaynguyentu.com/', devices: [] };
const browser = await chromium.launch();
try {
  for (const viewport of [{ width: 1365, height: 768 }, { width: 390, height: 844 }]) {
    const context = await browser.newContext({ viewport, locale: 'vi-VN' });
    const page = await context.newPage();
    const result = { viewport, console: [], uncaught: [], failedRequests: [], httpErrors: [] };
    proof.devices.push(result);
    page.on('console', msg => {
      if (['error', 'warning'].includes(msg.type())) result.console.push({ type: msg.type(), text: msg.text(), location: msg.location() });
    });
    page.on('pageerror', err => result.uncaught.push(String(err)));
    page.on('requestfailed', req => result.failedRequests.push({ url: req.url(), error: req.failure()?.errorText }));
    page.on('response', res => {
      if (res.status() >= 400) result.httpErrors.push({ url: res.url(), status: res.status() });
    });
    result.status = (await page.goto(proof.url, { waitUntil: 'domcontentloaded' })).status();
    await page.waitForFunction(() => document.querySelector('[data-nt-rental-result]')?.textContent.includes('150.000'));
    await page.waitForTimeout(6000);
    const axe = await new AxeBuilder({ page })
      .withRules(['target-size', 'label-content-name-mismatch'])
      .analyze();
    result.violations = axe.violations;
    result.incomplete = axe.incomplete;
    result.passes = axe.passes.map(item => ({ id: item.id, count: item.nodes.length }));
    result.labels = await page.locator('a[aria-label],button[aria-label],input[aria-label]').evaluateAll(nodes => nodes.filter(el => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden').map(el => ({ html: el.outerHTML.slice(0, 1600), text: el.innerText, label: el.getAttribute('aria-label'), rect: el.getBoundingClientRect().toJSON() })));
    await page.screenshot({ path: out + '/before-' + viewport.width + '.png', fullPage: true });
    await context.close();
    console.log(JSON.stringify(result, null, 2));
  }
} finally {
  proof.finished = new Date().toISOString();
  writeFileSync(out + '/proof.json', JSON.stringify(proof, null, 2));
  await browser.close();
}
