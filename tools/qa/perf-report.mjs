#!/usr/bin/env node
/**
 * reports/performance.md from tools/qa/perf.mjs output (and optional Lighthouse summaries).
 *   node tools/qa/perf-report.mjs reports/performance.json [reports/lighthouse-summary.json] > reports/performance.md
 */
import { readFile } from 'node:fs/promises';

const perf = JSON.parse(await readFile(process.argv[2], 'utf8'));
const lh = process.argv[3] ? JSON.parse(await readFile(process.argv[3], 'utf8').catch(() => 'null')) : null;
const kb = (n) => `${(n / 1024).toFixed(1)}`;
const rate = (v, good, poor) => (v <= good ? 'good' : v <= poor ? 'needs improvement' : 'poor');

const out = [
	'# Performance report (lab measurements)',
	'',
	`Measured ${perf.generated_at} with ${perf.chromium} against \`${perf.base}\`.`,
	'',
	'> **Read this first.** These are LAB measurements on a local PHP built-in server inside a container: no page cache, no gzip/brotli, no CDN, no HTTP/2, shared CPU. They show relative cost and catch regressions; they are **not** field data (CrUX/RUM), **not** a Lighthouse score claim and **not** a ranking promise. Real Core Web Vitals need real traffic (75th percentile mobile) after go-live. Thresholds quoted: LCP <= 2.5 s, INP <= 200 ms, CLS <= 0.1.',
	'',
];
for (const prof of [...new Set(perf.results.map((r) => r.profile))]) {
	out.push(`## ${prof}`, '', '| Page | LCP ms (element) | CLS | Interaction ms* | Requests | HTML KB | Total KB (raw) | Total KB (est. gzip) | CSS KB gz | JS KB gz | Fonts KB | Images KB | 3rd-party |', '| --- | --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |');
	for (const r of perf.results.filter((x) => x.profile === prof)) {
		out.push(`| ${r.page} | ${r.lcp_ms} (${r.lcp_element || '-'}) ${rate(r.lcp_ms, 2500, 4000)} | ${r.cls} ${rate(r.cls, 0.1, 0.25)} | ${r.interaction_ms} | ${r.requests} | ${kb(r.html_bytes)} | ${kb(r.total_bytes)} | ${kb(r.total_gz_estimate)} | ${kb(r.css_gz)} | ${kb(r.js_gz)} | ${kb(r.font_bytes)} | ${kb(r.image_bytes)} | ${r.third_party_requests} |`);
	}
	out.push('');
}
out.push('\\* Interaction = longest event-handling duration of the first menu/search click observed with PerformanceObserver (`event` entries). It is an INP-style lab proxy, not INP.', '');
if (lh) {
	out.push('## Lighthouse (mobile preset, simulated throttling)', '', '| Page | Performance | Accessibility | Best practices | SEO | LCP s | CLS | TBT ms |', '| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |');
	for (const r of lh.results) out.push(`| ${r.page} | ${r.performance} | ${r.accessibility} | ${r['best-practices']} | ${r.seo} | ${r.lcp} | ${r.cls} | ${r.tbt} |`);
	out.push('', `Lighthouse ${lh.version}. Scores come from a local, uncompressed server and vary between runs; treat them as a smoke test.`, '');
} else {
	out.push('## Lighthouse', '', '**NOT_TESTED** in this run (no Lighthouse summary was supplied).', '');
}
out.push('## Budgets enforced in CI', '', 'See `tools/qa/budgets.mjs`: main.css <= 34 KB raw / 9 KB gzip, theme.js <= 14 KB / 4.5 KB gzip, fonts <= 40 KB, critical path (main.css gzip + two fonts) <= 60 KB.', '', '## Limits of this environment', '', '- Single run per page and profile; variance between runs is not measured.', '- Local loopback network: CDP throttling approximates, but does not reproduce, a mobile network.', '- Production adds LiteSpeed Cache, brotli, HTTP/2/3, real images (the sandbox uses generated JPEGs) and third-party plugins (MotoAI, QuickCall, GTranslate, CF7, Site Kit) whose cost is not included here.', '');
console.log(out.join('\n'));
