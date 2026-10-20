import { readFileSync } from 'node:fs';
import { test, expect } from '@playwright/test';

test.describe('desktop menu (1280px)', () => {
	test.use({ viewport: { width: 1280, height: 800 } });

	test('shows 6 top-level items and all 36 links exist in one nav', async ({ page }) => {
		await page.goto('/');
		const nav = page.locator('#nt-nav');
		await expect(nav.locator('li.nt-menu__item--d0')).toHaveCount(6);
		await expect(nav.locator('a.nt-menu__link')).toHaveCount(36);
		await expect(page.locator('a.nt-menu__link')).toHaveCount(36);
		await expect(page.locator('[data-nt-menu-toggle]')).toBeHidden();
	});

	test('rental catalogue has distinct link labels without changing destinations', async ({ page }) => {
		await page.goto('/');
		for (const [slug, label] of [['xe-so', 'Thuê xe số'], ['xe-ga', 'Thuê xe ga'], ['xe-dien', 'Thuê xe điện']]) {
			const anchor = page.locator('#nt-nav a.nt-menu__link[href$="/danh-muc-san-pham/loai-xe/' + slug + '/"]');
			await expect(anchor).toHaveCount(1);
			await expect(anchor).toHaveText(label);
		}
		await expect(page.locator('#nt-nav a.nt-menu__link[href$="/blog/xe-dien/"]')).toHaveText('Xe điện');
	});

	test('keyboard: Tab to a parent, toggle opens it, Escape closes it and returns focus', async ({ page }) => {
		await page.goto('/');
		const blog = page.locator('li.nt-menu__item--d0.nt-has-mega');
		const toggle = blog.locator(':scope > [data-nt-sub-toggle]');
		await expect(toggle).toHaveAttribute('aria-expanded', 'false');
		await toggle.focus();
		await page.keyboard.press('Enter');
		await expect(toggle).toHaveAttribute('aria-expanded', 'true');
		const panel = blog.locator(':scope > ul.nt-sub--d1');
		await expect(panel).toBeVisible();
		// the mega panel shows its third level without a fly-out
		await expect(blog.locator('ul.nt-sub--d2 a').first()).toBeVisible();
		const box = await panel.boundingBox();
		expect(box.x).toBeGreaterThanOrEqual(0);
		expect(box.x + box.width).toBeLessThanOrEqual(1280 + 1);
		expect(box.y + box.height, 'panel stays inside the viewport height').toBeLessThanOrEqual(800 + 2);
		await page.keyboard.press('Escape');
		await expect(toggle).toHaveAttribute('aria-expanded', 'false');
		await expect(panel).toBeHidden();
		await expect(toggle).toBeFocused();
	});

	test('keyboard: Tab order reaches nested links of an open dropdown', async ({ page }) => {
		await page.goto('/');
		const toggle = page.locator('li.nt-menu__item--d0:has(> a[href*="danh-muc-san-pham"]) > [data-nt-sub-toggle]');
		await toggle.focus();
		await page.keyboard.press('Enter');
		await page.keyboard.press('Tab');
		const focused = await page.evaluate(() => document.activeElement?.getAttribute('href') ?? '');
		expect(focused).toContain('/danh-muc-san-pham/');
	});

	test('the current page is marked with aria-current in the menu', async ({ page }) => {
		await page.goto('/lien-he/');
		await expect(page.locator('a.nt-menu__link[aria-current="page"]')).toHaveCount(1);
	});
});

for (const width of [360, 768]) {
	test.describe(`mobile drawer (${width}px)`, () => {
		test.use({ viewport: { width, height: 800 } });

		test('opens, traps focus, closes with Escape and restores focus', async ({ page }) => {
			await page.goto('/');
			const toggle = page.locator('[data-nt-menu-toggle]');
			const nav = page.locator('#nt-nav');
			await expect(nav).toBeHidden();
			await expect(toggle).toHaveAttribute('aria-expanded', 'false');

			await toggle.click();
			await expect(nav).toBeVisible();
			await expect(toggle).toHaveAttribute('aria-expanded', 'true');
			await expect(page.locator('[data-nt-menu-close]')).toBeFocused();

			// focus stays inside the drawer while tabbing past the last element
			for (let i = 0; i < 80; i += 1) await page.keyboard.press('Tab');
			expect(await page.evaluate(() => document.activeElement?.closest('#nt-nav') !== null)).toBe(true);

			await page.keyboard.press('Escape');
			await expect(nav).toBeHidden();
			await expect(toggle).toBeFocused();
		});

		test('accordion reveals the three levels of the Blog branch', async ({ page }) => {
			await page.goto('/');
			await page.locator('[data-nt-menu-toggle]').click();
			const blog = page.locator('li.nt-menu__item--d0.nt-has-mega');
			await blog.locator(':scope > [data-nt-sub-toggle]').click();
			await expect(blog.locator(':scope > ul.nt-sub--d1')).toBeVisible();
			const xeDien = blog.locator('li.nt-menu__item--d1:has(> a[href*="/blog/xe-dien/"])');
			await xeDien.locator(':scope > [data-nt-sub-toggle]').click();
			await expect(xeDien.locator('ul.nt-sub--d2 a')).toHaveCount(4);
			// drawer is scrollable, never wider than the screen
			const box = await page.locator('#nt-nav').boundingBox();
			expect(box.width).toBeLessThanOrEqual(width);
		});

		test('scrim click closes the drawer and the page can scroll again', async ({ page }) => {
			await page.goto('/');
			await page.locator('[data-nt-menu-toggle]').click();
			await expect(page.locator('body')).toHaveClass(/nt-lock/);
			await page.locator('[data-nt-scrim]').click({ position: { x: 5, y: 300 } });
			await expect(page.locator('#nt-nav')).toBeHidden();
			await expect(page.locator('body')).not.toHaveClass(/nt-lock/);
		});
	});
}

test('search panel: opens from the header, submits, results page has results and noindex', async ({ page }) => {
	await page.goto('/');
	await page.locator('[data-nt-search-toggle]').click();
	await expect(page.locator('#nt-search-panel')).toBeVisible();
	await expect(page.locator('#nt-search-panel input[type="search"]')).toBeFocused();
	await page.keyboard.type('xe');
	await page.keyboard.press('Enter');
	await expect(page).toHaveURL(/\?s=xe/);
	await expect(page.locator('h1')).toContainText('Kết quả tìm kiếm');
	expect(await page.locator('.nt-result').count()).toBeGreaterThan(0);
	await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/);
});

test('without JavaScript every menu stays reachable', async ({ browser }) => {
	const ctx = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 390, height: 800 } });
	const page = await ctx.newPage();
	await page.goto('/');
	expect(await page.locator('a.nt-menu__link').count()).toBe(36);
	await expect(page.locator('#nt-nav')).toBeVisible();
	await ctx.close();
});

// A separate menu assigned to the mobile location replaces the main one in the drawer: the header markup carries both
// (nav.nt-nav--split) and the stylesheet shows one. Checked against the real stylesheet on a minimal document, so no
// site state (menu assignments) has to change while other specs run.
test.describe('separate mobile menu: the drawer shows it instead of the main menu', () => {
	const css = readFileSync('assets/css/main.css', 'utf8');
	const nav = (cls) => `<header class="nt-header"><nav id="nt-nav" class="${cls}" data-nt-nav><div class="nt-nav__scroll">
		<ul id="nt-menu-primary" class="nt-menu"><li class="nt-menu__item nt-menu__item--d0"><a class="nt-menu__link" href="#main-item">Main menu item</a></li></ul>
		<div class="nt-nav__mobile-only"><ul id="nt-menu-primary_mobile" class="nt-menu"><li class="nt-menu__item nt-menu__item--d0"><a class="nt-menu__link" href="#mobile-item">Mobile menu item</a></li></ul></div>
	</div></nav></header>`;
	const shown = (page) => page.evaluate(() => ({
		main: getComputedStyle(document.getElementById('nt-menu-primary')).display !== 'none',
		mobile: getComputedStyle(document.querySelector('.nt-nav__mobile-only')).display !== 'none',
	}));

	for (const [label, width, expected] of [['phone 390 px', 390, { main: false, mobile: true }], ['tablet 768 px', 768, { main: false, mobile: true }], ['desktop 1280 px', 1280, { main: true, mobile: false }]]) {
		test(`${label}: main menu ${expected.main ? 'shown' : 'hidden'}, mobile menu ${expected.mobile ? 'shown' : 'hidden'}`, async ({ browser }) => {
			const page = await browser.newPage({ viewport: { width, height: 900 } });
			await page.setContent(`<!doctype html><html lang="vi"><head><meta charset="utf-8"><style>${css}</style></head><body>${nav('nt-nav nt-nav--split')}</body></html>`);
			expect(await shown(page)).toEqual(expected);
			await page.close();
		});
	}

	test('without a separate mobile menu the stylesheet does not hide the main menu on a phone', async ({ browser }) => {
		const page = await browser.newPage({ viewport: { width: 390, height: 900 } });
		await page.setContent(`<!doctype html><html lang="vi"><head><meta charset="utf-8"><style>${css}</style></head><body>${nav('nt-nav')}</body></html>`);
		expect((await shown(page)).main).toBe(true);
		await page.close();
	});
});
