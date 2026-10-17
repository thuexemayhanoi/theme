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
		await expect(page.locator('.nt-price-contact')).toBeVisible();
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
