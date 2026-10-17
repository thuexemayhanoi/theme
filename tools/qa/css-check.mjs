#!/usr/bin/env node
/**
 * CSS gate: every stylesheet must parse (lightningcss), may not import or hot-link anything, and
 * every var(--nt-*) it uses must be defined in main.css :root (catches typos).
 */
import { readFile, readdir } from 'node:fs/promises';
import { transform } from 'lightningcss';

const dir = new URL('../../assets/css/', import.meta.url);
const files = (await readdir(dir)).filter((f) => f.endsWith('.css')).sort();
const sources = Object.fromEntries(await Promise.all(files.map(async (f) => [f, await readFile(new URL(f, dir), 'utf8')])));

const root = sources['main.css'].match(/:root\s*\{([^}]*)\}/s)?.[1] ?? '';
const defined = new Set([...root.matchAll(/(--[a-z0-9-]+)\s*:/gi)].map((m) => m[1]));
let fails = 0;
const fail = (file, msg) => {
	fails += 1;
	console.log(`FAIL  ${file}: ${msg}`);
};

for (const [file, css] of Object.entries(sources)) {
	try {
		transform({ filename: file, code: Buffer.from(css), minify: true, errorRecovery: false });
	} catch (err) {
		fail(file, `does not parse: ${err.message}`);
		continue;
	}
	if (/@import/i.test(css)) fail(file, '@import is not allowed (extra request)');
	if (/url\(\s*["']?(?:https?:)?\/\//i.test(css)) fail(file, 'external url() found (no CDN allowed)');
	const local = new Set([...css.matchAll(/(--[a-z0-9-]+)\s*:/gi)].map((m) => m[1]));
	const used = [...css.matchAll(/var\(\s*(--nt-[a-z0-9-]+)/gi)].map((m) => m[1]);
	for (const v of new Set(used)) if (!defined.has(v) && !local.has(v)) fail(file, `${v} is used but never defined`);
	console.log(`PASS  ${file} parses, no imports, no external urls`);
}
// Fonts referenced by @font-face must exist.
for (const m of sources['main.css'].matchAll(/url\("\.\.\/fonts\/([^"]+)"\)/g)) {
	try {
		await readFile(new URL(`../../assets/fonts/${m[1]}`, import.meta.url));
	} catch {
		fail('main.css', `font file missing: ${m[1]}`);
	}
}
console.log(fails ? `\n${fails} problem(s).` : '\nCSS gate passed.');
process.exit(fails ? 1 : 0);
