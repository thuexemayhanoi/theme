import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const ROUTES = ['/', '/gioi-thieu/', '/lien-he/', '/blog/', '/blog/xe-dien/xe-dap-dien/', '/shop/', '/san-pham/honda-wave/', '/bo-suu-tap/thue-ngay/', '/?s=xe', '/404-invalid-nonexistent/'];

for (const width of [390, 1280]) {
	test.describe(`axe-core @ ${width}px`, () => {
		test.use({ viewport: { width, height: 900 } });
		for (const path of ROUTES) {
			test(`${path} has no critical or serious accessibility violations`, async ({ page }) => {
				await page.goto(path, { waitUntil: 'networkidle' });
				// Vendor widgets (Moto QuickCall, MotoAI chat) are not theme markup; their stand-ins are excluded here.
				// The theme's own duty towards them (never overlap, never a second bar) is tested in contact.spec.mjs.
				const results = await new AxeBuilder({ page })
					.exclude('#moto-quickcall-sim')
					.exclude('#motoai-chat-bubble-sim')
					.exclude('#motoai-chat-panel-sim')
					.withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'])
					.analyze();
				const bad = results.violations.filter((v) => ['critical', 'serious'].includes(v.impact));
				expect(bad.map((v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => n.target.join(' ')).join(' | ')}`)).toEqual([]);
			});
		}
	});
}
