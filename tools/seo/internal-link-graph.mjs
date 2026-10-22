#!/usr/bin/env node
/**
 * Read-only audit of every URL the site publishes in its Yoast/WordPress sitemaps: HTTP status, redirect
 * chains, indexability, canonical, H1 and the internal link graph (orphan analysis). Not a recursive spider:
 * only sitemap URLs are requested, each once. GET requests only; no admin, cookies, forms or WordPress writes.
 *
 * Example (whole site, in bounded batches, resumable):
 *   node tools/seo/internal-link-graph.mjs --base https://thuexemaynguyentu.com \
 *     --out reports/internal-link-graph --concurrency 2 --delay 500 --batch-size 100 --max-minutes 40
 *   node tools/seo/internal-link-graph.mjs --base https://thuexemaynguyentu.com --out reports/internal-link-graph --resume
 *
 * Status is decided only by how many URLs really got a definitive answer:
 *   COMPLETE  every discovered URL checked and every sitemap file read;
 *   PARTIAL   some URLs unchecked or errored after retries (budget stop, outage, --limit, --types…);
 *   FAILED    no usable sitemap, or not one URL checked.
 * Orphans are only ORPHAN_CONFIRMED after a COMPLETE whole-site audit; otherwise they are candidates.
 */
import { mkdir, writeFile } from 'node:fs/promises';
import { appendFileSync } from 'node:fs';
import { gunzipSync } from 'node:zlib';
import { join } from 'node:path';
import { parseArgs } from 'node:util';
import {
  localPath, analyzeHtml, noindexFrom, buildGraph, classifyOrphans, detectIssues, canonicalGroups, countBy, renderReport, csv,
} from './internal-link-graph-lib.mjs';
import {
  discoverSitemaps, parseRobotsRules, robotsAllows, withRetry, followRedirects, stratify, parseRetryAfter,
  loadCheckpoint, prepareCheckpoint, appendResults, writeJsonAtomic, isFinalRecord, auditStatus,
} from './crawl-lib.mjs';

const { values: opt } = parseArgs({
  options: {
    base: { type: 'string', default: 'https://thuexemaynguyentu.com' },
    out: { type: 'string', default: 'reports/internal-link-graph' },
    limit: { type: 'string', default: '0' },
    'max-minutes': { type: 'string', default: '0' },
    'batch-size': { type: 'string', default: '100' },
    'batch-pause': { type: 'string', default: '1500' },
    concurrency: { type: 'string', default: '2' },
    delay: { type: 'string', default: '500' },
    timeout: { type: 'string', default: '20000' },
    retries: { type: 'string', default: '3' },
    backoff: { type: 'string', default: '1000' },
    types: { type: 'string', default: '' },
    resume: { type: 'boolean', default: false },
    strict: { type: 'boolean', default: false },
  },
});
const base = new URL(opt.base).origin;
if (base !== 'https://thuexemaynguyentu.com' && !/^(http:\/\/127\.0\.0\.1|http:\/\/localhost)(:\d+)?$/.test(base)) {
  throw Error('Only approved Nguyen Tu production and localhost sandbox origins are allowed');
}
const number = (name, min, max, integer = true) => {
  const value = Number(opt[name]);
  if (!Number.isFinite(value) || (integer && !Number.isInteger(value)) || value < min || value > max) throw Error(`--${name} must be ${integer ? 'an integer ' : ''}between ${min} and ${max}`);
  return value;
};
// Hosting safeguards: at most 3 concurrent requests, at least 300 ms pause per worker, bounded retries.
const limit = number('limit', 0, 100000);
const maxMinutes = number('max-minutes', 0, 600, false);
const batchSize = number('batch-size', 1, 500);
const batchPause = number('batch-pause', 0, 60000);
const concurrency = number('concurrency', 1, 3);
const delay = number('delay', 300, 10000);
const timeout = number('timeout', 1000, 30000);
const retries = number('retries', 0, 5);
const backoff = number('backoff', 50, 10000);
const typesFilter = new Set(opt.types.split(',').map(s => s.trim()).filter(Boolean));
const ua = 'NguyenTu-LinkGraph/2.0 (read-only sitemap audit)';
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
const cpDir = join(opt.out, 'checkpoint');
await mkdir(opt.out, { recursive: true });

// A throttling answer (429/503) pauses every worker, not just the one that received it.
const gate = { until: 0 };
let retryLogs = 0;
async function rawGet(url, wantBody) {
  const wait = gate.until - Date.now();
  if (wait > 0) await sleep(wait);
  const res = await fetch(url, {
    redirect: 'manual',
    headers: { 'User-Agent': ua, Accept: 'text/html,application/xml,text/xml,*/*' },
    signal: AbortSignal.timeout(timeout),
  });
  const result = {
    status: res.status,
    location: res.headers.get('location') || '',
    headers: { 'x-robots-tag': res.headers.get('x-robots-tag') || '', 'content-type': res.headers.get('content-type') || '' },
    retryAfterMs: parseRetryAfter(res.headers.get('retry-after')),
  };
  if (wantBody && res.status === 200) {
    result.body = /\.gz(?:\?|$)/i.test(url) ? gunzipSync(Buffer.from(await res.arrayBuffer())).toString('utf8') : await res.text();
  } else {
    await res.body?.cancel().catch(() => {});
  }
  return result;
}
const fetchWithRetry = (url, wantBody) => withRetry(() => rawGet(url, wantBody), {
  retries,
  baseMs: backoff,
  onRetry: info => {
    if (info.status === 429 || info.status === 503) gate.until = Math.max(gate.until, Date.now() + info.delayMs);
    if (retryLogs++ < 40) console.log(`retry ${info.attempt} ${url} (${info.status ?? info.error}) in ${info.delayMs} ms`);
  },
});
const fetchHop = async url => {
  const r = await fetchWithRetry(url, false);
  return r.exhausted ? { status: 0, error: r.error ?? `HTTP ${r.status} after ${r.attempts} attempts`, exhausted: true } : { status: r.status, location: r.location };
};

const startedAtMs = Date.now();
const startedAt = new Date(startedAtMs).toISOString();
const stopSignals = { requested: false };
for (const signal of ['SIGINT', 'SIGTERM']) {
  process.on(signal, () => { stopSignals.requested = true; console.log(`${signal}: finishing in-flight requests and saving the checkpoint`); });
}

async function finish(summary, files = {}) {
  await Promise.all(Object.entries(files).map(([name, content]) => writeFile(join(opt.out, name), content)));
  await writeFile(join(opt.out, 'summary.json'), JSON.stringify(summary, null, 2) + '\n');
}

// ---- robots.txt (read once) and sitemap discovery ----
let robotRules = [];
let robotsChecked = false;
let robotsSitemaps = [];
const robots = await fetchWithRetry(base + '/robots.txt', true);
if (!robots.exhausted && robots.status === 200) {
  robotRules = parseRobotsRules(robots.body);
  robotsSitemaps = [...robots.body.matchAll(/^\s*sitemap:\s*(\S+)/gim)].map(m => m[1]);
  robotsChecked = true;
}
const roots = [base + '/sitemap_index.xml', ...robotsSitemaps.filter(u => u.startsWith(base)), base + '/wp-sitemap.xml'];
const discovery = await discoverSitemaps({
  base,
  roots,
  toPath: localPath,
  fetchSitemap: async url => {
    const r = await fetchWithRetry(url, true);
    return { status: r.exhausted ? 0 : r.status, body: r.body ?? '' };
  },
  pause: () => sleep(delay),
});
if (!discovery.ok) {
  const summary = {
    generatedAt: new Date().toISOString(), base, status: 'FAILED', reason: 'No usable sitemap: no page crawl performed',
    sitemapFailures: discovery.failures, urlsDiscovered: 0, urlsChecked: 0, urlsErrored: 0, urlsPending: 0,
  };
  await finish(summary, { 'report.md': `# SEO audit — FAILED\n\n${summary.reason}.\n\n\`\`\`json\n${JSON.stringify(discovery.failures, null, 2)}\n\`\`\`\n` });
  console.log('::error title=SEO audit FAILED::' + summary.reason);
  console.log(JSON.stringify(summary, null, 2));
  process.exit(1);
}

let urls = discovery.urls.sort((a, b) => a.path.localeCompare(b.path));
if (typesFilter.size) urls = urls.filter(u => typesFilter.has(u.type));
else if (!urls.some(u => u.path === '/')) urls.push({ path: '/', url: base + '/', type: 'home', kind: 'content', sitemap: 'implicit homepage', lastmod: '' });
if (!urls.length) throw Error(`--types matched no sitemap URLs (available: ${[...new Set(discovery.urls.map(u => u.type))].join(', ')})`);
urls = stratify(urls);

// ---- checkpoint / resume ----
const checkpoint = opt.resume ? await loadCheckpoint(cpDir) : { meta: null, results: new Map() };
if (checkpoint.meta && checkpoint.meta.base !== base) throw Error(`Checkpoint belongs to ${checkpoint.meta.base}, not ${base}`);
await prepareCheckpoint(cpDir, { fresh: !opt.resume });
const results = new Map(checkpoint.results);
const resumedFinal = urls.filter(u => isFinalRecord(results.get(u.path))).length;
const meta = checkpoint.meta ?? { schema: 1, base, createdAt: startedAt, runs: [] };
const run = { startedAt, finishedAt: '', checkedThisRun: 0, erroredThisRun: 0, stoppedBy: '' };
meta.runs.push(run);
const todo = urls.filter(u => !isFinalRecord(results.get(u.path)));
const queue = limit > 0 ? todo.slice(0, limit) : todo;
console.log(`Sitemaps: ${discovery.sitemaps.length} files, ${urls.length} URLs (${discovery.duplicates} duplicate entries ignored); ${resumedFinal} already checked, ${queue.length} to request now; robots.txt ${robotsChecked ? 'read' : 'unavailable'}`);

const robotsAllowedFor = path => (robotsChecked ? robotsAllows(robotRules, path, '*') : null);
const hopCache = new Map();
async function auditUrl(row) {
  const record = { path: row.path, url: row.url, type: row.type, kind: row.kind, sitemap: row.sitemap, lastmod: row.lastmod, checkedAt: new Date().toISOString() };
  const first = await fetchWithRetry(row.url, true);
  if (first.exhausted) {
    return { ...record, status: 0, error: first.error ?? `HTTP ${first.status} after ${first.attempts} attempts`, lastStatus: first.status ?? 0, attempts: first.attempts };
  }
  Object.assign(record, {
    status: first.status, attempts: first.attempts, location: first.location,
    xRobotsTag: first.headers['x-robots-tag'], robotsTxtAllowed: robotsAllowedFor(row.path),
  });
  if (first.status >= 300 && first.status < 400 && first.location) {
    const start = new URL(first.location, row.url);
    start.hash = '';
    const walk = await followRedirects(fetchHop, start.href, { base, maxHops: 5, cache: hopCache, visited: [row.url] });
    if (walk.final.error || walk.final.exhausted) {
      return { ...record, status: 0, error: `redirect hop failed: ${walk.final.error ?? 'retries exhausted'}`, attempts: first.attempts };
    }
    const hops = [{ url: row.url, status: first.status, location: first.location }, ...walk.chain];
    const last = walk.chain.at(-1);
    return Object.assign(record, {
      hops: hops.filter(h => h.status >= 300 && h.status < 400).length,
      chain: hops.map(h => `${h.status} ${h.url}`).join(' > '),
      finalUrl: walk.offsite ? new URL(last.location, last.url).href : last.url,
      finalStatus: walk.final.status,
      redirectLoop: walk.loop, redirectOffsite: walk.offsite, redirectTooMany: walk.tooMany,
      indexable: false, edges: [],
    });
  }
  const contentType = first.headers['content-type'];
  if (first.status !== 200 || (contentType && !/html/i.test(contentType))) {
    return Object.assign(record, { nonHtml: first.status === 200, indexable: false, edges: [] });
  }
  const page = analyzeHtml(first.body, row, base);
  const directives = noindexFrom({ robotsMeta: page.robotsMeta, xRobotsTag: first.headers['x-robots-tag'] });
  const { edges, ...fields } = page;
  Object.assign(record, fields, { noindex: directives.noindex, nofollow: directives.nofollow, noindexSources: directives.sources });
  record.indexable = !directives.noindex && record.robotsTxtAllowed !== false;
  // Only a page that is indexable and its own canonical can pass or receive link weight in the graph.
  record.edges = record.indexable && record.selfCanonical ? edges : [];
  return record;
}

// ---- bounded batches with a time budget ----
const deadline = maxMinutes > 0 ? startedAtMs + maxMinutes * 60000 : Infinity;
const keepGoing = () => !stopSignals.requested && Date.now() < deadline;
let checkedThisRun = 0;
let erroredThisRun = 0;
const batches = Math.ceil(queue.length / batchSize);
for (let offset = 0; offset < queue.length && keepGoing(); offset += batchSize) {
  const batch = queue.slice(offset, offset + batchSize);
  const finished = [];
  let cursor = 0;
  const worker = async () => {
    while (cursor < batch.length && keepGoing()) {
      const row = batch[cursor++];
      let record;
      try { record = await auditUrl(row); } catch (error) { record = { path: row.path, url: row.url, type: row.type, kind: row.kind, sitemap: row.sitemap, lastmod: row.lastmod, status: 0, error: String(error?.message ?? error), attempts: 1, checkedAt: new Date().toISOString() }; }
      finished.push(record);
      results.set(record.path, record);
      if (isFinalRecord(record)) checkedThisRun++; else erroredThisRun++;
      await sleep(delay);
    }
  };
  await Promise.all(Array.from({ length: concurrency }, worker));
  await appendResults(cpDir, finished);
  Object.assign(run, { checkedThisRun, erroredThisRun });
  await writeJsonAtomic(join(cpDir, 'meta.json'), meta);
  const elapsed = Math.round((Date.now() - startedAtMs) / 1000);
  console.log(`[batch ${Math.floor(offset / batchSize) + 1}/${batches}] checked ${checkedThisRun} errored ${erroredThisRun} of ${queue.length} requested this run; elapsed ${elapsed}s`);
  if (offset + batchSize < queue.length && keepGoing()) await sleep(batchPause);
}
const untouched = queue.length - checkedThisRun - erroredThisRun;
run.stoppedBy = stopSignals.requested ? 'signal' : Date.now() >= deadline && untouched > 0 ? 'time budget' : limit > 0 && todo.length > queue.length ? 'limit' : '';
run.finishedAt = new Date().toISOString();
await writeJsonAtomic(join(cpDir, 'meta.json'), meta);

// ---- aggregate every discovered URL (earlier runs included) ----
const records = urls.map(u => results.get(u.path) ?? { path: u.path, url: u.url, type: u.type, kind: u.kind, sitemap: u.sitemap, lastmod: u.lastmod, status: 0, pending: true });
const checked = records.filter(isFinalRecord).length;
const errored = records.filter(r => r.error).length;
const pending = records.length - checked - errored;
const status = auditStatus({ sitemapOk: discovery.ok, discovered: records.length, checked, sitemapFailures: discovery.failures.length });
const wholeSite = status === 'COMPLETE' && typesFilter.size === 0;
const pages = records.map(({ edges: _edges, ...rest }) => rest);
const edgeRows = records.flatMap(r => (r.edges ?? []).map(e => ({ source: r.path, ...e })));
const graph = buildGraph(pages, edgeRows, { complete: wholeSite });
const issues = detectIssues(records);
const groups = canonicalGroups(records);
const orphans = classifyOrphans(records, graph, { complete: wholeSite });
const caution = {
  COMPLETE: typesFilter.size
    ? `COMPLETE for the selected sitemap types only (${[...typesFilter].join(', ')}); orphan results are candidates because other types were not crawled.`
    : 'COMPLETE: every URL listed in the current sitemaps has a definitive answer. An orphan has no inbound link from any other crawled indexable page; links from unlisted pages or other sites are unknown.',
  PARTIAL: `PARTIAL: ${checked} of ${records.length} URLs have a definitive answer (${errored} errored after retries, ${pending} not requested yet). Orphan results are candidates, never confirmed.`,
  FAILED: 'FAILED: no URL could be checked.',
}[status];
const summary = {
  generatedAt: new Date().toISOString(),
  base,
  tool: 'tools/seo/internal-link-graph.mjs v2',
  status,
  scope: typesFilter.size ? `sitemap types: ${[...typesFilter].join(', ')}` : 'all sitemap URLs',
  urlsDiscovered: records.length,
  urlsChecked: checked,
  urlsErrored: errored,
  urlsPending: pending,
  coveragePercent: Number(((checked / records.length) * 100).toFixed(1)),
  checkedThisRun,
  erroredThisRun,
  resumedFromCheckpoint: resumedFinal,
  urlsNoLongerInSitemap: [...results.keys()].filter(p => !urls.some(u => u.path === p)).length,
  stoppedBy: run.stoppedBy || (pending + errored === 0 ? 'complete' : 'unchecked URLs remain'),
  sitemapRoot: discovery.root,
  sitemapFiles: discovery.sitemaps.length,
  sitemapFailures: discovery.failures,
  duplicateSitemapEntries: discovery.duplicates,
  robotsTxtRead: robotsChecked,
  byType: countBy(records, r => r.type),
  byClassification: countBy(orphans, r => r.classification),
  findings: countBy(issues, i => i.code),
  indexableSelfCanonical200: graph.summary.indexableCanonical200,
  uniqueInternalEdges: graph.summary.uniqueInternalEdges,
  orphanConfirmed: orphans.filter(r => r.classification === 'ORPHAN_CONFIRMED').length,
  orphanCandidates: orphans.filter(r => r.classification === 'ORPHAN_CANDIDATE').length,
  canonicalGroups: groups.length,
  params: { concurrency, delayPerWorkerMs: delay, timeoutMs: timeout, retries, backoffMs: backoff, batchSize, batchPauseMs: batchPause, limitThisRun: limit, maxMinutes },
  runs: meta.runs,
  caution,
};

const incoming = new Map();
for (const e of graph.edges) {
  const list = incoming.get(e.target) || [];
  if (!list.includes(e.source)) list.push(e.source);
  incoming.set(e.target, list);
}
const opportunities = [...orphans.filter(r => r.classification.startsWith('ORPHAN_')).map(r => ({
  path: r.path, type: r.type, silo: graph.nodes.find(n => n.path === r.path)?.silo ?? '', priority: 'REVIEW_ORPHAN', inbound: 0,
  note: 'Check intentional archive/Yoast indexability and sitewide coverage before adding links',
})), ...graph.lowContextual.map(n => ({
  path: n.path, type: n.type, silo: n.silo, priority: 'REVIEW_CONTEXT', inbound: n.inbound,
  note: 'Referenced from navigation/breadcrumb but no content, related, or main contextual link in this crawl',
}))].sort((a, b) => a.priority.localeCompare(b.priority) || a.path.localeCompare(b.path));
const redirects = records.filter(r => r.status >= 300 && r.status < 400);
const errors = issues.filter(i => i.severity === 'error');
const report = renderReport(summary, { issueCounts: summary.findings, orphanCounts: summary.byClassification });

await finish(summary, {
  'urls.csv': csv(records, ['path', 'type', 'kind', 'sitemap', 'lastmod', 'status', 'finalStatus', 'hops', 'finalUrl', 'noindex', 'robotsTxtAllowed', 'indexable', 'canonicalCount', 'canonical', 'selfCanonical', 'h1Count', 'h1', 'title', 'xRobotsTag', 'attempts', 'error', 'checkedAt']),
  'issues.csv': csv(issues, ['severity', 'code', 'path', 'type', 'detail']),
  'errors.csv': csv(errors, ['code', 'path', 'type', 'detail']),
  'redirects.csv': csv(redirects, ['path', 'status', 'hops', 'finalStatus', 'finalUrl', 'redirectLoop', 'redirectOffsite', 'chain']),
  'canonical-groups.csv': csv(groups.map(g => ({ target: g.target, count: g.members.length, targetInSitemap: g.targetInSitemap, members: g.members.join(' ') })), ['target', 'count', 'targetInSitemap', 'members']),
  'orphans.csv': csv(orphans, ['classification', 'path', 'type', 'kind', 'inbound', 'inboundContextual', 'reason']),
  'nodes.csv': csv(graph.nodes, ['path', 'type', 'title', 'h1', 'silo', 'status', 'canonical', 'inbound', 'inboundContextual', 'outbound']),
  'edges.csv': csv(graph.edges, ['source', 'target', 'anchor', 'region', 'rel', 'sourceSilo', 'targetSilo', 'targetInSitemap', 'contextual']),
  'opportunities.csv': csv(opportunities, ['priority', 'path', 'type', 'silo', 'inbound', 'note']),
  'excluded.json': JSON.stringify({ excluded: pages.filter(p => !(p.status === 200 && p.indexable && p.selfCanonical)).map(({ path, type, status, finalStatus, noindex, robotsTxtAllowed, canonical, error }) => ({ path, type, status, finalStatus, noindex, robotsTxtAllowed, canonical, error })), sitemapFailures: discovery.failures }, null, 2) + '\n',
  'outside-sitemap-targets.json': JSON.stringify(graph.unknownTargets, null, 2) + '\n',
  'report.md': report,
});
if (process.env.GITHUB_STEP_SUMMARY) appendFileSync(process.env.GITHUB_STEP_SUMMARY, report);
if (status === 'PARTIAL') console.log(`::warning title=SEO audit PARTIAL::${checked}/${records.length} URLs checked, ${errored} errored, ${pending} not requested. Re-run with --resume (workflow input resume_run_id).`);
// The pages that need a decision go to the job log as well: logs stay readable where artifacts cannot be downloaded.
const listing = (title, rows, line, max = 40) => {
  if (!rows.length) return;
  console.log(`--- ${title}: ${rows.length}${rows.length > max ? ` (first ${max}; the CSV files have all of them)` : ''} ---`);
  for (const row of rows.slice(0, max)) console.log(line(row));
};
listing('Orphans and navigation-only pages (class, type, inbound, contextual inbound, path)', orphans.filter(r => !['LINKED', 'HOME'].includes(r.classification)), r => `${r.classification}\t${r.type}\t${r.inbound}\t${r.inboundContextual}\t${r.path}${r.reason ? `\t${r.reason}` : ''}`);
listing('Findings (severity, code, path, detail)', issues, i => `${i.severity}\t${i.code}\t${i.path}\t${i.detail ?? ''}`);
console.log(JSON.stringify({ ...summary, runs: undefined }, null, 2));
if (status === 'FAILED') { console.log('::error title=SEO audit FAILED::no URL could be checked'); process.exitCode = 1; }
else if (opt.strict && status !== 'COMPLETE') process.exitCode = 2;
