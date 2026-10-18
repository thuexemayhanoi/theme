#!/usr/bin/env node
/**
 * Runs Lighthouse (mobile preset, simulated throttling) against the sandbox pages and writes
 * reports/lighthouse-summary.json. Lighthouse is NOT a repo dependency (it is large); point
 * LIGHTHOUSE_BIN at an installed binary:  npm i lighthouse@12 --prefix /some/dir
 *   LIGHTHOUSE_BIN=/some/dir/node_modules/.bin/lighthouse node tools/qa/lighthouse.mjs
 * If the binary is missing the script exits 3 and nothing is claimed.
 *
 *   NT_LH_LABEL  label of this run (default "default" = theme settings as shipped)
 *   NT_LH_OUT    output file (default reports/lighthouse-summary.json)
 * The release gate "lighthouse" is recorded only for the "default" label and passes when the run
 * completed and the theme-owned categories (accessibility, best practices) score at least 90;
 * performance and SEO are reported, not gated (lab environment, SEO text belongs to Yoast).
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
		execFileSync(BIN, [`${BASE}${path}`, '--output=json', `--output-path=${file}`, '--quiet', '--chrome-flags=--headless=new --no-sandbox --disable-gpu', '--only-categories=performance,accessibility,best-practices,seo'], { stdio: ['ignore', 'ignore', 'inherit'], timeout: 180000, env: { ...process.env, CHROME_PATH: chrome } });
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
const LABEL = process.env.NT_LH_LABEL || 'default';
const OUT = process.env.NT_LH_OUT || 'reports/lighthouse-summary.json';
writeFileSync(OUT, `${JSON.stringify({ label: LABEL, version: results[0]?.version, generated_at: new Date().toISOString(), base: BASE, results }, null, 2)}\n`);
if (LABEL === 'default') {
	const weak = results.filter((r) => r.accessibility < 90 || r['best-practices'] < 90);
	const line = results.map((r) => `${r.page}: perf ${r.performance} / a11y ${r.accessibility} / bp ${r['best-practices']} / seo ${r.seo}`).join('; ');
	execFileSync('node', ['tools/qa/record.mjs', 'lighthouse', weak.length ? 'FAIL' : 'PASS', `Lighthouse ${results[0]?.version}, mobile preset, local sandbox (lab only): ${line}`, 'reports/performance.md'], { stdio: 'inherit' });
}
