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

test('footer headings and page/category links retain crawlable SEO semantics', async ({ page }) => {
	await page.setViewportSize({ width: 1280, height: 900 });
	await page.goto('/', { waitUntil: 'networkidle' });

	const footer = page.locator('#site-footer');
	await expect(footer.locator('.nt-footer__grid h1')).toHaveCount(0);
	await expect(footer.locator('.nt-footer__grid h2')).toHaveCount(3);
	await expect(footer.locator('.nt-footer__grid h3, .nt-footer__grid h4')).toHaveCount(0);
	await expect(footer.locator('.nt-footer__column--brand #nt-f-brand')).toHaveCount(1);

	for (const [group, heading] of [
		['rental', 'Thuê xe & bảng giá'],
		['blog', 'Cẩm nang & Blog'],
		['contact', 'Liên hệ'],
	]) {
		await expect(footer.locator('.nt-footer__column--' + group + ' > h2')).toHaveText(heading);
	}

	// Navigation items are real HTML links, never injected H2/H3 headings or JS-only buttons.
	const problems = await footer.locator('.nt-footer__menu, .nt-footer__list').evaluateAll((menus) =>
		menus.flatMap((menu) => [...menu.querySelectorAll('a')].flatMap((a) => {
			const href = a.getAttribute('href') || '';
			const label = (a.textContent || '').trim();
			return href && !/^javascript:/i.test(href) && label ? [] : [label + ' -> ' + href];
		}))
	);
	expect(problems, 'menu items must have useful anchor text and href').toEqual([]);
	await expect(footer.locator('.nt-footer__menu h1, .nt-footer__menu h2, .nt-footer__menu h3, .nt-footer__list h1, .nt-footer__list h2, .nt-footer__list h3')).toHaveCount(0);
});
