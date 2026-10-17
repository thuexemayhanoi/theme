#!/usr/bin/env node
/**
 * Lab performance measurement with real Chromium (Playwright + CDP throttling). It records what it
 * can actually observe: LCP and CLS via PerformanceObserver, request count and bytes, estimated
 * gzip transfer of text responses, and the latency of the first menu interaction (an INP-style
 * lab proxy). These are LAB numbers on a local server: not field data, not a ranking score.
 *
 *   NT_SITE_URL=http://127.0.0.1:8088 node tools/qa/perf.mjs > reports/performance.json
 */
import { chromium } from '@playwright/test';
import { gzipSync } from 'node:zlib';
import { writeFile } from 'node:fs/promises';

const BASE = (process.env.NT_SITE_URL || 'http://127.0.0.1:8088').replace(/\/$/, '');
if (!/127\.0\.0\.1|localhost/.test(BASE) && !process.env.NT_E2E_ALLOW_REMOTE) {
	console.error('Refusing to measure a remote host without NT_E2E_ALLOW_REMOTE=1');
	process.exit(2);
}

const browser = await chromium.launch(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE } : {});
const first = async () => {
	const ctx = await browser.newContext();
	const p = await ctx.newPage();
	await p.goto(`${BASE}/blog/xe-dien/`);
	const href = await p.locator('.nt-card--post .nt-card__title a').first().getAttribute('href');
	await ctx.close();
	return new URL(href, BASE).pathname;
};
const PAGES = [['home', '/'], ['post', await first()], ['category', '/blog/xe-dien/'], ['product', '/san-pham/honda-wave/'], ['contact', '/lien-he/']];
const PROFILES = [
	{ name: 'mobile 390px, 4x CPU, 1.6 Mbps / 150 ms RTT (Lighthouse "slow 4G" style)', viewport: { width: 390, height: 844 }, isMobile: true, cpu: 4, net: { latency: 150, down: (1.6 * 1024 * 1024) / 8, up: (750 * 1024) / 8 } },
	{ name: 'desktop 1440px, no throttling', viewport: { width: 1440, height: 900 }, isMobile: false, cpu: 1, net: null },
];

const out = { generated_at: new Date().toISOString(), base: BASE, chromium: browser.version(), note: 'LAB data on a local PHP built-in server (no compression, no CDN, no page cache). Not field data (CrUX/RUM) and not a guarantee of ranking.', results: [] };

for (const prof of PROFILES) {
	for (const [name, path] of PAGES) {
		const ctx = await browser.newContext({ viewport: prof.viewport, isMobile: prof.isMobile, hasTouch: prof.isMobile, deviceScaleFactor: prof.isMobile ? 2 : 1 });
		const page = await ctx.newPage();
		const cdp = await ctx.newCDPSession(page);
		await cdp.send('Network.enable');
		if (prof.net) await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: prof.net.latency, downloadThroughput: prof.net.down, uploadThroughput: prof.net.up });
		if (prof.cpu > 1) await cdp.send('Emulation.setCPUThrottlingRate', { rate: prof.cpu });
		await page.addInitScript(() => {
			window.__m = { lcp: 0, lcpTag: '', cls: 0, longest: 0 };
			new PerformanceObserver((l) => {
				for (const e of l.getEntries()) {
					window.__m.lcp = e.startTime;
					window.__m.lcpTag = e.element ? `${e.element.tagName.toLowerCase()}${e.element.className ? `.${String(e.element.className).split(' ')[0]}` : ''}` : '';
				}
			}).observe({ type: 'largest-contentful-paint', buffered: true });
			new PerformanceObserver((l) => {
				for (const e of l.getEntries()) if (!e.hadRecentInput) window.__m.cls += e.value;
			}).observe({ type: 'layout-shift', buffered: true });
			new PerformanceObserver((l) => {
				for (const e of l.getEntries()) if (e.interactionId) window.__m.longest = Math.max(window.__m.longest, e.duration);
			}).observe({ type: 'event', durationThreshold: 16, buffered: true });
		});
		const assets = [];
		page.on('response', async (r) => {
			try {
				const buf = await r.body();
				const type = r.headers()['content-type'] ?? '';
				const text = /text|json|javascript|css|xml|svg/.test(type);
				assets.push({ url: r.url(), type: r.request().resourceType(), bytes: buf.length, gz: text ? gzipSync(buf, { level: 6 }).length : buf.length });
			} catch {
				/* redirects / aborted */
			}
		});
		const t0 = Date.now();
		await page.goto(`${BASE}${path}`, { waitUntil: 'load' });
		await page.waitForTimeout(2500);
		// scroll to trigger lazy images, then let layout settle
		await page.evaluate(async () => {
			for (let y = 0; y < document.body.scrollHeight; y += 600) {
				window.scrollTo(0, y);
				await new Promise((r) => setTimeout(r, 60));
			}
			window.scrollTo(0, 0);
		});
		await page.waitForTimeout(800);
		// first interaction: open the menu (mobile) or the search panel (desktop)
		const trigger = prof.isMobile ? page.locator('[data-nt-menu-toggle]') : page.locator('[data-nt-search-toggle]');
		if (await trigger.count()) {
			await trigger.first().click();
			await page.waitForTimeout(400);
		}
		const m = await page.evaluate(() => window.__m);
		const sum = (f) => assets.reduce((a, x) => a + x[f], 0);
		const by = (t) => assets.filter((x) => x.type === t);
		out.results.push({
			profile: prof.name,
			page: name,
			path,
			lcp_ms: Math.round(m.lcp),
			lcp_element: m.lcpTag,
			cls: Number(m.cls.toFixed(4)),
			interaction_ms: Math.round(m.longest),
			load_wall_ms: Date.now() - t0,
			requests: assets.length,
			html_bytes: by('document')[0]?.bytes ?? 0,
			total_bytes: sum('bytes'),
			total_gz_estimate: sum('gz'),
			css_gz: by('stylesheet').reduce((a, x) => a + x.gz, 0),
			js_gz: by('script').reduce((a, x) => a + x.gz, 0),
			font_bytes: by('font').reduce((a, x) => a + x.bytes, 0),
			image_bytes: by('image').reduce((a, x) => a + x.bytes, 0),
			third_party_requests: assets.filter((x) => !x.url.startsWith(BASE)).length,
		});
		await ctx.close();
	}
}
await browser.close();
console.log(JSON.stringify(out, null, 2));
if (process.argv[2]) await writeFile(process.argv[2], `${JSON.stringify(out, null, 2)}\n`);
