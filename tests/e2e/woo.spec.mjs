import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

/** WP-CLI of the disposable sandbox (tools/sandbox/wp.sh); only available when the suite runs against it. */
const wp = (...args) => execFileSync('bash', ['tools/sandbox/wp.sh', ...args], { encoding: 'utf8' });

test.describe('WooCommerce (classic cart and checkout pages)', () => {
	test.use({ viewport: { width: 1280, height: 900 } });

	test('shop lists the 13 products with one H1 and the theme wrapper', async ({ page }) => {
		await page.goto('/shop/');
		await expect(page.locator('h1')).toHaveCount(1);
		await expect(page.locator('main.nt-shop')).toHaveCount(1);
		await expect(page.locator('ul.products li.product')).toHaveCount(13);
		await expect(page.locator('.nt-price-contact').first()).toContainText('Liên hệ báo giá');
	});

	// Geometry guards: WooCommerce's float/percentage widths once squeezed grid cards to ~60px and the
	// gallery to 275px while every DOM assertion still passed.
	for (const [label, width, minCards, minCard] of [['desktop', 1440, 4, 200], ['tablet', 768, 3, 170], ['phone', 360, 2, 140]]) {
		test(`shop grid @ ${label}: cards are real cards in a filled grid`, async ({ page }) => {
			await page.setViewportSize({ width, height: 900 });
			await page.goto('/shop/');
			const boxes = await page.locator('ul.products li.product').evaluateAll((els) => els.map((e) => e.getBoundingClientRect().toJSON()));
			expect(boxes.length).toBe(13);
			for (const b of boxes) expect(b.width, 'product card width').toBeGreaterThanOrEqual(minCard);
			const firstRow = boxes.filter((b) => Math.abs(b.y - boxes[0].y) < 4);
			expect(firstRow.length, 'cards per row').toBeGreaterThanOrEqual(minCards);
			const container = await page.locator('ul.products').evaluate((e) => e.getBoundingClientRect().left);
			expect(Math.abs(boxes[0].x - container), 'first card starts at the grid edge (no empty first column)').toBeLessThanOrEqual(2);
		});
	}

	test('single product @ desktop: gallery and summary sit side by side; related cards are real cards', async ({ page }) => {
		await page.setViewportSize({ width: 1440, height: 900 });
		await page.goto('/san-pham/honda-wave/');
		const g = await page.locator('div.woocommerce-product-gallery').evaluate((e) => e.getBoundingClientRect().toJSON());
		const s = await page.locator('div.summary').evaluate((e) => e.getBoundingClientRect().toJSON());
		expect(g.width, 'gallery width').toBeGreaterThanOrEqual(480);
		expect(s.width, 'summary width').toBeGreaterThanOrEqual(480);
		expect(s.x, 'summary is right of the gallery').toBeGreaterThan(g.x + g.width - 1);
		const rel = await page.locator('.related ul.products li.product').evaluateAll((els) => els.map((e) => e.getBoundingClientRect().toJSON()));
		expect(rel.length).toBeGreaterThan(0);
		for (const b of rel) expect(b.width, 'related card width').toBeGreaterThanOrEqual(200);
		expect(Math.abs(rel[0].x - g.x), 'related grid starts at the content edge').toBeLessThanOrEqual(2);
	});

	test('single product @ phone: gallery above summary, nothing wider than the screen', async ({ page }) => {
		await page.setViewportSize({ width: 360, height: 800 });
		await page.goto('/san-pham/honda-wave/');
		const g = await page.locator('div.woocommerce-product-gallery').evaluate((e) => e.getBoundingClientRect().toJSON());
		const s = await page.locator('div.summary').evaluate((e) => e.getBoundingClientRect().toJSON());
		expect(g.width).toBeGreaterThanOrEqual(300);
		expect(s.y, 'summary is below the gallery').toBeGreaterThanOrEqual(g.y + g.height - 1);
	});

	test('product category pages keep their term and counts', async ({ page }) => {
		for (const [path, count] of [['/danh-muc-san-pham/loai-xe/xe-so/', 4], ['/danh-muc-san-pham/loai-xe/xe-ga/', 6], ['/danh-muc-san-pham/loai-xe/xe-dien/', 3]]) {
			await page.goto(path);
			await expect(page.locator('ul.products li.product')).toHaveCount(count);
		}
	});

	test('priced product: gallery visible immediately, add to cart, cart, checkout form', async ({ page }) => {
		await page.goto('/san-pham/honda-wave/');
		await expect(page.locator('h1.product_title')).toHaveText('Honda Wave');
		await expect(page.locator('.woocommerce-product-gallery')).toHaveCSS('opacity', '1');
		await expect(page.locator('.nt-wc-contact a[href="tel:0942467674"]')).toBeVisible();
		await page.locator('button.single_add_to_cart_button').click();
		const notice = page.locator('.woocommerce-message');
		await expect(notice).toBeVisible();
		await notice.locator('a.button').click();
		await expect(page).toHaveURL(/\/(cart|gio-hang)\/?/);
		await expect(page.locator('table.shop_table tr.cart_item')).toHaveCount(1);
		await expect(page.locator('h1')).toHaveCount(1);
		await page.locator('a.checkout-button').click();
		await expect(page.locator('form.checkout')).toBeVisible();
		await expect(page.locator('main')).toHaveCount(1);
	});

	test('product without a published price asks for contact and cannot be added to the cart', async ({ page }) => {
		await page.goto('/san-pham/yamaha-sirius/');
		await expect(page.locator('.summary .nt-price-contact')).toBeVisible(); // related products below may show the same text
		await expect(page.locator('button.single_add_to_cart_button')).toHaveCount(0);
		await expect(page.locator('.nt-wc-contact a[href="tel:0942467674"]')).toBeVisible();
		await expect(page.locator('.nt-wc-contact a[href^="https://zalo.me/0942467674"]')).toBeVisible();
	});

	test('WooCommerce stylesheets (render-blocking) load only where WooCommerce output is shown', async ({ request }) => {
		const count = async (path) => ((await (await request.get(path)).text()).match(/plugins\/woocommerce\/assets\/css\//g) ?? []).length;
		for (const path of ['/', '/blog/', '/blog/xe-dien/', '/lien-he/', '/gioi-thieu/', '/bo-suu-tap/thue-ngay/', '/?s=xe']) expect(await count(path), `${path} must not load WooCommerce CSS`).toBe(0);
		for (const path of ['/shop/', '/san-pham/honda-wave/', '/cart/', '/checkout/', '/my-account/', '/danh-muc-san-pham/loai-xe/xe-so/']) expect(await count(path), `${path} needs WooCommerce CSS`).toBeGreaterThan(0);
	});

	test('the header cart badge is styled on pages that do not load WooCommerce CSS', async ({ page }) => {
		await page.goto('/san-pham/honda-wave/');
		await page.locator('button.single_add_to_cart_button').click();
		await expect(page.locator('.woocommerce-message')).toBeVisible();
		await page.goto('/blog/');
		const badge = page.locator('.nt-cartlink__count');
		await expect(badge).toBeVisible();
		await expect(badge).toHaveCSS('position', 'absolute');
		await expect(badge).toHaveCSS('border-radius', '999px');
	});

	test('optional lean mode: default keeps WooCommerce untouched; when on, jQuery-based Woo scripts load only on shop screens and order attribution stays', async ({ request }) => {
		test.setTimeout(120_000);
		const scripts = async (path) => [...(await (await request.get(path)).text()).matchAll(/<script[^>]*src="([^"]+)"/g)].map((m) => m[1].replace(/\?.*$/, '').split('/').pop());
		const setLean = (value) => wp('eval', `$o = (array) get_option('nguyentu_options', array()); $o['lean_woo_js'] = '${value}'; update_option('nguyentu_options', $o);`);
		try {
			setLean('off');
			expect(await scripts('/'), 'default: WooCommerce scripts are not touched').toContain('jquery.min.js');

			setLean('on');
			for (const path of ['/', '/blog/', '/lien-he/']) {
				const list = await scripts(path);
				expect(list, `${path}: no jQuery-based Woo script`).not.toContain('jquery.min.js');
				expect(list).not.toContain('woocommerce.min.js');
				expect(list, `${path}: Woo order attribution is kept`).toContain('order-attribution.min.js');
				expect(list).toContain('theme.js');
			}
			for (const path of ['/shop/', '/san-pham/honda-wave/', '/cart/', '/checkout/']) {
				const list = await scripts(path);
				expect(list, `${path} needs jQuery and the Woo scripts`).toContain('jquery.min.js');
				expect(list).toContain('woocommerce.min.js');
			}
		} finally {
			setLean('off');
		}
	});

	test('the cart link appears in the header only when the cart has items', async ({ page }) => {
		await page.context().clearCookies();
		await page.goto('/');
		await expect(page.locator('.nt-cartlink')).toHaveCount(0);
	});
});
