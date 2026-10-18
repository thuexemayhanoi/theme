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
const incoming = new Map();
const outgoing = new Map();
for (const page of snap.urls) {
 const targets = new Set((page.links?.sample || []).map(link => link.path).filter(path => path !== page.path));
 outgoing.set(page.path, targets.size);
 for (const path of targets) incoming.set(path, (incoming.get(path) || 0) + 1);
}
console.log(['source_url', 'target_url', 'anchor', 'target_status', 'source_silo', 'target_silo', 'breakage', 'recommendation', 'source_outgoing_count', 'target_incoming_count'].join(','));
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
		console.log([page.path, link.path, link.text, st ?? '', a, b, breakage, rec, outgoing.get(page.path) || 0, incoming.get(link.path) || 0].map(esc).join(','));
	}
}

// Orphans refer only to this snapshot's coverage, not all WordPress content.
for (const page of snap.urls) {
 if (page.path === '/' || incoming.has(page.path) || page.status >= 400) continue;
 const source = snap.urls.find(other => other.path !== page.path && silo(other.path) === silo(page.path) && other.status >= 200 && other.status < 300);
 console.log([source?.path || '', page.path, '', page.status || '', source ? silo(source.path) : '', silo(page.path), 'no-incoming-in-sample', 'Review a contextual link from a relevant published page; do not auto-insert', source ? outgoing.get(source.path) || 0 : '', 0].map(esc).join(','));
}
