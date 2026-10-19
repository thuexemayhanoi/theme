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
		await expect(page.locator('.nt-qc__link--maps')).toHaveAttribute('href', 'https://share.google/KHz2FVQKhtFilL01j');
		await expect(page.locator('.nt-qc__link--maps')).toHaveText('Chỉ đường');
		await expect(page.locator('.nt-qc__link--chat')).toBeVisible();
		await expect(page.locator('.nt-qc__link--chat')).toHaveAttribute('aria-controls', 'nt-assist-dialog');
		await page.locator('.nt-qc__link--chat').click();
		await expect(page.locator('.nt-qc__link--chat')).toHaveAttribute('aria-expanded', 'true');
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


for (const width of [360, 390, 768, 1024, 1440]) {
	test('native QuickCall avoids injected fixed dock and restores original position at ' + width + 'px', async ({ page }) => {
		test.skip(SIMS, 'the legacy plugin simulator deliberately takes precedence');
		await page.setViewportSize({ width, height: 844 });
		await page.goto('/', { waitUntil: 'networkidle' });
		await expect(page.locator('#nt-native-quickcall')).toBeVisible();
		const initial = await page.locator('[data-nt-qc-launch]').boundingBox();
		await page.evaluate(() => {
			const dock = document.createElement('div');
			dock.id = 'qa-fixed-bottom-dock';
			dock.style.cssText = 'position:fixed;bottom:0;left:0;width:100%;height:94px;z-index:1100;background:#111;';
			document.body.appendChild(dock);
		});
		await expect.poll(async () => page.evaluate(() => {
			const button = document.querySelector('[data-nt-qc-launch]').getBoundingClientRect();
			const dock = document.getElementById('qa-fixed-bottom-dock').getBoundingClientRect();
			return Math.round(dock.top - button.bottom);
		}), { timeout: 6500 }).toBeGreaterThanOrEqual(9);
		await noHorizontalScroll(page);
		await page.evaluate(() => document.getElementById('qa-fixed-bottom-dock').remove());
		await expect.poll(async () => page.locator('[data-nt-qc-launch]').boundingBox().then((r) => r.y), { timeout: 6500 })
			.toBeGreaterThanOrEqual(initial.y - 2);
		const restored = await page.locator('[data-nt-qc-launch]').boundingBox();
		expect(Math.abs(initial.y - restored.y)).toBeLessThanOrEqual(2);
	});
}

test('QuickCall yields to a full-screen blocking dialog instead of covering it', async ({ page }) => {
	test.skip(SIMS, 'the legacy plugin simulator deliberately takes precedence');
	await page.setViewportSize({ width: 390, height: 844 });
	await page.goto('/', { waitUntil: 'networkidle' });
	await page.evaluate(() => {
		const modal = document.createElement('div');
		modal.id = 'qa-fullscreen-modal';
		modal.setAttribute('role', 'dialog');
		modal.style.cssText = 'position:fixed;inset:0;z-index:1100;background:#fff;';
		document.body.appendChild(modal);
	});
	await expect(page.locator('#nt-native-quickcall')).toHaveClass(/nt-qc--suspended/);
	await page.evaluate(() => document.getElementById('qa-fullscreen-modal').remove());
	await expect(page.locator('#nt-native-quickcall')).not.toHaveClass(/nt-qc--suspended/);
});

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
