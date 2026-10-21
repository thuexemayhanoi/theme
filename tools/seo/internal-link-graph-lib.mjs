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
