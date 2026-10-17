#!/usr/bin/env node
/**
 * Builds reports/seo-urls.csv (the golden URL inventory) from the repository baseline
 * docs/wordpress-baseline-2026-10-17.json. Paths only, so it replays against any host.
 *
 * What is NOT in the baseline and therefore NOT listed here (never guessed):
 *   - individual blog posts (160)           -> sampled from the live post sitemap by discover.mjs
 *   - featured_item single permalinks (15)  -> read from featured_item-sitemap.xml by discover.mjs
 *
 *   node tools/seo/make-urls-csv.mjs > reports/seo-urls.csv
 */
import { readFile } from 'node:fs/promises';

const baseline = JSON.parse(await readFile(new URL('../../docs/wordpress-baseline-2026-10-17.json', import.meta.url), 'utf8'));
const rows = [];
const add = (type, path, source, extra = {}) => rows.push({ type, path, source, expected_status: 200, expected_robots: '', notes: '', ...extra });
const pathOf = (link) => new URL(link).pathname;

add('home', '/', 'baseline: page 562 is the static front page');
for (const t of baseline.taxonomies.category) {
	const note = t.slug === 'uncategorized' ? 'default bucket: Yoast may noindex it, only status is enforced' : '';
	add('category', pathOf(t.link), `baseline: category ${t.id}`, t.slug === 'uncategorized' ? { expected_robots: 'any', notes: note } : {});
}
for (const p of baseline.pages) {
	if (p.ID === 562) continue;
	const path = { 3040: '/shop/' }[p.ID] ?? null;
	const item = baseline.menus.main_items.find((m) => m.object === 'page' && m.object_id === p.ID);
	add(p.ID === 3040 ? 'shop' : 'page', path ?? (item ? pathOf(item.link) : `/${p.post_name}/`), `baseline: page ${p.ID}`);
}
// Pages whose URL is not in the menu but is documented in the README protected list.
const known = new Set(rows.map((r) => r.path));
for (const [path, id] of [['/faq/', 484], ['/bo-suu-tap/', 494], ['/chinh-sach-bao-mat/', 326], ['/dieu-khoan-su-dung/', 449]]) {
	if (!known.has(path)) add('page', path, `README: page ${id}`);
}
for (const t of baseline.taxonomies.featured_item_category) {
	add('featured_term', `/bo-suu-tap/${t.slug}/`, `baseline: featured_item_category ${t.term_id}`);
}
for (const t of baseline.taxonomies.product_cat.filter((x) => x.slug !== 'uncategorized')) {
	add('product_cat', t.parent ? `/danh-muc-san-pham/loai-xe/${t.slug}/` : `/danh-muc-san-pham/${t.slug}/`, `baseline: product_cat ${t.term_id}`);
}
for (const p of baseline.products) add('product', `/san-pham/${p.post_name}/`, `baseline: product ${p.ID}`);
add('search', '/?s=thue+xe+may', 'SEO-HULK: legitimate search page', { expected_robots: 'noindex' });
add('404', '/404-invalid-nonexistent/', 'SEO-HULK: 404 must remain 404', { expected_status: 404, expected_robots: 'noindex' });

// de-duplicate by path, keep first
const seen = new Set();
const out = rows.filter((r) => !seen.has(r.path) && seen.add(r.path));
const esc = (v) => (/[",\n]/.test(String(v)) ? `"${String(v).replace(/"/g, '""')}"` : String(v));
const cols = ['type', 'path', 'source', 'expected_status', 'expected_robots', 'notes'];
console.log(cols.join(','));
out.forEach((r) => console.log(cols.map((c) => esc(r[c])).join(',')));
