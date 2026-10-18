import { test } from '@playwright/test';
import { mkdir } from 'node:fs/promises';

/**
 * Evidence screenshots (360 / 768 / 1440), JPEG to keep the repository small. Run with NT_SHOTS=1;
 * output in reports/screenshots/. NT_SHOTS_SUFFIX adds a file-name suffix (e.g. "-with-plugins" for the
 * run where the QuickCall/MotoAI simulators are active) and NT_SHOTS_ONLY limits the pages (comma list).
 */
test.skip(!process.env.NT_SHOTS, 'set NT_SHOTS=1 to capture screenshots');
const SUFFIX = process.env.NT_SHOTS_SUFFIX ?? '';
const ONLY = (process.env.NT_SHOTS_ONLY ?? '').split(',').filter(Boolean);
const wanted = (name) => ONLY.length === 0 || ONLY.includes(name);
const shot = (page, name, w, fullPage) => page.screenshot({ path: `reports/screenshots/${name}-${w}${SUFFIX}.jpg`, type: 'jpeg', quality: 64, fullPage, animations: 'disabled', caret: 'hide' });

const WIDTHS = [
	{ w: 360, h: 800 },
	{ w: 390, h: 844 },
	{ w: 768, h: 1024 },
	{ w: 1440, h: 900 },
];
const PAGES = [
	['home', '/'],
	['category-hub', '/blog/xe-dien/'],
	['product', '/san-pham/honda-wave/'],
	['product-unpriced', '/san-pham/yamaha-sirius/'],
	['contact', '/lien-he/'],
	['featured-term', '/bo-suu-tap/thue-ngay/'],
	['shop', '/shop/'],
	['search', '/?s=xe'],
	['not-found', '/404-invalid-nonexistent/'],
];

for (const { w, h } of WIDTHS) {
	test.describe(`screenshots ${w}`, () => {
		test.use({ viewport: { width: w, height: h } });
		test.beforeAll(async () => mkdir('reports/screenshots', { recursive: true }));

		for (const [name, path] of PAGES.filter(([n]) => wanted(n))) {
			test(`${name} @ ${w}`, async ({ page }) => {
				await page.goto(path, { waitUntil: 'networkidle' });
				await shot(page, name, w, true);
			});
		}

		test(`post @ ${w}`, async ({ page }) => {
			test.skip(!wanted('post'));
			await page.goto('/blog/xe-dien/', { waitUntil: 'networkidle' });
			const first = await page.locator('.nt-card--post .nt-card__title a').first().getAttribute('href');
			await page.goto(first, { waitUntil: 'networkidle' });
			await shot(page, 'post', w, true);
		});

		test(`menu open @ ${w}`, async ({ page }) => {
			test.skip(!wanted('menu-open'));
			await page.goto('/', { waitUntil: 'networkidle' });
			if (w < 1024) {
				await page.locator('[data-nt-menu-toggle]').click();
				await page.locator('li.nt-menu__item--d0.nt-has-mega > [data-nt-sub-toggle]').click();
			} else {
				await page.locator('li.nt-menu__item--d0.nt-has-mega > [data-nt-sub-toggle]').focus();
				await page.keyboard.press('Enter');
			}
			await page.waitForTimeout(400);
			await shot(page, 'menu-open', w, false);
		});
	});
}
