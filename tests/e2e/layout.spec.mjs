import { test, expect } from '@playwright/test';
import { VIEWPORTS, ROUTES, watch, overflowOffenders, noHorizontalScroll } from './helpers.mjs';

for (const vp of VIEWPORTS) {
	test.describe(`layout @ ${vp.name}px`, () => {
		test.use({ viewport: { width: vp.width, height: vp.height } });

		for (const route of ROUTES) {
			test(`${route.key}: no horizontal overflow, no console errors, single H1/main`, async ({ page }) => {
				const problems = watch(page);
				const res = await page.goto(route.path, { waitUntil: 'networkidle' });
				expect(res.status()).toBe(route.status ?? 200);

				await noHorizontalScroll(page);
				expect(await overflowOffenders(page), 'elements wider than the viewport').toEqual([]);

				expect(await page.locator('h1').count(), 'exactly one <h1>').toBe(1);
				expect(await page.locator('main').count(), 'exactly one <main>').toBe(1);
				expect(await page.locator('.skip-link').count()).toBeGreaterThanOrEqual(1);

				// No raw legacy shortcode text, anywhere visible.
				const text = await page.locator('body').innerText();
				expect(text).not.toMatch(/\[\/?(?:ux_[a-z_]+|row|col|section|text_box|button|title|gap|divider|accordion|tabgroup)[\s\]]/i);

				// Every image has an alt attribute and decoded dimensions.
				const bad = await page.evaluate(() => [...document.images].filter((i) => !i.hasAttribute('alt')).map((i) => i.currentSrc || i.src));
				expect(bad, 'images without alt').toEqual([]);

				expect(problems, 'console errors / failed requests').toEqual([]);
			});
		}
	});
}
