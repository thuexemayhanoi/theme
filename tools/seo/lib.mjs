/**
 * Shared SEO extraction: turns one HTML response into a flat, diff-friendly record.
 * No network access here; snapshot.mjs fetches, check.mjs and diff.mjs consume the records.
 */
import * as cheerio from 'cheerio';
import { createHash } from 'node:crypto';

/** Shortcode-looking tokens that must never reach visitors (Flatsome UX Builder tags). */
export const RAW_SHORTCODE_RE =
	/\[\/?(?:ux_[a-z0-9_]+|row|col|section|row_inner|col_inner|gap|divider|title|text_box|button|accordion(?:-item)?|tabgroup|tab|message_box|scroll_to|blog_posts|ux_products|ux_banner)(?=[\s\]/])[^\]]*\]/gi;

export const PHONE_RE = /0942\s?467\s?674|\+84\s?942\s?467\s?674/;
export const ADDRESS_RE = /112\s+Nguyễn\s+Văn\s+Cừ/i;

const sha = (text) => createHash('sha256').update(text).digest('hex').slice(0, 16);

/** Normalise a URL to host-agnostic "path?query" with a single trailing slash on directories. */
export function pathOf(href, base = 'http://x.invalid/') {
	try {
		const u = new URL(href, base);
		let p = u.pathname.replace(/\/{2,}/g, '/');
		if (!/\.[a-z0-9]{2,5}$/i.test(p) && !p.endsWith('/')) p += '/';
		return p + u.search;
	} catch {
		return href;
	}
}

function collectTypes(node, out) {
	if (Array.isArray(node)) {
		node.forEach((n) => collectTypes(n, out));
		return;
	}
	if (node && typeof node === 'object') {
		const t = node['@type'];
		if (t) (Array.isArray(t) ? t : [t]).forEach((x) => out.push({ type: String(x), id: node['@id'] ?? null }));
		Object.values(node).forEach((v) => {
			if (v && typeof v === 'object') collectTypes(v, out);
		});
	}
}

/** Top-level graph nodes only (entities), not nested value objects such as PostalAddress. */
function topNodes(parsed) {
	if (Array.isArray(parsed)) return parsed.flatMap(topNodes);
	if (parsed && typeof parsed === 'object') {
		if (Array.isArray(parsed['@graph'])) return parsed['@graph'];
		return [parsed];
	}
	return [];
}

/**
 * @param {{html:string,url:string,status:number,headers?:Record<string,string>,finalUrl?:string,type?:string}} input
 */
export function extractPage({ html, url, status, headers = {}, finalUrl, type = '' }) {
	const base = finalUrl || url;
	const $ = cheerio.load(html);
	const host = new URL(base).host;

	// ---- head ----
	const titles = $('head title, title').map((_, e) => $(e).text().trim()).get();
	const attrs = (sel, attr) => $(sel).map((_, e) => $(e).attr(attr) ?? '').get();
	const descriptions = attrs('meta[name="description" i]', 'content');
	const robots = [...attrs('meta[name="robots" i]', 'content'), ...attrs('meta[name="googlebot" i]', 'content')];
	const canonicals = attrs('link[rel~="canonical"]', 'href');
	const social = { og: {}, twitter: {} };
	$('meta[property^="og:"]').each((_, e) => (social.og[$(e).attr('property')] ??= $(e).attr('content') ?? ''));
	$('meta[name^="twitter:"]').each((_, e) => (social.twitter[$(e).attr('name')] ??= $(e).attr('content') ?? ''));
	const hreflang = $('link[rel="alternate"][hreflang]').map((_, e) => ({ lang: $(e).attr('hreflang'), href: $(e).attr('href') })).get();

	// ---- structured data ----
	const blocks = [];
	$('script[type="application/ld+json"]').each((_, e) => {
		const raw = $(e).contents().text();
		try {
			blocks.push({ ok: true, class: $(e).attr('class') ?? '', parsed: JSON.parse(raw) });
		} catch (err) {
			blocks.push({ ok: false, class: $(e).attr('class') ?? '', error: String(err.message), parsed: null });
		}
	});
	const typeHits = [];
	const nodeIds = [];
	let graphBlocks = 0;
	for (const b of blocks) {
		if (!b.ok) continue;
		collectTypes(b.parsed, typeHits);
		topNodes(b.parsed).forEach((n) => n && n['@id'] && nodeIds.push(n['@id']));
		if (b.parsed && !Array.isArray(b.parsed) && Array.isArray(b.parsed['@graph'])) graphBlocks += 1;
	}
	const typeCounts = {};
	typeHits.forEach(({ type: t }) => (typeCounts[t] = (typeCounts[t] ?? 0) + 1));
	const idCounts = {};
	nodeIds.forEach((i) => (idCounts[i] = (idCounts[i] ?? 0) + 1));
	const topLevelTypeCounts = {};
	blocks.forEach((b) => {
		if (!b.ok) return;
		topNodes(b.parsed).forEach((n) => {
			const t = n?.['@type'];
			(Array.isArray(t) ? t : t ? [t] : []).forEach((x) => (topLevelTypeCounts[x] = (topLevelTypeCounts[x] ?? 0) + 1));
		});
	});

	// ---- body ----
	const $body = cheerio.load($.html($('body')));
	$body('script, style, noscript, template').remove();
	const text = $body('body').text().replace(/\s+/g, ' ').trim();
	const h1 = $('h1').map((_, e) => $(e).text().replace(/\s+/g, ' ').trim()).get();
	const headingCounts = {};
	const outline = [];
	$('h1,h2,h3,h4,h5,h6').each((_, e) => {
		const level = Number(e.tagName.slice(1));
		headingCounts[`h${level}`] = (headingCounts[`h${level}`] ?? 0) + 1;
		if (outline.length < 80) outline.push({ level, text: $(e).text().replace(/\s+/g, ' ').trim().slice(0, 120) });
	});
	const landmarks = {};
	['main', 'header', 'nav', 'footer', 'aside'].forEach((t) => (landmarks[t] = $(t).length));
	landmarks.role_main = $('[role="main"]').length;

	// ---- links ----
	const links = [];
	$('a[href]').each((_, e) => {
		const href = ($(e).attr('href') ?? '').trim();
		if (!href || href.startsWith('#') || /^(javascript:|mailto:|tel:|sms:)/i.test(href)) return;
		let abs;
		try {
			abs = new URL(href, base);
		} catch {
			return;
		}
		links.push({
			href: abs.href.split('#')[0],
			path: pathOf(abs.href),
			internal: abs.host === host || abs.host === `www.${host}` || `www.${abs.host}` === host,
			text: $(e).text().replace(/\s+/g, ' ').trim().slice(0, 100),
			rel: ($(e).attr('rel') ?? '').trim(),
		});
	});
	const internal = links.filter((l) => l.internal);
	const external = links.filter((l) => !l.internal);
	const telLinks = $('a[href^="tel:"]').map((_, e) => $(e).attr('href')).get();
	const zaloLinks = $('a[href*="zalo.me"]').map((_, e) => $(e).attr('href')).get();
	const mapsLinks = $('a[href*="maps.google"], a[href*="google.com/maps"], a[href*="goo.gl/maps"]').map((_, e) => $(e).attr('href')).get();

	// ---- images ----
	const imgs = $('img').toArray();
	const images = {
		total: imgs.length,
		missing_alt: imgs.filter((e) => $(e).attr('alt') === undefined).length,
		empty_alt: imgs.filter((e) => ($(e).attr('alt') ?? 'x') === '').length,
		missing_dimensions: imgs.filter((e) => !$(e).attr('width') || !$(e).attr('height')).length,
		lazy: imgs.filter((e) => $(e).attr('loading') === 'lazy').length,
		high_priority: imgs.filter((e) => $(e).attr('fetchpriority') === 'high').length,
		first_loading: imgs[0] ? ($(imgs[0]).attr('loading') ?? 'auto') : null,
	};

	const rawShortcodes = [...new Set([...(text.match(RAW_SHORTCODE_RE) ?? [])])].slice(0, 20);
	const breadcrumb = $('nav[aria-label="Breadcrumb" i], #breadcrumbs, .yoast-breadcrumbs, .rank-math-breadcrumb, .breadcrumb').length > 0;

	return {
		url,
		path: pathOf(url),
		type,
		status,
		final_url: base,
		html_bytes: Buffer.byteLength(html),
		headers: {
			'content-type': headers['content-type'] ?? '',
			'cache-control': headers['cache-control'] ?? '',
			'x-robots-tag': headers['x-robots-tag'] ?? '',
			link: headers.link ?? '',
			'content-encoding': headers['content-encoding'] ?? '',
		},
		lang: $('html').attr('lang') ?? '',
		titles,
		descriptions,
		robots,
		canonicals,
		viewport: attrs('meta[name="viewport" i]', 'content'),
		social,
		hreflang,
		prev_next: $('link[rel="prev"], link[rel="next"]').map((_, e) => `${$(e).attr('rel')}:${$(e).attr('href')}`).get(),
		h1,
		heading_counts: headingCounts,
		outline,
		landmarks,
		skip_link: $('a.skip-link, a[href="#main"], a[href="#content"]').length > 0,
		breadcrumb,
		jsonld: {
			blocks: blocks.length,
			invalid: blocks.filter((b) => !b.ok).length,
			graph_blocks: graphBlocks,
			type_counts: Object.fromEntries(Object.entries(typeCounts).sort()),
			top_level_type_counts: Object.fromEntries(Object.entries(topLevelTypeCounts).sort()),
			duplicate_ids: Object.entries(idCounts).filter(([, n]) => n > 1).map(([id]) => id),
			has_aggregate_rating: Boolean(typeCounts.AggregateRating),
			has_review: Boolean(typeCounts.Review),
			hash: sha(JSON.stringify(blocks.map((b) => b.parsed))),
			blocks_raw: blocks.map((b) => ({ ok: b.ok, class: b.class, data: b.parsed })),
		},
		links: {
			internal_count: internal.length,
			external_count: external.length,
			nofollow_count: links.filter((l) => /nofollow/i.test(l.rel)).length,
			internal_targets: [...new Set(internal.map((l) => l.path))].sort(),
			sample: internal.slice(0, 400).map(({ path, text: t }) => ({ path, text: t })),
		},
		images,
		raw_shortcodes: rawShortcodes,
		nap: {
			phone_in_text: PHONE_RE.test(text),
			address_in_text: ADDRESS_RE.test(text),
			tel_links: [...new Set(telLinks)],
			zalo_links: [...new Set(zaloLinks)],
			maps_links: [...new Set(mapsLinks)],
		},
		forms: $('form').length,
		iframes: attrs('iframe', 'src'),
		scripts: { total: $('script[src]').length, inline: $('script:not([src])').length },
		stylesheets: $('link[rel="stylesheet"]').length,
		word_count: (text.match(/[\p{L}\p{N}]+/gu) ?? []).length,
		text_hash: sha(text),
	};
}

/** Parse robots.txt into the facts that matter for indexing. */
export function parseRobots(body) {
	const lines = body.split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
	return {
		bytes: Buffer.byteLength(body),
		hash: sha(body),
		sitemaps: lines.filter((l) => /^sitemap:/i.test(l)).map((l) => l.replace(/^sitemap:\s*/i, '')),
		disallow: lines.filter((l) => /^disallow:/i.test(l)).map((l) => l.replace(/^disallow:\s*/i, '')),
		blocks_everything: lines.some((l, i) => /^disallow:\s*\/\s*$/i.test(l) && lines.slice(0, i).reverse().find((x) => /^user-agent:/i.test(x))?.match(/\*/)),
	};
}

/** Extract <loc> values from a sitemap or sitemap index. */
export function parseSitemap(xml) {
	const locs = [...xml.matchAll(/<loc>\s*([^<]+?)\s*<\/loc>/gi)].map((m) => m[1].replace(/&amp;/g, '&'));
	return { is_index: /<sitemapindex/i.test(xml), locs };
}
