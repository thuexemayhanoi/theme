#!/usr/bin/env node
/**
 * Read-only live audit of the homepage images at 390, 768 and 1440 px, on two renders of the same page:
 *   served  the homepage exactly as visitors get it (possibly from the LiteSpeed page cache),
 *   origin  the same page with a unique query string, which the page cache does not hold, so it is the
 *           current theme and WordPress data.
 * Judges them with tools/qa/lib/homepage-images.mjs. The origin render must pass. A served page that fails only
 * because the hero/service visuals are missing while the origin renders them is a stale page cache: reported as a
 * warning with the action needed, never fixed from here. Nothing is written to WordPress.
 */
import assert from 'node:assert/strict';
import { mkdirSync, writeFileSync } from 'node:fs';
import { chromium } from '@playwright/test';
import { observeVitals, collectImages, assessImages, formatImageTable, isStalePageCache, inventoryDiff } from './lib/homepage-images.mjs';

const base = 'https://thuexemaynguyentu.com';
const out = 'reports/homepage-images';
mkdirSync(out, { recursive: true });
const targets = { served: `${base}/`, origin: `${base}/?nt-origin=${Date.now()}` };
const proof = { started: new Date().toISOString(), commit: process.env.GITHUB_SHA, engine: 'Chromium viewport emulation; native Safari not tested', runs: [], verdicts: [], errors: [] };
const browser = await chromium.launch();
try {
  for (const [width, height] of [[390, 844], [768, 1024], [1440, 900]]) {
    const result = {};
    for (const [label, url] of Object.entries(targets)) {
      const context = await browser.newContext({ viewport: { width, height }, locale: 'vi-VN', timezoneId: 'Asia/Ho_Chi_Minh' });
      const page = await context.newPage();
      await page.addInitScript(observeVitals);
      const response = await page.goto(url, { waitUntil: 'domcontentloaded' });
      assert.equal(response.status(), 200, `${label} @${width}: HTTP status`);
      await page.waitForLoadState('load');
      const images = await page.evaluate(collectImages);
      const audit = assessImages({ images, vitals: await page.evaluate(() => window.__ntVitals), width, height });
      const cache = response.headers()['x-litespeed-cache'] || '';
      result[label] = { images, audit };
      proof.runs.push({ label, width, height, url: label === 'served' ? url : base + '/?nt-origin=…', litespeedCache: cache, summary: audit.summary, failures: audit.failures, warnings: audit.warnings, images });
      console.log(`${label} @${width} (x-litespeed-cache: ${cache || 'n/a'}): ${JSON.stringify(audit.summary)}\n${formatImageTable(images, width)}`);
      for (const warning of audit.warnings) console.log(`::warning title=Homepage images ${label} @${width}::${warning}`);
      for (const section of ['.nt-home-mag__hero-visual', '.nt-home-mag__visuals']) {
        const handle = page.locator(section).first();
        if (await handle.count()) await handle.screenshot({ path: `${out}/${label}-${width}-${section.replace(/[^a-z]+/gi, '_')}.png` }).catch(() => {});
      }
      await context.close();
    }
    const diff = inventoryDiff(result.served.images, result.origin.images);
    const stale = isStalePageCache(result.served.audit.failures, result.origin.audit.failures);
    proof.verdicts.push({ width, originPasses: result.origin.audit.failures.length === 0, servedPasses: result.served.audit.failures.length === 0, stalePageCache: stale, inventoryDiff: diff });
    console.log(`VERDICT @${width}: origin ${result.origin.audit.failures.length ? 'FAIL' : 'PASS'}, served ${result.served.audit.failures.length ? 'FAIL' : 'PASS'}${stale ? ' (STALE_PAGE_CACHE)' : ''}; only in served: ${JSON.stringify(diff.onlyServed)}; only in origin: ${JSON.stringify(diff.onlyOrigin)}`);
    if (result.origin.audit.failures.length) proof.errors.push(`origin @${width}: ${result.origin.audit.failures.join(' | ')}`);
    if (result.served.audit.failures.length && !stale) proof.errors.push(`served @${width}: ${result.served.audit.failures.join(' | ')}`);
    if (stale) console.log(`::warning title=Homepage page cache is stale @${width}::The served homepage lacks the hero and service images that the origin renders. Purge the LiteSpeed page cache of the homepage (WORDPRESS_ACTION_REQUIRED); the theme code is deployed.`);
  }
} finally {
  await browser.close();
  proof.finished = new Date().toISOString();
  proof.status = proof.errors.length ? 'FAIL' : proof.verdicts.some(v => v.stalePageCache) ? 'PASS_ORIGIN_STALE_PAGE_CACHE' : 'PASS';
  writeFileSync(`${out}/proof.json`, JSON.stringify({ ...proof, runs: proof.runs.map(({ images: _images, ...run }) => run) }, null, 2) + '\n');
  writeFileSync(`${out}/inventory.json`, JSON.stringify(proof.runs.map(({ label, width, images }) => ({ label, width, images })), null, 2) + '\n');
  console.log(JSON.stringify({ status: proof.status, verdicts: proof.verdicts, errors: proof.errors }, null, 2));
}
assert.equal(proof.errors.length, 0, `${proof.errors.length} image audit failure(s): ${proof.errors.join(' || ')}`);
