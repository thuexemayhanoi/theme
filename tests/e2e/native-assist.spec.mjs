import { test, expect } from '@playwright/test';
import { noHorizontalScroll } from './helpers.mjs';

const SIMS = (process.env.NT_SIMS ?? 'on') === 'on';

test('legacy Moto QuickCall and MotoAI take precedence without duplicate native widgets', async ({ page }) => {
	test.skip(!SIMS, 'runs only while the sandbox legacy simulators are active');
	await page.goto('/');
	await expect(page.locator('#moto-quickcall-sim')).toBeVisible();
	await expect(page.locator('#motoai-chat-bubble-sim')).toBeVisible();
	await expect(page.locator('#nt-native-quickcall')).toHaveCount(0);
	await expect(page.locator('#nt-native-assistant')).toHaveCount(0);
});

for (const width of [360, 390, 768, 1024, 1440]) {
	test('native QuickCall and source assistant remain usable at ' + width + 'px', async ({ page }) => {
		test.skip(SIMS, 'runs after simulator plugins are deactivated on the disposable WP sandbox');
		await page.setViewportSize({ width, height: 844 });
		await page.goto('/', { waitUntil: 'networkidle' });
		await expect(page.locator('.nt-ctabar')).toHaveCount(0);
		await expect(page.locator('#nt-native-quickcall')).toBeVisible();
		await page.locator('[data-nt-qc-launch]').click();
		for (const [name, href] of [
			['call', 'tel:0942467674'],
			['zalo', 'https://zalo.me/0942467674'],
			['whatsapp', 'https://wa.me/84942467674'],
		]) {
			await expect(page.locator('.nt-qc__link--' + name)).toHaveAttribute('href', href);
		}
		await expect(page.locator('.nt-qc__link--maps')).toHaveCount(0);
		await expect(page.locator('.nt-qc__link--chat')).toBeVisible();
		await page.locator('.nt-qc__link--chat').click();
		await expect(page.locator('#nt-assist-dialog')).toBeVisible();
		await expect(page.locator('body')).toHaveClass(/nt-chat-is-open/);
		await expect(page.locator('.nt-assist__head')).toContainText('Tra cứu nội dung công khai');
		await expect(page.locator('.nt-assist__form input')).toBeFocused();
		await page.locator('#nt-assist-question').fill('Tiền đặt cọc?');
		await page.locator('.nt-assist__form button').click();
		await expect(page.locator('.nt-assist__message--bot')).toContainText('2.000.000', { timeout: 20000 });
		await noHorizontalScroll(page);
		await page.keyboard.press('Escape');
		await expect(page.locator('#nt-assist-dialog')).toBeHidden();
		await expect(page.locator('body')).not.toHaveClass(/nt-chat-is-open/);
		const footprint = await page.locator('#nt-native-quickcall .nt-qc__launch').boundingBox();
		expect(footprint.width).toBeGreaterThanOrEqual(44);
		expect(footprint.height).toBeGreaterThanOrEqual(44);
	});
}

test('grounded answers contain only public local sources and do not require AI API', async ({ request }) => {
	test.skip(SIMS, 'native endpoint is intentionally disabled while MotoAI simulator runs');
	const res = await request.post('/wp-json/nguyentu/v1/assist', { data: { question: 'Xe điện' } });
	expect(res.status()).toBe(200);
	const result = await res.json();
	expect(result.mode).toBe('tra-cuu-noi-dung');
	expect(result.answer).toContain('xe điện');
	expect(result.sources.length).toBeLessThanOrEqual(4);
	for (const item of result.sources) {
		expect(new URL(item.url).host).toBe('127.0.0.1:8088');
		expect(item.title.length).toBeGreaterThan(0);
	}
	const bad = await request.post('/wp-json/nguyentu/v1/assist', { data: { question: 'x'.repeat(181) } });
	expect(bad.status()).toBe(400);
});
