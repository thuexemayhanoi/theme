#!/usr/bin/env node
/**
 * Extends the golden URL list with URLs that are NOT in the repository baseline, read from the
 * site's own Yoast sitemaps (GET only): a sample of blog posts and every featured_item single.
 *
 *   node tools/seo/discover.mjs --base https://thuexemaynguyentu.com --urls reports/seo-urls.csv \
 *        --out reports/seo-urls.full.csv --posts 12
 */
import { readFile, writeFile } from 'node:fs/promises';
import { parseArgs } from 'node:util';
import { parseSitemap, pathOf } from './lib.mjs';

const { values: opt } = parseArgs({ options: { base: { type: 'string' }, urls: { type: 'string', default: 'reports/seo-urls.csv' }, out: { type: 'string', default: 'reports/seo-urls.full.csv' }, posts: { type: 'string', default: '12' }, delay: { type: 'string', default: '300' } } });
if (!opt.base) {
	console.error('--base is required');
	process.exit(2);
}
const BASE = opt.base.replace(/\/+$/, '');
const UA = 'NguyenTu-SEO-Snapshot/1.0 (read-only audit; GET only)';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const fetchText = async (url) => {
	const res = await fetch(url.replace(/^https?:\/\/[^/]+/, BASE), { headers: { 'user-agent': UA }, signal: AbortSignal.timeout(30000) });
	if (!res.ok) throw new Error(`${res.status} ${url}`);
	return res.text();
};

const TYPE = { post: 'post', page: 'page', product: 'product', featured_item: 'featured_item', category: 'category', product_cat: 'product_cat', featured_item_category: 'featured_term' };
const existing = (await readFile(opt.urls, 'utf8')).trimEnd().split('\n');
const header = existing[0];
const known = new Set(existing.slice(1).map((l) => l.split(',')[1]));
const out = [...existing];

const index = parseSitemap(await fetchText(`${BASE}/sitemap_index.xml`));
for (const child of index.locs) {
	const name = child.split('/').pop().replace(/-sitemap\d*\.xml$/, '');
	const type = TYPE[name];
	if (!type) continue;
	await sleep(Number(opt.delay));
	let locs = parseSitemap(await fetchText(child)).locs.map((u) => pathOf(u));
	if (type === 'post') locs = locs.slice(0, Number(opt.posts));
	for (const path of locs) {
		if (known.has(path) || path === '/') continue;
		known.add(path);
		out.push([type, path, `sitemap: ${name}`, 200, '', ''].join(','));
	}
}
await writeFile(opt.out, `${out.join('\n')}\n`);
console.log(`${header}\nWrote ${opt.out}: ${out.length - 1} URLs (${out.length - existing.length} added from sitemaps).`);
