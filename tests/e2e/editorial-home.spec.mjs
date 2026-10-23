import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { expectReadable, noHorizontalScroll, overflowOffenders } from './helpers.mjs';

const curatedGroups = [[476, 1060, 895, 829], [847, 841, 862, 836, 857], [1062, 823, 887]];
let curatedUrls;
test.beforeAll(() => {
	curatedUrls = JSON.parse(execFileSync('bash', ['tools/sandbox/wp.sh', 'eval',
		'echo wp_json_encode(array_map("get_permalink", array(476,1060,895,829,847,841,862,836,857,1062,823,887)));'], { encoding: 'utf8' }));
});

for (const width of [390, 768, 1440]) {
	test(`editorial-commerce homepage has one H1, real Woo products, one price table and topic links at ${width}px`, async ({ page }) => {
		await page.setViewportSize({ width, height: 900 });
		const response = await page.goto('/', { waitUntil: 'networkidle' });
		expect(response.status()).toBe(200);

		await expect(page.locator('main#main')).toHaveCount(1);
		await expect(page.locator('main h1')).toHaveCount(1);
		await expect(page.locator('main h1')).toHaveText('Cho Thuê Xe Máy Hà Nội – Giao Tận Nơi, Cọc Nhẹ');
		await expect(page.locator('.nt-home-mag__intro')).toHaveCount(1);

		const hero = page.locator('.nt-home-mag__intro');
		const actions = hero.locator('.nt-home-mag__actions > a');
		await expect(actions).toHaveCount(2);
		await expect(actions).toHaveText(['Gọi ngay', 'Chỉ đường']);
		await expect(hero.locator('.nt-btn--zalo')).toHaveCount(0);
		await expect(actions.nth(0)).toHaveAttribute('href', /^tel:/);
		await expect(actions.nth(1)).toHaveAttribute('href', /^https?:\/\//);
		const actionLayout = await actions.evaluateAll((items) => items.map(item => {
			const r = item.getBoundingClientRect();
			return { top: r.top, bottom: r.bottom, left: r.left, right: r.right, height: r.height };
		}));
		expect(Math.abs(actionLayout[0].top - actionLayout[1].top)).toBeLessThanOrEqual(2);
		expect(actionLayout[0].right).toBeLessThanOrEqual(actionLayout[1].left + 1);
		expect(actionLayout[0].height).toBeGreaterThanOrEqual(44);
		expect(actionLayout[1].height).toBeGreaterThanOrEqual(44);
		const shortcuts = hero.locator('.nt-home-mag__shortcuts a');
		await expect(shortcuts).toHaveText(['Thuê xe số', 'Thuê xe ga', 'Thuê xe điện', 'Xe máy 50cc']);
		await expect(shortcuts.last()).toHaveAttribute('href', /\/(?:thue-xe-may-50cc-ha-noi|xe-ga-50cc)\/?$/);
		// The shared CTA renderer remains unchanged for the footer/other page sections.
		await expect(page.locator('.nt-home-mag__closing .nt-btn--zalo')).toHaveCount(1);
		await expect(page.locator('.nt-home__body'), 'legacy Flatsome HTML is preserved in DB but hidden from new home').toHaveCount(0);
		await expect(page.locator('main .nt-legacy')).toHaveCount(0);

		await expect(page.locator('.nt-home-mag__find form input[type="search"]')).toHaveCount(1);
		await expect(page.locator('.nt-home-mag__featured .nt-card--post')).toHaveCount(4);
		for (const [index, selector] of ['.nt-home-mag__featured', '[aria-labelledby="nt-home-topic-areas"]', '[aria-labelledby="nt-home-topic-rental-guides"]'].entries()) {
			const start = curatedGroups.slice(0, index).reduce((sum, group) => sum + group.length, 0);
			expect(await page.locator(selector + ' .nt-card__title a').evaluateAll(links => links.map(a => a.href)))
				.toEqual(curatedUrls.slice(start, start + curatedGroups[index].length));
		}
		await expect(page.locator('.nt-home-mag__latest .nt-card--post')).toHaveCount(4);
		await expect(page.locator('.nt-home-mag__hubs .nt-home-mag__hub').first()).toHaveAttribute('href', /\/blog\//);

		await expect(page.locator('.nt-section #nt-products-title')).toHaveCount(1);
		await expect(page.locator('.nt-section .nt-card--product')).toHaveCount(8);
		await expect(page.locator('main .nt-table--prices')).toHaveCount(1);
		await expect(page.locator('main .nt-table--prices tbody tr').first()).toBeVisible();
		await expect(page.locator('main .nt-policybox')).toHaveCount(1);
		// The deposit checklist has the same blue as Zalo, not a third green accent.
		expect(await page.locator('main .nt-checklist li').first().evaluate(
			el => getComputedStyle(el, '::before').backgroundColor
		)).toBe('rgb(0, 104, 255)');
		// In production the activated Smart Core injects one calculator under the native price table.
		// On sandbox installations without Smart Core, do not fabricate a second calculator.
		expect(await page.locator('main .ntsc-rental').count()).toBeLessThanOrEqual(1);

		const articleHrefs = await page.locator('.nt-home-mag__featured .nt-card__title a, .nt-home-mag__topic .nt-card__title a, .nt-home-mag__latest .nt-card__title a')
			.evaluateAll(els => els.map(a => a.href));
		expect(new Set(articleHrefs).size).toBe(articleHrefs.length);
		await noHorizontalScroll(page);
		expect(await overflowOffenders(page)).toEqual([]);
	});
}

for (const width of [390, 768, 1440]) {
	test(`dark mode: editorial hero and closing CTA keep legible text and outlined directions at ${width}px`, async ({ page }) => {
		await page.setViewportSize({ width, height: 900 });
		await page.addInitScript(() => localStorage.setItem('nt-color-mode', 'dark'));
		await page.goto('/', { waitUntil: 'networkidle' });
		await expect(page.locator('html')).toHaveAttribute('data-nt-color-mode', 'dark');

		const closing = page.locator('.nt-home-mag__closing');
		await expect(closing).toHaveCSS('background-color', 'rgb(27, 47, 91)');
		await expect(closing.locator('h2')).toHaveCSS('color', 'rgb(255, 255, 255)');
		const closingParagraphs = closing.locator('p');
		expect(await closingParagraphs.count()).toBeGreaterThan(0);
		for (const paragraph of await closingParagraphs.all()) {
			await expect(paragraph).toHaveCSS('color', 'rgb(224, 230, 241)');
		}

		for (const selector of ['.nt-home-mag__intro', '.nt-home-mag__closing']) {
			const directions = page.locator(selector + ' .nt-btn--ghost');
			await expect(directions).toHaveCount(1);
			await expect(directions).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)');
			await expect(directions).toHaveCSS('color', 'rgb(229, 239, 255)');
			await expect(directions).toHaveCSS('border-top-color', 'rgb(169, 191, 223)');
			await expect(directions).toHaveAttribute('href', /^https?:\/\//);
		}

		// The row headers of the price table (the vehicle names) were painted with the near-black navy that dark mode keeps for
		// backgrounds: 1.2:1 on the table row. Table at 768 and 1440 px, one card per row at 390 px.
		const rowHeaders = page.locator('main .nt-table--prices tbody th');
		expect(await rowHeaders.count()).toBeGreaterThan(0);
		for (const header of await rowHeaders.all()) {
			await expectReadable(header, `price table row header "${(await header.innerText()).trim()}" @${width}`);
		}
		await noHorizontalScroll(page);
		expect(await overflowOffenders(page)).toEqual([]);
	});
}
