#!/usr/bin/env node
/**
 * Read-only audit of the CURRENT Yoast sitemap graph, not a site-wide recursive spider.
 *
 * Example:
 * node tools/seo/internal-link-graph.mjs --base https://thuexemaynguyentu.com \
 *   --out reports/internal-link-graph --limit 250 --concurrency 2 --delay 500
 *
 * Each sitemap URL is requested once; no admin, cookies, forms or WordPress writes.
 * Results are local JSON and CSV. A partial crawl cannot claim definitive orphans.
 */
import { mkdir, writeFile } from 'node:fs/promises';
import { parseArgs } from 'node:util';
import * as cheerio from 'cheerio';
import { parseSitemap } from './lib.mjs';
import { localPath, extractInternalEdges, buildGraph, csv } from './internal-link-graph-lib.mjs';

const { values: opt } = parseArgs({
  options: {
    base: { type: 'string', default: 'https://thuexemaynguyentu.com' },
    out: { type: 'string', default: 'reports/internal-link-graph' },
    limit: { type: 'string', default: '250' },
    concurrency: { type: 'string', default: '2' },
    delay: { type: 'string', default: '500' },
    timeout: { type: 'string', default: '20000' },
  },
});
const base = new URL(opt.base).origin;
if (base !== 'https://thuexemaynguyentu.com' && !/^(http:\/\/127\.0\.0\.1|http:\/\/localhost)(:\d+)?$/.test(base)) {
  throw Error('Only approved Nguyen Tu production and localhost sandbox origins are allowed');
}
const limit = Number(opt.limit), concurrency = Number(opt.concurrency), delay = Number(opt.delay), timeout = Number(opt.timeout);
if (![limit, concurrency, delay, timeout].every(Number.isInteger) || limit < 1 || limit > 1000 || concurrency < 1 || concurrency > 2 || delay < 300 || timeout < 1000 || timeout > 30000) {
  throw Error('Invalid safeguards: limit 1-1000, concurrency 1-2, delay >=300ms, timeout 1000-30000ms');
}
await mkdir(opt.out, { recursive: true });
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
const ua = 'NguyenTu-LinkGraph/1.0 (read-only sitemap audit)';
async function fetchPage(url) {
  const res = await fetch(url, {
    redirect: 'manual',
    headers: { 'User-Agent': ua, Accept: 'text/html,application/xml,text/xml,*/*' },
    signal: AbortSignal.timeout(timeout),
  });
  return { status: res.status, location: res.headers.get('location') || '', body: res.ok ? await res.text() : '' };
}

const index = await fetchPage(base + '/sitemap_index.xml');
if (index.status !== 200 || !/<sitemapindex/i.test(index.body)) throw Error('Yoast sitemap index unavailable; no page crawl performed');
const originsMatch = url => {
  try { return new URL(url).origin === base; } catch { return false; }
};
const children = parseSitemap(index.body).locs.filter(originsMatch).filter(u => /-sitemap\d*\.xml(?:\?|$)/i.test(u));
if (children.length === 0) throw Error('No sitemap children found: no graph generated');

const listed = new Map();
const sitemapFailures = [];
for (const child of children) {
  await pause(delay);
  let page;
  try { page = await fetchPage(child); } catch (err) {
    sitemapFailures.push({ url: child, reason: String(err.message) }); continue;
  }
  if (page.status !== 200) {
    sitemapFailures.push({ url: child, status: page.status }); continue;
  }
  const type = child.split('/').at(-1).replace(/-sitemap\d*\.xml(?:\?.*)?$/i, '');
  for (const url of parseSitemap(page.body).locs) {
    if (!originsMatch(url)) continue;
    const path = localPath(url, base);
    if (!path) continue;
    if (!listed.has(path)) listed.set(path, { path, url: new URL(path, base).href, type, sitemap: child });
  }
}
if (!listed.size) throw Error('Sitemaps returned no valid local URLs');
if (!listed.has('/')) listed.set('/', { path: '/', url: base + '/', type: 'home', sitemap: 'implicit homepage' });
const all = [...listed.values()].sort((a, b) => a.path.localeCompare(b.path));
const targetPages = all.slice(0, limit);
const pages = new Array(targetPages.length);
const edgeLists = new Array(targetPages.length);
let cursor = 0;
async function worker() {
  while (cursor < targetPages.length) {
    const n = cursor++;
    const row = targetPages[n];
    try {
      const response = await fetchPage(row.url);
      const html = response.body;
      const $ = cheerio.load(html);
      const canonicals = $('link[rel~="canonical"]').map((_, el) => $(el).attr('href') || '').get();
      const canonical = canonicals.length === 1 ? new URL(canonicals[0], row.url).href : '';
      const robots = $('meta[name="robots"]').map((_, el) => $(el).attr('content') || '').get().join(',');
      const indexable = !/\bnoindex\b/i.test(robots) && !/^\s*noindex\b/i.test(robots);
      const selfCanonical = canonical === row.url;
      const record = {
        ...row, status: response.status, canonical, robots,
        indexable, selfCanonical, redirectTo: response.location,
        title: $('title').first().text().trim().slice(0, 200),
        h1: $('h1').first().text().replace(/\s+/g, ' ').trim().slice(0, 200),
      };
      pages[n] = record;
      edgeLists[n] = response.status === 200 && indexable && selfCanonical
        ? extractInternalEdges(html, row.path, base) : [];
    } catch (err) {
      pages[n] = { ...row, status: 0, indexable: false, selfCanonical: false, error: String(err.message) };
      edgeLists[n] = [];
    }
    await pause(delay);
  }
}
await Promise.all(Array.from({ length: concurrency }, worker));
const edges = edgeLists.flat();
const failed = pages.filter(p => p.status !== 200 || !p.indexable || !p.selfCanonical);
const complete = sitemapFailures.length === 0 && all.length <= limit && !pages.some(p => p.status === 0);
const graph = buildGraph(pages, edges, { complete });
graph.summary.sitemapSources = children.length;
graph.summary.sitemapFailures = sitemapFailures;
graph.summary.sitemapDiscoveredUrls = all.length;
graph.summary.scannedUrls = pages.length;
graph.summary.unscannedUrls = all.length - pages.length;
graph.summary.failedUrls = failed.length;
graph.summary.maxConcurrency = concurrency;
graph.summary.delayPerWorkerMs = delay;
graph.summary.robotsAndCanonicalAreReadOnly = true;

const incomingSources = new Map();
for (const e of graph.edges) {
  const arr = incomingSources.get(e.target) || [];
  if (!arr.includes(e.source)) arr.push(e.source);
  incomingSources.set(e.target, arr);
}
const opportunities = [...graph.orphans.map(n => ({
  path: n.path, type: n.type, silo: n.silo, priority: 'REVIEW_ORPHAN', inbound: 0,
  note: 'Check intentional archive/Yoast indexability and sitewide coverage before adding links',
})), ...graph.lowContextual.map(n => ({
  path: n.path, type: n.type, silo: n.silo, priority: 'REVIEW_CONTEXT', inbound: n.inbound,
  note: 'Referenced from navigation/breadcrumb but no content, related, or main contextual link in this crawl',
}))].sort((a, b) => a.priority.localeCompare(b.priority) || a.path.localeCompare(b.path));

await Promise.all([
  writeFile(opt.out + '/summary.json', JSON.stringify(graph.summary, null, 2) + '\n'),
  writeFile(opt.out + '/nodes.csv', csv(graph.nodes, ['path','type','title','h1','silo','status','canonical','inbound','inboundContextual','outbound'])),
  writeFile(opt.out + '/edges.csv', csv(graph.edges, ['source','target','anchor','region','rel','sourceSilo','targetSilo','targetInSitemap','contextual'])),
  writeFile(opt.out + '/opportunities.csv', csv(opportunities, ['priority','path','type','silo','inbound','note'])),
  writeFile(opt.out + '/excluded.json', JSON.stringify({ excluded: failed, sitemapFailures }, null, 2) + '\n'),
  writeFile(opt.out + '/outside-sitemap-targets.json', JSON.stringify(graph.unknownTargets, null, 2) + '\n'),
]);
console.log(JSON.stringify({
  ...graph.summary,
  mostRelevantOrphanCandidates: graph.orphans.slice(0, 12).map(n => ({ path: n.path, type: n.type, silo: n.silo })),
  lowContextualSamples: graph.lowContextual.slice(0, 12).map(n => ({ path: n.path, type: n.type, silo: n.silo, inbound: n.inbound })),
}, null, 2));
// Exit nonzero only for a completely failed crawl or sitemap discovery; SEO warnings
// remain in the evidence artifact for careful human review.
if (!pages.some(p => p.status === 200)) process.exitCode = 1;
