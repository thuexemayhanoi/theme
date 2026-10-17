import { test } from 'node:test';
import assert from 'node:assert/strict';
import { extractPage } from '../../tools/seo/lib.mjs';
import { diffSnapshots, renderDiffMarkdown } from '../../tools/seo/diff.mjs';
import { page, graph } from './helpers.mjs';

const snap = (htmls, meta = {}) => ({
	meta: { base: 'https://example.test', mode: 'test', ...meta },
	robots: { status: 200, hash: 'r1' },
	sitemap: { status: 200, total_urls: 10 },
	urls: htmls.map((h, i) => ({ ...extractPage({ html: h, url: `https://example.test/p${i}/`, status: 200, headers: {}, type: 'page' }), expected_status: 200 })),
});
const fields = (res, sev) => res.findings.filter((f) => f.severity === sev).map((f) => f.field);

test('identical snapshots produce no findings', () => {
	const a = snap([page({ canonical: 'https://example.test/p0/' })]);
	const res = diffSnapshots(a, structuredClone(a));
	assert.equal(res.verified, true);
	assert.deepEqual(res.findings, []);
});

test('title, description, H1, canonical and robots drift are P0', () => {
	const before = snap([page({ canonical: 'https://example.test/p0/' })]);
	const after = snap([page({ title: 'Khác', description: 'Khác', h1: ['Khác'], canonical: 'https://example.test/q/', robots: 'noindex, follow' })]);
	const p0 = fields(diffSnapshots(before, after), 'P0');
	for (const f of ['title', 'description', 'h1', 'canonical', 'robots']) assert.ok(p0.includes(f), f);
});

test('LocalBusiness added to the Organization node is reported as P1 (review), not silently accepted', () => {
	const noLb = graph().replace('["Organization","LocalBusiness"]', '"Organization"');
	const before = snap([page({ ld: noLb, canonical: 'https://example.test/p0/' })]);
	const after = snap([page({ canonical: 'https://example.test/p0/' })]);
	const res = diffSnapshots(before, after);
	assert.ok(fields(res, 'P1').includes('schema-type-added'));
	assert.equal(res.summary.p0, 0);
});

test('losing a schema type or duplicating an entity is P0', () => {
	const before = snap([page({ canonical: 'https://example.test/p0/' })]);
	const after = snap([page({ ld: JSON.stringify({ '@graph': [{ '@type': 'WebPage', '@id': 'a' }] }), canonical: 'https://example.test/p0/' })]);
	assert.ok(fields(diffSnapshots(before, after), 'P0').includes('schema-type-lost'));
});

test('lost text, lost phone and raw shortcodes are P0', () => {
	const long = 'từ '.repeat(300);
	const before = snap([page({ body: long, canonical: 'https://example.test/p0/' })]);
	const after = snap([page({ body: 'ngắn [button text="x"]', canonical: 'https://example.test/p0/' })]);
	const p0 = fields(diffSnapshots(before, after), 'P0');
	assert.ok(p0.includes('text-lost'));
	assert.ok(p0.includes('raw-shortcode'));
});

test('a URL that stops answering 200 is P0 and robots.txt/sitemap regressions are P0', () => {
	const before = snap([page({ canonical: 'https://example.test/p0/' })]);
	const after = structuredClone(before);
	after.urls[0].status = 404;
	after.robots.hash = 'r2';
	after.sitemap.total_urls = 4;
	const p0 = fields(diffSnapshots(before, after), 'P0');
	assert.ok(p0.includes('status'));
	assert.ok(p0.includes('robots.txt'));
	assert.ok(p0.includes('total_urls'));
});

test('no baseline means UNVERIFIED and never a parity claim', () => {
	const res = diffSnapshots({ meta: { status: 'UNVERIFIED', reason: 'blocked' }, urls: [] }, snap([page()]));
	assert.equal(res.verified, false);
	const md = renderDiffMarkdown(res);
	assert.match(md, /UNVERIFIED/);
	assert.doesNotMatch(md, /No differences found/);
});
