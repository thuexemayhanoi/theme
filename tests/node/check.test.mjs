import { test } from 'node:test';
import assert from 'node:assert/strict';
import { extractPage } from '../../tools/seo/lib.mjs';
import { checkSnapshot } from '../../tools/seo/check.mjs';
import { page, graph } from './helpers.mjs';

function snapshotOf(pages) {
	return {
		meta: { base: 'https://example.test', mode: 'test' },
		robots: { status: 200, sitemaps: ['https://example.test/sitemap_index.xml'], blocks_everything: false },
		sitemap: { status: 200, children: [{ name: 'post-sitemap.xml', status: 200, urls: 3 }] },
		urls: pages.map(({ html, type = 'page', status = 200, expected_status = 200, expected_robots = '', url = 'https://example.test/x/' }) => ({
			...extractPage({ html, url, status, headers: {}, type }),
			expected_status,
			expected_robots,
		})),
	};
}
const failing = (report) => report.results.flatMap((r) => r.checks.filter((c) => !c.ok).map((c) => c.id));

test('a well-formed page passes every P0 and P1 rule', () => {
	const report = checkSnapshot(snapshotOf([{ html: page() }]));
	assert.deepEqual(failing(report), []);
	assert.equal(report.summary.p0_fail, 0);
});

test('duplicate canonical, two H1s and two <main> are P0 failures', () => {
	const report = checkSnapshot(snapshotOf([{ html: page({ canonical: ['https://example.test/x/', 'https://example.test/y/'], h1: ['A', 'B'], main: 2 }) }]));
	const f = failing(report);
	assert.ok(f.includes('canonical-one'));
	assert.ok(f.includes('h1-one'));
	assert.ok(f.includes('main-one'));
	assert.ok(report.summary.p0_fail >= 3);
});

test('canonical drift to another URL fails', () => {
	const report = checkSnapshot(snapshotOf([{ html: page({ canonical: 'https://example.test/other/' }) }]));
	assert.ok(failing(report).includes('canonical-self'));
});

test('noindex on an indexable page fails, but is required for search and 404', () => {
	assert.ok(failing(checkSnapshot(snapshotOf([{ html: page({ robots: 'noindex, follow' }) }]))).includes('robots-index'));
	const search = snapshotOf([{ html: page({ robots: 'noindex, follow', canonical: null }), type: 'search', url: 'https://example.test/?s=a' }]);
	assert.deepEqual(failing(checkSnapshot(search)), []);
	const searchIndexed = snapshotOf([{ html: page({ robots: 'index, follow', canonical: null }), type: 'search', url: 'https://example.test/?s=a' }]);
	assert.ok(failing(checkSnapshot(searchIndexed)).includes('robots-noindex'));
});

test('a 404 must stay a 404 and not be a soft 200', () => {
	const soft = snapshotOf([{ html: page({ robots: 'noindex, follow', canonical: null }), type: '404', status: 200, expected_status: 404, url: 'https://example.test/nope/' }]);
	assert.ok(failing(checkSnapshot(soft)).includes('status'));
	const real = snapshotOf([{ html: page({ robots: 'noindex, follow', canonical: null }), type: '404', status: 404, expected_status: 404, url: 'https://example.test/nope/' }]);
	assert.equal(checkSnapshot(real).summary.p0_fail, 0);
});

test('competing schema, second LocalBusiness and invented ratings are P0', () => {
	const second = graph([{ '@type': 'LocalBusiness', '@id': 'https://example.test/#second' }]);
	assert.ok(failing(checkSnapshot(snapshotOf([{ html: page({ ld: second }) }]))).includes('jsonld-one-local-business'));
	const rated = graph([{ '@type': 'Product', review: { '@type': 'Review' } }]);
	assert.ok(failing(checkSnapshot(snapshotOf([{ html: page({ ld: rated }) }]))).includes('jsonld-no-invented-ratings'));
	assert.ok(failing(checkSnapshot(snapshotOf([{ html: page({ ld: '{bad' }) }]))).includes('jsonld-valid'));
});

test('raw shortcode leak is P0', () => {
	assert.ok(failing(checkSnapshot(snapshotOf([{ html: page({ body: '[row][col span="6"]x[/col][/row]' }) }]))).includes('no-raw-shortcodes'));
});

test('robots.txt that blocks everything fails the global check', () => {
	const s = snapshotOf([{ html: page() }]);
	s.robots.blocks_everything = true;
	const report = checkSnapshot(s);
	assert.ok(report.globals.some((g) => g.id === 'robots-open' && !g.ok));
	assert.ok(report.summary.p0_fail >= 1);
});

test('an unreachable URL is a failure, never a pass', () => {
	const s = snapshotOf([{ html: page() }]);
	s.urls.push({ url: 'https://example.test/down/', path: '/down/', type: 'page', status: 0, error: 'timeout', expected_status: 200 });
	const report = checkSnapshot(s);
	assert.ok(report.results.at(-1).checks.some((c) => c.id === 'fetch' && !c.ok));
});
