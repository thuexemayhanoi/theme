#!/usr/bin/env node
/**
 * Read-only check of the chat assistant on a running site: the live site, or the disposable CI sandbox.
 *
 * What it does (GET of the home page, POST of non-sensitive questions to the public read-only REST endpoint the
 * assistant itself uses, and a Chromium visit; nothing is written to WordPress, no cookie or form is used):
 *   1. reads the served home page: the six chips, the price table, SEO fingerprint (title, canonical, robots, H1,
 *      JSON-LD), the Quick Call menu and the assistant's CSS/JS files with their SHA-256;
 *   2. asks the questions of Issue #62 (tools/qa/lib/assist-cases.mjs): the six chips, contact and address wording, a
 *      price → month → deposit conversation, a vehicle switch, quote-only models, licence, look-alike wording;
 *   3. opens the chat in Chromium (390 px light and dark with every chip clicked, then 360, 768, 1024 and 1440 px) and
 *      measures it: six chips, 44 px targets, no overlap with the input, send or close button, no horizontal scroll.
 *
 *   --label before     live site before the change is deployed: failed checks are the reproduced bugs, never an error
 *   --label candidate  the pull-request build on the CI sandbox: every check must pass
 *   --label after      live site after the deploy: waits for the new chips, then every check must pass
 *
 * Chromium viewport emulation is not native Safari; this tool says so in its evidence. A page cache that still serves
 * the old page is reported, never fixed from here.
 */
import { parseArgs } from 'node:util';
import { createHash } from 'node:crypto';
import { mkdirSync, writeFileSync, appendFileSync } from 'node:fs';
import { join } from 'node:path';
import { chromium } from '@playwright/test';
import * as cheerio from 'cheerio';
import { CHIPS, NAP, buildCases, judge, parsePriceRows, safeAction } from './lib/assist-cases.mjs';

const { values: opt } = parseArgs({
  options: {
    base: { type: 'string', default: 'https://thuexemaynguyentu.com' },
    label: { type: 'string', default: 'after' },
    out: { type: 'string', default: 'reports/assistant-live' },
    'wait-minutes': { type: 'string', default: '15' },
    delay: { type: 'string', default: '800' },
    'no-browser': { type: 'boolean', default: false },
  },
});
if (!['before', 'candidate', 'after'].includes(opt.label)) throw new Error('--label must be before, candidate or after');
const base = opt.base.replace(/\/$/, '');
const origin = new URL(base).origin;
const delay = Math.max(300, Number(opt.delay) || 800);
const strict = opt.label !== 'before';
const UA = 'NguyenTu-AssistantQA/1.0 (read-only; non-sensitive questions)';
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const sha = (text) => createHash('sha256').update(text).digest('hex');
mkdirSync(join(opt.out, 'screenshots'), { recursive: true });

const evidence = {
  startedAt: new Date().toISOString(), label: opt.label, base, commit: process.env.NT_HEAD_SHA ?? process.env.GITHUB_SHA ?? null, run: process.env.GITHUB_RUN_ID ?? null,
  engine: 'Chromium viewport emulation; native Safari not tested',
  home: {}, rest: [], browser: [], problems: [], notTested: [], verdict: '',
};
const problem = (message) => { evidence.problems.push(message); console.log(`::${strict ? 'error' : 'warning'} title=Assistant ${opt.label}::${message}`); };

async function get(url, tries = 3) {
  let last;
  for (let i = 1; i <= tries; i += 1) {
    try {
      const res = await fetch(url, { headers: { 'user-agent': UA, accept: 'text/html,*/*;q=0.5' }, redirect: 'follow', signal: AbortSignal.timeout(30000) });
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

/** What the served page says about the assistant, SEO and Quick Call; the same view before and after, so they can be diffed. */
async function fingerprint(html) {
  const $ = cheerio.load(html);
  const chips = $('.nt-assist__shortcuts button').map((_, el) => ({ label: $(el).text().trim(), question: $(el).attr('data-nt-chat-suggest') ?? '' })).get();
  const jsonld = $('script[type="application/ld+json"]').map((_, el) => $(el).text().replace(/\s+/g, ' ').trim()).get();
  const assets = {};
  for (const [key, selector, attribute] of [['css', 'link[rel="stylesheet"][href*="assist.css"]', 'href'], ['js', 'script[src*="assist.js"]', 'src']]) {
    const url = $(selector).first().attr(attribute);
    if (!url) { assets[key] = null; continue; }
    const res = await get(new URL(url, `${base}/`).href);
    assets[key] = { url: url.replace(/\?.*$/, ''), version: (url.match(/[?&]ver=([^&]+)/) ?? [])[1] ?? '', bytes: Buffer.byteLength(res.text), sha256: sha(res.text) };
    await sleep(300);
  }
  return {
    chips,
    assets,
    quickCall: {
      present: $('#nt-native-quickcall').length === 1,
      menuSha256: sha($('#nt-qc-menu').html() ?? ''),
      links: $('#nt-qc-menu a').map((_, el) => `${$(el).attr('class')}|${$(el).attr('href')}`).get(),
    },
    assistantPresent: $('#nt-native-assistant').length === 1,
    seo: {
      title: $('title').first().text().trim(),
      canonical: $('link[rel="canonical"]').attr('href') ?? '',
      robots: $('meta[name="robots"]').attr('content') ?? '',
      h1: $('h1').map((_, el) => $(el).text().trim()).get(),
      jsonldBlocks: jsonld.length,
      jsonldSha256: sha(jsonld.join('\n')),
    },
  };
}

const chipsAreSix = (fp) => fp.chips.length === 6 && CHIPS.every((chip, i) => fp.chips[i]?.label === chip.label && fp.chips[i]?.question === chip.question);

async function loadHome() {
  const origin1 = await get(`${base}/?nt-assist=${Date.now()}`);
  const served = await get(`${base}/`);
  return { origin: origin1, served };
}

async function main() {
  let { origin: page, served } = await loadHome();
  if (page.status !== 200) { problem(`home page answered ${page.status}`); throw new Error('home page not reachable'); }
  let fp = await fingerprint(page.text);
  if (opt.label === 'after' && !chipsAreSix(fp)) {
    const until = Date.now() + Math.max(0, Number(opt['wait-minutes']) || 0) * 60000;
    console.log('waiting for the six chips to be served …');
    while (!chipsAreSix(fp) && Date.now() < until) {
      await sleep(30000);
      ({ origin: page, served } = await loadHome());
      if (page.status === 200) fp = await fingerprint(page.text);
    }
  }
  const servedFp = served.status === 200 ? await fingerprint(served.text) : null;
  evidence.home = { status: page.status, cache: page.cache, servedStatus: served.status, servedCache: served.cache, servedChips: servedFp?.chips.length ?? null, fingerprint: fp };
  console.log(`home: ${fp.chips.length} chips [${fp.chips.map((c) => c.label).join(', ')}] · quick call ${fp.quickCall.present ? 'present' : 'ABSENT'} · assist.js ${fp.assets.js?.sha256?.slice(0, 12) ?? 'none'} · assist.css ${fp.assets.css?.sha256?.slice(0, 12) ?? 'none'}`);
  if (!chipsAreSix(fp)) problem(`the page shows ${fp.chips.length} chips, not the six of Issue #62`);
  if (servedFp && servedFp.chips.length !== fp.chips.length) problem(`the cached page shows ${servedFp.chips.length} chips while the origin shows ${fp.chips.length}`);
  if (!fp.quickCall.present) problem('Quick Call is missing from the page');
  if (!fp.quickCall.links.includes('nt-qc__link nt-qc__link--maps|' + NAP.quickCallMaps)) problem('the Quick Call directions link is not the owner-approved share link');
  if (fp.seo.h1.length !== 1) problem(`the home page has ${fp.seo.h1.length} H1`);
  if (/noindex/i.test(fp.seo.robots)) problem('the home page is noindex');

  // ---- REST: the questions of Issue #62 -------------------------------------------------------------------
  const rows = parsePriceRows(page.text);
  evidence.home.priceRows = rows;
  if (rows.length === 0) evidence.notTested.push('price expectations: no price table found on the home page');
  const cases = buildCases(rows);
  for (const testCase of cases) {
    if (testCase.skip) { evidence.rest.push({ id: testCase.id, group: testCase.group, skipped: testCase.skip }); evidence.notTested.push(`${testCase.id}: ${testCase.skip}`); continue; }
    let context;
    const turns = [];
    for (const turn of testCase.turns) {
      let status = 0;
      let json = {};
      try {
        const res = await fetch(`${base}/wp-json/nguyentu/v1/assist`, {
          method: 'POST',
          headers: { 'content-type': 'application/json', 'user-agent': UA },
          body: JSON.stringify(context ? { question: turn.q, context } : { question: turn.q }),
          signal: AbortSignal.timeout(30000),
        });
        status = res.status;
        json = await res.json().catch(() => ({}));
      } catch (error) { json = { answer: '', error: String(error.message ?? error) }; }
      const fails = status === 200 ? judge(json, turn.expect, origin) : [`HTTP ${status}`];
      context = json.context && typeof json.context === 'object' ? json.context : context;
      turns.push({ q: turn.q, status, intent: json.intent ?? null, answer: json.answer ?? '', sources: (json.sources ?? []).map((s) => ({ title: s.title, path: (() => { try { return new URL(s.url).pathname; } catch { return s.url; } })() })), actions: (json.actions ?? []).map((a) => `${a.type} ${a.url}`), context: json.context ?? null, fails });
      console.log(`${fails.length === 0 ? 'PASS' : 'FAIL'} ${testCase.id} «${turn.q}» → ${json.intent ?? '?'} | ${String(json.answer ?? '').slice(0, 110)}${fails.length ? ' | ' + fails.join('; ') : ''}`);
      await sleep(delay);
    }
    const failed = turns.filter((t) => t.fails.length > 0);
    evidence.rest.push({ id: testCase.id, group: testCase.group, pass: failed.length === 0, turns });
    for (const t of failed) problem(`${testCase.id} «${t.q}»: ${t.fails.join('; ')}`);
  }

  // ---- Browser: layout, targets, the six chips, buttons --------------------------------------------------
  if (!opt['no-browser']) {
    const browser = await chromium.launch();
    try {
      const plan = [[390, 844, 'light', true], [390, 844, 'dark', true], [360, 800, 'light', false], [768, 1024, 'light', false], [1024, 768, 'light', false], [1440, 900, 'light', false], [1440, 900, 'dark', false]];
      for (const [width, height, mode, flows] of plan) evidence.browser.push(await browserCheck(browser, width, height, mode, flows));
    } finally { await browser.close(); }
  }

  const restFailed = evidence.rest.filter((r) => r.pass === false).length;
  const browserFailed = evidence.browser.filter((b) => b.fails.length > 0).length;
  evidence.counts = { restCases: evidence.rest.length, restPassed: evidence.rest.filter((r) => r.pass).length, restFailed, restNotTested: evidence.rest.filter((r) => r.skipped).length, browserRuns: evidence.browser.length, browserFailed };
  evidence.verdict = evidence.problems.length === 0 ? 'PASS' : strict ? 'FAIL' : 'REPRODUCED (informational: the change is not deployed yet)';
}

async function browserCheck(browser, width, height, mode, flows) {
  const tag = `${width}x${height}-${mode}`;
  const result = { tag, width, height, mode, fails: [], chips: [], answers: [] };
  const context = await browser.newContext({ viewport: { width, height }, deviceScaleFactor: 2, isMobile: width < 768, hasTouch: width < 768 });
  await context.addInitScript((m) => { try { localStorage.setItem('nt-color-mode', m); localStorage.removeItem('nt-assist-topic-v2'); localStorage.removeItem('nt-assist-sources-v2'); } catch { /* storage may be blocked */ } }, mode);
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  const fail = (message) => { result.fails.push(message); problem(`${tag}: ${message}`); };
  try {
    await page.goto(`${base}/?nt-assist=${Date.now()}`, { waitUntil: 'networkidle', timeout: 60000 });
    // Overflow the page already has is not the chat's doing; only what the chat adds is checked.
    const overflowBefore = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    if (overflowBefore > 0) result.notes = [`the page already scrolls horizontally by ${overflowBefore}px before the chat opens`];
    await page.locator('.nt-qc__launch').click({ timeout: 15000 });
    await page.locator('[data-nt-chat-open]').click({ timeout: 15000 });
    await page.locator('.nt-assist__panel').waitFor({ state: 'visible', timeout: 15000 });
    await page.waitForTimeout(150);
    const m = await page.evaluate(() => {
      const q = (s) => document.querySelector(s);
      const rect = (el) => { const b = el.getBoundingClientRect(); return { x: b.x, y: b.y, r: b.right, b: b.bottom, w: b.width, h: b.height }; };
      const inter = (a, b) => Math.max(0, Math.min(a.r, b.r) - Math.max(a.x, b.x)) * Math.max(0, Math.min(a.b, b.b) - Math.max(a.y, b.y));
      const panel = rect(q('.nt-assist__panel'));
      const chips = [...document.querySelectorAll('.nt-assist__shortcuts button')].map((el) => ({ label: el.textContent.trim(), r: rect(el), clipped: el.scrollWidth > el.clientWidth + 1 }));
      const named = { input: rect(q('.nt-assist__form input')), send: rect(q('.nt-assist__form button')), close: rect(q('.nt-assist__close')), clear: rect(q('[data-nt-chat-clear]')) };
      const overlaps = [];
      for (const c of chips) for (const [name, r] of Object.entries(named)) if (inter(c.r, r) > 2) overlaps.push(`${c.label} x ${name}`);
      const vw = document.documentElement.clientWidth;
      return { vw, vh: window.innerHeight, docOverflow: document.documentElement.scrollWidth - vw, panel, panelInside: panel.x >= -0.5 && panel.r <= vw + 0.5 && panel.y >= -0.5 && panel.b <= window.innerHeight + 0.5, chips, named, overlaps, quickCallHidden: getComputedStyle(q('.nt-qc')).visibility === 'hidden' };
    });
    result.chips = m.chips.map((c) => ({ label: c.label, w: Math.round(c.r.w), h: Math.round(c.r.h) }));
    if (m.chips.length !== 6) fail(`${m.chips.length} chips instead of 6`);
    if (m.docOverflow > overflowBefore) fail(`the open chat adds horizontal scroll (${overflowBefore}px before, ${m.docOverflow}px after)`);
    if (!m.panelInside && overflowBefore === 0) fail('the chat panel is not inside the viewport');
    if (m.overlaps.length) fail(`chips overlap other controls: ${m.overlaps.join(', ')}`);
    if (!m.quickCallHidden) fail('the Quick Call launcher stays visible over the open chat');
    for (const c of m.chips) {
      if (c.clipped) fail(`chip "${c.label}" text is clipped`);
      if (height > 520 && (c.r.h < 43.5 || c.r.w < 43.5)) fail(`chip "${c.label}" is ${Math.round(c.r.w)}x${Math.round(c.r.h)}, under 44 px`);
    }
    for (const name of ['input', 'send', 'close']) if (m.named[name].w < 43.5 || m.named[name].h < 43.5) fail(`${name} target is under 44 px`);
    await page.screenshot({ path: join(opt.out, 'screenshots', `${tag}-open.png`) });

    if (flows) {
      for (const chip of CHIPS) {
        const button = page.locator('.nt-assist__shortcuts button', { hasText: chip.label });
        if (await button.count() === 0) { fail(`chip "${chip.label}" is not on the page`); continue; }
        const before = await page.locator('.nt-assist__message--bot').count();
        await button.first().click({ timeout: 5000 }).catch((e) => fail(`chip "${chip.label}" could not be clicked: ${String(e.message).split('\n')[0]}`));
        const answered = await page.waitForFunction((n) => document.querySelectorAll('.nt-assist__message--bot').length > n, before, { timeout: 25000 }).then(() => true, () => false);
        if (!answered) { fail(`chip "${chip.label}" got no answer in 25 s`); continue; }
        const got = await page.evaluate(() => {
          const users = [...document.querySelectorAll('.nt-assist__message--user')];
          const bot = [...document.querySelectorAll('.nt-assist__message--bot')].pop();
          return {
            asked: users.length ? users[users.length - 1].textContent : '',
            answer: bot.querySelector('p').textContent,
            ctas: [...bot.querySelectorAll('.nt-assist__cta')].map((a) => ({ type: [...a.classList].find((c) => /--(call|zalo|maps)$/.test(c))?.split('--')[1], text: a.textContent, url: a.href, h: a.getBoundingClientRect().height, target: a.target, rel: a.rel })),
            links: [...bot.querySelectorAll('.nt-assist__sources a')].map((a) => ({ text: a.textContent, href: a.href })),
          };
        });
        result.answers.push({ chip: chip.label, ...got });
        if (got.asked !== chip.question) fail(`chip "${chip.label}" sent "${got.asked}" instead of "${chip.question}"`);
        if (/^Chưa tra cứu được/.test(got.answer)) fail(`chip "${chip.label}" got the offline fallback`);
        for (const cta of got.ctas) {
          if (!safeAction({ type: cta.type, url: cta.url })) fail(`chip "${chip.label}" shows an unsafe button ${cta.url}`);
          if (cta.h < 43.5) fail(`button "${cta.text}" is ${Math.round(cta.h)} px high`);
          if (cta.type !== 'call' && (cta.target !== '_blank' || !/noopener/.test(cta.rel))) fail(`button "${cta.text}" opens without noopener`);
        }
        const types = got.ctas.map((c) => c.type).join(',');
        if (chip.label === 'Liên hệ' && !(got.ctas.some((c) => c.url === NAP.tel) && got.ctas.some((c) => c.url === NAP.zalo))) fail(`the contact answer has buttons [${types}] instead of call ${NAP.tel} and Zalo ${NAP.zalo}`);
        if (chip.label === 'Địa chỉ' && !got.ctas.some((c) => c.url === NAP.maps)) fail(`the address answer has buttons [${types}], not the Maps profile ${NAP.maps}`);
        for (const link of got.links) if (new URL(link.href).origin !== origin) fail(`source link leaves the site: ${link.href}`);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        if (overflow > overflowBefore) fail(`page scrolls horizontally after "${chip.label}"`);
        if (['Liên hệ', 'Địa chỉ'].includes(chip.label)) await page.screenshot({ path: join(opt.out, 'screenshots', `${tag}-${chip.label === 'Liên hệ' ? 'lien-he' : 'dia-chi'}.png`) });
        await sleep(delay);
      }
      await page.screenshot({ path: join(opt.out, 'screenshots', `${tag}-answers.png`) });
    }
  } catch (error) {
    fail(`browser run stopped: ${String(error.message ?? error).split('\n')[0]}`);
  } finally {
    result.pageErrors = errors;
    await context.close();
  }
  console.log(`browser ${tag}: ${result.fails.length === 0 ? 'PASS' : 'FAIL ' + result.fails.length} · chips ${result.chips.map((c) => `${c.w}x${c.h}`).join(' ')}`);
  return result;
}

function summary() {
  const lines = [];
  const c = evidence.counts ?? {};
  lines.push(`## Assistant check — ${opt.label} — ${evidence.verdict}`, '', `- base \`${base}\` · commit \`${evidence.commit ?? 'n/a'}\` · run \`${evidence.run ?? 'local'}\` · ${evidence.startedAt}`, `- ${evidence.engine}`);
  const fp = evidence.home.fingerprint;
  if (fp) {
    lines.push(`- chips on the page: **${fp.chips.length}** (${fp.chips.map((x) => x.label).join(', ')}) · page cache \`${evidence.home.cache || 'n/a'}\``,
      `- assist.js \`${fp.assets.js?.sha256 ?? 'none'}\` (${fp.assets.js?.bytes ?? 0} B, ver ${fp.assets.js?.version ?? ''}) · assist.css \`${fp.assets.css?.sha256 ?? 'none'}\` (${fp.assets.css?.bytes ?? 0} B)`,
      `- Quick Call menu sha256 \`${fp.quickCall.menuSha256}\``,
      `- SEO: title «${fp.seo.title}» · canonical ${fp.seo.canonical} · robots \`${fp.seo.robots || '—'}\` · H1 ×${fp.seo.h1.length} · JSON-LD ×${fp.seo.jsonldBlocks} \`${fp.seo.jsonldSha256.slice(0, 16)}\``);
  }
  lines.push('', `REST questions: ${c.restPassed ?? 0}/${c.restCases ?? 0} cases pass, ${c.restFailed ?? 0} fail, ${c.restNotTested ?? 0} not tested. Browser runs: ${(c.browserRuns ?? 0) - (c.browserFailed ?? 0)}/${c.browserRuns ?? 0} pass.`, '',
    '| case | result | first problem |', '| --- | --- | --- |');
  for (const r of evidence.rest) lines.push(`| ${r.id} | ${r.skipped ? 'NOT_TESTED' : r.pass ? 'PASS' : 'FAIL'} | ${r.skipped ?? r.turns.flatMap((t) => t.fails.map((f) => `«${t.q}» ${f}`))[0] ?? ''} |`);
  for (const b of evidence.browser) lines.push(`| browser ${b.tag} | ${b.fails.length ? 'FAIL' : 'PASS'} | ${b.fails[0] ?? ''} |`);
  if (evidence.notTested.length) lines.push('', 'Not tested: ' + evidence.notTested.join('; '));
  return lines.join('\n');
}

let fatal = null;
try { await main(); } catch (error) { fatal = error; evidence.verdict = 'NOT_TESTED'; evidence.problems.push(`stopped: ${error.message}`); }
evidence.finishedAt = new Date().toISOString();
writeFileSync(join(opt.out, 'evidence.json'), JSON.stringify(evidence, null, 2));
const md = summary();
writeFileSync(join(opt.out, 'summary.md'), md + '\n');
if (process.env.GITHUB_STEP_SUMMARY) appendFileSync(process.env.GITHUB_STEP_SUMMARY, md + '\n');
console.log('\n' + md);
if (fatal) { console.error(fatal); process.exit(1); }
process.exit(strict && evidence.problems.length > 0 ? 1 : 0);
