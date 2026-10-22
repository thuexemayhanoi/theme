import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  localPath, extractInternalEdges, inferSilo, buildGraph, csv, urlKey, noindexFrom, analyzeHtml, detectIssues, canonicalGroups,
  classifyOrphans, countBy, renderReport,
} from '../../tools/seo/internal-link-graph-lib.mjs';

const base = 'https://thuexemaynguyentu.com';
const html = `<!DOCTYPE html><html><body>
<header><a href="/">Home</a><a href="/blog/xe-dien/">Xe điện</a></header>
<main>
<nav class="nt-breadcrumbs" aria-label="Breadcrumb"><a href="/blog/xe-dien/pin-va-sac/">Pin và sạc</a></nav>
<article><div class="entry-content">
<a href="/san-pham/xe-dap-dien/">Thuê xe đạp điện</a>
<a href="/san-pham/xe-dap-dien/#prices">Thuê xe đạp điện</a>
<a href="https://evil.example/steal">Không thuộc site</a>
<a href="/wp-admin/">Admin</a>
<a href="/?s=thue">Tìm kiếm</a>
<a href="/blog/xe-dien/">Xem xe điện</a>
</div></article>
<section class="nt-related"><a href="/kinh-nghiem-chon-xe/">Bài liên quan</a></section>
</main>
<footer><a href="/lien-he/">Liên hệ</a></footer>
</body></html>`;

test('URL normalization excludes external, script/admin/search and asset targets', () => {
  assert.equal(localPath('/blog/xe-dien', base), '/blog/xe-dien/');
  assert.equal(localPath('/blog/xe-dien/?utm_source=x', base), '/blog/xe-dien/');
  assert.equal(localPath('/?s=xe', base), null);
  assert.equal(localPath('https://elsewhere.example/a', base), null);
  assert.equal(localPath('/wp-json/wp/v2/posts', base), null);
  assert.equal(localPath('/uploads/image.webp', base), null);
  assert.equal(localPath('javascript:alert(1)', base), null);
});

test('all body links, correct regions, anchored duplicates deduplicated, old pages untouched', () => {
  const edges = extractInternalEdges(html, '/my-post/', base);
  assert.equal(edges.filter(e => e.target === '/san-pham/xe-dap-dien/').length, 1);
  assert.equal(edges.find(e => e.target === '/san-pham/xe-dap-dien/').region, 'content');
  assert.equal(edges.find(e => e.target === '/blog/xe-dien/pin-va-sac/').region, 'breadcrumb');
  assert.equal(edges.find(e => e.target === '/kinh-nghiem-chon-xe/').region, 'related');
  assert.equal(edges.find(e => e.target === '/lien-he/').region, 'footer');
  assert.ok(!edges.some(e => /evil|wp-admin|\?s=/.test(e.target)));
  assert.equal(inferSilo('/my-post/', edges), 'blog/xe-dien');
  assert.equal(inferSilo('/completely-unclassified/', []), 'unclassified');
});

test('root-level post category chips are used as authoritative silo evidence', () => {
  const page = '<main><article><nav class="nt-breadcrumb" aria-label="Breadcrumb"><a href="/">Home</a></nav>'
    + '<div class="nt-article__chips"><a class="nt-chip" href="/blog/xe-dien/">Xe Điện</a></div>'
    + '<div class="entry-content"><a href="/san-pham/xe-dap-dien/">Xem xe</a></div></article></main>';
  const edges = extractInternalEdges(page, '/bao-nhieu-tuoi-duoc-di-xe-may-dien/', base);
  assert.equal(edges.find(e => e.target === '/blog/xe-dien/').region, 'category');
  assert.equal(inferSilo('/bao-nhieu-tuoi-duoc-di-xe-may-dien/', edges), 'blog/xe-dien');
});

test('graph separates contextual inbound, sitewide inbound, missing inbound and unknown targets', () => {
  const mk = (path, type = 'post') => ({ path, type, status: 200, indexable: true, selfCanonical: true });
  const pages = [mk('/','home'),mk('/blog/xe-dien/','category'),mk('/article-1/'),mk('/article-2/')];
  const links = [
    {source:'/', target:'/blog/xe-dien/', anchor:'Xe điện', region:'header', rel:''},
    {source:'/', target:'/article-1/', anchor:'Bài 1', region:'footer', rel:''},
    {source:'/article-1/', target:'/blog/xe-dien/', anchor:'xe điện', region:'breadcrumb', rel:''},
    {source:'/article-1/', target:'/article-2/', anchor:'đọc thêm', region:'content', rel:''},
    {source:'/article-1/', target:'/article-2/', anchor:'đọc thêm', region:'content', rel:''},
    {source:'/article-1/', target:'/outside/', anchor:'đọc thêm', region:'content', rel:''}
  ];
  const g = buildGraph(pages, links, { complete: true, generatedAt:'test' });
  assert.equal(g.summary.indexableCanonical200, 4);
  assert.equal(g.summary.uniqueInternalEdges, 4);
  assert.equal(g.nodes.find(n => n.path === '/article-2/').inboundContextual, 1);
  assert.equal(g.nodes.find(n => n.path === '/article-1/').inboundContextual, 0);
  assert.equal(g.orphans.length, 0);
  assert.deepEqual(g.unknownTargets, ['/outside/']);
});

test('unknown and noindex pages cannot be counted as live indexable destinations', () => {
  const mk = (path, extra={}) => ({path,type:'post', status:200,indexable:true,selfCanonical:true,...extra});
  const g = buildGraph([mk('/'),mk('/a/'),mk('/b/',{indexable:false})],[
    {source:'/',target:'/a/',region:'footer',anchor:'a',rel:''},
    {source:'/b/',target:'/a/',region:'content',anchor:'a',rel:''}
  ],{complete:false});
  assert.equal(g.summary.indexableCanonical200,2);
  assert.equal(g.nodes.find(n=>n.path==='/a/').inbound,1);
  assert.equal(g.summary.complete,false);
  assert.match(g.summary.caution,/PARTIAL/);
});

test('orphan means zero inbound from all scanned indexable nodes, not just no contextual inbound', () => {
  const mk = path => ({path,type:'post',status:200,indexable:true,selfCanonical:true});
  const g = buildGraph([mk('/'),mk('/no-links/'),mk('/footer-only/')],[
    {source:'/',target:'/footer-only/',region:'footer',anchor:'test',rel:''}
  ],{complete:true});
  assert.deepEqual(g.orphans.map(n=>n.path), ['/no-links/']);
  assert.deepEqual(g.lowContextual.map(n=>n.path), ['/footer-only/']);
});

test('CSV escapes Unicode, commas, quotes, and line breaks', () => {
  assert.match(csv([{title:'Nội dung, "tốt"\nA',path:'/a/'}],['title','path']),/^title,path\n"Nội dung, ""tốt""\nA",\/a\/\n$/);
});

test('URL keys ignore fragments and trailing-slash style but keep scheme, host and query', () => {
  assert.equal(urlKey('https://Example.test/a', base), 'https://example.test/a/');
  assert.equal(urlKey('/a/#top', 'https://example.test/'), 'https://example.test/a/');
  assert.notEqual(urlKey('http://example.test/a/', base), urlKey('https://example.test/a/', base));
  assert.notEqual(urlKey('https://example.test/a/?p=1', base), urlKey('https://example.test/a/', base));
  assert.equal(urlKey('https://example.test/file.pdf', base), 'https://example.test/file.pdf');
});

test('noindex comes from the robots meta or X-Robots-Tag; other crawlers\' header rules are ignored', () => {
  assert.deepEqual(noindexFrom({ robotsMeta: 'index, follow, max-snippet:-1' }), { noindex: false, nofollow: false, sources: [] });
  assert.deepEqual(noindexFrom({ robotsMeta: 'NOINDEX, follow' }), { noindex: true, nofollow: false, sources: ['meta'] });
  assert.deepEqual(noindexFrom({ xRobotsTag: 'noindex, nofollow' }), { noindex: true, nofollow: true, sources: ['header'] });
  assert.equal(noindexFrom({ robotsMeta: 'none' }).noindex, true, '"none" means noindex, nofollow');
  assert.equal(noindexFrom({ xRobotsTag: 'bingbot: noindex' }).noindex, false);
  assert.equal(noindexFrom({ xRobotsTag: 'googlebot: noindex, otherbot: nofollow' }).noindex, true);
  assert.equal(noindexFrom({ xRobotsTag: 'otherbot: noindex, googlebot: nofollow' }).noindex, false);
  assert.equal(noindexFrom({ xRobotsTag: 'unavailable_after: 25 Jun 2030 15:00:00 PST' }).noindex, false);
  assert.deepEqual(noindexFrom({ robotsMeta: 'noindex', xRobotsTag: 'noindex' }).sources, ['meta', 'header']);
});

test('analyzeHtml reads canonical state, H1 count, title, robots meta and internal edges', () => {
  const row = { url: base + '/a/', path: '/a/' };
  const head = extra => `<html><head><title>  Tiêu  đề </title>${extra}</head><body><main><h1>Một</h1><h1>Hai</h1><article class="entry-content"><a href="/b/">B</a></article></main></body></html>`;
  const ok = analyzeHtml(head(`<link rel="canonical" href="${base}/a/"><meta name="robots" content="index, follow">`), row, base);
  assert.deepEqual([ok.title, ok.h1Count, ok.h1, ok.canonicalCount, ok.selfCanonical, ok.canonicalOffsite, ok.canonicalTarget, ok.robotsMeta], ['Tiêu đề', 2, 'Một', 1, true, false, '/a/', 'index, follow']);
  assert.deepEqual(ok.edges.map(e => [e.target, e.region]), [['/b/', 'content']]);
  const other = analyzeHtml(head(`<link rel="canonical" href="${base}/b/">`), row, base);
  assert.deepEqual([other.selfCanonical, other.canonicalTarget], [false, '/b/']);
  const away = analyzeHtml(head('<link rel="canonical" href="https://elsewhere.test/a/">'), row, base);
  assert.deepEqual([away.canonicalOffsite, away.canonicalTarget, away.selfCanonical], [true, '', false]);
  const http = analyzeHtml(head(`<link rel="canonical" href="${base.replace('https:', 'http:')}/a/">`), row, base);
  assert.equal(http.selfCanonical, false, 'scheme mismatch is not self canonical');
  const twice = analyzeHtml(head(`<link rel="canonical" href="${base}/a/"><link rel="canonical" href="${base}/a/">`), row, base);
  assert.deepEqual([twice.canonicalCount, twice.canonical, twice.selfCanonical], [2, '', false]);
  const none = analyzeHtml(head(''), row, base);
  assert.deepEqual([none.canonicalCount, none.selfCanonical], [0, false]);
  assert.equal(analyzeHtml('<html><body></body></html>', row, base).h1Count, 0);
});

const rec = (path, extra = {}) => ({ path, url: base + path, type: 'post', kind: 'content', status: 200, indexable: true, selfCanonical: true, canonicalCount: 1, canonicalTarget: path, canonical: base + path, h1Count: 1, title: 'T', ...extra });

test('findings cover errors, redirects, noindex, robots.txt, canonical, H1 and duplicate canonical targets', () => {
  const records = [
    rec('/ok/'),
    rec('/err/', { status: 0, error: 'timeout', attempts: 4 }),
    rec('/404/', { status: 404 }),
    rec('/503/', { status: 503 }),
    rec('/r1/', { status: 301, finalUrl: base + '/ok/', finalStatus: 200, hops: 1, chain: '301 /r1/ > 200 /ok/' }),
    rec('/r3/', { status: 301, finalUrl: base + '/ok/', finalStatus: 200, hops: 3, chain: 'chain' }),
    rec('/loop/', { status: 302, redirectLoop: true, chain: 'loop', hops: 2 }),
    rec('/dead/', { status: 301, finalStatus: 404, hops: 1, finalUrl: base + '/gone/' }),
    rec('/noidx/', { noindex: true, noindexSources: ['meta', 'header'] }),
    rec('/blocked/', { robotsTxtAllowed: false }),
    rec('/nocanon/', { canonicalCount: 0, canonical: '', selfCanonical: false }),
    rec('/multi/', { canonicalCount: 2, canonicals: ['a', 'b'], selfCanonical: false }),
    rec('/away/', { canonicalOffsite: true, canonical: 'https://elsewhere.test/', selfCanonical: false }),
    rec('/dup1/', { canonicalTarget: '/ok/', canonical: base + '/ok/', selfCanonical: false }),
    rec('/dup2/', { canonicalTarget: '/ok/', canonical: base + '/ok/', selfCanonical: false }),
    rec('/h1/', { h1Count: 0, title: '' }),
    rec('/h2/', { h1Count: 3 }),
    rec('/pdf/', { nonHtml: true, canonicalCount: 0 }),
  ];
  const codes = Object.fromEntries(Object.entries(Object.groupBy(detectIssues(records), i => i.code)).map(([code, list]) => [code, list.map(i => i.path).sort()]));
  assert.deepEqual(codes, {
    FETCH_ERROR: ['/err/'], HTTP_4XX: ['/404/'], HTTP_5XX: ['/503/'],
    REDIRECT_IN_SITEMAP: ['/dead/', '/loop/', '/r1/', '/r3/'], REDIRECT_CHAIN: ['/loop/', '/r3/'], REDIRECT_LOOP: ['/loop/'], REDIRECT_TO_ERROR: ['/dead/'],
    NOINDEX_IN_SITEMAP: ['/noidx/'], ROBOTS_TXT_BLOCKED_IN_SITEMAP: ['/blocked/'],
    CANONICAL_MISSING: ['/nocanon/'], CANONICAL_MULTIPLE: ['/multi/'], CANONICAL_OFFSITE: ['/away/'], CANONICAL_NOT_SELF: ['/dup1/', '/dup2/'],
    CANONICAL_DUPLICATE_TARGET: ['/dup1/', '/dup2/'], H1_MISSING: ['/h1/'], TITLE_MISSING: ['/h1/'], H1_MULTIPLE: ['/h2/'],
  });
  const issues = detectIssues(records);
  assert.equal(issues[0].severity, 'error', 'errors are listed first');
  assert.ok(!issues.some(i => i.path === '/pdf/' || i.path === '/ok/'), 'non-HTML files and healthy pages have no findings');
});

test('canonical groups list the target with every sitemap URL that folds into it', () => {
  const groups = canonicalGroups([
    rec('/target/'), rec('/x/', { canonicalTarget: '/target/', selfCanonical: false }), rec('/y/', { canonicalTarget: '/target/', selfCanonical: false }),
    rec('/solo/'), rec('/z/', { canonicalTarget: '/unlisted/', selfCanonical: false }), rec('/w/', { canonicalTarget: '/unlisted/', selfCanonical: false }),
    rec('/off/', { canonicalOffsite: true, canonicalTarget: '' }), rec('/redir/', { status: 301, canonicalTarget: '/target/' }),
  ]);
  assert.deepEqual(groups, [
    { target: '/target/', members: ['/target/', '/x/', '/y/'], targetInSitemap: true },
    { target: '/unlisted/', members: ['/w/', '/z/'], targetInSitemap: false },
  ]);
});

test('orphans are separated from noindex taxonomies, redirects, errors and non-canonical pages', () => {
  const records = [
    rec('/', { type: 'home' }), rec('/linked/'), rec('/lonely/'), rec('/footer-only/'), rec('/tag/open/', { type: 'post_tag', kind: 'taxonomy' }),
    rec('/tag/x/', { type: 'post_tag', kind: 'taxonomy', noindex: true, noindexSources: ['meta'], indexable: false }),
    rec('/author/a/', { type: 'author', kind: 'archive', noindex: true, noindexSources: ['header'], indexable: false }),
    rec('/hidden/', { noindex: true, noindexSources: ['meta'], indexable: false }),
    rec('/blocked/', { robotsTxtAllowed: false, indexable: false }),
    rec('/moved/', { status: 301, finalUrl: base + '/linked/', indexable: false }),
    rec('/gone/', { status: 410, indexable: false }), rec('/error/', { status: 0, error: 'timeout', indexable: false }),
    rec('/todo/', { status: 0, pending: true, indexable: false }),
    rec('/alt/', { selfCanonical: false, canonical: base + '/linked/' }),
  ];
  const links = [
    { source: '/', target: '/linked/', region: 'main', anchor: 'a', rel: '' },
    { source: '/', target: '/footer-only/', region: 'footer', anchor: 'b', rel: '' },
    { source: '/linked/', target: '/footer-only/', region: 'footer', anchor: 'b', rel: '' },
  ];
  const pages = records;
  const graph = buildGraph(pages, links, { complete: true });
  const of = list => Object.fromEntries(list.map(r => [r.path, r.classification]));
  const expected = {
    '/': 'HOME', '/linked/': 'LINKED', '/lonely/': 'ORPHAN_CONFIRMED', '/footer-only/': 'LOW_CONTEXTUAL', '/tag/open/': 'ORPHAN_CONFIRMED',
    '/tag/x/': 'NOT_APPLICABLE_TAXONOMY_NOINDEX', '/author/a/': 'NOT_APPLICABLE_TAXONOMY_NOINDEX', '/hidden/': 'NOT_APPLICABLE_NOINDEX',
    '/blocked/': 'NOT_APPLICABLE_ROBOTS_BLOCKED', '/moved/': 'NOT_APPLICABLE_REDIRECT', '/gone/': 'NOT_APPLICABLE_HTTP_ERROR',
    '/error/': 'NOT_CHECKED', '/todo/': 'NOT_CHECKED', '/alt/': 'NOT_APPLICABLE_NON_SELF_CANONICAL',
  };
  assert.deepEqual(of(classifyOrphans(records, graph, { complete: true })), expected);
  const partial = of(classifyOrphans(records, buildGraph(pages, links, { complete: false }), { complete: false }));
  assert.equal(partial['/lonely/'], 'ORPHAN_CANDIDATE');
  assert.equal(partial['/tag/open/'], 'ORPHAN_CANDIDATE');
  assert.equal(partial['/footer-only/'], 'LOW_CONTEXTUAL');
});

test('report states the status, the real counts and how to resume a partial audit', () => {
  assert.deepEqual(countBy([{ t: 'b' }, { t: 'a' }, { t: 'b' }], x => x.t), { a: 1, b: 2 });
  const summary = {
    status: 'PARTIAL', base, generatedAt: 'now', urlsDiscovered: 803, urlsChecked: 700, urlsErrored: 3, urlsPending: 100, checkedThisRun: 400,
    sitemapFiles: 9, sitemapFailures: [], scope: 'all sitemap URLs', caution: 'PARTIAL caution', byType: { post: 700 },
  };
  const text = renderReport(summary, { issueCounts: { H1_MISSING: 2 }, orphanCounts: { ORPHAN_CANDIDATE: 4 } });
  assert.match(text, /# SEO audit — PARTIAL/);
  assert.match(text, /\| URLs checked \(definitive answer\) \| 700 \(87\.2%\) \|/);
  assert.match(text, /\| H1_MISSING \| 2 \|/);
  assert.match(text, /--resume/);
  assert.doesNotMatch(renderReport({ ...summary, status: 'COMPLETE' }, { issueCounts: {}, orphanCounts: {} }), /--resume/);
});
