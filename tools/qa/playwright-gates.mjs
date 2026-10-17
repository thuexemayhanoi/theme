#!/usr/bin/env node
/**
 * Maps Playwright JSON reports to release gates and records them.
 *   node tools/qa/playwright-gates.mjs reports/results/raw/pw-*.json
 * A gate fed by several report files (the contact spec runs twice: simulators on and off) only
 * passes when every contributing test passed.
 */
import { readFile } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';

const specs = [];
function walk(suite, file) {
	const f = suite.file ?? file;
	for (const spec of suite.specs ?? []) {
		const tests = spec.tests ?? [];
		const ran = tests.filter((t) => !['skipped'].includes(t.status));
		specs.push({ file: String(f ?? '').split('/').pop(), title: spec.title, ran: ran.length, ok: ran.length > 0 && ran.every((t) => t.status === 'expected'), skipped: ran.length === 0 });
	}
	for (const child of suite.suites ?? []) walk(child, f);
}
for (const file of process.argv.slice(2)) {
	const json = JSON.parse(await readFile(file, 'utf8'));
	for (const s of json.suites ?? []) walk(s, s.file);
}

const GATES = {
	'e2e-layout': (s) => s.file === 'layout.spec.mjs',
	'e2e-menu': (s) => s.file === 'menu.spec.mjs',
	'e2e-contact': (s) => s.file === 'contact.spec.mjs',
	'e2e-woo': (s) => s.file === 'woo.spec.mjs',
	'e2e-blog': (s) => s.file === 'blog.spec.mjs',
	'e2e-seo-head': (s) => s.file === 'seo-head.spec.mjs',
	'a11y-axe': (s) => s.file === 'a11y.spec.mjs',
	'e2e-search-404': (s) => /search|404|not-found|noindex/i.test(s.title) && s.file !== 'screenshots.spec.mjs',
};
for (const [gate, pick] of Object.entries(GATES)) {
	const set = specs.filter(pick).filter((s) => !s.skipped);
	const bad = set.filter((s) => !s.ok);
	const status = set.length === 0 ? 'NOT_TESTED' : bad.length ? 'FAIL' : 'PASS';
	const detail = set.length === 0 ? 'no matching browser tests were run' : bad.length ? `${bad.length}/${set.length} failed: ${bad.slice(0, 4).map((b) => b.title).join(' | ').slice(0, 300)}` : `${set.length} browser test(s) passed`;
	execFileSync('node', ['tools/qa/record.mjs', gate, status, detail, 'playwright-report/index.html'], { stdio: 'inherit' });
}
