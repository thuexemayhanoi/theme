import { test, expect } from '@playwright/test';
import { noHorizontalScroll } from './helpers.mjs';

for (const [width, expectedColumns] of [[390, 1], [768, 2], [1280, 4]]) {
	test('footer uses ' + expectedColumns + ' responsive columns at ' + width + 'px', async ({ page }) => {
		await page.setViewportSize({ width, height: 900 });
		await page.goto('/', { waitUntil: 'networkidle' });

		await expect(page.locator('.nt-footer__grid > .nt-footer__column')).toHaveCount(4);
		const columns = await page.locator('.nt-footer__grid').evaluate((el) =>
			getComputedStyle(el).gridTemplateColumns.split(' ').filter(Boolean).length
		);
		expect(columns).toBe(expectedColumns);

		await expect(page.locator('.nt-footer__column--brand .nt-footer__tagline')).toHaveCount(1);
		await expect(page.locator('.nt-footer__column--brand .nt-footer__nap')).toHaveCount(0);
		await expect(page.locator('.nt-footer__column--contact .nt-footer__nap')).toHaveCount(1);
		await expect(page.locator('.nt-footer__column--contact .nt-cta')).toHaveCount(1);
		await expect(page.locator('#nt-f-contact')).toContainText('Liên hệ');
		await noHorizontalScroll(page);
	});
}
