#!/usr/bin/env node
/**
 * WCAG 2.2 contrast gate for the design tokens. Reads the :root colour tokens from main.css, then
 * checks every foreground/background pair the templates actually use.
 *   - normal text: >= 4.5:1 (AA)
 *   - large text and UI components: >= 3:1
 * Exit code 1 on any failure. This measures the palette, not every rendered pixel.
 */
import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';

export function parseTokens(css) {
	const root = css.match(/:root\s*\{([^}]*)\}/s)?.[1] ?? '';
	const tokens = {};
	for (const [, name, value] of root.matchAll(/--([a-z0-9-]+):\s*(#[0-9a-f]{3,6})\s*;/gi)) tokens[name] = value.toLowerCase();
	return tokens;
}

const toRgb = (hex) => {
	let h = hex.replace('#', '');
	if (h.length === 3) h = [...h].map((c) => c + c).join('');
	return [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16));
};
const lin = (v) => {
	const c = v / 255;
	return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
};
export const luminance = (hex) => {
	const [r, g, b] = toRgb(hex).map(lin);
	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};
export const ratio = (a, b) => {
	const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
	return (hi + 0.05) / (lo + 0.05);
};

/** [label, foreground, background, minimum ratio] ; names starting with "nt-" are tokens. */
export const PAIRS = [
	['body text on cream', 'nt-ink', 'nt-cream', 4.5],
	['body text on white', 'nt-ink', 'nt-paper', 4.5],
	['body text on sand', 'nt-ink', 'nt-sand', 4.5],
	['links (navy) on cream', 'nt-navy', 'nt-cream', 4.5],
	['links (navy) on white', 'nt-navy', 'nt-paper', 4.5],
	['chip text (navy) on navy tint', 'nt-navy', 'nt-navy-100', 4.5],
	['muted text on cream', 'nt-muted', 'nt-cream', 4.5],
	['muted text on white', 'nt-muted', 'nt-paper', 4.5],
	['muted text on sand', 'nt-muted', 'nt-sand', 4.5],
	['eyebrow / hover red on cream', 'nt-accent-700', 'nt-cream', 4.5],
	['eyebrow / hover red on white', 'nt-accent-700', 'nt-paper', 4.5],
	['eyebrow / hover red on sand', 'nt-accent-700', 'nt-sand', 4.5],
	['price (red) on white', 'nt-accent-700', 'nt-paper', 4.5],
	['call button text', '#ffffff', 'nt-accent', 4.5],
	['call button hover text', '#ffffff', 'nt-accent-700', 4.5],
	['Zalo button text', '#ffffff', 'nt-zalo', 4.5],
	['Zalo button hover text', '#ffffff', 'nt-zalo-700', 4.5],
	['primary button text', '#ffffff', 'nt-navy', 4.5],
	['success badge text', '#ffffff', 'nt-success', 4.5],
	['footer text on navy-900', '#d7deec', 'nt-navy-900', 4.5],
	['footer muted text on navy-900', '#9fb0cd', 'nt-navy-900', 4.5],
	['footer links on navy-900', '#ffffff', 'nt-navy-900', 4.5],
	['top bar text on navy-900', '#e8edf6', 'nt-navy-900', 4.5],
	['hero card secondary text on navy', '#c9d4ea', 'nt-navy', 4.5],
	['hero card secondary text on navy-900', '#c9d4ea', 'nt-navy-900', 4.5],
	['hero card kicker on navy', '#ffb4a6', 'nt-navy', 4.5],
	['eyebrow on dark section', '#ffb4a6', 'nt-navy-900', 4.5],
	['note on dark card', '#b9c6e0', 'nt-navy', 4.5],
	['table header text', '#ffffff', 'nt-navy', 4.5],
	['search result url on white', '#7b8499', 'nt-paper', 3.0],
	['focus ring vs cream (UI component)', 'nt-focus', 'nt-cream', 3.0],
	['focus ring vs white (UI component)', 'nt-focus', 'nt-paper', 3.0],
	['icon on dark footer (UI component)', '#ff8a73', 'nt-navy-900', 3.0],
];

export function evaluate(tokens) {
	const color = (c) => (c.startsWith('#') ? c : tokens[c.replace(/^--/, '')]);
	return PAIRS.map(([label, fg, bg, min]) => {
		const f = color(fg);
		const b = color(bg);
		const r = f && b ? ratio(f, b) : NaN;
		return { label, fg: f, bg: b, ratio: r, min, ok: Number.isFinite(r) && r >= min };
	});
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
	const css = await readFile(new URL('../../assets/css/main.css', import.meta.url), 'utf8');
	const rows = evaluate(parseTokens(css));
	let fails = 0;
	for (const r of rows) {
		if (!r.ok) fails += 1;
		console.log(`${r.ok ? 'PASS' : 'FAIL'}  ${Number.isFinite(r.ratio) ? r.ratio.toFixed(2).padStart(5) : '  n/a'}:1  (min ${r.min})  ${r.label}  ${r.fg} on ${r.bg}`);
	}
	console.log(`\n${rows.length - fails}/${rows.length} pairs pass.`);
	process.exit(fails ? 1 : 0);
}
