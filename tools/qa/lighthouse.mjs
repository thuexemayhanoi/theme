#!/usr/bin/env node
/**
 * Runs Lighthouse (mobile preset, simulated throttling) against the sandbox pages and writes
 * reports/lighthouse-summary.json. Lighthouse is NOT a repo dependency (it is large); point
 * LIGHTHOUSE_BIN at an installed binary:  npm i lighthouse@12 --prefix /some/dir
 *   LIGHTHOUSE_BIN=/some/dir/node_modules/.bin/lighthouse node tools/qa/lighthouse.mjs
 * If the binary is missing the script exits 3 and nothing is claimed.
 */
import { chromium } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const BIN = process.env.LIGHTHOUSE_BIN;
if (!BIN || !existsSync(BIN)) {
	console.error('LIGHTHOUSE_BIN is not set or not found: Lighthouse is NOT_TESTED');
	process.exit(3);
}
const BASE = (process.env.NT_SITE_URL || 'http://127.0.0.1:8088').replace(/\/$/, '');
const chrome = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE || chromium.executablePath();
const dir = mkdtempSync(join(tmpdir(), 'lh-'));

const first = await (await fetch(`${BASE}/blog/xe-dien/`)).text();
const post = first.match(/class="nt-card__title"><a href="([^"]+)"/)?.[1];
const PAGES = [['home', '/'], ['post', post ? new URL(post).pathname : '/blog/'], ['category', '/blog/xe-dien/'], ['product', '/san-pham/honda-wave/']];

const results = [];
for (const [name, path] of PAGES) {
	const file = join(dir, `${name}.json`);
	try {
		execFileSync(BIN, [`${BASE}${path}`, '--output=json', `--output-path=${file}`, '--quiet', `--chrome-path=${chrome}`, '--chrome-flags=--headless=new --no-sandbox --disable-gpu', '--only-categories=performance,accessibility,best-practices,seo'], { stdio: ['ignore', 'ignore', 'inherit'], timeout: 180000 });
		const j = JSON.parse(readFileSync(file, 'utf8'));
		const pct = (k) => Math.round((j.categories[k]?.score ?? 0) * 100);
		results.push({
			page: name,
			path,
			performance: pct('performance'),
			accessibility: pct('accessibility'),
			'best-practices': pct('best-practices'),
			seo: pct('seo'),
			lcp: Number((j.audits['largest-contentful-paint'].numericValue / 1000).toFixed(2)),
			cls: Number(j.audits['cumulative-layout-shift'].numericValue.toFixed(3)),
			tbt: Math.round(j.audits['total-blocking-time'].numericValue),
			version: j.lighthouseVersion,
		});
		console.log(`${name}: perf ${results.at(-1).performance} a11y ${results.at(-1).accessibility} bp ${results.at(-1)['best-practices']} seo ${results.at(-1).seo}`);
	} catch (err) {
		console.error(`Lighthouse failed for ${name}: ${String(err.message).slice(0, 200)}`);
		process.exit(1);
	}
}
writeFileSync('reports/lighthouse-summary.json', `${JSON.stringify({ version: results[0]?.version, generated_at: new Date().toISOString(), base: BASE, results }, null, 2)}\n`);
