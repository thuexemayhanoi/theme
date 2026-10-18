#!/usr/bin/env node
/*
 * Browser smoke test for the sandbox: proves that the PHP server serves a headless Chromium correctly
 * (no failed requests, no console errors, no horizontal overflow, no broken images) and writes
 * screenshots at 360 / 768 / 1440 px. It is NOT the theme's test suite, just a harness check you can
 * copy from.
 *
 *   node tools/sandbox/smoke-playwright.cjs
 *
 * Env: BASE_URL (default http://127.0.0.1:$PORT or :8088), OUT_DIR (default $SANDBOX_DIR/screenshots or
 *      /tmp/nt-sandbox-screenshots), SANDBOX_THEME (sends X-Sandbox-Theme), FULL_PAGE=1, PAGES="/,/lien-he/"
 * Playwright is taken from the project's node_modules or from the global install; browsers come from
 * PLAYWRIGHT_BROWSERS_PATH (preinstalled, nothing is downloaded).
 */
'use strict';
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execSync } = require('child_process');

let playwright;
try {
  playwright = require('playwright');
} catch (e) {
  playwright = require(path.join(execSync('npm root -g').toString().trim(), 'playwright'));
}

const base = process.env.BASE_URL || `http://127.0.0.1:${process.env.PORT || 8088}`;
const outDir = process.env.OUT_DIR || (process.env.SANDBOX_DIR ? path.join(process.env.SANDBOX_DIR, 'screenshots') : path.join(os.tmpdir(), 'nt-sandbox-screenshots'));
const pages = (process.env.PAGES ? process.env.PAGES.split(',') : [
  '/', '/blog/', '/blog/xe-dien/', '/gioi-thieu/ve-chung-toi/', '/lien-he/', '/shop/', '/san-pham/honda-wave/',
  '/bo-suu-tap/thue-ngay/', '/featured_item/thue-xe-may-ha-noi-1-ngay/', '/so-sanh-xe-may-dien-va-xe-xang-khi-thue-o-ha-noi/', '/cart/',
]).map((p) => p.trim());
const viewports = [['360', 360, 740], ['768', 768, 1024], ['1440', 1440, 900]];

const slug = (p) => (p === '/' ? 'home' : p.replace(/^\/|\/$/g, '').replace(/[^a-z0-9]+/gi, '-'));

(async () => {
  fs.mkdirSync(outDir, { recursive: true });
  const browser = await playwright.chromium.launch();
  let hardFailures = 0;
  console.log(`base ${base}  screenshots ${outDir}`);
  console.log('vw   status  ms    lang h1 overflow broken  path');
  for (const [vw, width, height] of viewports) {
    const context = await browser.newContext({
      viewport: { width, height },
      extraHTTPHeaders: process.env.SANDBOX_THEME ? { 'X-Sandbox-Theme': process.env.SANDBOX_THEME } : {},
    });
    const page = await context.newPage();
    let problems = [];
    page.on('console', (m) => { if (m.type() === 'error') problems.push(`console.error: ${m.text().slice(0, 160)}`); });
    page.on('pageerror', (e) => problems.push(`pageerror: ${String(e).slice(0, 160)}`));
    page.on('requestfailed', (r) => problems.push(`requestfailed: ${r.url().slice(0, 120)} ${(r.failure() || {}).errorText || ''}`));
    page.on('response', (r) => { if (r.status() >= 400 && r.request().resourceType() !== 'document') problems.push(`HTTP ${r.status()}: ${r.url().slice(0, 120)}`); });
    for (const p of pages) {
      problems = [];
      const t0 = Date.now();
      const response = await page.goto(base + p, { waitUntil: 'networkidle', timeout: 90000 });
      const ms = Date.now() - t0;
      const status = response ? response.status() : 0;
      await page.screenshot({ path: path.join(outDir, `${slug(p)}-${vw}.png`), fullPage: process.env.FULL_PAGE === '1' });
      const info = await page.evaluate(() => ({
        lang: document.documentElement.lang,
        h1: document.querySelectorAll('h1').length,
        overflow: document.documentElement.scrollWidth > window.innerWidth,
        broken: [...document.images].filter((i) => i.complete && i.naturalWidth === 0).length,
      }));
      const bad = status >= 400 || problems.length > 0 || info.overflow || info.broken > 0;
      if (bad) hardFailures += 1;
      console.log(`${vw.padEnd(4)} ${String(status).padEnd(7)} ${String(ms).padEnd(5)} ${info.lang.padEnd(4)} ${String(info.h1).padEnd(2)} ${String(info.overflow).padEnd(8)} ${String(info.broken).padEnd(6)} ${p}${bad ? '   <-- ' + (status >= 400 ? 'status ' + status : '') : ''}`);
      for (const line of problems) console.log(`       ${line}`);
    }
    await context.close();
  }
  await browser.close();
  console.log(hardFailures ? `\n${hardFailures} page/viewport combination(s) with problems` : '\nall pages clean');
  process.exit(hardFailures ? 1 : 0);
})().catch((e) => { console.error('smoke test crashed:', e); process.exit(2); });
