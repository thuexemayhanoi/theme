#!/usr/bin/env node
/**
 * Builds reports/release-gates.md from recorded results (reports/results/*.json).
 * A gate with no recorded result is NOT_TESTED. Nothing here is hand-edited and no gate can turn
 * into PASS without a recorded run. "Blocking" gates must all PASS before a go-live proposal.
 */
import { readFile, writeFile, readdir } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';

const root = new URL('../../', import.meta.url);
const results = {};
try {
	for (const f of await readdir(new URL('reports/results/', root))) {
		if (f.endsWith('.json')) {
			const r = JSON.parse(await readFile(new URL(`reports/results/${f}`, root), 'utf8'));
			results[r.gate] = r;
		}
	}
} catch {
	/* no results yet */
}

const exists = async (p) => readFile(new URL(p, root), 'utf8').then(() => true, () => false);

// featured-items needs both the PHPUnit part and the round trip
{
	const a = results['featured-items-phpunit'];
	const b = results['featured-roundtrip'];
	if (a || b) {
		const parts = [a, b].filter(Boolean);
		const status = parts.length < 2 ? 'NOT_TESTED' : parts.every((x) => x.status === 'PASS') ? 'PASS' : parts.some((x) => x.status === 'FAIL') ? 'FAIL' : 'NOT_TESTED';
		results['featured-items'] = { status, detail: `PHPUnit: ${a?.status ?? 'NOT_TESTED'} (${a?.detail ?? '-'}); round trip: ${b?.status ?? 'NOT_TESTED'} (${b?.detail ?? '-'})`, at: new Date().toISOString() };
	}
}

// ---- static gates computed here ----
const progress = await readFile(new URL('docs/PROGRESS.md', root), 'utf8').catch(() => '');
results['live-unchanged'] = {
	status: /LIVE_UNCHANGED\s*=\s*TRUE/i.test(progress) ? 'PASS' : 'FAIL',
	detail: /LIVE_UNCHANGED\s*=\s*TRUE/i.test(progress) ? 'docs/PROGRESS.md records LIVE_UNCHANGED = TRUE (no activation, DB, option or file change on production)' : 'LIVE_UNCHANGED marker missing',
};
for (const [gate, file] of [['rollback-doc', 'docs/ROLLBACK.md'], ['deploy-doc', 'docs/DEPLOY.md'], ['legacy-matrix-doc', 'docs/LEGACY-COMPAT.md'], ['staging-checklist', 'docs/STAGING-CHECKLIST.md']]) {
	results[gate] = (await exists(file)) ? { status: 'PASS', detail: `${file} exists` } : { status: 'FAIL', detail: `${file} missing` };
}
try {
	const files = execFileSync('git', ['ls-files'], { cwd: new URL('.', root), encoding: 'utf8' }).split('\n').filter((f) => f && !/\.(woff2|png|jpg|webp|lock)$/.test(f) && !f.startsWith('reports/screenshots/') && f !== 'tools/qa/release-gates.mjs');
	const patterns = [/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/, /\bghp_[A-Za-z0-9]{30,}\b/, /\bAKIA[0-9A-Z]{16}\b/, /\bxox[baprs]-[A-Za-z0-9-]{10,}/, /(?:application[_ -]?password|webhook[_ -]?secret)\s*[:=]\s*["'][^"']{8,}["']/i];
	const hits = [];
	for (const f of files) {
		const text = await readFile(new URL(f, root), 'utf8').catch(() => '');
		if (patterns.some((p) => p.test(text))) hits.push(f);
	}
	results['secret-scan'] = hits.length ? { status: 'FAIL', detail: `possible secret in: ${hits.join(', ')}` } : { status: 'PASS', detail: `${files.length} tracked files scanned for keys/tokens/passwords: none found` };
} catch {
	results['secret-scan'] = { status: 'NOT_TESTED', detail: 'git not available' };
}

/** blocking = must be PASS before anyone may propose go-live. */
const GATES = [
	['php-lint', 'PHP syntax of every theme and plugin file', true],
	['phpcs', 'WordPress coding standards (PHPCS)', false],
	['unit', 'PHPUnit unit tests (pure libraries, structure and SEO-contract guards)', true],
	['js-lint', 'ESLint on theme JS, tools and tests', false],
	['node-tests', 'Node tests of the SEO snapshot / check / diff tooling', true],
	['css-valid', 'CSS parses, no @import, no external url(), tokens defined', false],
	['budgets', 'Asset byte budgets (render-blocking path)', false],
	['contrast', 'WCAG AA contrast of the design tokens', true],
	['integration', 'PHPUnit integration against the disposable WordPress (menus, terms, counts, legacy render, Yoast coexistence)', true],
	['menu-36', 'Menu #67: all 36 items with identical parent ids, 3 levels rendered', true],
	['categories-26', 'Blog categories: 26 terms with identical parents', true],
	['product-terms', 'WooCommerce product_cat tree 70 > 71/72/73', true],
	['featured-items', 'featured_item: 15 items, 4 terms with correct hierarchy and URLs (PHPUnit) AND the capture-then-replay round trip', true],
	['legacy-render', 'Legacy shortcode pages render without raw shortcodes and keep the H1', true],
	['seo-rules-sandbox', 'Absolute SEO rules on the sandbox snapshot (one canonical/H1/main, no duplicate schema, noindex only where intended, real 404)', true],
	['seo-diff-live', 'SEO before/after diff against the LIVE site (title, description, canonical, robots, H1, schema, links, sitemap)', true],
	['php-runtime-log', 'No PHP warning, notice or deprecation raised by theme or plugin code while the sandbox pipeline ran (WP_DEBUG_LOG)', true],
	['e2e-layout', 'Playwright: 360/390/430/768/1280/1440 px, no horizontal overflow, no console errors', true],
	['e2e-menu', 'Playwright: 36-item menu keyboard operation, drawer focus trap, aria-expanded', true],
	['e2e-contact', 'Playwright: single contact bar, no overlap with chat bubble / QuickCall simulator', true],
	['e2e-woo', 'Playwright: product page, add to cart, cart page, contact fallback for unpriced product', true],
	['e2e-search-404', 'Playwright: search results, 404 status and content, noindex on both', false],
	['e2e-blog', 'Playwright: category ancestry, pagination, post template, TOC, related posts', true],
	['e2e-seo-head', 'Playwright: one canonical/title, Yoast graph with merged LocalBusiness, robots.txt, sitemap, feed', true],
	['a11y-axe', 'axe-core (WCAG 2.2 A/AA + best practice): no critical or serious violations at 390 and 1280 px', true],
	['link-check', 'Internal link check over the sandbox crawl (no 404/500)', true],
	['lighthouse', 'Lighthouse lab run (home, post, category, product)', false],
	['yoast-premium', 'Yoast SEO Premium behaviour (not obtainable in the build environment)', false],
	['quickcall-real', 'Real Moto QuickCall plugin compatibility (plugin not obtainable)', true],
	['motoai-real', 'Real MotoAI chat widget compatibility (plugin not obtainable)', true],
	['flatsome-real-content', 'Rendering of the real raw post_content of page #562 and siblings (needs staging with real DB)', true],
	['live-unchanged', 'Production untouched: no activation, DB/option/file change, webhook or merge', true],
	['secret-scan', 'No credentials, tokens or keys in tracked files', true],
	['rollback-doc', 'Rollback plan documented', true],
	['deploy-doc', 'Deployment plan documented', true],
	['legacy-matrix-doc', 'Shortcode compatibility matrix documented', true],
	['staging-checklist', 'Owner staging checklist documented', true],
];

const NOT_OBTAINABLE = {
	'yoast-premium': 'Yoast SEO Premium is a paid plugin and could not be installed in the build container.',
	'quickcall-real': 'Moto QuickCall Pro+ Social is not publicly obtainable; only a layout simulator was tested (see e2e-contact).',
	'motoai-real': 'MotoAI Chat Widget is not publicly obtainable; only a layout simulator was tested (see e2e-contact).',
	'flatsome-real-content': 'The raw post_content of page #562 is not readable without authenticated access; run `wp nguyentu legacy-scan` and the staging checklist on a copy of the production database.',
	'seo-diff-live': 'The build container cannot reach https://thuexemaynguyentu.com (egress policy returns 403). Run the manual workflow seo-snapshot-live or tools/seo/snapshot.mjs from an allowed network.',
	lighthouse: 'Lighthouse could not be run in this environment unless a result is recorded.',
};

const rows = GATES.map(([id, title, blocking]) => {
	const r = results[id];
	return { id, title, blocking, status: r?.status ?? 'NOT_TESTED', detail: r?.detail ?? NOT_OBTAINABLE[id] ?? 'No result recorded.', at: r?.at ?? '', evidence: r?.evidence ?? '' };
});
const count = (s) => rows.filter((r) => r.status === s).length;
const blockers = rows.filter((r) => r.blocking && r.status !== 'PASS');

const md = [
	'# Release gates',
	'',
	'> Generated by `tools/qa/release-gates.mjs` from `reports/results/*.json` on ' + new Date().toISOString().slice(0, 10) + '. PASS only means a recorded run passed. NOT_TESTED means nothing was run: it is never a pass.',
	'',
	`**PASS ${count('PASS')} · FAIL ${count('FAIL')} · NOT_TESTED ${count('NOT_TESTED')}**`,
	'',
	blockers.length === 0
		? '**Verdict: all blocking gates PASS. A go-live PROPOSAL may be made to the owner (activation still needs the owner\'s explicit approval).**'
		: `**Verdict: NOT READY FOR GO-LIVE. ${blockers.length} blocking gate(s) are not PASS:** ${blockers.map((b) => `\`${b.id}\` (${b.status})`).join(', ')}.`,
	'',
	'| Gate | Blocking | Status | Detail |',
	'| --- | :-: | :-: | --- |',
	...rows.map((r) => `| \`${r.id}\` — ${r.title} | ${r.blocking ? 'yes' : 'no'} | **${r.status}** | ${String(r.detail).replace(/\|/g, '\\|').replace(/\n/g, ' ').slice(0, 260)}${r.evidence ? ` (evidence: \`${r.evidence}\`)` : ''} |`),
	'',
	'## How a gate becomes PASS',
	'',
	'Run `bash tools/qa/run-all.sh` (no sandbox needed) and `bash tools/qa/run-sandbox.sh` (needs `tools/sandbox/setup.sh` + `seed.sh`); each step records its own result with `tools/qa/record.mjs`. Gates that need the live site or paid/private plugins stay NOT_TESTED until someone with access runs them; the exact manual steps are in `docs/STAGING-CHECKLIST.md`.',
	'',
].join('\n');

await writeFile(new URL('reports/release-gates.md', root), md);
console.log(md.split('\n').slice(4, 8).join('\n'));
console.log(`Wrote reports/release-gates.md (${rows.length} gates).`);
