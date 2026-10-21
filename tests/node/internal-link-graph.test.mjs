import { test } from 'node:test';
import assert from 'node:assert/strict';
import { localPath, extractInternalEdges, inferSilo, buildGraph, csv } from '../../tools/seo/internal-link-graph-lib.mjs';

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
