#!/usr/bin/env node
/**
 * Builds reports/internal-links.csv from a snapshot (and an optional link-check result):
 * source URL, target URL, anchor text, target status, silo, detected breakage, recommendation.
 * Coverage is exactly the URL list of the snapshot (label it when you publish the report).
 *
 *   node tools/seo/links-report.mjs reports/seo-after.json [--check reports/link-check-after.json] > reports/internal-links.csv
 */
import { readFile } from 'node:fs/promises';
import { parseArgs } from 'node:util';

const { values: opt, positionals } = parseArgs({ options: { check: { type: 'string' } }, allowPositionals: true });
const snap = JSON.parse(await readFile(positionals[0], 'utf8'));
const status = new Map();
for (const p of snap.urls) status.set(p.path, p.status);
if (opt.check) for (const r of JSON.parse(await readFile(opt.check, 'utf8')).results) status.set(r.path, r.status);

const silo = (path) => {
	const m = path.match(/^\/blog\/([^/]+)\//);
	if (m) return `blog/${m[1]}`;
	if (path === '/blog/') return 'blog';
	if (/^\/(san-pham|danh-muc-san-pham|shop)\//.test(path)) return 'shop';
	if (/^\/bo-suu-tap\//.test(path)) return 'bang-gia';
	return 'site';
};
const esc = (v) => (/[",\n]/.test(String(v)) ? `"${String(v).replace(/"/g, '""')}"` : String(v));
console.log(['source_url', 'target_url', 'anchor', 'target_status', 'source_silo', 'target_silo', 'breakage', 'recommendation'].join(','));
for (const page of snap.urls) {
	if (!page.links) continue;
	const seen = new Set();
	for (const link of page.links.sample) {
		const key = `${link.path}|${link.text}`;
		if (seen.has(key) || link.path === page.path) continue;
		seen.add(key);
		const st = status.get(link.path);
		let breakage = '';
		let rec = '';
		if (st === undefined) {
			breakage = 'not-checked';
			rec = 'Run tools/seo/link-check.mjs to verify this target';
		} else if (st >= 400 || st === 0) {
			breakage = `broken (${st})`;
			rec = 'Fix or remove the link';
		} else if (st >= 300) {
			breakage = `redirect (${st})`;
			rec = 'Link to the final URL';
		}
		const a = silo(page.path);
		const b = silo(link.path);
		if (!breakage && a.startsWith('blog/') && b === 'shop' && /thuê|xe máy/i.test(link.text) === false) rec = 'Check relevance: commercial link from a supporting article';
		console.log([page.path, link.path, link.text, st ?? '', a, b, breakage, rec].map(esc).join(','));
	}
}
