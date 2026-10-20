import { test, expect } from '@playwright/test';

for (const width of [360, 768, 1280]) {
	test.describe(`header color and hours controls at ${width}px`, () => {
		test.use({ viewport: { width, height: 800 } });

		test('keeps both controls beside search without horizontal overflow', async ({ page }) => {
			await page.goto('/');
			const hours = page.locator('[data-nt-hours-status]');
			const mode = page.locator('[data-nt-theme-toggle]');
			await expect(hours).toBeVisible();
			await expect(mode).toBeVisible();
			await expect(page.locator('[data-nt-search-toggle]')).toBeVisible();
			await expect(hours.locator('.nt-hours-indicator__dot')).toBeVisible();
			expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(width + 1);
		});

		test('switches dark/light, saves choice, and retains the mode after reload', async ({ page }) => {
			await page.addInitScript(() => { if (localStorage.getItem('nt-color-mode') === null) { localStorage.setItem('nt-color-mode', 'light'); } });
			await page.goto('/');
			const mode = page.locator('[data-nt-theme-toggle]');
			await expect(page.locator('html')).toHaveAttribute('data-nt-color-mode', 'light');
			await mode.click();
			await expect(page.locator('html')).toHaveAttribute('data-nt-color-mode', 'dark');
			await expect(mode).toHaveAttribute('aria-pressed', 'true');
			expect(await page.evaluate(() => localStorage.getItem('nt-color-mode'))).toBe('dark');
			await page.reload();
			await expect(page.locator('html')).toHaveAttribute('data-nt-color-mode', 'dark');
			await page.locator('[data-nt-theme-toggle]').click();
			await expect(page.locator('html')).toHaveAttribute('data-nt-color-mode', 'light');
		});
	});
}

test('opening dot uses Hanoi local clock, not browser locale or cached HTML', async ({ page }) => {
	await page.clock.install({ time: new Date('2026-10-20T03:00:00Z') }); // 10:00 Hanoi.
	await page.goto('/');
	const hours = page.locator('[data-nt-hours-status]');
	await expect(hours).toHaveAttribute('data-nt-hours-state', 'open');
	await expect(hours).toHaveAttribute('aria-label', /Cửa hàng đang mở/);
	await expect(hours.locator('.nt-hours-indicator__dot')).toHaveCSS('background-color', 'rgb(22, 128, 60)');
	await page.clock.setFixedTime(new Date('2026-10-20T15:00:00Z')); // 22:00 Hanoi.
	await page.reload();
	await expect(page.locator('[data-nt-hours-status]')).toHaveAttribute('data-nt-hours-state', 'closed');
	await expect(page.locator('.nt-hours-indicator__dot')).toHaveCSS('background-color', 'rgb(212, 59, 54)');
});

test('the indicator is visible without a written OPEN/CLOSED label', async ({ page }) => {
	await page.goto('/');
	const badge = page.locator('[data-nt-hours-status]');
	await expect(badge).toBeVisible();
	await expect(badge).toHaveText('');
	await expect(badge).toHaveAttribute('role', 'img');
});

for (const [time, before, after] of [
	['2026-10-20T01:59:59Z', 'closed', 'open'],
	['2026-10-20T13:59:59Z', 'open', 'closed'],
]) {
	test('opening indicator crosses Hanoi boundary ' + time + ' without reload', async ({ page }) => {
		await page.clock.install({ time: new Date(new Date(time).getTime() - 10000) });
		await page.goto('/');
		await page.clock.pauseAt(new Date(time));
		const hours = page.locator('[data-nt-hours-status]');
		await expect(hours).toHaveAttribute('data-nt-hours-state', before);
		await page.clock.runFor(1021);
		await expect(hours).toHaveAttribute('data-nt-hours-state', after);
	});
}
