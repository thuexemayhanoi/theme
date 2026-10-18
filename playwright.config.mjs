import { defineConfig } from '@playwright/test';

const baseURL = process.env.NT_SITE_URL || 'http://127.0.0.1:8088';
if (!/^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/.test(baseURL) && !process.env.NT_E2E_ALLOW_REMOTE) {
	throw new Error(`Refusing to run browser tests against ${baseURL}. Use the sandbox, or set NT_E2E_ALLOW_REMOTE=1 for a staging URL you own.`);
}

export default defineConfig({
	testDir: 'tests/e2e',
	timeout: 60_000,
	expect: { timeout: 10_000 },
	fullyParallel: false,
	workers: 2,
	retries: 0,
	reporter: [['list'], ['json', { outputFile: process.env.NT_PW_JSON || 'reports/results/raw/pw-default.json' }], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
	use: {
		baseURL,
		locale: 'vi-VN',
		timezoneId: 'Asia/Ho_Chi_Minh',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		launchOptions: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE } : {},
	},
	projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
});
