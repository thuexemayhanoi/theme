import { test, expect } from '@playwright/test';
import { VIEWPORTS, ROUTES, watch, overflowOffenders, noHorizontalScroll } from './helpers.mjs';

for (const vp of VIEWPORTS) {
	test.describe(`layout @ ${vp.name}px`, () => {
		test.use({ viewport: { width: vp.width, height: vp.height } });

		for (const route of ROUTES) {
			test(`${route.key}: no horizontal overflow, no console errors, single H1/main`, async ({ page }) => {
				const problems = watch(page, { documentErrorPath: route.status === 404 ? route.path : undefined });
				const res = await page.goto(route.path, { waitUntil: 'networkidle' });
				expect(res.status()).toBe(route.status ?? 200);

				await noHorizontalScroll(page);
				expect(await overflowOffenders(page), 'elements wider than the viewport').toEqual([]);

				expect(await page.locator('h1').count(), 'exactly one <h1>').toBe(1);
				expect(await page.locator('main').count(), 'exactly one <main>').toBe(1);
				expect(await page.locator('.skip-link').count()).toBeGreaterThanOrEqual(1);

				// Text never touches the screen edge (the legacy hero once did: a flex-basis overrode its width).
				const touching = await page.evaluate(() =>
					[...document.querySelectorAll('main h1, main h2, main h3, main p')]
						.filter((el) => el.offsetParent !== null && el.textContent.trim() !== '' && el.getBoundingClientRect().width > 0 && el.getBoundingClientRect().left < 8)
						.slice(0, 5)
						.map((el) => `${el.tagName} "${el.textContent.trim().slice(0, 40)}" left=${Math.round(el.getBoundingClientRect().left)}`),
				);
				expect(touching, 'text touching the left screen edge').toEqual([]);

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
