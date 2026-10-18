import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { VIEWPORTS, ROUTES, watch, overflowOffenders, noHorizontalScroll } from './helpers.mjs';

/** WP-CLI of the disposable sandbox (tools/sandbox/wp.sh); only available when the suite runs against it. */
const wp = (...args) => execFileSync('bash', ['tools/sandbox/wp.sh', ...args], { encoding: 'utf8' });

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

// Content that brings its own wide layout and H1 must keep a visible breadcrumb and must not get a second heading.
// The sandbox featured items are plain text, so the test publishes one with a wide body and removes it afterwards.
test.describe('wide authored content', () => {
	test.use({ viewport: { width: 390, height: 844 } });

	test('featured item with its own H1: one H1, one visible breadcrumb, no theme page header', async ({ page }) => {
		test.setTimeout(120_000);
		const body = '[section][row][col span="12"]<h1>Tiêu đề do nội dung tự cung cấp</h1><p>Đoạn văn của bộ sưu tập.</p>[/col][/row][/section]';
		const b64 = Buffer.from(body, 'utf8').toString('base64');
		let id = 0;
		try {
			id = Number(wp('eval', `echo wp_insert_post(array('post_type' => 'featured_item', 'post_status' => 'publish', 'post_title' => 'E2E bộ sưu tập (tạm thời)', 'post_content' => base64_decode('${b64}')));`).trim());
			expect(id, 'the fixture featured item was created').toBeGreaterThan(0);
			const path = new URL(wp('eval', `echo get_permalink(${id});`).trim()).pathname;
			const res = await page.goto(path);
			expect(res.status()).toBe(200);
			await expect(page.locator('main .nt-legacy .nt-lg-section')).toHaveCount(1);
			await expect(page.locator('h1')).toHaveCount(1);
			await expect(page.locator('h1')).toHaveText('Tiêu đề do nội dung tự cung cấp');
			await expect(page.locator('.nt-pagehead'), 'the theme prints no page header of its own').toHaveCount(0);
			await expect(page.locator('.nt-pagecrumbs .nt-breadcrumb'), 'the breadcrumb is still there').toHaveCount(1);
			await expect(page.locator('.nt-breadcrumb')).toBeVisible();
			await noHorizontalScroll(page);
		} finally {
			if (id > 0) wp('eval', `wp_delete_post(${id}, true);`);
		}
	});

	test('home quick facts: "auto" adds nothing next to an authored hero, "on" prints the strip, still one H1', async ({ page }) => {
		test.setTimeout(120_000);
		const read = () => wp('eval', "$o = (array) get_option('nguyentu_options', array()); echo (string) ( $o['home']['quickfacts'] ?? '' );").trim();
		const write = (value) => wp('eval', `$o = (array) get_option('nguyentu_options', array()); $o['home'] = (array) ( $o['home'] ?? array() ); ${value === '' ? "unset( $o['home']['quickfacts'] );" : `$o['home']['quickfacts'] = '${value}';`} update_option('nguyentu_options', $o);`);
		const original = read();
		try {
			write('auto');
			await page.goto('/');
			await expect(page.locator('h1')).toHaveCount(1);
			await expect(page.locator('.nt-hero'), 'the seeded home content brings its own H1, so there is no native hero').toHaveCount(0);
			await expect(page.locator('.nt-quickfacts')).toHaveCount(0);

			write('on');
			await page.goto('/');
			await expect(page.locator('h1')).toHaveCount(1);
			await expect(page.locator('.nt-quickfacts .nt-hero__facts li')).toHaveCount(3);
			await expect(page.locator('.nt-quickfacts')).toContainText('Mở cửa 09:00');
			await noHorizontalScroll(page);

			write('off');
			await page.goto('/');
			await expect(page.locator('.nt-quickfacts')).toHaveCount(0);
		} finally {
			write(original);
		}
	});
});
