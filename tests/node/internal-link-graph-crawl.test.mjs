import { test, describe, before, after } from 'node:test';
import assert from 'node:assert/strict';
import http from 'node:http';
import { spawn } from 'node:child_process';
import { mkdtemp, readFile, rm, writeFile, mkdir } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import {
  parseSitemapEntries, sitemapType, kindOf, discoverSitemaps, parseRobotsRules, robotsAllows, parseRetryAfter,
  backoffDelay, withRetry, followRedirects, stratify, appendResults, loadCheckpoint, prepareCheckpoint, isFinalRecord,
  auditStatus, writeJsonAtomic, RETRYABLE_STATUS,
} from '../../tools/seo/crawl-lib.mjs';
import { localPath } from '../../tools/seo/internal-link-graph-lib.mjs';

const base = 'https://example.test';

describe('sitemap parsing and discovery', () => {
  test('entries carry loc and lastmod, entities and CDATA are decoded, image blocks are ignored', () => {
    const xml = `<?xml version="1.0"?><urlset><url><loc>https://example.test/a/?x=1&amp;y=2</loc><lastmod>2026-10-01T00:00:00+00:00</lastmod>
      <image:image><image:loc>https://example.test/i.jpg</image:loc></image:image></url><url><loc><![CDATA[https://example.test/b/]]></loc></url></urlset>`;
    const { isIndex, entries } = parseSitemapEntries(xml);
    assert.equal(isIndex, false);
    assert.deepEqual(entries, [{ loc: 'https://example.test/a/?x=1&y=2', lastmod: '2026-10-01T00:00:00+00:00' }, { loc: 'https://example.test/b/', lastmod: '' }]);
    assert.equal(parseSitemapEntries('<sitemapindex><sitemap><loc>https://example.test/post-sitemap.xml</loc></sitemap></sitemapindex>').isIndex, true);
  });

  test('sitemap file names map to content types and kinds (post, page, product, category, taxonomies)', () => {
    assert.deepEqual(sitemapType('https://example.test/post-sitemap2.xml'), { type: 'post', kind: 'content' });
    assert.deepEqual(sitemapType('https://example.test/product-sitemap.xml'), { type: 'product', kind: 'content' });
    assert.deepEqual(sitemapType('https://example.test/category-sitemap.xml'), { type: 'category', kind: 'taxonomy' });
    assert.deepEqual(sitemapType('https://example.test/featured_item_tag-sitemap.xml'), { type: 'featured_item_tag', kind: 'taxonomy' });
    assert.deepEqual(sitemapType('https://example.test/product_cat-sitemap.xml'), { type: 'product_cat', kind: 'taxonomy' });
    assert.equal(kindOf('author'), 'archive');
    assert.equal(kindOf('featured_item'), 'content');
    assert.equal(sitemapType('https://example.test/sitemap.xml.gz').type, 'sitemap');
  });

  const xml = (kind, locs) => kind === 'index'
    ? `<sitemapindex>${locs.map(l => `<sitemap><loc>${l}</loc></sitemap>`).join('')}</sitemapindex>`
    : `<urlset>${locs.map(l => `<url><loc>${l}</loc></url>`).join('')}</urlset>`;

  test('nested indexes are walked, duplicates and off-site URLs are ignored, failures are reported', async () => {
    const files = {
      [`${base}/sitemap_index.xml`]: xml('index', [`${base}/post-sitemap.xml`, `${base}/nested-sitemap.xml`, `${base}/missing-sitemap.xml`, 'https://other.test/x-sitemap.xml']),
      [`${base}/post-sitemap.xml`]: xml('set', [`${base}/a/`, `${base}/b`, `https://other.test/z/`, `${base}/logo.png`]),
      [`${base}/nested-sitemap.xml`]: xml('index', [`${base}/page-sitemap.xml`]),
      [`${base}/page-sitemap.xml`]: xml('set', [`${base}/b/`, `${base}/c/`]),
    };
    const fetched = [];
    const result = await discoverSitemaps({
      base, roots: [`${base}/sitemap_index.xml`], toPath: localPath,
      fetchSitemap: async url => { fetched.push(url); return files[url] ? { status: 200, body: files[url] } : { status: 404, body: '' }; },
    });
    assert.equal(result.ok, true);
    assert.deepEqual(result.urls.map(u => u.path).sort(), ['/a/', '/b/', '/c/']);
    assert.equal(result.duplicates, 1, 'b listed twice');
    assert.deepEqual(result.urls.find(u => u.path === '/c/'), { path: '/c/', url: `${base}/c/`, type: 'page', kind: 'content', sitemap: `${base}/page-sitemap.xml`, lastmod: '' });
    assert.deepEqual(result.failures, [{ url: `${base}/missing-sitemap.xml`, status: 404 }]);
    assert.ok(!fetched.some(u => u.startsWith('https://other.test')));
  });

  test('a root that is not a sitemap falls through to the next candidate; depth is bounded', async () => {
    const files = { [`${base}/wp-sitemap.xml`]: xml('set', [`${base}/x/`]), [`${base}/sitemap_index.xml`]: '<html>login</html>' };
    const fallback = await discoverSitemaps({ base, roots: [`${base}/sitemap_index.xml`, `${base}/wp-sitemap.xml`], toPath: localPath, fetchSitemap: async url => ({ status: 200, body: files[url] ?? '' }) });
    assert.equal(fallback.ok, true);
    assert.equal(fallback.root, `${base}/wp-sitemap.xml`);
    assert.equal(fallback.failures[0].reason, 'not a sitemap');
    const deep = await discoverSitemaps({ base, roots: [`${base}/i0.xml`], maxDepth: 1, toPath: localPath, fetchSitemap: async url => ({ status: 200, body: xml('index', [`${base}/i${Number(url.match(/i(\d)/)[1]) + 1}.xml`]) }) });
    assert.equal(deep.ok, false);
    assert.ok(deep.failures.some(f => /too deep/.test(f.reason)));
    const none = await discoverSitemaps({ base, roots: [`${base}/sitemap_index.xml`], toPath: localPath, fetchSitemap: async () => { throw Error('offline'); } });
    assert.equal(none.ok, false);
    assert.equal(none.failures[0].reason, 'offline');
  });
});

describe('robots.txt rules', () => {
  const rules = parseRobotsRules(`# comment
User-agent: googlebot
Disallow: /private/

User-agent: *
Disallow: /wp-admin/
Allow: /wp-admin/admin-ajax.php
Disallow: /*?replytocom=
Disallow: /tmp$
Disallow:
Sitemap: https://example.test/sitemap_index.xml`);

  test('longest match wins, Allow wins a tie, wildcards and end anchors work', () => {
    assert.equal(robotsAllows(rules, '/wp-admin/options.php'), false);
    assert.equal(robotsAllows(rules, '/wp-admin/admin-ajax.php'), true);
    assert.equal(robotsAllows(rules, '/post/?replytocom=3'), false);
    assert.equal(robotsAllows(rules, '/tmp'), false);
    assert.equal(robotsAllows(rules, '/tmp/file'), true, '$ anchors the end');
    assert.equal(robotsAllows(rules, '/blog/'), true);
  });

  test('a named group applies to that crawler only; unknown agents use the wildcard group', () => {
    assert.equal(robotsAllows(rules, '/private/x', 'Googlebot/2.1'), false);
    assert.equal(robotsAllows(rules, '/private/x', '*'), true);
    assert.equal(robotsAllows([], '/anything'), true);
    assert.equal(parseRobotsRules('Disallow: /orphan-rule/').length, 0, 'rules before any user-agent are ignored');
  });
});

describe('retry and backoff', () => {
  test('Retry-After is read as seconds or HTTP date; backoff doubles, jitters up to 25% and is capped', () => {
    assert.equal(parseRetryAfter('3'), 3000);
    assert.equal(parseRetryAfter('Wed, 21 Oct 2026 07:28:05 GMT', Date.parse('Wed, 21 Oct 2026 07:28:00 GMT')), 5000);
    assert.equal(parseRetryAfter(''), 0);
    assert.equal(parseRetryAfter('nonsense'), 0);
    assert.equal(backoffDelay(0, { baseMs: 1000, random: () => 0 }), 1000);
    assert.equal(backoffDelay(2, { baseMs: 1000, random: () => 0 }), 4000);
    assert.equal(backoffDelay(1, { baseMs: 1000, random: () => 1 }), 2500);
    assert.equal(backoffDelay(10, { baseMs: 1000, maxMs: 30000, random: () => 0 }), 30000);
    assert.equal(backoffDelay(0, { baseMs: 1000, retryAfterMs: 8000, random: () => 0 }), 8000, 'the server asked for longer');
    assert.equal(backoffDelay(0, { baseMs: 1000, retryAfterMs: 999999 }), 60000, 'never above 60 s');
    assert.deepEqual([...RETRYABLE_STATUS].sort(), [408, 425, 429, 500, 502, 503, 504]);
  });

  test('retries transient statuses until success and reports the attempts', async () => {
    const sleeps = [];
    let calls = 0;
    const result = await withRetry(async () => (++calls < 3 ? { status: 503, retryAfterMs: calls === 1 ? 7000 : 0 } : { status: 200, body: 'ok' }), { retries: 3, baseMs: 100, random: () => 0, sleep: async ms => { sleeps.push(ms); } });
    assert.deepEqual({ status: result.status, attempts: result.attempts, exhausted: result.exhausted }, { status: 200, attempts: 3, exhausted: false });
    assert.deepEqual(sleeps, [7000, 200], 'first wait follows Retry-After, second is exponential');
  });

  test('a persistent 503 or a network error ends as exhausted, never as a finding; 404 and redirects are final', async () => {
    const noSleep = async () => {};
    const stuck = await withRetry(async () => ({ status: 503 }), { retries: 2, sleep: noSleep });
    assert.deepEqual({ status: stuck.status, attempts: stuck.attempts, exhausted: stuck.exhausted }, { status: 503, attempts: 3, exhausted: true });
    const down = await withRetry(async () => { throw Error('ECONNRESET'); }, { retries: 1, sleep: noSleep });
    assert.deepEqual({ status: down.status, error: down.error, attempts: down.attempts, exhausted: down.exhausted }, { status: 0, error: 'ECONNRESET', attempts: 2, exhausted: true });
    for (const status of [200, 301, 404, 410]) {
      const final = await withRetry(async () => ({ status }), { retries: 3, sleep: noSleep });
      assert.deepEqual({ attempts: final.attempts, exhausted: final.exhausted }, { attempts: 1, exhausted: false }, String(status));
    }
    const events = [];
    await withRetry(async n => { if (n === 1) throw Error('boom'); return { status: 200 }; }, { retries: 1, baseMs: 10, random: () => 0, sleep: noSleep, onRetry: e => events.push(e) });
    assert.deepEqual(events, [{ attempt: 1, error: 'boom', delayMs: 10 }]);
  });
});

describe('redirect chains', () => {
  const site = {
    [`${base}/b/`]: { status: 301, location: '/c/' },
    [`${base}/c/`]: { status: 301, location: `${base}/d/#frag` },
    [`${base}/d/`]: { status: 200 },
    [`${base}/loop1/`]: { status: 302, location: '/loop2/' },
    [`${base}/loop2/`]: { status: 302, location: '/loop1/' },
    [`${base}/away/`]: { status: 301, location: 'https://elsewhere.test/x/' },
  };
  const count = new Map();
  const fetchHop = async url => { count.set(url, (count.get(url) ?? 0) + 1); return site[url] ?? { status: 404 }; };

  test('follows hop by hop, strips fragments and requests a shared destination once', async () => {
    const cache = new Map();
    const first = await followRedirects(fetchHop, `${base}/b/`, { base, cache, visited: [`${base}/a/`] });
    assert.deepEqual(first.chain.map(h => h.status), [301, 301, 200]);
    assert.equal(first.final.status, 200);
    assert.deepEqual([first.loop, first.offsite, first.tooMany], [false, false, false]);
    await followRedirects(fetchHop, `${base}/c/`, { base, cache });
    assert.equal(count.get(`${base}/c/`), 1);
    assert.equal(count.get(`${base}/d/`), 1);
  });

  test('detects loops (including back to the source), off-site targets and over-long chains', async () => {
    const loop = await followRedirects(fetchHop, `${base}/loop2/`, { base, visited: [`${base}/loop1/`] });
    assert.equal(loop.loop, true);
    assert.equal((await followRedirects(fetchHop, `${base}/away/`, { base })).offsite, true);
    const long = await followRedirects(async url => ({ status: 301, location: `/n${Number(url.match(/n(\d+)/)?.[1] ?? 0) + 1}/` }), `${base}/n0/`, { base, maxHops: 3 });
    assert.equal(long.tooMany, true);
    assert.equal(long.chain.length, 4);
    const failed = await followRedirects(async () => ({ status: 0, error: 'timeout', exhausted: true }), `${base}/x/`, { base });
    assert.equal(failed.final.error, 'timeout');
  });
});

describe('batching order, checkpoints and status', () => {
  test('stratify interleaves types so a partial run still samples every type', () => {
    const items = ['post', 'post', 'post', 'page', 'page', 'product'].map((type, n) => ({ type, n }));
    assert.deepEqual(stratify(items).map(i => i.type), ['post', 'page', 'product', 'post', 'page', 'post']);
    assert.deepEqual(stratify([]), []);
    assert.equal(stratify(items).length, items.length);
  });

  test('checkpoint: later lines win, a truncated last line is ignored, errors are re-queued, writes are atomic', async () => {
    const dir = await mkdtemp(join(tmpdir(), 'nt-checkpoint-'));
    try {
      await prepareCheckpoint(dir, { fresh: true });
      await appendResults(dir, [{ path: '/a/', status: 0, error: 'timeout' }, { path: '/b/', status: 200 }]);
      await appendResults(dir, [{ path: '/a/', status: 200 }]);
      await appendResults(dir, []);
      await writeFile(join(dir, 'results.jsonl'), (await readFile(join(dir, 'results.jsonl'), 'utf8')) + '{"path":"/c/","sta');
      await writeJsonAtomic(join(dir, 'meta.json'), { base, runs: [] });
      const { meta, results } = await loadCheckpoint(dir);
      assert.equal(meta.base, base);
      assert.deepEqual([...results.keys()].sort(), ['/a/', '/b/']);
      assert.equal(results.get('/a/').status, 200);
      assert.equal(isFinalRecord(results.get('/a/')), true);
      assert.equal(isFinalRecord({ path: '/x/', status: 0, error: 'timeout' }), false);
      assert.equal(isFinalRecord({ path: '/x/', status: 404 }), true);
      assert.equal(isFinalRecord(undefined), false);
      await prepareCheckpoint(dir, { fresh: false });
      assert.equal((await loadCheckpoint(dir)).results.size, 2, 'resume keeps results');
      await prepareCheckpoint(dir, { fresh: true });
      assert.equal((await loadCheckpoint(dir)).results.size, 0, 'a fresh run starts empty');
      assert.deepEqual(await loadCheckpoint(join(dir, 'missing')), { meta: null, results: new Map() });
    } finally { await rm(dir, { recursive: true, force: true }); }
  });

  test('COMPLETE, PARTIAL and FAILED depend only on how many URLs were really checked', () => {
    const ok = { sitemapOk: true, sitemapFailures: 0 };
    assert.equal(auditStatus({ ...ok, discovered: 803, checked: 803 }), 'COMPLETE');
    assert.equal(auditStatus({ ...ok, discovered: 803, checked: 802 }), 'PARTIAL');
    assert.equal(auditStatus({ ...ok, discovered: 803, checked: 803, sitemapFailures: 1 }), 'PARTIAL');
    assert.equal(auditStatus({ ...ok, discovered: 803, checked: 0 }), 'FAILED');
    assert.equal(auditStatus({ ...ok, discovered: 0, checked: 0 }), 'FAILED');
    assert.equal(auditStatus({ sitemapOk: false, sitemapFailures: 2, discovered: 5, checked: 5 }), 'FAILED');
  });
});

// ---- the command line tool against a local simulated site ----
const cli = new URL('../../tools/seo/internal-link-graph.mjs', import.meta.url).pathname;
const run = (args, cwd) => new Promise(resolve => {
  const child = spawn(process.execPath, [cli, ...args], { cwd });
  let stdout = '';
  let stderr = '';
  child.stdout.on('data', d => { stdout += d; });
  child.stderr.on('data', d => { stderr += d; });
  child.on('close', code => resolve({ code, stdout, stderr }));
});
const parseCsv = text => {
  const rows = [];
  let row = [];
  let cell = '';
  let quoted = false;
  for (let i = 0; i < text.length; i++) {
    const c = text[i];
    if (quoted) {
      if (c === '"' && text[i + 1] === '"') { cell += '"'; i++; } else if (c === '"') quoted = false; else cell += c;
    } else if (c === '"') quoted = true;
    else if (c === ',') { row.push(cell); cell = ''; } else if (c === '\n') { row.push(cell); rows.push(row); row = []; cell = ''; } else cell += c;
  }
  const [head, ...body] = rows;
  return body.map(r => Object.fromEntries(head.map((h, i) => [h, r[i]])));
};

function simulatedSite() {
  const state = { hits: {}, down: true, flaky: 0, sitemapUp: true, origin: '' };
  const page = ({ title = 'Trang', h1 = ['Tiêu đề'], canonical, robots = '', links = [] }) => `<!doctype html><html><head><title>${title}</title>${canonical ? `<link rel="canonical" href="${canonical}">` : ''}${robots ? `<meta name="robots" content="${robots}">` : ''}</head><body><header><nav><a href="/">Trang chủ</a></nav></header><main>${h1.map(x => `<h1>${x}</h1>`).join('')}<article class="entry-content">${links.map(l => `<a href="${l}">liên kết ${l}</a>`).join(' ')}</article></main><footer></footer></body></html>`;
  const defs = {
    '/': { links: ['/a/', '/about/', '/blog/cat/'] },
    '/a/': { links: ['/b/', '/moved/', '/chain1/'] },
    '/b/': { links: ['/a/'] },
    '/orphan/': {},
    '/flaky/': { links: ['/a/'] },
    '/two-h1/': { h1: ['Một', 'Hai'] },
    '/canon-other/': { canonical: '/a/' },
    '/dup1/': { canonical: '/dup-target/' },
    '/dup2/': { canonical: '/dup-target/' },
    '/dup-target/': {},
    '/hdr-noindex/': { headers: { 'x-robots-tag': 'noindex' } },
    '/no-canon/': { canonical: null },
    '/blocked/': {},
    '/about/': { links: ['/a/', '/dup-target/'] },
    '/blog/cat/': { links: ['/a/', '/b/'] },
    '/tag/x/': { robots: 'noindex, follow' },
    '/tag/y/': {},
    '/always503/': {},
  };
  const sitemap = urls => `<urlset>${urls.map(u => `<url><loc>${state.origin}${u}</loc></url>`).join('')}</urlset>`;
  const files = {
    '/post-sitemap.xml': ['/a/', '/b/', '/orphan/', '/moved/', '/chain1/', '/gone/', '/flaky/', '/always503/', '/two-h1/', '/canon-other/', '/dup1/', '/dup2/', '/hdr-noindex/', '/no-canon/', '/blocked/'],
    '/page-sitemap.xml': ['/about/', '/dup-target/'],
    '/category-sitemap.xml': ['/blog/cat/'],
    '/post_tag-sitemap.xml': ['/tag/x/', '/tag/y/'],
  };
  const server = http.createServer((req, res) => {
    const path = new URL(req.url, 'http://x').pathname;
    state.hits[path] = (state.hits[path] ?? 0) + 1;
    const send = (status, body = '', headers = {}) => { res.writeHead(status, { 'content-type': 'text/html; charset=utf-8', ...headers }); res.end(body); };
    if (path === '/robots.txt') return send(200, `User-agent: *\nDisallow: /blocked/\nSitemap: ${state.origin}/sitemap_index.xml\n`, { 'content-type': 'text/plain' });
    if (path === '/sitemap_index.xml' || path === '/wp-sitemap.xml') {
      return state.sitemapUp && path === '/sitemap_index.xml'
        ? send(200, `<sitemapindex>${Object.keys(files).map(f => `<sitemap><loc>${state.origin}${f}</loc></sitemap>`).join('')}</sitemapindex>`, { 'content-type': 'application/xml' })
        : send(404, 'missing');
    }
    if (files[path]) return send(200, sitemap(files[path]), { 'content-type': 'application/xml' });
    const redirect = { '/moved/': '/b/', '/chain1/': '/chain2/', '/chain2/': '/chain3/', '/chain3/': '/b/' }[path];
    if (redirect) return send(301, '', { location: redirect });
    if (path === '/gone/') return send(404, 'gone');
    if (path === '/flaky/' && ++state.flaky <= 2) return send(503, 'busy', { 'retry-after': '0' });
    if (path === '/always503/' && state.down) return send(503, 'down');
    const def = defs[path];
    if (!def) return send(404, 'not found');
    const canonical = def.canonical === null ? '' : state.origin + (def.canonical ?? path);
    return send(200, page({ ...def, canonical }), def.headers ?? {});
  });
  return {
    state,
    start: () => new Promise(resolve => server.listen(0, '127.0.0.1', () => { state.origin = `http://127.0.0.1:${server.address().port}`; resolve(state.origin); })),
    stop: () => new Promise(resolve => server.close(resolve)),
    reset: () => { state.hits = {}; },
  };
}

describe('command line audit against a simulated site', { concurrency: false }, () => {
  const site = simulatedSite();
  let root;
  let origin;
  const common = ['--concurrency', '3', '--delay', '300', '--backoff', '50', '--retries', '2', '--batch-pause', '0'];
  const read = async (out, file) => JSON.parse(await readFile(join(out, file), 'utf8'));
  const classes = async out => Object.fromEntries(parseCsv(await readFile(join(out, 'orphans.csv'), 'utf8')).map(r => [r.path, r.classification]));

  before(async () => { origin = await site.start(); root = await mkdtemp(join(tmpdir(), 'nt-audit-')); });
  after(async () => { await site.stop(); await rm(root, { recursive: true, force: true }); });

  test('first run: retries a flaky page, leaves a persistent 503 unchecked, reports findings and only candidate orphans', async () => {
    const out = join(root, 'run1');
    const result = await run(['--base', origin, '--out', out, ...common], root);
    assert.equal(result.code, 0, result.stderr + result.stdout.slice(-600));
    const summary = await read(out, 'summary.json');
    assert.deepEqual([summary.status, summary.urlsDiscovered, summary.urlsChecked, summary.urlsErrored, summary.urlsPending], ['PARTIAL', 21, 20, 1, 0]);
    assert.equal(summary.sitemapFiles, 4);
    assert.equal(site.state.hits['/flaky/'], 3, 'two 503 answers retried, third attempt succeeded');
    assert.equal(site.state.hits['/always503/'], 3, 'one attempt plus two retries, then left unchecked');
    for (const path of ['/a/', '/orphan/', '/dup1/', '/tag/y/', '/gone/']) assert.equal(site.state.hits[path], 1, `${path} requested once: ${JSON.stringify(site.state.hits)}`);
    assert.ok(site.state.hits['/b/'] <= 2, 'own request plus one shared redirect hop');
    assert.ok(site.state.hits['/chain2/'] === 1 && site.state.hits['/chain3/'] === 1, 'chain hops requested once');
    const urls = Object.fromEntries(parseCsv(await readFile(join(out, 'urls.csv'), 'utf8')).map(r => [r.path, r]));
    assert.equal(urls['/flaky/'].attempts, '3');
    assert.equal(urls['/chain1/'].hops, '3');
    assert.equal(urls['/moved/'].finalStatus, '200');
    assert.equal(urls['/two-h1/'].h1Count, '2');
    const findings = summary.findings;
    for (const code of ['FETCH_ERROR', 'HTTP_4XX', 'REDIRECT_IN_SITEMAP', 'REDIRECT_CHAIN', 'H1_MULTIPLE', 'CANONICAL_NOT_SELF', 'CANONICAL_DUPLICATE_TARGET', 'CANONICAL_MISSING', 'NOINDEX_IN_SITEMAP', 'ROBOTS_TXT_BLOCKED_IN_SITEMAP']) assert.ok(findings[code] >= 1, code);
    assert.equal(findings.NOINDEX_IN_SITEMAP, 2, 'header noindex and meta noindex');
    const errors = parseCsv(await readFile(join(out, 'errors.csv'), 'utf8'));
    assert.deepEqual(errors.map(e => e.path).sort(), ['/always503/', '/gone/']);
    const orphan = await classes(out);
    assert.equal(orphan['/orphan/'], 'ORPHAN_CANDIDATE', 'a partial audit never confirms an orphan');
    assert.equal(orphan['/tag/y/'], 'ORPHAN_CANDIDATE', 'an indexable taxonomy archive without links is an orphan candidate');
    assert.deepEqual(['/tag/x/', '/hdr-noindex/', '/blocked/', '/moved/', '/gone/', '/always503/', '/canon-other/'].map(p => orphan[p]),
      ['NOT_APPLICABLE_TAXONOMY_NOINDEX', 'NOT_APPLICABLE_NOINDEX', 'NOT_APPLICABLE_ROBOTS_BLOCKED', 'NOT_APPLICABLE_REDIRECT', 'NOT_APPLICABLE_HTTP_ERROR', 'NOT_CHECKED', 'NOT_APPLICABLE_NON_SELF_CANONICAL']);
    assert.deepEqual(Object.entries(orphan).filter(([, c]) => c === 'ORPHAN_CANDIDATE').map(([p]) => p).sort(), ['/flaky/', '/orphan/', '/tag/y/', '/two-h1/'], 'zero inbound, indexable and self-canonical');
    assert.equal(summary.orphanConfirmed, 0);
    assert.match(result.stdout, /^--- Orphans and navigation-only pages[^\n]*---$/m, 'the pages that need a decision are in the job log, not only in the CSV artifacts');
    assert.match(result.stdout, /^ORPHAN_CANDIDATE\t[^\t]+\t0\t0\t\/orphan\/$/m);
    assert.match(result.stdout, /^--- Findings[^\n]*---$/m);
    assert.match(result.stdout, /^error\tFETCH_ERROR\t\/always503\/\t/m);
    assert.doesNotMatch(result.stdout, /^ORPHAN_CANDIDATE\t[^\t]+\t[^\t]+\t[^\t]+\t\/a\/$/m, 'a linked page is not listed');
    const groups = parseCsv(await readFile(join(out, 'canonical-groups.csv'), 'utf8'));
    assert.deepEqual(groups.map(g => [g.target, g.count, g.targetInSitemap]), [['/a/', '2', 'true'], ['/dup-target/', '3', 'true']], '/canon-other/ folds into /a/; /dup1/ and /dup2/ into /dup-target/');
    assert.match(await readFile(join(out, 'report.md'), 'utf8'), /SEO audit — PARTIAL/);
    assert.ok((await readFile(join(out, 'checkpoint', 'results.jsonl'), 'utf8')).split('\n').filter(Boolean).length >= 21);
  });

  test('resume requests only the unchecked URL, then the audit is COMPLETE and orphans are confirmed', async () => {
    const out = join(root, 'run1');
    site.state.down = false;
    site.reset();
    const result = await run(['--base', origin, '--out', out, '--resume', ...common], root);
    assert.equal(result.code, 0, result.stderr);
    assert.deepEqual(Object.keys(site.state.hits).filter(p => !/sitemap|robots/.test(p)), ['/always503/'], 'nothing else is requested again');
    const summary = await read(out, 'summary.json');
    assert.deepEqual([summary.status, summary.urlsChecked, summary.urlsErrored, summary.resumedFromCheckpoint, summary.checkedThisRun], ['COMPLETE', 21, 0, 20, 1]);
    assert.equal(summary.runs.length, 2);
    const orphan = await classes(out);
    assert.equal(orphan['/orphan/'], 'ORPHAN_CONFIRMED');
    assert.equal(orphan['/tag/y/'], 'ORPHAN_CONFIRMED');
    assert.equal(orphan['/tag/x/'], 'NOT_APPLICABLE_TAXONOMY_NOINDEX');
    assert.equal(orphan['/a/'], 'LINKED');
    assert.deepEqual(Object.entries(orphan).filter(([, c]) => c === 'ORPHAN_CONFIRMED').map(([p]) => p).sort(), ['/always503/', '/flaky/', '/orphan/', '/tag/y/', '/two-h1/']);
    assert.equal(summary.orphanConfirmed, 5);
    assert.match(result.stdout, /^ORPHAN_CONFIRMED\t[^\t]+\t0\t0\t\/orphan\/$/m, 'confirmed orphans are listed in the job log');
    assert.match(result.stdout, /^ORPHAN_CONFIRMED\t[^\t]+\t0\t0\t\/tag\/y\/$/m);
    assert.equal(summary.orphanCandidates, 0);
  });

  test('--limit requests a bounded number of URLs per run and --resume continues without repeats', async () => {
    const out = join(root, 'run2');
    site.reset();
    const first = await run(['--base', origin, '--out', out, '--limit', '5', '--batch-size', '2', ...common], root);
    assert.equal(first.code, 0, first.stderr);
    const partial = await read(out, 'summary.json');
    assert.deepEqual([partial.status, partial.urlsChecked, partial.urlsPending, partial.stoppedBy], ['PARTIAL', 5, 16, 'limit']);
    assert.equal(first.stdout.match(/\[batch \d\/3\]/g).length, 3);
    assert.deepEqual(new Set(Object.values(partial.byType)).size > 0, true);
    const hitsAfterFirst = { ...site.state.hits };
    site.reset();
    const second = await run(['--base', origin, '--out', out, '--resume', '--strict', ...common], root);
    assert.equal(second.code, 0, second.stderr);
    const done = await read(out, 'summary.json');
    assert.deepEqual([done.status, done.urlsChecked, done.resumedFromCheckpoint, done.checkedThisRun], ['COMPLETE', 21, 5, 16]);
    const repeated = Object.keys(hitsAfterFirst).filter(p => !/sitemap|robots/.test(p) && site.state.hits[p] > (p === '/b/' ? 1 : 0) && !/^\/(chain|b\/|moved)/.test(p));
    assert.deepEqual(repeated.filter(p => ['/a/', '/orphan/', '/about/', '/tag/y/'].includes(p)), [], 'checked URLs are not requested again');
  });

  test('--max-minutes stops starting new URLs, saves progress and --strict makes PARTIAL a non-zero exit', async () => {
    const out = join(root, 'run3');
    const result = await run(['--base', origin, '--out', out, '--max-minutes', '0.03', '--strict', '--concurrency', '1', '--delay', '300', '--batch-size', '3', '--backoff', '50', '--retries', '1', '--batch-pause', '0'], root);
    assert.equal(result.code, 2, 'strict: PARTIAL exits 2');
    const summary = await read(out, 'summary.json');
    assert.equal(summary.status, 'PARTIAL');
    assert.equal(summary.stoppedBy, 'time budget');
    assert.ok(summary.urlsChecked >= 1 && summary.urlsChecked < 21, `checked ${summary.urlsChecked}`);
    assert.equal(summary.urlsChecked + summary.urlsErrored + summary.urlsPending, 21);
    assert.match(result.stdout, /::warning title=SEO audit PARTIAL/);
  });

  test('--types audits only the chosen sitemap types and never confirms orphans', async () => {
    const out = join(root, 'run4');
    site.state.down = false;
    const result = await run(['--base', origin, '--out', out, '--types', 'category,post_tag', ...common], root);
    assert.equal(result.code, 0, result.stderr);
    const summary = await read(out, 'summary.json');
    assert.deepEqual([summary.urlsDiscovered, summary.urlsChecked, summary.status], [3, 3, 'COMPLETE']);
    assert.match(summary.scope, /category, post_tag/);
    assert.equal((await classes(out))['/tag/y/'], 'ORPHAN_CANDIDATE', 'other types were not crawled, so inbound links are unknown');
    const refused = await run(['--base', origin, '--out', join(root, 'run4b'), '--types', 'nope', ...common], root);
    assert.notEqual(refused.code, 0);
    assert.match(refused.stderr, /matched no sitemap URLs/);
  });

  test('no usable sitemap is FAILED with exit code 1 and no page is requested', async () => {
    const out = join(root, 'run5');
    site.state.sitemapUp = false;
    site.reset();
    const result = await run(['--base', origin, '--out', out, ...common], root);
    site.state.sitemapUp = true;
    assert.equal(result.code, 1);
    const summary = await read(out, 'summary.json');
    assert.deepEqual([summary.status, summary.urlsChecked], ['FAILED', 0]);
    assert.ok(summary.sitemapFailures.length >= 2);
    assert.equal(site.state.hits['/a/'], undefined);
    assert.match(result.stdout, /::error title=SEO audit FAILED/);
  });

  test('safeguards reject unsafe settings and foreign origins; a checkpoint of another site is refused', async () => {
    for (const args of [['--concurrency', '4'], ['--delay', '100'], ['--retries', '9'], ['--timeout', '50000'], ['--limit=-1']]) {
      const result = await run(['--base', origin, '--out', join(root, 'bad'), ...args], root);
      assert.notEqual(result.code, 0, args.join(' '));
      assert.match(result.stderr, /must be/);
    }
    const foreign = await run(['--base', 'https://example.org', '--out', join(root, 'bad')], root);
    assert.notEqual(foreign.code, 0);
    assert.match(foreign.stderr, /approved/);
    const out = join(root, 'other-site');
    await mkdir(join(out, 'checkpoint'), { recursive: true });
    await writeJsonAtomic(join(out, 'checkpoint', 'meta.json'), { schema: 1, base: 'https://thuexemaynguyentu.com', runs: [] });
    const mixed = await run(['--base', origin, '--out', out, '--resume', ...common], root);
    assert.notEqual(mixed.code, 0);
    assert.match(mixed.stderr, /Checkpoint belongs to/);
  });
});
