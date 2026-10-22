#!/usr/bin/env node
/**
 * Read-only probe: are the WordPress images added by PR #54 (hero 4990, service visuals 4987 and 4978) on the
 * live homepage, and if not, why? A handful of public GET requests, no cookies, no writes:
 *   1. the homepage as served (cache headers, image zones),
 *   2. the homepage with a unique query string (bypasses a page cache that keys on the URL),
 *   3. every stylesheet of that page, looking for the rules that PR #54 added,
 *   4. the public media endpoint for the three attachment ids.
 * It prints the evidence and a verdict; it never fails the job (the strict checks live in homepage-live.mjs).
 */
import { mkdirSync, writeFileSync } from 'node:fs';

const base = 'https://thuexemaynguyentu.com';
const ATTACHMENTS = [4990, 4987, 4978];
const ZONES = ['nt-home-mag__hero-visual', 'nt-home-mag__visuals', 'nt-home-mag__visual-card'];
const RULE = 'nt-home-mag__hero-visual';
const headers = { 'user-agent': 'NguyenTu-ImageProbe/1.0 (read-only; few GET requests)', accept: 'text/html,application/json,text/css,*/*' };

async function get(url) {
  const response = await fetch(url, { headers, redirect: 'manual', signal: AbortSignal.timeout(30000) });
  const text = await response.text();
  const pick = name => response.headers.get(name) || '';
  return {
    url, status: response.status, text,
    cache: { 'x-litespeed-cache': pick('x-litespeed-cache'), 'x-litespeed-cache-control': pick('x-litespeed-cache-control'), 'cache-control': pick('cache-control'), age: pick('age'), date: pick('date'), 'last-modified': pick('last-modified'), etag: pick('etag') },
  };
}

function describePage(page) {
  const html = page.text;
  const stylesheets = [...html.matchAll(/<link\b[^>]*rel=["']stylesheet["'][^>]*>/gi)].map(m => m[0].match(/href=["']([^"']+)["']/i)?.[1]).filter(Boolean).map(href => new URL(href.replace(/&#038;|&amp;/g, '&'), base).href);
  return {
    url: page.url, status: page.status, cache: page.cache, bytes: Buffer.byteLength(html),
    zones: Object.fromEntries(ZONES.map(zone => [zone, html.includes(zone)])),
    imgTags: (html.match(/<img\b/gi) || []).length,
    editorialCssLinks: stylesheets.filter(href => /home-editorial|\/assets\/css\//.test(href)).map(href => href.replace(base, '')),
    stylesheets: stylesheets.filter(href => href.startsWith(base)),
  };
}

const evidence = { checkedAt: new Date().toISOString(), base, pages: {}, css: [], media: {}, verdict: '' };
try {
  const served = await get(`${base}/`);
  const fresh = await get(`${base}/?nt-image-probe=${Date.now()}`);
  evidence.pages = { served: describePage(served), cacheBusted: describePage(fresh) };
  // The raw tag as the server sent it, to tell a missing attribute in the HTML from one dropped later in the browser.
  const heroTag = fresh.text.match(/<figure[^>]*nt-home-mag__hero-visual[^>]*>\s*(<img\b[^>]*>)/i)?.[1] ?? '';
  evidence.heroTag = { attributes: [...heroTag.matchAll(/\s([a-z-]+)=/gi)].map(m => m[1].toLowerCase()), fetchpriority: /fetchpriority=["']?high/i.test(heroTag), loading: heroTag.match(/loading=["']?(\w+)/i)?.[1] ?? '' };
  console.log(`hero <img> in the origin HTML: ${JSON.stringify(evidence.heroTag)}`);
  for (const [name, page] of Object.entries(evidence.pages)) console.log(`${name}: HTTP ${page.status}, ${page.bytes} bytes, ${page.imgTags} <img>, zones ${JSON.stringify(page.zones)}, cache ${JSON.stringify(page.cache)}`);
  for (const href of evidence.pages.cacheBusted.stylesheets.slice(0, 25)) {
    const css = await get(href);
    const entry = { href: href.replace(base, ''), status: css.status, bytes: Buffer.byteLength(css.text), hasHeroRule: css.text.includes(RULE) };
    evidence.css.push(entry);
    if (entry.hasHeroRule || /home-editorial/.test(href)) console.log(`css: ${entry.href} HTTP ${entry.status} ${entry.bytes} bytes hasHeroRule=${entry.hasHeroRule}`);
  }
  for (const id of ATTACHMENTS) {
    const media = await get(`${base}/wp-json/wp/v2/media/${id}?_fields=id,mime_type,media_type,source_url,alt_text,post,media_details.width,media_details.height`);
    let body = null;
    try { body = JSON.parse(media.text); } catch { /* not JSON */ }
    evidence.media[id] = { status: media.status, body: body && body.id ? body : String(media.text).slice(0, 160) };
    console.log(`media ${id}: HTTP ${media.status} ${JSON.stringify(evidence.media[id].body)}`);
  }
  const servedHas = evidence.pages.served.zones[ZONES[0]];
  const freshHas = evidence.pages.cacheBusted.zones[ZONES[0]];
  const cssHas = evidence.css.some(c => c.hasHeroRule);
  const mediaOk = ATTACHMENTS.every(id => evidence.media[id].status === 200 && /^image\//.test(evidence.media[id].body?.mime_type || ''));
  const mediaMissing = ATTACHMENTS.some(id => [404, 410].includes(evidence.media[id].status));
  if (servedHas) evidence.verdict = 'DISPLAYED: the served homepage contains the PR #54 hero zone.';
  else if (freshHas) evidence.verdict = 'STALE_PAGE_CACHE: a cache-busted request renders the new images but the served homepage does not. A LiteSpeed page-cache purge of the homepage is needed (WORDPRESS_ACTION_REQUIRED).';
  else if (!cssHas) evidence.verdict = 'THEME_NOT_DEPLOYED: no stylesheet served by the site contains the PR #54 rules (.nt-home-mag__hero-visual). The Deployer for Git release of c339a4b has not reached production, or CSS is cached/combined from an older build (WORDPRESS_ACTION_REQUIRED).';
  else if (mediaMissing) evidence.verdict = 'ATTACHMENT_MISSING: the theme is deployed but at least one of attachments 4990, 4987, 4978 does not exist in the live media library, so the template prints nothing (WORDPRESS_ACTION_REQUIRED).';
  else if (!mediaOk) evidence.verdict = 'INCONCLUSIVE: the theme CSS is live but the public media endpoint did not confirm the three attachments as images (it may be restricted).';
  else evidence.verdict = 'UNEXPECTED: CSS and attachments are present but the hero zone is not rendered, even on a cache-busted request. Check wp_attachment_is_image() and the front-page template on the live site.';
} catch (error) {
  evidence.verdict = `NOT_TESTED: ${String(error?.message ?? error)}`;
}
console.log(`VERDICT ${evidence.verdict}`);
mkdirSync('reports/homepage-image-probe', { recursive: true });
writeFileSync('reports/homepage-image-probe/evidence.json', JSON.stringify(evidence, null, 2) + '\n');
