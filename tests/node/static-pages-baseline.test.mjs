import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { META_KEYS, validateLedger, expectedFor, expectedLinks, listDiff, diffMeta, describeDiffs } from '../../tools/qa/lib/static-baseline.mjs';
import { legacyTermPairs } from '../../tools/qa/homepage-legacy-parity.mjs';

const baseline = JSON.parse(readFileSync('reports/static-pages/before-2026-10-21.json', 'utf8'));
const ledger = JSON.parse(readFileSync('reports/static-pages/approved-changes-2026-10-22.json', 'utf8'));
const replacements = new Map(legacyTermPairs(baseline.site).map((p) => [p.old, p.canonical]));
const RUN = 'https://github.com/thuexemayhanoi/theme/actions/runs/39839840364';

const pages = [{ id: 1, title: 'Cũ', description: 'Mô tả', canonical: ['https://x.test/a/'], robots: 'index, follow', h1: ['A', 'B'], nav: ['https://x.test/', 'https://x.test/featured_item_category/thue-ngay/'], footer: ['https://x.test/lien-he/'] }];
const change = (extra = {}) => ({ id: 1, field: 'title', from: 'Cũ', to: 'Mới', classification: 'VALID_WORDPRESS_CHANGE', observedIn: RUN, evidence: ['Quan sát trực tiếp', 'Theme không đặt title'], ...extra });
const withChange = (extra) => ({ changes: [change(extra)] });

test('the committed ledger validates against the committed archived baseline', () => {
	const changes = validateLedger(ledger, baseline.pages);
	assert.ok(changes.length >= 2);
	assert.ok(changes.every((c) => baseline.pages.some((p) => p.id === c.id)));
});

test('ledger refuses fields that protect SEO: canonical, robots, nav, footer', () => {
	for (const field of ['canonical', 'robots', 'nav', 'footer']) {
		assert.throws(() => validateLedger(withChange({ field, from: pages[0][field], to: 'x' }), pages), /may be approved here/, field);
	}
});

test('ledger entries must be anchored to the archived value, evidence and an observing run', () => {
	assert.doesNotThrow(() => validateLedger(withChange(), pages));
	assert.throws(() => validateLedger(withChange({ from: 'Khác' }), pages), /stale entry/);
	assert.throws(() => validateLedger(withChange({ id: 9 }), pages), /not in the archived baseline/);
	assert.throws(() => validateLedger(withChange({ to: 'Cũ' }), pages), /equals "from"/);
	assert.throws(() => validateLedger(withChange({ to: '  ' }), pages), /non-empty/);
	assert.throws(() => validateLedger(withChange({ evidence: ['một dòng'] }), pages), /two evidence/);
	assert.throws(() => validateLedger(withChange({ observedIn: 'https://example.test/run/1' }), pages), /observedIn/);
	assert.throws(() => validateLedger(withChange({ classification: 'ANY' }), pages), /classification/);
	assert.throws(() => validateLedger({ changes: [change(), change()] }, pages), /duplicate/);
});

test('an approved H1 change must leave exactly one non-empty H1', () => {
	const h1 = (to) => withChange({ field: 'h1', from: ['A', 'B'], to });
	assert.doesNotThrow(() => validateLedger(h1(['A']), pages));
	assert.throws(() => validateLedger(h1([]), pages), /exactly one/);
	assert.throws(() => validateLedger(h1(['A', 'C']), pages), /exactly one/);
	assert.throws(() => validateLedger(h1(['  ']), pages), /exactly one/);
});

test('expectedFor applies only that page\'s approved changes and never mutates the baseline', () => {
	const copy = structuredClone(pages[0]);
	const expected = expectedFor(pages[0], withChange());
	assert.equal(expected.title, 'Mới');
	assert.deepEqual(pages[0], copy);
	assert.equal(expectedFor({ ...pages[0], id: 2 }, withChange()).title, 'Cũ');
});

test('verified aliases are replaced; an alias that comes back is a difference', () => {
	const alias = 'https://x.test/featured_item_category/thue-ngay/';
	const canonical = 'https://x.test/bo-suu-tap/thue-ngay/';
	const map = new Map([[alias, canonical]]);
	assert.deepEqual(expectedLinks(pages[0].nav, map), ['https://x.test/', canonical]);
	const live = { ...pages[0], nav: ['https://x.test/', canonical] };
	assert.deepEqual(diffMeta(pages[0], live, map), []);
	const regressed = diffMeta(pages[0], pages[0], map);
	assert.deepEqual(regressed.map((d) => d.key), ['nav']);
	assert.deepEqual(regressed[0].missing, [canonical]);
	assert.deepEqual(regressed[0].unexpected, [alias]);
	assert.equal(diffMeta(pages[0], live, new Map()).length, 1, 'without proof the new URL is not accepted');
});

test('every difference is reported, not only the first, and order changes are visible', () => {
	const live = { ...pages[0], title: 'Khác', robots: 'noindex', canonical: ['https://x.test/b/'], nav: [...pages[0].nav].reverse(), h1: ['A'] };
	const diffs = diffMeta(pages[0], live);
	assert.deepEqual(diffs.map((d) => d.key), ['title', 'canonical', 'robots', 'h1', 'nav']);
	assert.equal(diffs.find((d) => d.key === 'nav').reordered, true);
	assert.match(describeDiffs(1, 390, diffs), /1 @390: 5 baseline difference/);
	assert.deepEqual(listDiff(['a', 'b'], ['a', 'b']), { missing: [], unexpected: [], reordered: false });
});

test('committed baseline + ledger + proven aliases describe a live page without differences', () => {
	for (const page of baseline.pages) {
		const expected = expectedFor(page, ledger);
		const live = { ...expected, nav: expectedLinks(page.nav, replacements), footer: expectedLinks(page.footer, replacements) };
		assert.deepEqual(diffMeta(expected, live, replacements), [], `page ${page.id}`);
		assert.equal(expected.h1.length, 1, `page ${page.id} keeps exactly one H1`);
	}
	assert.equal(META_KEYS.length, 7);
});

test('the four legacy aliases are the only link changes the baseline needs', () => {
	const archivedTermLinks = new Set(baseline.pages.flatMap((p) => [...p.nav, ...p.footer]).filter((u) => u.includes('/featured_item_category/')));
	assert.deepEqual([...archivedTermLinks].sort(), [...replacements.keys()].sort());
});
