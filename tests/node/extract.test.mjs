import { test } from 'node:test';
import assert from 'node:assert/strict';
import { extractPage, parseRobots, parseSitemap, pathOf } from '../../tools/seo/lib.mjs';
import { page, graph } from './helpers.mjs';

const run = (html, extra = {}) => extractPage({ html, url: 'https://example.test/x/', status: 200, headers: {}, type: 'page', ...extra });

test('extracts the Yoast-owned head signals', () => {
	const p = run(page());
	assert.equal(p.lang, 'vi');
	assert.deepEqual(p.titles, ['Cho thuê xe máy Hà Nội | Nguyễn Tú']);
	assert.equal(p.canonicals.length, 1);
	assert.deepEqual(p.robots, ['index, follow']);
	assert.deepEqual(p.h1, ['Cho Thuê Xe Máy Hà Nội']);
	assert.equal(p.landmarks.main, 1);
	assert.equal(p.skip_link, true);
	assert.equal(p.breadcrumb, true);
});

test('reads the JSON-LD graph, types and duplicate ids', () => {
	const p = run(page());
	assert.equal(p.jsonld.blocks, 1);
	assert.equal(p.jsonld.graph_blocks, 1);
	assert.equal(p.jsonld.top_level_type_counts.LocalBusiness, 1);
	assert.equal(p.jsonld.top_level_type_counts.Organization, 1);
	assert.deepEqual(p.jsonld.duplicate_ids, []);
	const dup = run(page({ ld: graph([{ '@type': 'WebSite', '@id': 'https://example.test/#organization' }]) }));
	assert.deepEqual(dup.jsonld.duplicate_ids, ['https://example.test/#organization']);
});

test('flags invalid JSON-LD and rating markup', () => {
	assert.equal(run(page({ ld: '{broken' })).jsonld.invalid, 1);
	const p = run(page({ ld: graph([{ '@type': 'Product', aggregateRating: { '@type': 'AggregateRating', ratingValue: 5 } }]) }));
	assert.equal(p.jsonld.has_aggregate_rating, true);
});

test('detects raw legacy shortcodes in visible text but not in scripts or comments', () => {
	const leaked = run(page({ body: 'Xin chào [ux_banner height="300px"] và [button text="Gọi"] [/ux_banner]' }));
	assert.ok(leaked.raw_shortcodes.length >= 2);
	const safe = run(page({ body: '<!-- [ux_banner] --><script>var a="[ux_banner]"</script>Bình thường' }));
	assert.deepEqual(safe.raw_shortcodes, []);
});

test('NAP, links and images are summarised', () => {
	const p = run(page({ body: '<a href="/lien-he/">Liên hệ</a> <a href="https://other.test/">x</a> <a href="/lien-he/">again</a>' }));
	assert.equal(p.nap.phone_in_text, true);
	assert.equal(p.nap.address_in_text, true);
	assert.deepEqual(p.nap.tel_links, ['tel:0942467674']);
	assert.ok(p.links.internal_targets.includes('/lien-he/'));
	assert.equal(p.links.external_count, 1);
	assert.equal(p.images.total, 1);
	assert.equal(p.images.missing_alt, 0);
});

test('pathOf is host agnostic and normalises trailing slashes', () => {
	assert.equal(pathOf('https://a.test/blog/xe-dien'), '/blog/xe-dien/');
	assert.equal(pathOf('http://127.0.0.1:8088/blog/xe-dien/'), '/blog/xe-dien/');
	assert.equal(pathOf('https://a.test/sitemap_index.xml'), '/sitemap_index.xml');
	assert.equal(pathOf('https://a.test/?s=xe'), '/?s=xe');
});

test('robots.txt and sitemap parsing', () => {
	const r = parseRobots('User-agent: *\nDisallow: /wp-admin/\nSitemap: https://a.test/sitemap_index.xml\n');
	assert.deepEqual(r.sitemaps, ['https://a.test/sitemap_index.xml']);
	assert.ok(!r.blocks_everything);
	assert.ok(parseRobots('User-agent: *\nDisallow: /\n').blocks_everything);
	const sm = parseSitemap('<sitemapindex><sitemap><loc>https://a.test/post-sitemap.xml</loc></sitemap></sitemapindex>');
	assert.equal(sm.is_index, true);
	assert.deepEqual(sm.locs, ['https://a.test/post-sitemap.xml']);
});
