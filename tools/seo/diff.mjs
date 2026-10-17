#!/usr/bin/env node
/**
 * Before/after SEO diff. "Before" is the production snapshot, "after" the staging/sandbox one.
 * URLs are matched by path, so the two hosts may differ.
 *
 *   node tools/seo/diff.mjs reports/seo-before.json reports/seo-after.json \
 *        --json reports/seo-diff.json --md reports/seo-diff.md
 *
 * If the "before" file is missing, empty or marked UNVERIFIED the report says so and does NOT
 * claim parity. Severities follow docs/SEO-HULK.md section 1 (P0 blocks deployment).
 */
import { readFile, writeFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import { parseArgs } from 'node:util';
import { pathOf } from './lib.mjs';

const norm = (s) => String(s ?? '').replace(/\s+/g, ' ').trim();
const first = (a) => (Array.isArray(a) && a.length ? a[0] : '');
const noindex = (p) => [...(p.robots ?? []), p.headers?.['x-robots-tag'] ?? ''].some((r) => /noindex/i.test(r));

export function diffSnapshots(before, after) {
	const findings = [];
	const add = (severity, path, field, b, a, message) => findings.push({ severity, path, field, before: b, after: a, message });

	const verified = Boolean(before?.urls?.length) && before.meta?.status !== 'UNVERIFIED';
	if (!verified) {
		return {
			verified: false,
			summary: { compared: 0, p0: 0, p1: 0, p2: 0 },
			findings,
			reason: before?.meta?.reason ?? 'No usable "before" snapshot (live access was not available).',
		};
	}

	const beforeBy = new Map(before.urls.map((u) => [u.path, u]));
	const afterBy = new Map(after.urls.map((u) => [u.path, u]));
	let compared = 0;

	for (const [path, b] of beforeBy) {
		const a = afterBy.get(path);
		if (!a) {
			add('P0', path, 'url', 'in before', 'missing in after', 'URL was not captured in the after snapshot');
			continue;
		}
		compared += 1;
		if (b.error || a.error) {
			add('P0', path, 'fetch', b.error ?? 'ok', a.error ?? 'ok', 'fetch error');
			continue;
		}
		// ---- status ----
		if (b.status !== a.status) {
			add(b.status === 200 || b.status === 404 ? 'P0' : 'P1', path, 'status', b.status, a.status, 'HTTP status changed');
		}
		if (b.status !== 200 || a.status !== 200) continue;

		// ---- redirects ----
		if ((a.redirect_chain?.length ?? 0) > (b.redirect_chain?.length ?? 0)) add('P0', path, 'redirect', b.redirect_chain?.length ?? 0, a.redirect_chain.length, 'a redirect appeared');

		// ---- Yoast-owned head signals ----
		const bc = pathOf(first(b.canonicals) || '/');
		const ac = a.canonicals.length ? pathOf(first(a.canonicals)) : '(none)';
		if (a.canonicals.length !== 1) add('P0', path, 'canonical-count', b.canonicals.length, a.canonicals.length, 'canonical count is not exactly one');
		if (bc !== ac) add('P0', path, 'canonical', bc, ac, 'canonical drift');
		if (norm(first(b.titles)) !== norm(first(a.titles))) add('P0', path, 'title', first(b.titles), first(a.titles), 'title changed');
		if (norm(first(b.descriptions)) !== norm(first(a.descriptions))) add('P0', path, 'description', first(b.descriptions), first(a.descriptions), 'meta description changed');
		if (noindex(b) !== noindex(a)) add(noindex(a) ? 'P0' : 'P1', path, 'robots', b.robots.join('|'), a.robots.join('|'), 'indexability changed');
		if (JSON.stringify(b.social?.og ?? {}) !== JSON.stringify(a.social?.og ?? {})) add('P1', path, 'open-graph', Object.keys(b.social?.og ?? {}).length, Object.keys(a.social?.og ?? {}).length, 'Open Graph tags changed');
		if ((b.hreflang?.length ?? 0) !== (a.hreflang?.length ?? 0)) add('P1', path, 'hreflang', b.hreflang?.length ?? 0, a.hreflang?.length ?? 0, 'hreflang set changed');

		// ---- headings and landmarks ----
		if (b.h1.length !== a.h1.length || norm(first(b.h1)) !== norm(first(a.h1))) add('P0', path, 'h1', b.h1.join(' | '), a.h1.join(' | '), 'H1 changed');
		if (a.landmarks.main !== 1) add('P0', path, 'main', b.landmarks.main, a.landmarks.main, 'missing or duplicate <main>');
		if (b.breadcrumb && !a.breadcrumb) add('P1', path, 'breadcrumb', 'yes', 'no', 'breadcrumb lost');

		// ---- structured data ----
		const bt = b.jsonld.top_level_type_counts;
		const at = a.jsonld.top_level_type_counts;
		for (const t of Object.keys(bt)) {
			if (!(t in at)) add('P0', path, 'schema-type-lost', t, '(none)', `schema type ${t} disappeared`);
		}
		for (const t of Object.keys(at)) {
			if (!(t in bt)) add('P1', path, 'schema-type-added', '(none)', t, `new schema type ${t} (confirm it is intended and not a duplicate)`);
			else if (at[t] > bt[t]) add('P0', path, 'schema-duplicate', bt[t], at[t], `schema type ${t} now appears more often`);
		}
		if (a.jsonld.duplicate_ids.length) add('P0', path, 'schema-duplicate-id', '', a.jsonld.duplicate_ids.join(', '), 'duplicate entity @id');

		// ---- content integrity ----
		if (a.raw_shortcodes.length) add('P0', path, 'raw-shortcode', '', a.raw_shortcodes.join(' '), 'raw shortcode text visible');
		if (b.nap.phone_in_text && !a.nap.phone_in_text) add('P0', path, 'nap-phone', 'present', 'missing', 'phone number no longer visible');
		if (b.nap.address_in_text && !a.nap.address_in_text) add('P0', path, 'nap-address', 'present', 'missing', 'address no longer visible');
		if (b.word_count > 0) {
			const ratio = a.word_count / b.word_count;
			if (ratio < 0.7) add('P0', path, 'text-lost', b.word_count, a.word_count, 'more than 30% of the words are gone');
			else if (ratio < 0.9) add('P1', path, 'text-reduced', b.word_count, a.word_count, 'more than 10% of the words are gone');
		}
		const lost = b.links.internal_targets.filter((t) => !a.links.internal_targets.includes(t));
		if (lost.length) {
			const share = lost.length / Math.max(1, b.links.internal_targets.length);
			add(share > 0.25 ? 'P0' : 'P1', path, 'internal-links-lost', b.links.internal_targets.length, a.links.internal_targets.length, `${lost.length} internal link target(s) no longer linked: ${lost.slice(0, 8).join(', ')}`);
		}
		if (a.images.missing_alt > b.images.missing_alt) add('P1', path, 'img-alt', b.images.missing_alt, a.images.missing_alt, 'more images without alt');
		if (a.images.missing_dimensions > b.images.missing_dimensions) add('P1', path, 'img-dimensions', b.images.missing_dimensions, a.images.missing_dimensions, 'more images without width/height');
		if (a.html_bytes > b.html_bytes * 1.5 && a.html_bytes > 60 * 1024) add('P1', path, 'html-bytes', b.html_bytes, a.html_bytes, 'HTML grew by more than 50%');
	}
	for (const [path, a] of afterBy) {
		if (!beforeBy.has(path)) add('P2', path, 'url', 'not in before', a.status, 'URL only exists in the after snapshot');
	}

	// ---- robots and sitemap ----
	if (before.robots && after.robots) {
		if (before.robots.hash && before.robots.hash !== after.robots.hash) add('P0', '/robots.txt', 'robots.txt', before.robots.hash, after.robots.hash, 'robots.txt content changed');
	}
	if (before.sitemap && after.sitemap && before.sitemap.status === 200) {
		if (after.sitemap.status !== 200) add('P0', '/sitemap_index.xml', 'status', 200, after.sitemap.status, 'sitemap index no longer answers 200');
		else if (after.sitemap.total_urls < before.sitemap.total_urls) add('P0', '/sitemap_index.xml', 'total_urls', before.sitemap.total_urls, after.sitemap.total_urls, 'sitemap lists fewer URLs');
	}

	const count = (s) => findings.filter((f) => f.severity === s).length;
	return { verified: true, summary: { compared, p0: count('P0'), p1: count('P1'), p2: count('P2') }, findings };
}

export function renderDiffMarkdown(result, beforeMeta = {}, afterMeta = {}) {
	const head = ['# SEO before / after diff', ''];
	if (!result.verified) {
		return [
			...head,
			'> **UNVERIFIED — no live baseline.** This report does not claim parity with production.',
			'',
			`Reason: ${result.reason}`,
			'',
			'## What is still required',
			'',
			'1. Run `node tools/seo/snapshot.mjs --base https://thuexemaynguyentu.com --urls reports/seo-urls.csv --out reports/seo-before.json` from a network that can reach the live site (or the manual GitHub Action `seo-snapshot-live`).',
			'2. Run the same command against the staging copy to produce `reports/seo-after.json`.',
			'3. Re-run `node tools/seo/diff.mjs reports/seo-before.json reports/seo-after.json`.',
			'',
			'Until then the only SEO evidence is the absolute-rule check of the sandbox (`reports/seo-rules-after.md`), which tests the contract (one canonical, one H1, no duplicate schema ...), not equality with production.',
			'',
		].join('\n');
	}
	const s = result.summary;
	const lines = [
		...head,
		`Before: \`${beforeMeta.base}\` (${beforeMeta.mode}) ${beforeMeta.generated_at}  `,
		`After: \`${afterMeta.base}\` (${afterMeta.mode}) ${afterMeta.generated_at}`,
		'',
		'| URLs compared | P0 (blocks deployment) | P1 (fix before go-live) | P2 (info) |',
		'| ---: | ---: | ---: | ---: |',
		`| ${s.compared} | **${s.p0}** | ${s.p1} | ${s.p2} |`,
		'',
	];
	for (const sev of ['P0', 'P1', 'P2']) {
		const rows = result.findings.filter((f) => f.severity === sev);
		if (!rows.length) continue;
		lines.push(`## ${sev}`, '', '| URL | Field | Before | After | Note |', '| --- | --- | --- | --- | --- |');
		rows.forEach((f) => lines.push(`| \`${f.path}\` | ${f.field} | ${String(f.before).slice(0, 80)} | ${String(f.after).slice(0, 80)} | ${f.message} |`));
		lines.push('');
	}
	if (s.p0 + s.p1 + s.p2 === 0) lines.push('**No differences found for the compared URLs.**', '');
	return lines.join('\n');
}

async function load(file) {
	try {
		return JSON.parse(await readFile(file, 'utf8'));
	} catch {
		return null;
	}
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
	const { values, positionals } = parseArgs({ options: { json: { type: 'string' }, md: { type: 'string' } }, allowPositionals: true });
	const [beforeFile, afterFile] = positionals;
	if (!beforeFile || !afterFile) {
		console.error('usage: diff.mjs <before.json> <after.json> [--json out.json] [--md out.md]');
		process.exit(2);
	}
	const before = (await load(beforeFile)) ?? { meta: { status: 'UNVERIFIED', reason: `Could not read ${beforeFile}` }, urls: [] };
	const after = (await load(afterFile)) ?? { urls: [] };
	const result = diffSnapshots(before, after);
	if (values.json) await writeFile(values.json, `${JSON.stringify(result, null, 2)}\n`);
	if (values.md) await writeFile(values.md, `${renderDiffMarkdown(result, before.meta, after.meta)}\n`);
	console.log(result.verified ? `compared ${result.summary.compared} | P0 ${result.summary.p0} | P1 ${result.summary.p1} | P2 ${result.summary.p2}` : `UNVERIFIED: ${result.reason}`);
	process.exit(result.verified && result.summary.p0 > 0 ? 1 : 0);
}
