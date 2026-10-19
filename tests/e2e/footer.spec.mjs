import { test, expect } from '@playwright/test';
import { noHorizontalScroll, overflowOffenders } from './helpers.mjs';

const widths = [
	[360, 1],
	[390, 1],
	[768, 2],
	[1024, 4],
	[1440, 4],
];

for (const [width, expectedColumns] of widths) {
	test('permanent footer: ' + expectedColumns + ' columns at ' + width + 'px', async ({ page }) => {
		await page.setViewportSize({ width, height: 900 });
		await page.goto('/', { waitUntil: 'networkidle' });

		const footer = page.locator('#site-footer');
		await expect(footer.locator('.nt-footer__grid > .nt-footer__column')).toHaveCount(4);
		const tracks = await footer.locator('.nt-footer__grid').evaluate((el) =>
			getComputedStyle(el).gridTemplateColumns.split(' ').filter(Boolean).length
		);
		expect(tracks).toBe(expectedColumns);
		await expect(footer.locator('.nt-footer__column--brand .nt-footer__tagline')).toContainText('tư vấn nhu cầu thuê theo giờ');
		await expect(footer.locator('.nt-footer__column--brand .nt-footer__nap')).toHaveCount(1);
		await expect(footer.locator('.nt-footer__column--contact .nt-footer__nap')).toHaveCount(0);
		await expect(footer.locator('.nt-footer__column--contact .nt-cta')).toHaveCount(1);
		// Call / Zalo / Directions mirror native Quick Call's semantic palette,
		// while using the same compact pill shape as the four social links.
		const footerActions = footer.locator('.nt-footer__column--contact .nt-cta > a');
		await expect(footerActions).toHaveCount(3);
		const palette = await footerActions.evaluateAll((buttons) =>
			buttons.map((button) => {
				const style = getComputedStyle(button);
				return { background: style.backgroundColor, radius: style.borderRadius, height: button.getBoundingClientRect().height, foreground: style.color };
			})
		);
		expect(palette.map((button) => button.background)).toEqual(['rgb(210, 56, 31)', 'rgb(8, 105, 213)', 'rgb(71, 87, 117)']);
		for (const button of palette) {
			expect(button.radius).toBe('999px');
			expect(button.height).toBeGreaterThanOrEqual(44);
			expect(button.foreground).toBe('rgb(255, 255, 255)');
		}
		await expect(footer.locator('.nt-footer__column--brand .nt-footer__social')).toHaveCount(1);
		const socials = footer.locator('.nt-footer__column--brand .nt-footer__social');
		await expect(socials.locator('a')).toHaveText(['Facebook', 'Zalo', 'WhatsApp', 'Tripadvisor']);
		const tripadvisor = socials.locator('a.nt-footer__social--tripadvisor');
		await expect(tripadvisor).toHaveAttribute('href', 'https://www.tripadvisor.com.vn/Attraction_Review-g21333608-d9826317-Reviews-Hanoi_Motorbike_Rental_Nguyen_Tu-Long_Bien_Hanoi.html');
		await expect(tripadvisor).toHaveAttribute('aria-label', 'Xem Nguyễn Tú trên Tripadvisor');
		await expect(tripadvisor).toHaveAttribute('rel', /noopener.*noreferrer/);
		await expect(tripadvisor.locator('svg.nt-icon--tripadvisor[aria-hidden="true"]')).toHaveCount(1);
		const isTouchTarget = await tripadvisor.evaluate((element) => element.getBoundingClientRect().height >= 44);
		expect(isTouchTarget).toBe(true);
		await expect(footer.locator('.nt-footer__nap address')).toHaveCount(0);
		await expect(footer.locator('address')).toHaveCount(1);
		await noHorizontalScroll(page);
		expect(await overflowOffenders(page)).toEqual([]);
	});
}

test('only strategic Hub, rental and information links; no copied 36-item menu', async ({ page }) => {
	await page.setViewportSize({ width: 1280, height: 900 });
	await page.goto('/', { waitUntil: 'networkidle' });
	const footer = page.locator('#site-footer');
	await expect(footer.locator('h1')).toHaveCount(0);
	await expect(footer.locator('.nt-footer__grid h2')).toHaveCount(3);
	await expect(footer.locator('.nt-footer__grid h3')).toHaveCount(0);
	await expect(footer.locator('.nt-footer__menu')).toHaveCount(0);
	await expect(footer.locator('.nt-footer__widgets')).toHaveCount(0);
	for (const [group, max, min] of [
		['rental', 9, 6],
		['blog', 7, 5],
		['contact', 5, 4],
	]) {
		const n = await footer.locator('.nt-footer__column--' + group + ' nav .nt-footer__list > li > a').count();
		expect(n).toBeGreaterThanOrEqual(min);
		expect(n).toBeLessThanOrEqual(max);
	}
	await expect(footer.locator('.nt-footer__column--rental nav a')).toContainText([
		'Bảng giá thuê xe máy', 'Thuê theo ngày', 'Thuê theo tuần', 'Thuê theo tháng',
		'Xe ga 50cc', 'Xe số', 'Xe ga', 'Thuê xe máy điện', 'Thuê xe đạp điện',
	]);
	const problems = await footer.locator('.nt-footer__list').evaluateAll((menus) => {
		const links = menus.flatMap((el) => [...el.querySelectorAll('a')]);
		const urls = links.map((a) => a.getAttribute('href'));
		const labels = links.map((a) => (a.textContent || '').trim());
		const invalid = links.filter((a) => !a.href || !a.textContent.trim() || /^javascript:/i.test(a.getAttribute('href') || ''));
		return { invalid: invalid.length, duplicateUrls: urls.length - new Set(urls).size, duplicateLabels: labels.length - new Set(labels).size };
	});
	expect(problems).toEqual({ invalid: 0, duplicateUrls: 0, duplicateLabels: 0 });
	await expect(footer.locator('.nt-footer__list .sub-menu')).toHaveCount(0);
	await expect(footer.locator('.nt-footer__list h2, .nt-footer__list h3')).toHaveCount(0);
});

test('footer is shared by core page, archive, WooCommerce and collection', async ({ page }) => {
	for (const path of ['/', '/gioi-thieu/', '/lien-he/', '/blog/xe-dien/', '/san-pham/honda-wave/', '/bo-suu-tap/']) {
		const res = await page.goto(path, { waitUntil: 'networkidle' });
		expect(res.status()).toBe(200);
		await expect(page.locator('#site-footer')).toHaveCount(1);
		await expect(page.locator('.nt-footer__grid > .nt-footer__column')).toHaveCount(4);
		await expect(page.locator('.nt-footer__column--brand .nt-footer__nap')).toContainText('112 Nguyễn Văn Cừ');
		await expect(page.locator('.nt-footer__column--brand .nt-footer__nap')).toContainText('0942 467 674');
		await expect(page.locator('.nt-footer__column--brand .nt-footer__nap')).toContainText('09:00');
		await expect(page.locator('#site-footer h1')).toHaveCount(0);
	}
});
