#!/usr/bin/env node
/**
 * Absolute SEO rules on ONE snapshot (no baseline needed). They encode the SEO-HULK contract:
 * exactly one canonical / H1 / <main>, a Yoast-style graph without duplicates, no raw shortcodes,
 * noindex only where intended, real 404s, NAP present, images with alt text.
 *
 *   node tools/seo/check.mjs reports/seo-after.json [--out reports/seo-rules-after.json]
 *
 * Exit code 1 when any P0 rule fails. Results are PASS / FAIL only for what was actually
 * fetched; a URL that could not be fetched is reported as FAIL, never as a pass.
 */
import { readFile, writeFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import { parseArgs } from 'node:util';
import { pathOf } from './lib.mjs';

const NOINDEX_TYPES = new Set(['search', '404']);
/** 'index' | 'noindex' | 'any' (from the URL list; defaults by page type). */
const expectedRobots = (p) => p.expected_robots || (NOINDEX_TYPES.has(p.type) ? 'noindex' : 'index');
const norm = (s) => String(s ?? '').replace(/\s+/g, ' ').trim();
const hasNoindex = (page) => [...page.robots, page.headers?.['x-robots-tag'] ?? ''].some((r) => /noindex/i.test(r));

/** @type {Array<{id:string,severity:'P0'|'P1'|'P2',title:string,applies?:(p:any)=>boolean,test:(p:any)=>{ok:boolean,message?:string}}>} */
export const RULES = [
	{
		id: 'status',
		severity: 'P0',
		title: 'HTTP status equals the expected status (200; 404 stays 404)',
		test: (p) => ({ ok: p.status === p.expected_status, message: `status ${p.status}, expected ${p.expected_status}` }),
	},
	{
		id: 'lang',
		severity: 'P1',
		title: '<html lang> is Vietnamese',
		applies: (p) => p.status === 200,
		test: (p) => ({ ok: /^vi/i.test(p.lang), message: `lang="${p.lang}"` }),
	},
	{
		id: 'title',
		severity: 'P0',
		title: 'exactly one non-empty <title>',
		test: (p) => ({ ok: p.titles.length === 1 && norm(p.titles[0]) !== '', message: `${p.titles.length} title tag(s)` }),
	},
	{
		// Two descriptions means the theme or a plugin prints a second one next to the SEO plugin's.
		id: 'description-single',
		severity: 'P0',
		title: 'never more than one meta description',
		applies: (p) => p.status === 200 && !NOINDEX_TYPES.has(p.type),
		test: (p) => ({ ok: p.descriptions.length <= 1, message: `${p.descriptions.length} description tags` }),
	},
	{
		// The description text belongs to the SEO plugin (Yoast). Absent is reported, not blamed on the
		// theme; whether the live site had one is decided by the before/after diff (tools/seo/diff.mjs).
		id: 'description',
		severity: 'P1',
		title: 'a meta description is present (owned by the SEO plugin)',
		applies: (p) => p.status === 200 && !NOINDEX_TYPES.has(p.type),
		test: (p) => ({ ok: p.descriptions.length === 1 && norm(p.descriptions[0]) !== '', message: `${p.descriptions.length} description tag(s): set one in the SEO plugin` }),
	},
	{
		id: 'canonical-one',
		severity: 'P0',
		title: 'exactly one rel=canonical (indexable pages)',
		applies: (p) => p.status === 200 && !NOINDEX_TYPES.has(p.type),
		test: (p) => ({ ok: p.canonicals.length === 1 && p.canonicals[0] !== '', message: `${p.canonicals.length} canonical tag(s)` }),
	},
	{
		id: 'canonical-self',
		severity: 'P0',
		title: 'canonical points to the page itself (no canonical drift)',
		applies: (p) => p.status === 200 && !NOINDEX_TYPES.has(p.type) && p.canonicals.length === 1,
		test: (p) => ({ ok: pathOf(p.canonicals[0]) === pathOf(p.final_url), message: `canonical ${pathOf(p.canonicals[0])} vs page ${pathOf(p.final_url)}` }),
	},
	{
		id: 'robots-index',
		severity: 'P0',
		title: 'indexable pages are not noindex',
		applies: (p) => p.status === 200 && expectedRobots(p) === 'index',
		test: (p) => ({ ok: !hasNoindex(p), message: `robots: ${p.robots.join(' | ') || '(none)'}` }),
	},
	{
		id: 'robots-noindex',
		severity: 'P0',
		title: 'internal search results and 404 pages are noindex',
		applies: (p) => expectedRobots(p) === 'noindex',
		test: (p) => ({ ok: hasNoindex(p), message: `robots: ${p.robots.join(' | ') || '(none)'}` }),
	},
	{
		id: 'h1-one',
		severity: 'P0',
		title: 'exactly one non-empty <h1>',
		applies: (p) => p.status === 200 || p.type === '404',
		test: (p) => ({ ok: p.h1.length === 1 && norm(p.h1[0]) !== '', message: `${p.h1.length} h1: ${p.h1.map((h) => `"${h}"`).join(', ')}` }),
	},
	{
		id: 'main-one',
		severity: 'P0',
		title: 'exactly one <main> landmark',
		applies: (p) => p.status === 200 || p.type === '404',
		test: (p) => ({ ok: p.landmarks.main === 1, message: `${p.landmarks.main} <main>` }),
	},
	{
		id: 'landmarks',
		severity: 'P1',
		title: 'header, nav and footer landmarks and a skip link are present',
		applies: (p) => p.status === 200 || p.type === '404',
		test: (p) => ({ ok: p.landmarks.header >= 1 && p.landmarks.nav >= 1 && p.landmarks.footer >= 1 && p.skip_link, message: JSON.stringify({ ...p.landmarks, skip_link: p.skip_link }) }),
	},
	{
		id: 'viewport',
		severity: 'P1',
		title: 'meta viewport is present',
		applies: (p) => p.status === 200 || p.type === '404',
		test: (p) => ({ ok: p.viewport.length === 1 && /width=device-width/.test(p.viewport[0]), message: p.viewport.join(' | ') }),
	},
	{
		id: 'jsonld-valid',
		severity: 'P0',
		title: 'every JSON-LD block parses',
		applies: (p) => p.status === 200,
		test: (p) => ({ ok: p.jsonld.invalid === 0, message: `${p.jsonld.invalid} invalid of ${p.jsonld.blocks}` }),
	},
	{
		id: 'jsonld-no-dup-ids',
		severity: 'P0',
		title: 'no entity @id is declared twice (no competing graphs)',
		applies: (p) => p.status === 200,
		test: (p) => ({ ok: p.jsonld.duplicate_ids.length === 0, message: p.jsonld.duplicate_ids.join(', ') }),
	},
	{
		id: 'jsonld-single-graph',
		severity: 'P0',
		title: 'at most one @graph block on the page',
		applies: (p) => p.status === 200,
		test: (p) => ({ ok: p.jsonld.graph_blocks <= 1, message: `${p.jsonld.graph_blocks} @graph blocks` }),
	},
	{
		id: 'jsonld-one-local-business',
		severity: 'P0',
		title: 'at most one LocalBusiness entity (never a second competing business node)',
		applies: (p) => p.status === 200,
		test: (p) => ({ ok: (p.jsonld.top_level_type_counts.LocalBusiness ?? 0) <= 1, message: `${p.jsonld.top_level_type_counts.LocalBusiness ?? 0} LocalBusiness nodes` }),
	},
	{
		id: 'jsonld-no-invented-ratings',
		severity: 'P0',
		title: 'no AggregateRating / Review markup (nothing may be invented)',
		applies: (p) => p.status === 200,
		test: (p) => ({ ok: !p.jsonld.has_aggregate_rating && !p.jsonld.has_review, message: 'rating/review markup found' }),
	},
	{
		id: 'no-raw-shortcodes',
		severity: 'P0',
		title: 'no raw legacy shortcode text is visible',
		applies: (p) => p.status === 200 || p.type === '404',
		test: (p) => ({ ok: p.raw_shortcodes.length === 0, message: p.raw_shortcodes.join(' ') }),
	},
	{
		id: 'img-alt',
		severity: 'P1',
		title: 'every <img> has an alt attribute',
		applies: (p) => p.status === 200,
		test: (p) => ({ ok: p.images.missing_alt === 0, message: `${p.images.missing_alt} of ${p.images.total} without alt` }),
	},
	{
		id: 'img-dimensions',
		severity: 'P1',
		title: 'images carry width and height (no layout shift)',
		applies: (p) => p.status === 200 && p.images.total > 0,
		test: (p) => ({ ok: p.images.missing_dimensions === 0, message: `${p.images.missing_dimensions} of ${p.images.total} without dimensions` }),
	},
	{
		id: 'nap',
		severity: 'P1',
		title: 'verified phone and address are visible (footer/header NAP)',
		applies: (p) => p.status === 200,
		test: (p) => ({ ok: p.nap.phone_in_text && p.nap.address_in_text && p.nap.tel_links.some((t) => t.includes('0942467674')), message: JSON.stringify(p.nap) }),
	},
	{
		id: 'breadcrumb',
		severity: 'P1',
		title: 'breadcrumb present on inner pages',
		applies: (p) => p.status === 200 && !['home'].includes(p.type) && !NOINDEX_TYPES.has(p.type),
		test: (p) => ({ ok: p.breadcrumb, message: 'no breadcrumb navigation' }),
	},
	{
		id: 'page-weight',
		severity: 'P1',
		title: 'HTML under 400 KB',
		applies: (p) => p.status === 200,
		test: (p) => ({ ok: p.html_bytes < 400 * 1024, message: `${Math.round(p.html_bytes / 1024)} KB` }),
	},
	{
		id: 'heading-order',
		severity: 'P2',
		title: 'no heading level is skipped (h1 -> h3)',
		applies: (p) => p.status === 200,
		test: (p) => {
			let prev = 0;
			for (const h of p.outline) {
				if (prev && h.level > prev + 1) return { ok: false, message: `h${prev} followed by h${h.level} ("${h.text}")` };
				prev = h.level;
			}
			return { ok: true };
		},
	},
];

/** Global checks on robots.txt and the sitemap index. */
export function checkGlobals(snapshot) {
	const out = [];
	const robots = snapshot.robots;
	out.push({ id: 'robots-200', severity: 'P0', title: '/robots.txt answers 200', ok: robots?.status === 200, message: `status ${robots?.status}` });
	if (robots?.status === 200) {
		out.push({ id: 'robots-open', severity: 'P0', title: 'robots.txt does not block the whole site', ok: !robots.blocks_everything, message: 'Disallow: / for *' });
		out.push({ id: 'robots-sitemap', severity: 'P1', title: 'robots.txt announces a sitemap', ok: (robots.sitemaps ?? []).length > 0, message: 'no Sitemap: line' });
	}
	const sm = snapshot.sitemap;
	if (sm) {
		out.push({ id: 'sitemap-200', severity: 'P0', title: '/sitemap_index.xml answers 200', ok: sm.status === 200, message: `status ${sm.status}` });
		if (sm.status === 200) {
			out.push({ id: 'sitemap-children', severity: 'P0', title: 'every child sitemap answers 200 and lists URLs', ok: sm.children.length > 0 && sm.children.every((c) => c.status === 200 && c.urls > 0), message: sm.children.filter((c) => c.status !== 200 || c.urls === 0).map((c) => `${c.name}:${c.status}/${c.urls}`).join(', ') });
		}
	}
	return out;
}

export function checkSnapshot(snapshot) {
	const results = [];
	for (const page of snapshot.urls) {
		const checks = [];
		if (page.error || page.status === 0) {
			checks.push({ id: 'fetch', severity: 'P0', title: 'URL could be fetched', ok: false, message: page.error ?? 'no response' });
		} else {
			for (const rule of RULES) {
				if (rule.applies && !rule.applies(page)) continue;
				const r = rule.test(page);
				checks.push({ id: rule.id, severity: rule.severity, title: rule.title, ok: r.ok, message: r.ok ? '' : (r.message ?? '') });
			}
		}
		results.push({ path: page.path, type: page.type, status: page.status, checks });
	}
	const globals = checkGlobals(snapshot);
	const all = [...results.flatMap((r) => r.checks), ...globals];
	const count = (sev, ok) => all.filter((c) => c.severity === sev && c.ok === ok).length;
	return {
		generated_at: new Date().toISOString(),
		source: { base: snapshot.meta?.base, mode: snapshot.meta?.mode, generated_at: snapshot.meta?.generated_at },
		summary: {
			urls: results.length,
			checks: all.length,
			pass: all.filter((c) => c.ok).length,
			p0_fail: count('P0', false),
			p1_fail: count('P1', false),
			p2_fail: count('P2', false),
		},
		globals,
		results,
	};
}

export function renderMarkdown(report, title = 'SEO rules') {
	const s = report.summary;
	const lines = [
		`# ${title}`,
		'',
		`Source: \`${report.source.base}\` (${report.source.mode}), snapshot ${report.source.generated_at}.`,
		'',
		`| URLs | Checks | PASS | P0 FAIL | P1 FAIL | P2 FAIL |`,
		`| ---: | ---: | ---: | ---: | ---: | ---: |`,
		`| ${s.urls} | ${s.checks} | ${s.pass} | **${s.p0_fail}** | ${s.p1_fail} | ${s.p2_fail} |`,
		'',
	];
	const fails = [
		...report.globals.filter((c) => !c.ok).map((c) => ({ path: '(global)', ...c })),
		...report.results.flatMap((r) => r.checks.filter((c) => !c.ok).map((c) => ({ path: r.path, ...c }))),
	].sort((a, b) => a.severity.localeCompare(b.severity));
	if (fails.length === 0) {
		lines.push('**All rules PASS for the fetched URLs.**', '');
	} else {
		lines.push('## Failures', '', '| Severity | URL | Rule | Detail |', '| --- | --- | --- | --- |');
		fails.forEach((f) => lines.push(`| ${f.severity} | \`${f.path}\` | ${f.id} | ${String(f.message).replace(/\|/g, '\\|').slice(0, 160)} |`));
		lines.push('');
	}
	return lines.join('\n');
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
	const { values, positionals } = parseArgs({ options: { out: { type: 'string' }, md: { type: 'string' } }, allowPositionals: true });
	const file = positionals[0];
	if (!file) {
		console.error('usage: check.mjs <snapshot.json> [--out report.json] [--md report.md]');
		process.exit(2);
	}
	const report = checkSnapshot(JSON.parse(await readFile(file, 'utf8')));
	if (values.out) await writeFile(values.out, `${JSON.stringify(report, null, 2)}\n`);
	if (values.md) await writeFile(values.md, `${renderMarkdown(report)}\n`);
	const s = report.summary;
	console.log(`URLs ${s.urls} | checks ${s.checks} | PASS ${s.pass} | P0 FAIL ${s.p0_fail} | P1 FAIL ${s.p1_fail} | P2 FAIL ${s.p2_fail}`);
	for (const r of report.results) {
		for (const c of r.checks.filter((x) => !x.ok)) console.log(`  ${c.severity} ${r.path} ${c.id}: ${c.message}`);
	}
	for (const c of report.globals.filter((x) => !x.ok)) console.log(`  ${c.severity} (global) ${c.id}: ${c.message}`);
	process.exit(s.p0_fail > 0 ? 1 : 0);
}
