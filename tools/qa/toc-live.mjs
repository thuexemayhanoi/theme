#!/usr/bin/env node
/**
 * Read-only live check of the post contents list (the collapsible list that follows the first H2 of a post).
 * GET requests only (a few per post, at most about one per second); nothing is written to WordPress.
 *
 *   --mode baseline  before the change is deployed: sample posts, take the text of each as served, run the theme's own
 *                    placement function (tools/qa/toc-placement-cli.php) on it and check the result with an independent
 *                    HTML5 parser. Proves the placement is right on real content and that nothing else in the body changes.
 *   --mode deployed  after the merge: wait until the new template is served, then check sampled posts on two renders,
 *                    the origin (a unique query string, which the page cache does not hold) and the page as served, and in
 *                    Chromium at 390/768/1024/1440 px in light and dark mode. A page cache that still serves the old layout
 *                    is reported (the origin render is what must pass); it is never "fixed" from here.
 */
import { parseArgs } from 'node:util';
import { mkdirSync, mkdtempSync, writeFileSync, appendFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { chromium } from '@playwright/test';
import { analyzePost, checkNewLayout, isOldLayout, hasNoList, sampleEvenly, parseSitemapLocs, rawProse, oracleCheck, contrastRatio } from './lib/toc-structure.mjs';

const { values: opt } = parseArgs({
  options: {
    base: { type: 'string', default: 'https://thuexemaynguyentu.com' },
    mode: { type: 'string', default: 'deployed' },
    sample: { type: 'string', default: '' },
    'browser-sample': { type: 'string', default: '4' },
    'wait-minutes': { type: 'string', default: '12' },
    delay: { type: 'string', default: '700' },
    out: { type: 'string', default: 'reports/toc-live' },
  },
});
if (!['baseline', 'deployed'].includes(opt.mode)) throw new Error('--mode must be baseline or deployed');
const base = opt.base.replace(/\/$/, '');
const delay = Math.max(300, Number(opt.delay) || 700);
const sampleSize = Number(opt.sample) || (opt.mode === 'baseline' ? 150 : 24);
const UA = 'NguyenTu-TocQA/1.0 (read-only; few GET requests)';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
mkdirSync(opt.out, { recursive: true });
const evidence = { startedAt: new Date().toISOString(), base, mode: opt.mode, commit: process.env.GITHUB_SHA ?? null, engine: 'Chromium viewport emulation; native Safari not tested', sampled: 0, posts: [], browser: [], replay: null, problems: [], warnings: [], verdict: '' };
const problem = (msg) => { evidence.problems.push(msg); console.log(`::error title=Post contents list::${msg}`); };
const warn = (msg) => { evidence.warnings.push(msg); console.log(`::warning title=Post contents list::${msg}`); };

async function get(url, tries = 3) {
  let last;
  for (let i = 1; i <= tries; i += 1) {
    try {
      const res = await fetch(url, { headers: { 'user-agent': UA, accept: 'text/html,application/xml;q=0.9,*/*;q=0.5' }, redirect: 'manual', signal: AbortSignal.timeout(30000) });
      const body = await res.text();
      if (res.status === 429 || res.status >= 500) { last = new Error(`HTTP ${res.status}`); await sleep(1500 * i); continue; }
      return { status: res.status, text: body, cache: res.headers.get('x-litespeed-cache') ?? '' };
    } catch (error) {
      last = error;
      await sleep(1500 * i);
    }
  }
  throw last;
}

async function postUrls() {
  const index = await get(`${base}/sitemap_index.xml`);
  if (index.status !== 200) throw new Error(`sitemap index answered ${index.status}`);
  const children = parseSitemapLocs(index.text).filter((u) => /post-sitemap\d*\.xml$/i.test(u));
  if (children.length === 0) throw new Error('the sitemap index lists no post sitemap');
  const urls = [];
  for (const child of children) {
    await sleep(delay);
    const res = await get(child);
    if (res.status !== 200) throw new Error(`${child} answered ${res.status}`);
    urls.push(...parseSitemapLocs(res.text));
  }
  return [...new Set(urls)];
}

const originUrl = (url) => `${url}${url.includes('?') ? '&' : '?'}nt-toc=${Date.now()}`;
const stateOf = (a) => (hasNoList(a) ? 'NO_LIST' : isOldLayout(a) ? 'OLD' : a.toc.placement ? 'NEW' : 'UNKNOWN');

async function runBaseline(urls) {
  const bodies = {};
  const counts = { OLD: 0, NEW: 0, NO_LIST: 0, UNKNOWN: 0, skipped: 0 };
  for (const url of urls) {
    await sleep(delay);
    let res;
    try { res = await get(url); } catch (error) { counts.skipped += 1; warn(`${url}: ${error.message}`); continue; }
    if (res.status !== 200) { counts.skipped += 1; warn(`${url}: HTTP ${res.status}`); continue; }
    const a = analyzePost(res.text);
    counts[stateOf(a)] += 1;
    if (a.embeddedTocMarkers) warn(`${url}: the text carries markup of another contents-list plugin`);
    evidence.posts.push({ url, state: stateOf(a), headings: a.headings.length, list: !hasNoList(a) });
    if (hasNoList(a)) continue;
    const prose = rawProse(res.text);
    if (prose === null) { counts.skipped += 1; warn(`${url}: the text container could not be isolated`); continue; }
    bodies[url] = prose.replace(/<details\b[^>]*nt-toc--inline[\s\S]*?<\/details>/i, '');
  }
  const dir = mkdtempSync(join(tmpdir(), 'toc-bodies-'));
  const file = join(dir, 'bodies.json');
  writeFileSync(file, JSON.stringify(bodies));
  const run = spawnSync('php', ['tools/qa/toc-placement-cli.php', file], { encoding: 'utf8', maxBuffer: 256 * 1024 * 1024 });
  if (run.status !== 0) throw new Error(`placement script failed: ${run.stderr || run.status}`);
  const placed = JSON.parse(run.stdout);
  const byPlacement = { 'after-first-h2': 0, 'after-intro': 0, 'before-content': 0 };
  const mismatches = [];
  for (const [url, result] of Object.entries(placed)) {
    byPlacement[result.placement] += 1;
    const issues = oracleCheck(bodies[url], result.html, result.placement);
    if (issues.length) mismatches.push({ url, placement: result.placement, issues });
  }
  evidence.replay = { servedStates: counts, listPosts: Object.keys(bodies).length, byPlacement, mismatches };
  console.log(`served states ${JSON.stringify(counts)}; placement of ${Object.keys(bodies).length} posts with a list ${JSON.stringify(byPlacement)}; ${mismatches.length} mismatch(es)`);
  for (const m of mismatches) problem(`${m.url}: ${m.placement}: ${m.issues.join('; ')}`);
  const fallbacks = Object.entries(placed).filter(([, r]) => r.placement === 'before-content').map(([u]) => u);
  if (fallbacks.length) console.log(`posts that keep the old place (no safe position): ${fallbacks.slice(0, 15).join(' ')}${fallbacks.length > 15 ? ' …' : ''}`);
  evidence.verdict = mismatches.length ? 'FAIL' : 'BASELINE_OK';
}

async function waitForDeploy(probe) {
  const deadline = Date.now() + Number(opt['wait-minutes']) * 60000;
  for (let n = 1; ; n += 1) {
    const res = await get(originUrl(probe));
    const a = analyzePost(res.text);
    if (res.status === 200 && a.toc.placement) { console.log(`new template served after ${n} check(s)`); return true; }
    if (Date.now() > deadline) return false;
    console.log(`waiting for the new template (check ${n}: ${stateOf(a)})`);
    await sleep(20000);
  }
}

async function runDeployed(urls) {
  const served = [];
  for (const url of urls) {
    await sleep(delay);
    try {
      const res = await get(url);
      if (res.status === 200) served.push({ url, a: analyzePost(res.text), cache: res.cache });
      else warn(`${url}: HTTP ${res.status}`);
    } catch (error) { warn(`${url}: ${error.message}`); }
  }
  const withList = served.filter((s) => !hasNoList(s.a));
  if (withList.length === 0) throw new Error('no sampled post has a contents list');
  if (!(await waitForDeploy(withList[0].url))) {
    evidence.verdict = 'NOT_DEPLOYED';
    problem(`MERGED_NOT_LIVE_VERIFIED: the origin render of ${withList[0].url} still has the old template after ${opt['wait-minutes']} minutes`);
    return;
  }
  const counts = { OLD: 0, NEW: 0, NO_LIST: 0, UNKNOWN: 0 };
  const origins = [];
  for (const s of served) {
    await sleep(delay);
    const res = await get(originUrl(s.url));
    const o = analyzePost(res.text);
    const fails = res.status === 200 ? checkNewLayout(o) : [`origin HTTP ${res.status}`];
    if (JSON.stringify(o.headings) !== JSON.stringify(s.a.headings)) fails.push('the heading list differs between the served page and the origin render');
    if (o.canonical !== s.a.canonical) fails.push(`canonical differs (${s.a.canonical} / ${o.canonical})`);
    if (o.embeddedTocMarkers) warn(`${s.url}: the text carries markup of another contents-list plugin`);
    counts[stateOf(s.a)] += 1;
    evidence.posts.push({ url: s.url, served: stateOf(s.a), servedCache: s.cache, origin: o.toc.placement ?? (hasNoList(o) ? 'NO_LIST' : 'UNKNOWN'), failures: fails });
    for (const f of fails) problem(`${s.url}: ${f}`);
    origins.push({ url: s.url, o });
  }
  evidence.servedStates = counts;
  console.log(`served pages: ${JSON.stringify(counts)}; origin placements: ${JSON.stringify(origins.reduce((m, { o }) => ({ ...m, [o.toc.placement ?? 'none']: (m[o.toc.placement ?? 'none'] ?? 0) + 1 }), {}))}`);
  if (counts.OLD > 0) warn(`${counts.OLD} of ${served.length} sampled posts are still served from an older page cache entry (old layout). Purge the LiteSpeed page cache (WORDPRESS_ACTION_REQUIRED); the origin render passes.`);

  // Chromium on the origin render: one post per placement first, then the rest, at most --browser-sample posts.
  const withOrigin = origins.filter(({ o }) => !hasNoList(o));
  const picked = [];
  for (const label of ['after-first-h2', 'after-intro', 'before-content']) {
    const hit = withOrigin.find(({ o }) => o.toc.placement === label);
    if (hit) picked.push(hit);
  }
  for (const entry of withOrigin) if (picked.length < Number(opt['browser-sample']) && !picked.includes(entry)) picked.push(entry);
  const h1Warned = new Set();
  const browser = await chromium.launch();
  try {
    for (const { url } of picked.slice(0, Math.max(1, Number(opt['browser-sample'])))) {
      for (const [width, height] of [[390, 844], [768, 1024], [1024, 768], [1440, 900]]) {
        for (const mode of ['light', 'dark']) {
          const tag = `${new URL(url).pathname} @${width} ${mode}`;
          const ctx = await browser.newContext({ viewport: { width, height }, locale: 'vi-VN', timezoneId: 'Asia/Ho_Chi_Minh' });
          await ctx.addInitScript((m) => localStorage.setItem('nt-color-mode', m), mode);
          const page = await ctx.newPage();
          const errors = [];
          page.on('pageerror', (e) => errors.push(e.message));
          await page.goto(originUrl(url), { waitUntil: 'domcontentloaded' });
          await page.waitForLoadState('load');
          const row = { page: tag, failures: [] };
          const f = (msg) => { row.failures.push(msg); problem(`${tag}: ${msg}`); };
          const info = await page.evaluate(() => {
            const visible = (el) => el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';
            const h1 = document.querySelector('h1');
            return {
              mode: document.documentElement.getAttribute('data-nt-color-mode'),
              visible: [...document.querySelectorAll('.nt-toc')].filter(visible).map((el) => (el.classList.contains('nt-toc--inline') ? 'inline' : 'sticky')),
              scrollW: document.documentElement.scrollWidth,
              clientW: document.documentElement.clientWidth,
              h1: h1 ? { color: getComputedStyle(h1).color, bg: getComputedStyle(document.body).backgroundColor } : null,
            };
          });
          if (info.mode !== mode) f(`colour mode ${info.mode}`);
          if (JSON.stringify(info.visible) !== JSON.stringify([width < 1100 ? 'inline' : 'sticky'])) f(`visible lists ${JSON.stringify(info.visible)}`);
          if (info.scrollW > info.clientW) f(`horizontal overflow ${info.scrollW} > ${info.clientW}`);
          if (info.h1) {
            row.h1Contrast = Number(contrastRatio(info.h1.color, info.h1.bg).toFixed(2));
            const seen = `${new URL(url).pathname}|${mode}`;
            if (row.h1Contrast < 3 && !h1Warned.has(seen)) {
              h1Warned.add(seen);
              warn(`${new URL(url).pathname} (${mode} mode): the H1 is hard to read (contrast ${row.h1Contrast}:1; ${info.h1.color} on ${info.h1.bg}); not caused by the contents list`);
            }
          }
          if (width < 1100 && info.visible[0] === 'inline') {
            const details = page.locator('.nt-prose .nt-toc--inline');
            const summary = details.locator('summary');
            if (await details.evaluate((d) => d.open)) f('the list is open before any click');
            await summary.scrollIntoViewIfNeeded();
            await summary.click();
            if (!(await details.evaluate((d) => d.open))) f('click does not open the list');
            const colours = await details.evaluate((d) => ({ bg: getComputedStyle(d).backgroundColor, summary: getComputedStyle(d.querySelector('summary')).color, link: getComputedStyle(d.querySelector('a')).color }));
            for (const [name, fg] of [['summary', colours.summary], ['link', colours.link]]) {
              const ratio = contrastRatio(fg, colours.bg);
              if (ratio < 4.5) f(`${name} contrast ${ratio.toFixed(2)}`);
            }
            const links = details.locator('a[data-nt-toc-link]');
            const href = await links.last().getAttribute('href');
            await links.last().click();
            await page.waitForTimeout(700);
            const pos = await page.evaluate((id) => ({ hash: location.hash, top: document.getElementById(id)?.getBoundingClientRect().top ?? -1, header: document.getElementById('site-header')?.getBoundingClientRect().bottom ?? 0 }), href.slice(1));
            if (pos.hash !== href || pos.top < pos.header - 2) f(`anchor ${href} lands at ${pos.top} under the header (${pos.header})`);
            await summary.click().catch(() => {});
            if (mode === 'light' && width === 390) await details.screenshot({ path: join(opt.out, `toc-${evidence.browser.length}.png`) }).catch(() => {});
          }
          if (errors.length) f(`page errors: ${errors.join(' | ')}`);
          evidence.browser.push(row);
          await ctx.close();
        }
      }
    }
  } finally {
    await browser.close();
  }
  evidence.verdict = evidence.problems.length ? 'FAIL' : counts.OLD > 0 ? 'PASS_ORIGIN_SERVED_CACHE_OLD' : 'PASS';
}

try {
  const all = await postUrls();
  const urls = sampleEvenly(all, sampleSize);
  evidence.sampled = urls.length;
  evidence.postsInSitemap = all.length;
  console.log(`${all.length} posts in the sitemap, sampling ${urls.length}`);
  if (opt.mode === 'baseline') await runBaseline(urls);
  else await runDeployed(urls);
} catch (error) {
  evidence.verdict = `NOT_TESTED: ${String(error?.message ?? error)}`;
  problem(evidence.verdict);
} finally {
  evidence.finishedAt = new Date().toISOString();
  writeFileSync(join(opt.out, 'evidence.json'), `${JSON.stringify(evidence, null, 2)}\n`);
  const md = [
    `# Post contents list — ${evidence.mode} check`,
    '',
    `Verdict: **${evidence.verdict}** · ${evidence.sampled} of ${evidence.postsInSitemap ?? '?'} posts sampled · ${evidence.problems.length} problem(s), ${evidence.warnings.length} warning(s) · ${evidence.engine}`,
    '',
    ...(evidence.replay ? [`Placement of ${evidence.replay.listPosts} posts with a list: ${JSON.stringify(evidence.replay.byPlacement)}; mismatches: ${evidence.replay.mismatches.length}`, ''] : []),
    ...(evidence.servedStates ? [`Served pages: ${JSON.stringify(evidence.servedStates)}`, ''] : []),
    ...evidence.problems.map((p) => `- ❌ ${p}`),
    ...evidence.warnings.slice(0, 20).map((w) => `- ⚠️ ${w}`),
  ].join('\n');
  writeFileSync(join(opt.out, 'summary.md'), `${md}\n`);
  if (process.env.GITHUB_STEP_SUMMARY) appendFileSync(process.env.GITHUB_STEP_SUMMARY, `${md}\n`);
  console.log(`VERDICT ${evidence.verdict}`);
  process.exitCode = /^(PASS|BASELINE_OK)/.test(evidence.verdict) ? 0 : 1;
}
