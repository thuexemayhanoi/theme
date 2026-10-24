import { test, expect } from '@playwright/test';
import { load } from 'cheerio';
import { noHorizontalScroll, overflowOffenders, expectReadable } from './helpers.mjs';
import { NAP } from '../../tools/qa/lib/assist-cases.mjs';

// This spec checks the customer journey and initial HTML, not keyword density or an invented SEO score.
for (const mode of ['light', 'dark']) {
	for (const width of [360, 390, 768, 1440]) {
		test(`homepage service decisions remain useful and accessible at ${width}px, ${mode}`, async ({ page }, testInfo) => {
			await page.setViewportSize({ width, height: 844 });
			await page.addInitScript((value) => localStorage.setItem('nt-color-mode', value), mode);
			const response = await page.goto('/', { waitUntil: 'networkidle' });
			expect(response.status()).toBe(200);
			const source = load(await response.text());

			// Checking the HTTP response catches a cache/plugin replacing the hero src with a lazy placeholder.
			const sourceHero = source('.nt-home-mag__hero-img');
			// The disposable historical fixture may not contain production attachment 4990.
			expect(sourceHero.length).toBeLessThanOrEqual(1);
			if (sourceHero.length) {
				expect(sourceHero.attr('src')).toMatch(/^https?:\/\//);
				expect(sourceHero.attr('src')).not.toMatch(/^data:/);
				expect(sourceHero.attr('srcset')).toBeTruthy();
				expect(sourceHero.attr('sizes')).toBeTruthy();
				expect(sourceHero.attr('loading')).toBe('eager');
				expect(sourceHero.attr('fetchpriority')).toBe('high');
				expect(sourceHero.attr('data-no-lazy')).toBe('1');
				expect(Number(sourceHero.attr('width'))).toBeGreaterThan(0);
				expect(Number(sourceHero.attr('height'))).toBeGreaterThan(0);
			} else {
				await expect(page.locator('.nt-home-mag__hero-visual')).toHaveCount(0);
			}


			await expect(page.locator('main')).toHaveCount(1);
			await expect(page.locator('main h1')).toHaveCount(1);
			await expect(page.locator('main h1')).toContainText(/thuê xe máy Hà Nội/i);
			const intro = page.locator('.nt-home-mag__intro');
			await expect(intro).toContainText('112 Nguyễn Văn Cừ');
			await expect(intro).toContainText('09:00–21:00');
			await expect(intro.locator('.nt-home-mag__actions > a')).toHaveText(['Gọi ngay', 'Chỉ đường']);

			// The commercial answer precedes the editorial feed and remains the only price table.
			await expect(page.locator('main .nt-table--prices')).toHaveCount(1);
			await expect(page.locator('main .nt-policybox')).toHaveCount(1);
			const priceOrder = await page.locator('#bang-gia-tham-khao').evaluate((price) => {
				const featured = document.querySelector('.nt-home-mag__featured');
				return Boolean(featured && (price.compareDocumentPosition(featured) & Node.DOCUMENT_POSITION_FOLLOWING));
			});
			expect(priceOrder, 'customers should see rental prices before the guide-card feed').toBe(true);
			const priceHeaders = page.locator('.nt-table--prices thead th');
			await expect(priceHeaders).toHaveText(['Loại xe', 'Theo ngày', 'Theo tuần', 'Theo tháng', 'Tiền cọc']);

			const decisions = page.locator('.nt-home-mag__decisions');
			await expect(decisions).toHaveCount(1);
			for (const id of ['nt-home-rental-process', 'nt-home-service-area', 'nt-home-rental-faq']) {
				await expect(page.locator(`#${id}`)).toHaveCount(1);
			}
			await expect(decisions).toContainText('112 Nguyễn Văn Cừ');
			const english = decisions.locator('[lang="en"]');
			await expect(english).toHaveCount(1);
			await expect(english).toContainText(/rent|rental/i);
			await expect(english).toContainText(/confirm/i);
			const faq = decisions.locator('details');
			await expect(faq).toHaveCount(6);
			const questions = await faq.locator('summary').allTextContents();
			expect(new Set(questions.map((text) => text.trim())).size).toBe(6);
			for (const item of await faq.all()) {
				const summary = item.locator('summary');
				await expect(summary).not.toBeEmpty();
				await summary.click();
				await expect(item).toHaveAttribute('open', '');
				expect((await item.innerText()).length - (await summary.innerText()).length).toBeGreaterThan(30);
				await expectReadable(summary, `FAQ question at ${width}px ${mode}`);
				await summary.click();
				await expect(item).not.toHaveAttribute('open');
			}

			// Existing navigation and the protected features keep a single instance and their approved destinations.
			await expect(page.locator('#nt-native-quickcall')).toHaveCount(1);
			await expect(page.locator('#nt-native-assistant')).toHaveCount(1);
			await expect(page.locator('[data-nt-qc-launch]')).toHaveCount(1);
			await expect(page.locator('#nt-assist-dialog')).toHaveCount(1);
			await expect(page.locator('.nt-qc__link--call')).toHaveAttribute('href', NAP.tel);
			await expect(page.locator('.nt-qc__link--zalo')).toHaveAttribute('href', NAP.zalo);
			await expect(page.locator('.nt-qc__link--maps')).toHaveAttribute('href', NAP.quickCallMaps);
			await expect(page.locator('#nt-assist-dialog')).toBeHidden();

			await noHorizontalScroll(page);
			expect(await overflowOffenders(page)).toEqual([]);
			await page.evaluate(() => window.scrollTo(0, 0));
			const screenshot = testInfo.outputPath(`homepage-service-${width}-${mode}.png`);
			await page.screenshot({ path: screenshot });
			await testInfo.attach(`homepage service ${width}px ${mode}`, { path: screenshot, contentType: 'image/png' });
		});
	}
}
