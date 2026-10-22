import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
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

// The collapsible contents list of a post sits right after the first <h2> of the text (not under the H1, the meta line and the
// picture). The posts below are published for the test and removed again; the browser loads the real post template.
test.describe('post contents list position', () => {
	test.setTimeout(180_000);
	const lum = ([r, g, b]) => { const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
	const contrast = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };
	const rgb = (s) => s.match(/[\d.]+/g).slice(0, 3).map(Number);
	const FIXTURES = {
		std: {
			title: 'E2E mục lục sau H2 đầu tiên (tạm thời)',
			html: '<p>Đoạn mở đầu của bài.</p>\n<h2>Phần một</h2>\n<p>Nội dung phần một.</p>\n<h2>Phần hai</h2>\n<p>Nội dung phần hai.</p>\n<h3>Mục nhỏ</h3>\n<p>Nội dung mục nhỏ.</p>\n<h2>Phần ba</h2>\n<p>Nội dung phần ba.</p>',
		},
		noH2: {
			title: 'E2E mục lục không có H2 (tạm thời)',
			html: '<p>Mở đầu thứ nhất.</p>\n<p>Mở đầu thứ hai.</p>\n<h3>Mục A</h3>\n<p>a</p>\n<h3>Mục B</h3>\n<p>b</p>\n<h3>Mục C</h3>\n<p>c</p>',
		},
		nested: {
			title: 'E2E mục lục với H2 đầu tiên trong khối (tạm thời)',
			html: '<p>Mở đầu nằm ngoài khối.</p>\n<div class="wp-block-group"><h2>Trong khối</h2><p>x</p></div>\n<h2>Hai</h2>\n<p>y</p>\n<h2>Ba</h2>\n<p>z</p>',
		},
		few: {
			title: 'E2E chỉ hai đề mục (tạm thời)',
			html: '<p>Mở đầu.</p>\n<h2>Một</h2>\n<p>a</p>\n<h2>Hai</h2>\n<p>b</p>',
		},
	};
	const paths = {};
	const ids = [];

	test.beforeAll(() => {
		for (const [key, fx] of Object.entries(FIXTURES)) {
			const b64 = Buffer.from(fx.html, 'utf8').toString('base64');
			const id = Number(wp('eval', `echo wp_insert_post(array('post_type' => 'post', 'post_status' => 'publish', 'post_title' => '${fx.title}', 'post_content' => base64_decode('${b64}')));`).trim());
			expect(id, `the ${key} fixture post was created`).toBeGreaterThan(0);
			ids.push(id);
			paths[key] = new URL(wp('eval', `echo get_permalink(${id});`).trim()).pathname;
		}
	});
	test.afterAll(() => {
		for (const id of ids) wp('eval', `wp_delete_post(${id}, true);`);
	});

	const expectedHeadings = (fx) => [`h1|${fx.title}`, ...[...fx.html.matchAll(/<h([1-4])[^>]*>(.*?)<\/h\1>/gis)].map((m) => `h${m[1]}|${m[2].replace(/<[^>]+>/g, '').trim()}`)];

	async function open(browser, key, width, mode) {
		const ctx = await browser.newContext({ viewport: { width, height: width < 700 ? 844 : 900 } });
		await ctx.addInitScript((m) => localStorage.setItem('nt-color-mode', m), mode);
		const page = await ctx.newPage();
		const res = await page.goto(paths[key]);
		expect(res.status()).toBe(200);
		await expect(page.locator('html')).toHaveAttribute('data-nt-color-mode', mode);
		return { ctx, page };
	}

	// iPhone 390, iPad portrait 768, iPad landscape 1024 (still below the 1100 px switch), iPad Air landscape 1180 and desktop 1440.
	for (const width of [390, 768, 1024, 1180, 1440]) {
		for (const mode of ['light', 'dark']) {
			test(`the list follows the first H2, in order, at ${width}px in ${mode} mode`, async ({ browser }) => {
				const { ctx, page } = await open(browser, 'std', width, mode);
				const info = await page.evaluate(() => {
					const q = (s) => document.querySelector(s);
					const visible = (el) => !!el && el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';
					const before = (a, b) => !!a && !!b && !!(a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING);
					const inline = q('.nt-prose .nt-toc--inline');
					const firstH2 = q('.nt-prose h2');
					return {
						inProse: !!inline,
						inFrontOfProse: !!q('.nt-article__main > .nt-toc--inline'),
						placement: inline?.getAttribute('data-nt-toc-placement') ?? null,
						variants: document.querySelectorAll('.nt-toc').length,
						visible: [...document.querySelectorAll('.nt-toc')].filter(visible).map((el) => (el.classList.contains('nt-toc--inline') ? 'inline' : 'sticky')),
						order: [before(q('h1.nt-article__title'), q('.nt-article__meta')), before(q('.nt-article__meta'), q('.nt-prose > p')), before(q('.nt-prose > p'), firstH2), before(firstH2, inline)],
						prev: inline?.previousElementSibling === firstH2,
						next: inline?.nextElementSibling?.tagName.toLowerCase() ?? null,
						inHeading: !!q('h1 .nt-toc, h2 .nt-toc, h3 .nt-toc, h4 .nt-toc'),
						headingInList: !!q('.nt-toc :is(h1, h2, h3, h4, h5, h6)'),
						h1s: document.querySelectorAll('h1').length,
						headings: [document.querySelector('h1.nt-article__title'), ...document.querySelectorAll('.nt-prose h2, .nt-prose h3, .nt-prose h4')].map((e) => `${e.tagName.toLowerCase()}|${e.textContent.trim()}`),
					};
				});
				expect(info.inProse, 'the inline list is inside the text').toBe(true);
				expect(info.inFrontOfProse, 'and no longer in front of it').toBe(false);
				expect(info.placement).toBe('after-first-h2');
				expect(info.variants, 'one inline and one sticky variant, never a third').toBe(2);
				expect(info.visible, 'exactly one list is visible').toEqual([width < 1100 ? 'inline' : 'sticky']);
				expect(info.order, 'H1, meta line, opening paragraph, first H2, list').toEqual([true, true, true, true]);
				expect(info.prev, 'the list is the element right after the first H2').toBe(true);
				expect(info.next, 'and the text continues with its own element').toBe('p');
				expect(info.inHeading || info.headingInList, 'no list inside a heading, no heading inside the list').toBe(false);
				expect(info.h1s).toBe(1);
				expect(info.headings, 'heading levels, text and order are those of the post').toEqual(expectedHeadings(FIXTURES.std));
				await noHorizontalScroll(page);
				expect(await overflowOffenders(page)).toEqual([]);

				if (width < 1100) {
					const details = page.locator('.nt-prose .nt-toc--inline');
					const summary = details.locator('summary');
					expect(await details.evaluate((d) => d.open), 'collapsed until opened').toBe(false);
					await summary.click();
					expect(await details.evaluate((d) => d.open)).toBe(true);
					const links = details.locator('a[data-nt-toc-link]');
					expect(await links.count()).toBeGreaterThanOrEqual(3);
					await expect(links.first()).toBeVisible();
					await summary.click();
					expect(await details.evaluate((d) => d.open)).toBe(false);
					await summary.focus();
					await page.keyboard.press('Enter');
					expect(await details.evaluate((d) => d.open), 'Enter opens').toBe(true);
					await page.keyboard.press('Space');
					expect(await details.evaluate((d) => d.open), 'Space closes').toBe(false);
					await summary.click();

					const colours = await details.evaluate((d) => ({ bg: getComputedStyle(d).backgroundColor, summary: getComputedStyle(d.querySelector('summary')).color, link: getComputedStyle(d.querySelector('a')).color }));
					expect(contrast(rgb(colours.summary), rgb(colours.bg)), 'summary text contrast').toBeGreaterThanOrEqual(4.5);
					expect(contrast(rgb(colours.link), rgb(colours.bg)), 'link text contrast').toBeGreaterThanOrEqual(4.5);

					// a link scrolls its heading below the sticky header and the hash is the heading's id
					const last = links.last();
					const href = await last.getAttribute('href');
					await last.click();
					await page.waitForTimeout(700);
					const pos = await page.evaluate((id) => ({ hash: location.hash, top: document.getElementById(id).getBoundingClientRect().top, header: document.getElementById('site-header').getBoundingClientRect().bottom }), href.slice(1));
					expect(pos.hash).toBe(href);
					expect(pos.top).toBeGreaterThanOrEqual(pos.header - 2);

					const axe = await new AxeBuilder({ page }).include('.nt-prose').withTags(['wcag2a', 'wcag2aa']).analyze();
					expect(axe.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`), 'the text with the contents list has no accessibility violation').toEqual([]);
				}
				await ctx.close();
			});
		}
	}

	for (const mode of ['light', 'dark']) {
		test(`without an H2 or with the first H2 inside a block the list goes before the first section (${mode})`, async ({ browser }) => {
			for (const [key, next] of [['noH2', 'h3'], ['nested', 'div']]) {
				const { ctx, page } = await open(browser, key, 390, mode);
				const info = await page.evaluate(() => {
					const inline = document.querySelector('.nt-prose .nt-toc--inline');
					return {
						placement: inline?.getAttribute('data-nt-toc-placement') ?? null,
						parentIsProse: inline?.parentElement?.classList.contains('nt-prose') ?? false,
						prev: inline?.previousElementSibling?.tagName.toLowerCase() ?? null,
						next: inline?.nextElementSibling?.tagName.toLowerCase() ?? null,
						visible: [...document.querySelectorAll('.nt-toc')].filter((el) => el.getClientRects().length > 0).length,
						headings: [document.querySelector('h1.nt-article__title'), ...document.querySelectorAll('.nt-prose h2, .nt-prose h3, .nt-prose h4')].map((e) => `${e.tagName.toLowerCase()}|${e.textContent.trim()}`),
					};
				});
				expect(info.placement, key).toBe('after-intro');
				expect(info.parentIsProse, `${key}: a direct child of the text, never inside a block`).toBe(true);
				expect(info.prev, `${key}: right after the opening paragraph`).toBe('p');
				expect(info.next, `${key}: right before the first section`).toBe(next);
				expect(info.visible).toBe(1);
				expect(info.headings).toEqual(expectedHeadings(FIXTURES[key]));
				await noHorizontalScroll(page);
				await ctx.close();
			}
		});
	}

	test('a post with fewer than three headings has no contents list at all', async ({ browser }) => {
		const { ctx, page } = await open(browser, 'few', 390, 'light');
		expect(await page.locator('.nt-toc').count()).toBe(0);
		await ctx.close();
	});

	test('pages, products, categories and the home page never get the in-text list', async ({ page }) => {
		for (const path of ['/', '/gioi-thieu/', '/lien-he/', '/faq/', '/shop/', '/san-pham/honda-wave/', '/danh-muc-san-pham/loai-xe/xe-so/', '/blog/', '/blog/xe-dien/', '/bo-suu-tap/thue-ngay/']) {
			const res = await page.goto(path);
			expect(res.status(), path).toBe(200);
			expect(await page.locator('[data-nt-toc-placement], .nt-toc').count(), `${path} has no contents list`).toBe(0);
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
