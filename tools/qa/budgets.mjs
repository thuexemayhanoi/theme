#!/usr/bin/env node
/**
 * Asset byte budgets (raw and gzip). Budgets are deliberately tight: they are what keeps the
 * render-blocking path small for LCP on mobile. Sizes are facts, not scores.
 */
import { readFile, readdir } from 'node:fs/promises';
import { gzipSync } from 'node:zlib';

const root = new URL('../../', import.meta.url);
const KB = 1024;
const BUDGETS = [
	['assets/css/main.css', 34 * KB, 9 * KB],
	['assets/css/home.css', 16 * KB, 4.5 * KB],
	['assets/css/blog.css', 16 * KB, 4.5 * KB],
	['assets/css/woo.css', 16 * KB, 4 * KB],
	['assets/css/legacy.css', 18 * KB, 5 * KB],
	['assets/js/theme.js', 14 * KB, 4.5 * KB],
];
const rows = [];
let fails = 0;
for (const [file, rawMax, gzMax] of BUDGETS) {
	const buf = await readFile(new URL(file, root));
	const gz = gzipSync(buf, { level: 9 }).length;
	const ok = buf.length <= rawMax && gz <= gzMax;
	if (!ok) fails += 1;
	rows.push({ file, raw: buf.length, gz, rawMax, gzMax, ok });
}
const fonts = await readdir(new URL('assets/fonts/', root));
let fontBytes = 0;
for (const f of fonts.filter((x) => x.endsWith('.woff2'))) fontBytes += (await readFile(new URL(`assets/fonts/${f}`, root))).length;
const fontOk = fontBytes <= 40 * KB;
if (!fontOk) fails += 1;

// Render-blocking critical path on a typical inner page: main.css + the two preloaded fonts.
const main = rows.find((r) => r.file.endsWith('main.css'));
const critical = main.gz + fontBytes;
const criticalOk = critical <= 60 * KB;
if (!criticalOk) fails += 1;

for (const r of rows) console.log(`${r.ok ? 'PASS' : 'FAIL'}  ${r.file.padEnd(24)} raw ${(r.raw / KB).toFixed(1)} KB (max ${(r.rawMax / KB).toFixed(0)})  gzip ${(r.gz / KB).toFixed(1)} KB (max ${(r.gzMax / KB).toFixed(1)})`);
console.log(`${fontOk ? 'PASS' : 'FAIL'}  fonts (woff2)            ${(fontBytes / KB).toFixed(1)} KB (max 40)`);
console.log(`${criticalOk ? 'PASS' : 'FAIL'}  critical path main.css(gz)+fonts  ${(critical / KB).toFixed(1)} KB (max 60)`);
process.exit(fails ? 1 : 0);
