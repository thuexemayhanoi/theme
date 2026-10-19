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
		// Footer already provides a dedicated Contact link and Call/Zalo/Maps actions.
		// Avoid a redundant, long hourly-rental sentence under rental links on every viewport.
		await expect(footer.locator('.nt-footer__column--rental .nt-footer__hint')).toHaveCount(0);
		await expect(footer.locator('.nt-footer__column--rental')).not.toContainText('Cần thuê theo giờ?');
		await expect(footer.locator('.nt-footer__column--contact').getByRole('link', { name: 'Liên hệ', exact: true })).toHaveCount(1);
		await expect(footer.locator('.nt-footer__column--brand .nt-footer__nap')).toHaveCount(1);
		const email = footer.locator('.nt-footer__column--brand .nt-footer__nap a[href^="mailto:"]');
		await expect(email).toHaveCount(1);
		await expect(email).toHaveText('nguyentuantu8x@gmail.com');
		await expect(email).toHaveAttribute('href', 'mailto:nguyentuantu8x@gmail.com');
		await expect(email.locator('..').locator('svg.nt-icon--mail[width="18"][aria-hidden="true"]')).toHaveCount(1);
		const iconPalette = await footer.locator('.nt-footer__nap .nt-icon').evaluateAll((icons) =>
			icons.map((icon) => getComputedStyle(icon).color)
		);
		expect(new Set(iconPalette).size).toBe(1);
		const emailLayout = await email.evaluate((a) => ({
			linkRight: a.getBoundingClientRect().right,
			footerRight: document.querySelector('.nt-footer__column--brand').getBoundingClientRect().right
		}));
		expect(emailLayout.linkRight).toBeLessThanOrEqual(emailLayout.footerRight + 1);
		await expect(footer.locator('.nt-footer__column--contact .nt-footer__nap')).toHaveCount(0);
		await expect(footer.locator('.nt-footer__column--contact .nt-cta')).toHaveCount(1);
		// All three footer CTAs must match the four outlined social links,
		// not the filled colors of the independently floating Quick Call menu.
		const footerActions = footer.locator('.nt-footer__column--contact .nt-cta > a');
		const socialActions = footer.locator('.nt-footer__column--brand .nt-footer__social a');
		await expect(footerActions).toHaveCount(3);
		await expect(socialActions).toHaveCount(4);
		const styles = await footer.evaluate((node) => {
			const getStyle = (selector) => [...node.querySelectorAll(selector)].map((a) => {
				const css = getComputedStyle(a);
				return {
					background: css.backgroundColor,
					borderColor: css.borderTopColor,
					borderWidth: css.borderTopWidth,
					radius: css.borderRadius,
					paddingTop: css.paddingTop,
					paddingRight: css.paddingRight,
					fontSize: css.fontSize,
					fontWeight: css.fontWeight,
					color: css.color,
					height: a.getBoundingClientRect().height,
				};
			});
			return {
				cta: getStyle('.nt-footer__column--contact .nt-cta > a'),
				social: getStyle('.nt-footer__column--brand .nt-footer__social > a'),
			};
		});
		for (const button of styles.cta) {
			for (const key of ['background', 'borderColor', 'borderWidth', 'radius', 'paddingTop', 'paddingRight', 'fontSize', 'fontWeight', 'color']) {
				expect(button[key], key + ' must match four social pills').toBe(styles.social[0][key]);
			}
			expect(button.height).toBeGreaterThanOrEqual(44);
			expect(button.background).toBe('rgba(0, 0, 0, 0)');
			expect(button.radius).toBe('999px');
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

for (const width of [320, 360, 390, 768, 1024, 1440]) {
	test('footer copyright and back to top do not overlap contact UI at ' + width + 'px', async ({ page }) => {
		await page.setViewportSize({ width, height: 844 });
		await page.goto('/blog/', { waitUntil: 'networkidle' });
		const copyright = page.locator('.nt-footer__bottom p');
		const backtop = page.locator('.nt-footer__backtop');
		await expect(copyright).toHaveText(/© 2026 Cho Thuê Xe Máy Nguyễn Tú/);
		await expect(copyright).not.toContainText('Giá thuê và điều kiện đặt cọc');
		await expect(backtop).toHaveAttribute('href', '#nt-page-top');
		await expect(backtop).toHaveAttribute('aria-label', 'Lên đầu trang');
		await expect(page.locator('body#nt-page-top')).toHaveCount(1);
		await page.evaluate(() => {
			document.documentElement.style.scrollBehavior = 'auto';
			window.scrollTo(0, document.documentElement.scrollHeight);
		});
		const layout = await page.evaluate(() => {
			const copyright = document.querySelector('.nt-footer__bottom p').getBoundingClientRect();
			const backtop = document.querySelector('.nt-footer__backtop').getBoundingClientRect();
			const fixedContact = document.querySelector('#nt-native-quickcall .nt-qc__launch, #moto-quickcall-sim, .nt-ctabar');
			const contact = fixedContact?.getBoundingClientRect();
			const intersects = (a, b) => !!b && a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top;
			return {
				copyright, backtop,
				buttonBackground: getComputedStyle(document.querySelector('.nt-footer__backtop')).backgroundColor,
				buttonBorder: getComputedStyle(document.querySelector('.nt-footer__backtop')).borderTopColor,
				nonFloating: getComputedStyle(document.querySelector('.nt-footer__backtop')).position !== 'fixed',
				copyrightOverlapsButton: intersects(copyright, backtop),
				contactOverlapsButton: intersects(backtop, contact),
			};
		});
		expect(layout.backtop.height).toBeGreaterThanOrEqual(44);
		expect(layout.backtop.width).toBeGreaterThanOrEqual(44);
		expect(layout.buttonBackground).toBe('rgba(0, 0, 0, 0)');
		expect(layout.buttonBorder).toBe('rgba(255, 255, 255, 0.38)');
		expect(layout.nonFloating).toBe(true);
		// LiteSpeed may delay/rewrite bundled CSS. Inline critical styles must
		// still anchor the Back-to-Top link on the right side of the Footer.
		expect(layout.backtop.right).toBeGreaterThan(layout.copyright.right);

		expect(layout.copyrightOverlapsButton).toBe(false);
		expect(layout.contactOverlapsButton).toBe(false);
		await noHorizontalScroll(page);
		await backtop.click();
		await expect.poll(() => page.evaluate(() => window.scrollY), { timeout: 5000 }).toBeLessThanOrEqual(20);
		await expect(page.locator('#site-header')).toBeVisible();
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
		await expect(page.locator('.nt-footer__column--brand .nt-footer__nap')).toContainText('nguyentuantu8x@gmail.com');
		await expect(page.locator('.nt-footer__column--brand .nt-footer__nap')).toContainText('09:00');
		await expect(page.locator('#site-footer h1')).toHaveCount(0);
	}
});
