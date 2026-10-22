/**
 * Internal-link graph pure helpers. No requests, no WordPress writes.
 * A post is NOT "orphan" because it lacks contextual links: count separately.
 */
import * as cheerio from 'cheerio';

const ASSET = /\.(?:jpe?g|png|webp|gif|svg|css|js|woff2?|ico|pdf|zip|mp4|mp3)(?:$|\?)/i;

export function localPath(href, baseUrl) {
  try {
    const root = new URL(baseUrl);
    const url = new URL(href, baseUrl);
    if (!/^https?:$/.test(url.protocol) || url.hostname.replace(/^www\./, '') !== root.hostname.replace(/^www\./, '')) return null;
    if (url.port && url.port !== root.port) return null;
    if (ASSET.test(url.pathname) || /^\/(?:wp-admin|wp-json|wp-content|wp-includes)(?:\/|$)/.test(url.pathname)) return null;
    let path = url.pathname.replace(/\/{2,}/g, '/');
    if (!/\.[a-z\d]{2,6}$/i.test(path) && !path.endsWith('/')) path += '/';
    // Tracking/query pages are NOT canonical nodes; do not treat search & sorting links
    // as proof that the canonical permalink receives a normal internal link.
    if (url.search && (/(?:^|[?&])(?:s|page_id|p|orderby|filter_|add-to-cart)=/i.test(url.search))) return null;
    return path;
  } catch { return null; }
}

export function linkRegion($, el) {
  const link = $(el);
  if (link.closest('footer, #site-footer').length) return 'footer';
  if (link.closest('.breadcrumbs, .breadcrumb, .yoast-breadcrumb, nav[aria-label*="readcrumb"]').length) return 'breadcrumb';
  if (link.closest('.nt-related, .related-posts, [data-related-posts]').length) return 'related';
  if (link.closest('.nt-article__chips, .post-categories, .category-links').length) return 'category';
  if (link.closest('header, #nt-nav, .site-nav, .mobile-nav').length) return 'header';
  if (link.closest('aside, .sidebar').length) return 'sidebar';
  if (link.closest('article .entry-content, .post-content, .entry-content, .nt-single__content, .wp-block-post-content').length) return 'content';
  return link.closest('main, article').length ? 'main' : 'other';
}

export function extractInternalEdges(html, source, baseUrl) {
  const $ = cheerio.load(html);
  const found = new Map();
  $('a[href]').each((_, el) => {
    const raw = ($(el).attr('href') || '').trim();
    if (!raw || raw.startsWith('#') || /^(?:mailto|tel|sms|javascript|data):/i.test(raw)) return;
    const target = localPath(raw, baseUrl);
    if (!target || target === source) return;
    const region = linkRegion($, el);
    const anchor = $(el).text().replace(/\s+/g, ' ').trim().slice(0, 120);
    const rel = ($(el).attr('rel') || '').toLowerCase();
    const key = JSON.stringify([source, target, region, anchor, rel]);
    found.set(key, { source, target, region, anchor, rel });
  });
  return [...found.values()];
}

export function inferSilo(path, outgoingEdges = []) {
  const cat = path.match(/^\/blog\/([^/]+)\//);
  if (cat) return 'blog/' + cat[1];
  if (path === '/blog/') return 'blog';
  if (/^\/(?:san-pham|shop|danh-muc-san-pham)\//.test(path)) return 'shop';
  if (/^\/bo-suu-tap\//.test(path)) return 'bang-gia';
  // Post permalink is generally at the root. Follow an actual breadcrumb/category
  // link, never invent a silo from keywords in the title or slug.
  const cats = outgoingEdges.filter(e => ['breadcrumb', 'category'].includes(e.region) && /^\/blog\/[^/]+\//.test(e.target));
  if (cats.length) {
    cats.sort((a, b) => b.target.split('/').length - a.target.split('/').length);
    return 'blog/' + cats[0].target.split('/')[2];
  }
  return 'unclassified';
}

export function buildGraph(pages, edges, options = {}) {
  const valid = pages.filter(p => p.status === 200 && p.indexable && p.selfCanonical);
  const indexed = new Map(valid.map(p => [p.path, { ...p, inbound: 0, inboundContextual: 0, outbound: 0, silo: '' }]));
  const buckets = new Map();
  for (const edge of edges) {
    if (!indexed.has(edge.source)) continue;
    const set = buckets.get(edge.source) || [];
    set.push(edge);
    buckets.set(edge.source, set);
  }
  for (const [path, node] of indexed) node.silo = inferSilo(path, buckets.get(path) || []);

  const seen = new Set();
  const seenContext = new Set();
  const edgeRows = [];
  for (const e of edges) {
    if (!indexed.has(e.source)) continue;
    const matched = indexed.has(e.target);
    const contextual = ['content', 'related', 'main'].includes(e.region);
    edgeRows.push({ ...e, targetInSitemap: matched, contextual, sourceSilo: indexed.get(e.source).silo, targetSilo: indexed.get(e.target)?.silo || '' });
    if (!matched) continue;
    const pair = e.source + '\0' + e.target;
    if (!seen.has(pair)) {
      indexed.get(e.source).outbound++;
      indexed.get(e.target).inbound++;
      seen.add(pair);
    }
    if (contextual && !seenContext.has(pair)) {
      indexed.get(e.target).inboundContextual++;
      seenContext.add(pair);
    }
  }
  const nodes = [...indexed.values()].sort((a, b) => a.path.localeCompare(b.path));
  const orphans = nodes.filter(n => n.inbound === 0 && n.path !== '/');
  const lowContextual = nodes.filter(n => n.inboundContextual === 0 && n.inbound > 0 && n.path !== '/');
  const known = new Set(pages.map(p => p.path));
  const unknownTargets = [...new Set(edgeRows.filter(e => !known.has(e.target)).map(e => e.target))].sort();
  return {
    nodes, edges: edgeRows, orphans, lowContextual, unknownTargets,
    summary: {
      generatedAt: options.generatedAt || new Date().toISOString(),
      source: options.source || 'sitemap-live-html',
      discovered: pages.length,
      indexableCanonical200: nodes.length,
      excludedOrFailed: pages.length - nodes.length,
      uniqueInternalEdges: seen.size,
      totalLinkPlacements: edgeRows.length,
      orphanCandidates: orphans.length,
      noContextualInbound: lowContextual.length,
      outsideSitemapTargets: unknownTargets.length,
      complete: Boolean(options.complete),
      caution: options.complete
        ? 'Orphan candidates are within successfully crawled indexable self-canonical sitemap pages only; links from unlisted pages are unknown.'
        : 'PARTIAL crawl: do not label orphan candidates as confirmed; sitewide inbound may be missing.',
    },
  };
}

export function csv(rows, fields) {
  const quote = v => {
    const s = String(v ?? '');
    return /[",\r\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
  };
  return fields.join(',') + '\n' + rows.map(r => fields.map(k => quote(r[k])).join(',')).join('\n') + '\n';
}

/** Comparable form of a URL: scheme and host in lower case, no fragment, directory paths end with a slash. */
export function urlKey(href, baseUrl) {
  try {
    const url = new URL(href, baseUrl);
    let path = url.pathname.replace(/\/{2,}/g, '/');
    if (!/\.[a-z\d]{2,6}$/i.test(path) && !path.endsWith('/')) path += '/';
    return `${url.protocol}//${url.host.toLowerCase()}${path}${url.search}`;
  } catch { return ''; }
}

/**
 * Indexing directives from the robots meta tag and the X-Robots-Tag header. A header rule addressed to a
 * crawler other than Googlebot (or the wildcard) is ignored; "none" means noindex + nofollow.
 *
 * @param {{robotsMeta?:string, xRobotsTag?:string}} input Directive sources.
 * @returns {{noindex:boolean, nofollow:boolean, sources:string[]}} Combined result.
 */
export function noindexFrom({ robotsMeta = '', xRobotsTag = '' }) {
  const tokens = [];
  const sources = [];
  const add = (source, text) => {
    for (const token of text.toLowerCase().split(/[\s,]+/).filter(Boolean)) tokens.push({ source, token });
  };
  add('meta', robotsMeta);
  // fetch() joins repeated headers with ", ", so a new "<crawler>:" prefix starts a new rule.
  for (const value of xRobotsTag.split(/\n|,\s*(?=[a-z][a-z0-9-]*\s*:)/i)) {
    const match = value.match(/^\s*([a-z][a-z0-9-]*)\s*:\s*(.+)$/i);
    const named = match && !/^(?:noindex|nofollow|none|noarchive|nosnippet|noimageindex|notranslate|unavailable_after|max-[a-z-]+|index|follow|all)$/i.test(match[1]);
    if (named && !/^(?:googlebot|\*)$/i.test(match[1])) continue;
    add('header', named ? match[2] : value);
  }
  const noindex = tokens.filter(t => t.token === 'noindex' || t.token === 'none');
  const nofollow = tokens.filter(t => t.token === 'nofollow' || t.token === 'none');
  for (const t of noindex) if (!sources.includes(t.source)) sources.push(t.source);
  return { noindex: noindex.length > 0, nofollow: nofollow.length > 0, sources };
}

/**
 * Everything the audit reads from one HTML page: canonical state, H1, title, robots meta and internal links.
 *
 * @param {string} html Page HTML.
 * @param {{url:string, path:string}} row Sitemap row (requested URL and path).
 * @param {string} base Site origin.
 * @returns {object} Extracted fields; `edges` only lists links of the requested page.
 */
export function analyzeHtml(html, row, base) {
  const $ = cheerio.load(html);
  const canonicals = $('link[rel~="canonical"]').map((_, el) => ($(el).attr('href') || '').trim()).get();
  const canonical = canonicals.length === 1 && canonicals[0] ? new URL(canonicals[0], row.url).href : '';
  const target = canonical ? localPath(canonical, base) : null;
  const robotsMeta = $('meta[name="robots" i], meta[name="googlebot" i]').map((_, el) => $(el).attr('content') || '').get().join(',');
  const h1s = $('h1').map((_, el) => $(el).text().replace(/\s+/g, ' ').trim()).get();
  return {
    title: $('title').first().text().replace(/\s+/g, ' ').trim().slice(0, 200),
    h1Count: h1s.length,
    h1: (h1s[0] || '').slice(0, 200),
    canonicals,
    canonicalCount: canonicals.length,
    canonical,
    canonicalTarget: target || '',
    canonicalOffsite: Boolean(canonical) && target === null,
    selfCanonical: Boolean(canonical) && urlKey(canonical, row.url) === urlKey(row.url, row.url),
    robotsMeta,
    edges: extractInternalEdges(html, row.path, base),
  };
}

/**
 * Per-URL findings. A finding is evidence for a human to review; nothing here edits metadata or content.
 *
 * @param {object[]} records Audit records of the discovered URLs.
 * @returns {{path:string,url:string,type:string,severity:string,code:string,detail:string}[]} Findings, errors first.
 */
export function detectIssues(records) {
  const issues = [];
  const add = (r, code, severity, detail = '') => issues.push({ path: r.path, url: r.url, type: r.type, severity, code, detail });
  for (const r of records) {
    if (r.error) { add(r, 'FETCH_ERROR', 'error', `${r.error} (attempts: ${r.attempts ?? 1})`); continue; }
    if (!r.status) continue;
    if (r.status >= 400) { add(r, r.status >= 500 ? 'HTTP_5XX' : 'HTTP_4XX', 'error', String(r.status)); continue; }
    if (r.status >= 300) {
      add(r, 'REDIRECT_IN_SITEMAP', 'warning', `${r.status} → ${r.finalUrl || r.location || ''}`);
      if (r.redirectLoop) add(r, 'REDIRECT_LOOP', 'error', r.chain || '');
      if (r.redirectOffsite) add(r, 'REDIRECT_OFFSITE', 'warning', r.finalUrl || '');
      if (r.redirectTooMany) add(r, 'REDIRECT_CHAIN_TOO_LONG', 'error', r.chain || '');
      if (r.hops >= 2) add(r, 'REDIRECT_CHAIN', 'warning', `${r.hops} hops: ${r.chain}`);
      if (r.finalStatus >= 400) add(r, 'REDIRECT_TO_ERROR', 'error', `final ${r.finalStatus}`);
      continue;
    }
    if (r.status !== 200) continue;
    if (r.robotsTxtAllowed === false) add(r, 'ROBOTS_TXT_BLOCKED_IN_SITEMAP', 'warning', 'Disallowed for User-agent: *');
    if (r.noindex) add(r, 'NOINDEX_IN_SITEMAP', 'warning', (r.noindexSources || []).join('+'));
    if (r.nonHtml) continue;
    if (r.canonicalCount === 0) add(r, 'CANONICAL_MISSING', 'warning');
    else if (r.canonicalCount > 1) add(r, 'CANONICAL_MULTIPLE', 'warning', r.canonicals.join(' | '));
    else if (r.canonicalOffsite) add(r, 'CANONICAL_OFFSITE', 'warning', r.canonical);
    else if (!r.selfCanonical) add(r, 'CANONICAL_NOT_SELF', 'warning', r.canonical);
    if (r.h1Count === 0) add(r, 'H1_MISSING', 'warning');
    else if (r.h1Count > 1) add(r, 'H1_MULTIPLE', 'warning', String(r.h1Count));
    if (!r.title) add(r, 'TITLE_MISSING', 'warning');
  }
  for (const group of canonicalGroups(records)) {
    for (const member of group.members.filter(m => m !== group.target)) {
      const r = records.find(x => x.path === member);
      if (r) add(r, 'CANONICAL_DUPLICATE_TARGET', 'warning', `${group.members.length} sitemap URLs share ${group.target}`);
    }
  }
  const rank = { error: 0, warning: 1 };
  return issues.sort((a, b) => rank[a.severity] - rank[b.severity] || a.code.localeCompare(b.code) || a.path.localeCompare(b.path));
}

/**
 * Sitemap URLs whose canonical points at the same on-site target as at least one other sitemap URL.
 *
 * @param {object[]} records Audit records.
 * @returns {{target:string, members:string[], targetInSitemap:boolean}[]} Groups of two or more URLs.
 */
export function canonicalGroups(records) {
  const byTarget = new Map();
  const listed = new Set(records.map(r => r.path));
  for (const r of records) {
    if (r.status !== 200 || r.nonHtml || r.canonicalCount !== 1 || r.canonicalOffsite || !r.canonicalTarget) continue;
    const members = byTarget.get(r.canonicalTarget) ?? [];
    members.push(r.path);
    byTarget.set(r.canonicalTarget, members);
  }
  const groups = [];
  for (const [target, found] of byTarget) {
    const members = [...new Set(listed.has(target) ? [...found, target] : found)].sort();
    if (members.length > 1) groups.push({ target, members, targetInSitemap: listed.has(target) });
  }
  return groups.sort((a, b) => a.target.localeCompare(b.target));
}

/**
 * Orphan analysis that separates real orphans from pages that were never meant to receive links.
 *
 * Only a page that answers 200, is indexable (no noindex, not blocked by robots.txt) and is its own canonical
 * counts in the orphan denominator. With zero inbound links it is ORPHAN_CONFIRMED only after a COMPLETE
 * whole-site audit, otherwise ORPHAN_CANDIDATE. noindex taxonomy archives, redirects, non-canonical pages and
 * errors are listed with their own NOT_APPLICABLE_* class instead of being counted as orphans.
 *
 * @param {object[]} records Audit records.
 * @param {{nodes:object[]}} graph Graph from buildGraph.
 * @param {{complete:boolean}} options Whether the audit covered every URL of the whole site.
 * @returns {{path:string,type:string,kind:string,classification:string,reason:string,inbound:number,inboundContextual:number}[]} One row per URL.
 */
export function classifyOrphans(records, graph, { complete }) {
  const nodes = new Map(graph.nodes.map(n => [n.path, n]));
  return records.map(r => {
    const row = { path: r.path, type: r.type, kind: r.kind || '', classification: '', reason: '', inbound: 0, inboundContextual: 0 };
    const node = nodes.get(r.path);
    if (node) {
      Object.assign(row, { inbound: node.inbound, inboundContextual: node.inboundContextual });
      if (r.path === '/') row.classification = 'HOME';
      else if (node.inbound === 0) row.classification = complete ? 'ORPHAN_CONFIRMED' : 'ORPHAN_CANDIDATE';
      else if (node.inboundContextual === 0) row.classification = 'LOW_CONTEXTUAL';
      else row.classification = 'LINKED';
      return row;
    }
    if (r.error || !r.status) { row.classification = 'NOT_CHECKED'; row.reason = r.error || 'not requested yet'; }
    else if (r.status >= 300 && r.status < 400) { row.classification = 'NOT_APPLICABLE_REDIRECT'; row.reason = `${r.status} → ${r.finalUrl || ''}`; }
    else if (r.status !== 200) { row.classification = 'NOT_APPLICABLE_HTTP_ERROR'; row.reason = String(r.status); }
    else if (r.robotsTxtAllowed === false) { row.classification = 'NOT_APPLICABLE_ROBOTS_BLOCKED'; row.reason = 'robots.txt'; }
    else if (r.noindex) {
      row.classification = ['taxonomy', 'archive'].includes(r.kind) ? 'NOT_APPLICABLE_TAXONOMY_NOINDEX' : 'NOT_APPLICABLE_NOINDEX';
      row.reason = (r.noindexSources || []).join('+');
    } else { row.classification = 'NOT_APPLICABLE_NON_SELF_CANONICAL'; row.reason = r.canonical || 'no canonical'; }
    return row;
  });
}

/** Counts of the values a function returns for each item, sorted by name. */
export function countBy(items, fn) {
  const counts = {};
  for (const item of items) counts[fn(item)] = (counts[fn(item)] ?? 0) + 1;
  return Object.fromEntries(Object.entries(counts).sort(([a], [b]) => a.localeCompare(b)));
}

/**
 * Human-readable report (also used as the GitHub job summary).
 *
 * @param {object} summary Audit summary.
 * @param {object} extra Extra lists for the report.
 * @param {object} extra.issueCounts Counts of findings by code.
 * @param {object} extra.orphanCounts Counts of orphan classes.
 * @returns {string} Markdown.
 */
export function renderReport(summary, { issueCounts, orphanCounts }) {
  const pct = summary.urlsDiscovered ? ((summary.urlsChecked / summary.urlsDiscovered) * 100).toFixed(1) : '0.0';
  const table = obj => Object.entries(obj).map(([k, v]) => `| ${k} | ${v} |`).join('\n') || '| – | 0 |';
  const lines = [
    `# SEO audit — ${summary.status}`,
    '',
    `Read-only GET audit of ${summary.base}. Generated ${summary.generatedAt}. Nothing on the site was changed.`,
    '',
    '| Measure | Value |', '| --- | ---: |',
    `| Status | **${summary.status}** |`,
    `| URLs discovered in sitemaps | ${summary.urlsDiscovered} |`,
    `| URLs checked (definitive answer) | ${summary.urlsChecked} (${pct}%) |`,
    `| URLs errored after retries | ${summary.urlsErrored} |`,
    `| URLs not yet requested | ${summary.urlsPending} |`,
    `| Checked in this run | ${summary.checkedThisRun} |`,
    `| Sitemap files read / failed | ${summary.sitemapFiles} / ${summary.sitemapFailures.length} |`,
    `| Scope | ${summary.scope} |`,
    '',
    summary.caution,
    '',
    '## URLs by sitemap type', '', '| Type | URLs |', '| --- | ---: |', table(summary.byType),
    '',
    '## Findings by code', '', '| Code | URLs |', '| --- | ---: |', table(issueCounts),
    '',
    '## Orphan analysis', '', '| Class | URLs |', '| --- | ---: |', table(orphanCounts),
  ];
  if (summary.status !== 'COMPLETE') {
    lines.push('', '## Resume', '', 'Run again with `--resume` (workflow input `resume_run_id`) to check the remaining and errored URLs; URLs with a definitive answer are not requested again.');
  }
  return lines.join('\n') + '\n';
}
