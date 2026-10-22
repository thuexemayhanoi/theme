import { expect } from '@playwright/test';

export const VIEWPORTS = [
	{ name: '360', width: 360, height: 800 },
	{ name: '390', width: 390, height: 844 },
	{ name: '430', width: 430, height: 932 },
	{ name: '768', width: 768, height: 1024 },
	{ name: '1280', width: 1280, height: 800 },
	{ name: '1440', width: 1440, height: 900 },
];

/** Routes shared by several specs. Paths only; the sandbox seeds all of them. */
export const ROUTES = [
	{ key: 'home', path: '/' },
	{ key: 'about', path: '/gioi-thieu/' },
	{ key: 'contact', path: '/lien-he/' },
	{ key: 'faq', path: '/faq/' },
	{ key: 'collection', path: '/bo-suu-tap/' },
	{ key: 'shop', path: '/shop/' },
	{ key: 'blog', path: '/blog/' },
	{ key: 'category-nested', path: '/blog/xe-dien/xe-dap-dien/' },
	{ key: 'category-hub', path: '/blog/xe-dien/' },
	{ key: 'product-priced', path: '/san-pham/honda-wave/' },
	{ key: 'product-unpriced', path: '/san-pham/yamaha-sirius/' },
	{ key: 'product-cat', path: '/danh-muc-san-pham/loai-xe/xe-so/' },
	{ key: 'featured-term', path: '/bo-suu-tap/thue-ngay/' },
	{ key: 'search', path: '/?s=xe' },
	{ key: 'not-found', path: '/404-invalid-nonexistent/', status: 404 },
];

/**
 * Collect console errors and failed requests while a page loads.
 * Chromium reports a 404 *document* as a console error ("Failed to load resource ... 404"); for a
 * route that is expected to answer 404 pass its path as documentErrorPath so only that one
 * message is ignored (a 404 for any other resource still counts).
 */
export function watch(page, { documentErrorPath } = {}) {
	const problems = [];
	page.on('console', (m) => {
		if (m.type() !== 'error') return;
		if (documentErrorPath && /status of 404/.test(m.text()) && new URL(m.location().url || 'http://x/').pathname === documentErrorPath) return;
		problems.push(`console: ${m.text()}`);
	});
	page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
	page.on('requestfailed', (r) => {
		if (!/favicon|\/wp-json\/|admin-ajax|google|gravatar/i.test(r.url())) problems.push(`requestfailed: ${r.url()} ${r.failure()?.errorText}`);
	});
	page.on('response', (r) => {
		if (r.status() >= 400 && !/favicon|\/wp-json\/|admin-ajax|gravatar/i.test(r.url()) && r.request().resourceType() !== 'document') problems.push(`http ${r.status()}: ${r.url()}`);
	});
	return problems;
}

/** Elements whose box reaches beyond the viewport (the real cause of horizontal scrolling). */
export async function overflowOffenders(page) {
	return page.evaluate(() => {
		const vw = document.documentElement.clientWidth;
		const out = [];
		for (const el of document.querySelectorAll('body *')) {
			const cs = getComputedStyle(el);
			if (cs.position === 'fixed' || cs.display === 'none' || cs.visibility === 'hidden') continue;
			// Elements inside an intentionally scrollable region are fine.
			if (el.closest('.nt-table-wrap, .nt-lg-slider, .nt-nav, pre, [data-nt-scroll]')) continue;
			// So is anything clipped by an ancestor that hides its overflow (e.g. WooCommerce's zoom image).
			let clipped = false;
			for (let p = el.parentElement; p && p !== document.body && !clipped; p = p.parentElement) {
				const o = getComputedStyle(p);
				if (/(hidden|clip|auto|scroll)/.test(o.overflowX)) clipped = true;
			}
			if (clipped) continue;
			const r = el.getBoundingClientRect();
			if (r.width > 0 && (r.right > vw + 1 || r.left < -1)) {
				out.push(`${el.tagName.toLowerCase()}.${String(el.className).split(' ').slice(0, 2).join('.')} right=${Math.round(r.right)} left=${Math.round(r.left)} vw=${vw}`);
			}
		}
		return out.slice(0, 15);
	});
}

export async function noHorizontalScroll(page) {
	const { sw, cw } = await page.evaluate(() => ({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth }));
	expect(sw, `page scrolls horizontally (scrollWidth ${sw} > clientWidth ${cw})`).toBeLessThanOrEqual(cw);
}

/** WCAG contrast ratio of two computed rgb()/rgba() colour strings. */
export function contrastRatio(a, b) {
	const rgb = (s) => s.match(/[\d.]+/g).slice(0, 3).map(Number);
	const luminance = ([r, g, b]) => {
		const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; };
		return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
	};
	const [hi, lo] = [luminance(rgb(a)), luminance(rgb(b))].sort((x, y) => y - x);
	return (hi + 0.05) / (lo + 0.05);
}

/**
 * Text colour of an element and the first opaque background behind it. Gradients and images are not
 * resolved, so use it for text that sits on a flat surface (titles on the page, table cells).
 */
export async function textOnBackground(locator) {
	return locator.evaluate((el) => {
		let bg = 'rgb(255, 255, 255)';
		for (let e = el; e; e = e.parentElement) {
			const value = getComputedStyle(e).backgroundColor;
			const parts = (value.match(/[\d.]+/g) || []).map(Number);
			if (parts.length >= 3 && (parts.length < 4 || parts[3] > 0.99)) {
				bg = value;
				break;
			}
		}
		return { color: getComputedStyle(el).color, bg };
	});
}

/** The text of the element reaches WCAG AA for normal text (4.5:1) against the surface behind it. */
export async function expectReadable(locator, label, minimum = 4.5) {
	const { color, bg } = await textOnBackground(locator);
	expect(contrastRatio(color, bg), `${label}: ${color} on ${bg}`).toBeGreaterThanOrEqual(minimum);
}
