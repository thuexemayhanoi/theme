import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { noHorizontalScroll, overflowOffenders } from './helpers.mjs';

/** WCAG contrast ratio of two computed rgb()/rgba() colours. */
const contrastOf = (a, b) => {
	const rgb = (s) => s.match(/[\d.]+/g).slice(0, 3).map(Number);
	const lum = ([r, g, b2]) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b2); };
	const [hi, lo] = [lum(rgb(a)), lum(rgb(b))].sort((x, y) => y - x);
	return (hi + 0.05) / (lo + 0.05);
};

/** WP-CLI of the disposable sandbox (tools/sandbox/wp.sh); only available when the suite runs against it. */
const wp = (...args) => execFileSync('bash', ['tools/sandbox/wp.sh', ...args], { encoding: 'utf8' });

test.describe('blog templates', () => {
	test.use({ viewport: { width: 1440, height: 900 } });

	// The first crumb is Yoast's configurable "home" label (default "Home"); the theme never renames it.
	test('nested category: H1 is the term, breadcrumb shows ancestry, posts are listed', async ({ page }) => {
		await page.goto('/blog/ky-thuat-bao-duong/cham-soc-xe-may/');
		await expect(page.locator('h1')).toHaveText('Chăm sóc xe máy');
		const crumbs = (await page.locator('.nt-breadcrumb').innerText()).replace(/\s+/g, ' ');
		expect(crumbs).toMatch(/(Trang chủ|Home).*Blog.*Kỹ Thuật & Bảo Dưỡng.*Chăm sóc xe máy/);
		expect(await page.locator('.nt-card--post').count()).toBeGreaterThan(0);
	});

	test('an existing but empty category answers 200 with one H1, an empty state and a way out', async ({ page }) => {
		const res = await page.goto('/blog/xe-dien/xe-dap-dien/'); // 0 posts in the baseline
		expect(res.status()).toBe(200);
		await expect(page.locator('h1')).toHaveText('Xe đạp điện');
		expect(await page.locator('.nt-card--post').count()).toBe(0);
		await expect(page.locator('main .nt-empty')).toBeVisible();
		await expect(page.locator('main .nt-empty a[href$="/blog/"], main .nt-empty a[href$="/blog/xe-dien/"]').first()).toBeVisible();
	});

	test('hub category lists child categories as chips with real links', async ({ page }) => {
		await page.goto('/blog/xe-dien/');
		const chips = page.locator('.nt-subcats a');
		expect(await chips.count()).toBe(5); // the 5 real child terms of "Xe Điện" (the menu lists 4 of them)
		for (const href of await chips.evaluateAll((as) => as.map((a) => a.getAttribute('href')))) {
			expect(href).toMatch(/\/blog\/xe-dien\/[a-z0-9-]+\/$/);
		}
	});

	test('blog root paginates with real URLs and keeps the right post set', async ({ page, request }) => {
		await page.goto('/blog/');
		await expect(page.locator('h1')).toHaveText('Blog');
		const next = page.locator('.navigation.pagination a.next');
		await expect(next).toHaveAttribute('href', /\/blog\/page\/2\/$/);
		const res = await request.get('/blog/page/2/');
		expect(res.status()).toBe(200);
		expect((await request.get('/blog/page/999/')).status(), 'out-of-range page is a real 404').toBe(404);
	});

	test('single post: one H1, meta, TOC from real headings, related posts, prev/next, no duplicate hero', async ({ page }) => {
		await page.goto('/blog/ky-thuat-bao-duong/cham-soc-xe-may/');
		const first = await page.locator('.nt-card--post .nt-card__title a').first().getAttribute('href');
		await page.goto(first);
		await expect(page.locator('h1.nt-article__title')).toHaveCount(1);
		await expect(page.locator('.nt-article__meta time').first()).toHaveAttribute('datetime', /^\d{4}-\d{2}-\d{2}T/);
		await expect(page.locator('.nt-article__meta a[rel="author"]')).toBeVisible();
		// Yoast owns the trail: with its default settings a post shows "Home » title"; the Blog › category path
		// appears when Yoast's "taxonomy to show in breadcrumbs for posts" is set. Either way the last crumb is the post.
		const title = (await page.locator('h1.nt-article__title').innerText()).trim();
		await expect(page.locator('.nt-breadcrumb')).toContainText(/Home|Trang chủ/);
		await expect(page.locator('.nt-breadcrumb')).toContainText(title);

		const tocLinks = page.locator('.nt-toc--sticky a[data-nt-toc-link]');
		const n = await tocLinks.count();
		expect(n).toBeGreaterThanOrEqual(3);
		// every TOC link points at a heading that exists
		for (const href of await tocLinks.evaluateAll((as) => as.map((a) => a.getAttribute('href')))) {
			expect(await page.locator(`${href.replace('#', '#')}`).count(), href).toBe(1);
		}
		// jumping to a heading keeps it below the sticky header
		await tocLinks.nth(Math.min(2, n - 1)).click();
		await page.waitForTimeout(600);
		const { top, header } = await page.evaluate(() => {
			const id = location.hash.slice(1);
			return { top: document.getElementById(id).getBoundingClientRect().top, header: document.getElementById('site-header').getBoundingClientRect().bottom };
		});
		expect(top).toBeGreaterThanOrEqual(header - 2);

		const related = page.locator('.nt-related .nt-card--post');
		expect(await related.count()).toBeLessThanOrEqual(4);
		expect(await page.locator('.nt-ctabox').count(), 'contact card lives in the sidebar, not in the reading text').toBe(1);
		expect(await page.locator('.nt-prose .nt-ctabox').count()).toBe(0);
	});

	test('mobile single post shows the collapsible TOC and hides the sticky one', async ({ browser }) => {
		const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
		const page = await ctx.newPage();
		await page.goto('/blog/ky-thuat-bao-duong/cham-soc-xe-may/');
		const first = await page.locator('.nt-card--post .nt-card__title a').first().getAttribute('href');
		await page.goto(first);
		await expect(page.locator('.nt-toc--inline')).toBeVisible();
		await expect(page.locator('.nt-toc--sticky')).toBeHidden();
		await page.locator('.nt-toc--inline summary').click();
		await expect(page.locator('.nt-toc--inline a').first()).toBeVisible();
		await ctx.close();
	});

	// None of the seeded posts holds a table, so the test publishes one (twelve long columns, far wider than a phone) and
	// removes it again; the browser then loads the real post template and its stylesheet.
	test('a wide table in a post scrolls inside its own region instead of the page', async ({ browser }) => {
		test.setTimeout(120_000);
		const cells = (tag) => Array.from({ length: 12 }, (_, i) => `<${tag}>Cột ${i + 1}: tiêu đề đủ dài để bảng rộng hơn màn hình</${tag}>`).join('');
		const html = `<p>Đoạn mở đầu.</p><table><thead><tr>${cells('th')}</tr></thead><tbody><tr>${cells('td')}</tr></tbody></table><p>Đoạn kết.</p>`;
		const b64 = Buffer.from(html, 'utf8').toString('base64');
		let id = 0;
		try {
			id = Number(wp('eval', `echo wp_insert_post(array('post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'E2E bảng rộng (tạm thời)', 'post_content' => base64_decode('${b64}')));`).trim());
			expect(id, 'the fixture post was created').toBeGreaterThan(0);
			const path = new URL(wp('eval', `echo get_permalink(${id});`).trim()).pathname;

			const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
			const page = await ctx.newPage();
			const res = await page.goto(path);
			expect(res.status()).toBe(200);

			const region = page.locator('.nt-prose .nt-table-wrap');
			await expect(region, 'the theme wraps the table once').toHaveCount(1);
			await expect(region).toHaveAttribute('role', 'region');
			await expect(region).toHaveAttribute('tabindex', '0');
			const m = await region.evaluate((el) => ({ wider: el.scrollWidth > el.clientWidth + 50, overflowX: getComputedStyle(el).overflowX }));
			expect(m.wider, 'the table is wider than its region').toBe(true);
			expect(['auto', 'scroll']).toContain(m.overflowX);

			await noHorizontalScroll(page);
			expect(await overflowOffenders(page), 'nothing outside the region sticks out of the viewport').toEqual([]);

			// keyboard users can scroll the focused region
			await region.focus();
			await page.keyboard.press('ArrowRight');
			await expect.poll(() => region.evaluate((el) => el.scrollLeft)).toBeGreaterThan(0);
			await noHorizontalScroll(page);
			await ctx.close();
		} finally {
			if (id > 0) wp('eval', `wp_delete_post(${id}, true);`);
		}
	});
});

// The title of a post and of the blog index was painted with --nt-navy-900, which dark mode redefines as a near-black navy:
// 1.02:1 against the dark page. The title must be readable in both colour modes.
test.describe('titles are readable in light and dark mode', () => {
	for (const mode of ['light', 'dark']) {
		test(`post and blog index titles, ${mode} mode`, async ({ browser }) => {
			for (const width of [390, 1440]) {
				const ctx = await browser.newContext({ viewport: { width, height: 900 } });
				await ctx.addInitScript((m) => localStorage.setItem('nt-color-mode', m), mode);
				const page = await ctx.newPage();
				await page.goto('/blog/ky-thuat-bao-duong/cham-soc-xe-may/');
				const first = await page.locator('.nt-card--post .nt-card__title a').first().getAttribute('href');
				for (const path of [first, '/blog/', '/blog/xe-dien/']) {
					await page.goto(path);
					await expect(page.locator('html')).toHaveAttribute('data-nt-color-mode', mode);
					const c = await page.locator('h1').first().evaluate((h) => ({ color: getComputedStyle(h).color, bg: getComputedStyle(document.body).backgroundColor }));
					expect(contrastOf(c.color, c.bg), `${path} @${width} ${mode}: ${c.color} on ${c.bg}`).toBeGreaterThanOrEqual(4.5);
				}
				await ctx.close();
			}
		});
	}
});
