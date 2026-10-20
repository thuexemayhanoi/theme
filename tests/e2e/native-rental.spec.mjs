import { test, expect } from '@playwright/test';

test('native estimate is unique and shares every published table price', async ({ page }) => {
	await page.goto('/');
	const calculator = page.locator('[data-nt-native-rental]');
	await expect(calculator).toHaveCount(1);
	await expect(page.locator('.ntsc-rental')).toHaveCount(1);
	const vehicle = calculator.locator('[data-nt-rental-vehicle]');
	const days = calculator.locator('[data-nt-rental-days]');
	const result = calculator.locator('[data-nt-rental-result]');
	const rows = await page.locator('.nt-table--prices tbody tr').evaluateAll(items =>
		items.map(row => [...row.querySelectorAll('th,td')].map(cell => cell.textContent.trim()))
	);
	for (const [name, daily, weekly, monthly, deposit] of rows) {
		await vehicle.selectOption({ label: name + ' — ' + daily });
		for (const count of [1, 3, 7, 30]) {
			await days.fill(String(count));
			if (count === 7 || count === 30) {
				const price = count === 7 ? weekly : monthly;
				if (/\d/.test(price)) await expect(result).toContainText(price);
				else await expect(result).toContainText('cần liên hệ');
			} else if (/\d/.test(daily)) {
				const amount = Number(daily.replace(/[^0-9]/g, '')) * count;
				await expect(result).toContainText(new Intl.NumberFormat('vi-VN').format(amount) + 'đ');
			} else await expect(result).toContainText('cần liên hệ');
			await expect(result).toContainText(deposit);
		}
	}
	for (const count of ['0', '-1', '1.5', '31', '999999999', '']) {
		await days.fill(count);
		await expect(result).toHaveText('Chọn từ 1 đến 30 ngày.');
	}
});

test('native estimate follows dark surfaces and fits a narrow phone', async ({ page }) => {
	await page.setViewportSize({ width: 320, height: 844 });
	await page.addInitScript(() => localStorage.setItem('nt-color-mode', 'dark'));
	await page.goto('/');
	const calculator = page.locator('[data-nt-native-rental]');
	await expect(calculator).toHaveCSS('background-color', 'rgb(27, 41, 64)');
	await expect(calculator.locator('select')).toHaveCSS('color', 'rgb(242, 245, 255)');
	expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(321);
});
