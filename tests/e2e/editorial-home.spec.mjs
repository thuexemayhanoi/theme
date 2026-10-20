import { test, expect } from '@playwright/test';
import { noHorizontalScroll, overflowOffenders } from './helpers.mjs';

for (const width of [390, 768, 1440]) {
	test(`editorial-commerce homepage has one H1, real Woo products, one price table and topic links at ${width}px`, async ({ page }) => {
		await page.setViewportSize({ width, height: 900 });
		const response = await page.goto('/', { waitUntil: 'networkidle' });
		expect(response.status()).toBe(200);

		await expect(page.locator('main#main')).toHaveCount(1);
		await expect(page.locator('main h1')).toHaveCount(1);
		await expect(page.locator('main h1')).toHaveText('Cho Thuê Xe Máy Hà Nội – Giao Tận Nơi, Cọc Nhẹ');
		await expect(page.locator('.nt-home-mag__intro')).toHaveCount(1);
		await expect(page.locator('.nt-home__body'), 'legacy Flatsome HTML is preserved in DB but hidden from new home').toHaveCount(0);
		await expect(page.locator('main .nt-legacy')).toHaveCount(0);

		await expect(page.locator('.nt-home-mag__find form input[type="search"]')).toHaveCount(1);
		await expect(page.locator('.nt-home-mag__featured .nt-card--post')).toHaveCount(4);
		await expect(page.locator('.nt-home-mag__hubs .nt-home-mag__hub').first()).toHaveAttribute('href', /\/blog\//);

		await expect(page.locator('.nt-section #nt-products-title')).toHaveCount(1);
		await expect(page.locator('.nt-section .nt-card--product')).toHaveCount(8);
		await expect(page.locator('main .nt-table--prices')).toHaveCount(1);
		await expect(page.locator('main .nt-table--prices tbody tr').first()).toBeVisible();
		await expect(page.locator('main .nt-policybox')).toHaveCount(1);
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
