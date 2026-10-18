#!/usr/bin/env node
/**
 * Internal link checker. Takes every internal link target found in a snapshot, requests each one
 * once (GET, no redirects followed) and records its status. Reads only.
 *
 *   node tools/seo/link-check.mjs reports/seo-after.json --base http://127.0.0.1:8088 --out reports/link-check-after.json
 *
 * Exit code 1 when a target answers 4xx/5xx or redirects.
 */
import { readFile, writeFile } from 'node:fs/promises';
import { parseArgs } from 'node:util';

const { values: opt, positionals } = parseArgs({
	options: { base: { type: 'string' }, out: { type: 'string', default: 'reports/link-check.json' }, concurrency: { type: 'string', default: '4' }, delay: { type: 'string', default: '0' }, limit: { type: 'string', default: '600' } },
	allowPositionals: true,
});
if (!positionals[0] || !opt.base) {
	console.error('usage: link-check.mjs <snapshot.json> --base <site url> [--out file] [--concurrency 4] [--delay ms]');
	process.exit(2);
}
const BASE = opt.base.replace(/\/+$/, '');
const snap = JSON.parse(await readFile(positionals[0], 'utf8'));
const targets = new Map(); // path -> sources
for (const page of snap.urls) {
	for (const path of page.links?.internal_targets ?? []) {
		if (/\.(jpe?g|png|webp|gif|svg|css|js|woff2?|ico|pdf|zip)(\?|$)/i.test(path) || path.startsWith('/wp-')) continue;
		if (!targets.has(path)) targets.set(path, []);
		targets.get(path).push(page.path);
	}
}
const list = [...targets.keys()].sort().slice(0, Number(opt.limit));
const results = [];
let i = 0;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
async function worker() {
	while (i < list.length) {
		const path = list[i++];
		try {
			const res = await fetch(`${BASE}${path}`, { redirect: 'manual', headers: { 'user-agent': 'NguyenTu-LinkCheck/1.0 (read-only)' }, signal: AbortSignal.timeout(30000) });
			await res.arrayBuffer();
			results.push({ path, status: res.status, location: res.headers.get('location') ?? '', sources: targets.get(path).slice(0, 5), source_count: targets.get(path).length });
		} catch (err) {
			results.push({ path, status: 0, error: String(err.message), sources: targets.get(path).slice(0, 5), source_count: targets.get(path).length });
		}
		if (Number(opt.delay)) await sleep(Number(opt.delay));
	}
}
await Promise.all(Array.from({ length: Number(opt.concurrency) }, worker));
results.sort((a, b) => a.path.localeCompare(b.path));
const bad = results.filter((r) => r.status === 0 || r.status >= 300);
await writeFile(opt.out, `${JSON.stringify({ meta: { base: BASE, checked: results.length, bad: bad.length, generated_at: new Date().toISOString(), snapshot: positionals[0] }, results }, null, 2)}\n`);
console.log(`checked ${results.length} internal targets, ${bad.length} not 2xx`);
for (const b of bad.slice(0, 30)) console.log(`  ${b.status} ${b.path}${b.location ? ` -> ${b.location}` : ''}  (from ${b.sources[0]})`);
process.exit(bad.length ? 1 : 0);
