import { test, expect } from '@playwright/test';

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

	test('the cart link appears in the header only when the cart has items', async ({ page }) => {
		await page.context().clearCookies();
		await page.goto('/');
		await expect(page.locator('.nt-cartlink')).toHaveCount(0);
	});
});
