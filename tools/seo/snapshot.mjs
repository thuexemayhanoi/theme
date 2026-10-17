#!/usr/bin/env node
/**
 * Read-only SEO snapshot. Issues plain GET requests only (no cookies, no auth, no writes) and
 * records status, redirect chain, canonical, robots, title, description, H1, JSON-LD types,
 * links, images, NAP and raw-shortcode leaks for every URL of a list.
 *
 *   node tools/seo/snapshot.mjs --base https://thuexemaynguyentu.com --urls reports/seo-urls.csv \
 *        --out reports/seo-before.json --delay 400
 *   node tools/seo/snapshot.mjs --base http://127.0.0.1:8088 --out reports/seo-after.json --delay 0
 *
 * The host is only a prefix: the URL list holds paths, so the same list is replayed against the
 * live site (before) and the staging/sandbox site (after).
 */
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { dirname } from 'node:path';
import { parseArgs } from 'node:util';
import { extractPage, parseRobots, parseSitemap } from './lib.mjs';

const { values: opt } = parseArgs({
	options: {
		base: { type: 'string' },
		urls: { type: 'string', default: 'reports/seo-urls.csv' },
		out: { type: 'string', default: 'reports/seo-snapshot.json' },
		delay: { type: 'string', default: '300' },
		timeout: { type: 'string', default: '30000' },
		limit: { type: 'string', default: '0' },
		mode: { type: 'string', default: '' },
		sitemaps: { type: 'boolean', default: true },
		'sitemap-out': { type: 'string', default: '' },
	},
});

if (!opt.base) {
	console.error('--base is required, for example --base https://thuexemaynguyentu.com');
	process.exit(2);
}
const BASE = opt.base.replace(/\/+$/, '');
const DELAY = Number(opt.delay);
const TIMEOUT = Number(opt.timeout);
const UA = 'NguyenTu-SEO-Snapshot/1.0 (read-only audit; GET only)';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** GET with manual redirect handling so the whole chain is visible. */
async function get(url, max = 5) {
	const chain = [];
	let current = url;
	const started = performance.now();
	for (let hop = 0; hop <= max; hop += 1) {
		const res = await fetch(current, {
			redirect: 'manual',
			headers: { 'user-agent': UA, 'accept-language': 'vi,en;q=0.5', accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8' },
			signal: AbortSignal.timeout(TIMEOUT),
		});
		const headers = Object.fromEntries(res.headers.entries());
		if (res.status >= 300 && res.status < 400 && headers.location) {
			chain.push({ status: res.status, from: current, location: new URL(headers.location, current).href });
			current = new URL(headers.location, current).href;
			continue;
		}
		const body = await res.text();
		return { status: res.status, headers, body, finalUrl: current, chain, ms: Math.round(performance.now() - started) };
	}
	return { status: 310, headers: {}, body: '', finalUrl: current, chain, ms: Math.round(performance.now() - started), error: 'too many redirects' };
}

function parseCsv(text) {
	const [head, ...rows] = text.split(/\r?\n/).filter((l) => l.trim() && !l.startsWith('#'));
	const cols = head.split(',').map((c) => c.trim());
	return rows.map((line) => {
		const cells = line.match(/("([^"]|"")*"|[^,]*)(,|$)/g)?.map((c) => c.replace(/,$/, '').replace(/^"|"$/g, '').replace(/""/g, '"')) ?? [];
		return Object.fromEntries(cols.map((c, i) => [c, (cells[i] ?? '').trim()]));
	});
}

const list = parseCsv(await readFile(opt.urls, 'utf8'));
const entries = Number(opt.limit) > 0 ? list.slice(0, Number(opt.limit)) : list;

const snapshot = {
	meta: {
		tool: 'tools/seo/snapshot.mjs',
		tool_version: '1.0.0',
		generated_at: new Date().toISOString(),
		base: BASE,
		mode: opt.mode || (/127\.0\.0\.1|localhost/.test(BASE) ? 'sandbox' : 'live'),
		user_agent: UA,
		delay_ms: DELAY,
		url_list: opt.urls,
		url_count: entries.length,
		note: 'GET requests only. No cookies, no authentication, no writes.',
	},
	robots: null,
	sitemap: null,
	urls: [],
};

// ---- robots.txt and sitemaps ----
try {
	const r = await get(`${BASE}/robots.txt`);
	snapshot.robots = { status: r.status, ...(r.status === 200 ? parseRobots(r.body) : {}) };
} catch (err) {
	snapshot.robots = { status: 0, error: String(err.message) };
}
if (opt.sitemaps) {
	try {
		const idx = await get(`${BASE}/sitemap_index.xml`);
		const out = { status: idx.status, children: [], total_urls: 0 };
		if (idx.status === 200) {
			const { locs } = parseSitemap(idx.body);
			const all = {};
			for (const child of locs) {
				await sleep(DELAY);
				const c = await get(child.replace(/^https?:\/\/[^/]+/, BASE));
				const urls = c.status === 200 ? parseSitemap(c.body).locs : [];
				all[child] = urls;
				out.children.push({ name: child.split('/').pop(), status: c.status, urls: urls.length, sample: urls.slice(0, 3) });
				out.total_urls += urls.length;
			}
			if (opt['sitemap-out']) {
				await mkdir(dirname(opt['sitemap-out']), { recursive: true });
				await writeFile(opt['sitemap-out'], `${JSON.stringify(all, null, 2)}\n`);
			}
		}
		snapshot.sitemap = out;
	} catch (err) {
		snapshot.sitemap = { status: 0, error: String(err.message) };
	}
}

// ---- pages ----
let n = 0;
for (const entry of entries) {
	n += 1;
	const url = `${BASE}${entry.path.startsWith('/') ? entry.path : `/${entry.path}`}`;
	try {
		const r = await get(url);
		const page = extractPage({ html: r.body, url, status: r.status, headers: r.headers, finalUrl: r.finalUrl, type: entry.type });
		page.expected_status = Number(entry.expected_status || 200);
		page.expected_robots = entry.expected_robots || '';
		page.redirect_chain = r.chain;
		page.response_ms = r.ms;
		snapshot.urls.push(page);
		process.stdout.write(`[${n}/${entries.length}] ${r.status} ${entry.path}${r.chain.length ? `  (${r.chain.length} redirect)` : ''}\n`);
	} catch (err) {
		snapshot.urls.push({ url, path: entry.path, type: entry.type, status: 0, error: String(err.message), expected_status: Number(entry.expected_status || 200) });
		process.stdout.write(`[${n}/${entries.length}] ERR ${entry.path} ${err.message}\n`);
	}
	await sleep(DELAY);
}

await mkdir(dirname(opt.out), { recursive: true });
await writeFile(opt.out, `${JSON.stringify(snapshot, null, 2)}\n`);
console.log(`\nWrote ${opt.out}: ${snapshot.urls.length} URLs (${snapshot.meta.mode}).`);
