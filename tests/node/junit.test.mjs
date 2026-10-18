import { test } from 'node:test';
import assert from 'node:assert/strict';
import { gateResult, parseJUnit } from '../../tools/qa/lib/junit.mjs';

const xml = `<?xml version="1.0" encoding="UTF-8"?>
<testsuites><testsuite name="integration">
<testcase name="test_menu_has_36_items" class="MenuTest" classname="MenuTest" assertions="3"/>
<testcase name="test_menu_levels" class="MenuTest" classname="MenuTest"><skipped/></testcase>
<testcase name="test_blog_root" class="TaxonomyTest" classname="TaxonomyTest"><failure type="PHPUnit\\Framework\\ExpectationFailedException">Failed asserting that 25 matches expected 26.</failure></testcase>
<testcase name="test_product_category_tree" class="TaxonomyTest" classname="TaxonomyTest"><skipped/></testcase>
<testcase name="test_render_pages" class="LegacyRenderTest" classname="LegacyRenderTest"><error type="Error">Call to undefined function</error></testcase>
</testsuite></testsuites>`;

const cases = parseJUnit(xml);
const byClass = (re) => (c) => re.test(c.cls);

test('parses passed, skipped, failed and errored cases', () => {
	assert.equal(cases.length, 5);
	assert.deepEqual(cases.map((c) => [c.failed, c.skipped]), [[false, false], [false, true], [true, false], [false, true], [true, false]]);
	assert.match(cases[2].message, /expected 26/);
});

test('a gate with a passing case and a skipped one passes and reports the skip', () => {
	const r = gateResult(cases, byClass(/MenuTest/));
	assert.equal(r.status, 'PASS');
	assert.match(r.detail, /1 test\(s\) passed, 1 skipped/);
});

test('a gate whose matching tests were all skipped is NOT_TESTED, never PASS', () => {
	const r = gateResult(cases, (c) => c.name === 'test_product_category_tree');
	assert.equal(r.status, 'NOT_TESTED');
	assert.match(r.detail, /skipped/);
});

test('a gate that matches no test is NOT_TESTED', () => {
	const r = gateResult(cases, byClass(/DoesNotExist/));
	assert.equal(r.status, 'NOT_TESTED');
	assert.match(r.detail, /no matching tests/);
});

test('failures and errors fail the gate and name the test', () => {
	const r = gateResult(cases, byClass(/TaxonomyTest/));
	assert.equal(r.status, 'FAIL');
	assert.match(r.detail, /1\/1 failed, 1 skipped: TaxonomyTest::test_blog_root/);
	assert.equal(gateResult(cases, byClass(/LegacyRenderTest/)).status, 'FAIL');
});

test('the catch-all gate ignores skipped cases when it counts executed tests', () => {
	const r = gateResult(cases, () => true);
	assert.equal(r.status, 'FAIL');
	assert.match(r.detail, /2\/3 failed, 2 skipped/);
});
