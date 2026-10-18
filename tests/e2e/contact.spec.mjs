import { test, expect } from '@playwright/test';

/**
 * NT_SIMS=on  (default): QuickCall + MotoAI simulators are active -> the theme must add NO fixed bar.
 * NT_SIMS=off          : simulators deactivated -> the theme draws its single mobile contact bar.
 * tools/qa/run-sandbox.sh runs this file twice, toggling the simulators with tools/sandbox/activate-sims.sh.
 */
const SIMS = (process.env.NT_SIMS ?? 'on') === 'on';

async function visibleFixed(page) {
	return page.evaluate(() =>
		[...document.querySelectorAll('body *')]
			.filter((el) => {
				const cs = getComputedStyle(el);
				const r = el.getBoundingClientRect();
				return cs.position === 'fixed' && cs.display !== 'none' && cs.visibility !== 'hidden' && r.width > 0 && r.height > 0 && !el.closest('[hidden]');
			})
			.map((el) => ({ id: el.id, cls: String(el.className), rect: el.getBoundingClientRect().toJSON() })),
	);
}

test.describe(`mobile contact UI (simulators ${SIMS ? 'ON' : 'OFF'}, 390px)`, () => {
	test.use({ viewport: { width: 390, height: 844 } });

	test('exactly one theme-level contact bar decision and no stacked duplicates', async ({ page }) => {
		await page.goto('/blog/xe-dien/');
		const classes = (await page.locator('body').getAttribute('class')) ?? '';
		const fixed = await visibleFixed(page);
		const bars = fixed.filter((f) => /nt-tools|moto-quickcall-bar/.test(f.cls));
		expect(bars.length, `fixed contact bars: ${JSON.stringify(bars.map((b) => b.id || b.cls))}`).toBe(1);

		if (SIMS) {
			expect(classes).toContain('nt-has-quickcall');
			expect(classes).toContain('nt-has-chat');
			expect(await page.locator('.nt-ctabar').count(), 'theme bar must not exist while QuickCall is active').toBe(0);
			await expect(page.locator('#moto-quickcall-sim')).toBeVisible();
			await expect(page.locator('#motoai-chat-bubble-sim')).toBeVisible();
		} else {
			expect(classes).toContain('nt-has-cta-bar');
			await expect(page.locator('.nt-quickcall')).toBeVisible();
			await page.locator('.nt-quickcall summary').click();
			await expect(page.locator('.nt-quickcall a[href="tel:0942467674"]')).toBeVisible();
			await expect(page.locator('.nt-quickcall a[href^="https://zalo.me/0942467674"]')).toBeVisible();
		}
	});

	test('the last footer line is never hidden under a fixed element', async ({ page }) => {
		await page.goto('/blog/');
		await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
		await page.waitForTimeout(300);
		const lines = await page.evaluate(() => {
			const p = document.querySelector('.nt-footer__bottom p');
			const range = document.createRange();
			range.selectNodeContents(p);
			return [...range.getClientRects()].map((r) => r.toJSON());
		});
		expect(lines.length).toBeGreaterThan(0);
		const fixed = await visibleFixed(page);
		for (const f of fixed) {
			for (const l of lines) {
				const overlap = !(l.right <= f.rect.left || l.left >= f.rect.right || l.bottom <= f.rect.top || l.top >= f.rect.bottom);
				expect(overlap, `footer text overlaps fixed element ${f.id || f.cls}`).toBe(false);
			}
		}
	});

	test('the theme never places fixed UI over the main content area at the top', async ({ page }) => {
		await page.goto('/');
		const fixed = await visibleFixed(page);
		for (const f of fixed) {
			if (/nt-tools|moto-quickcall|motoai/.test(f.id + f.cls)) continue;
			// anything else fixed must be the sticky header's drawer parts, which are hidden when closed
			expect(f.cls + f.id, 'unexpected visible fixed element').toMatch(/skip-link|admin/);
		}
	});
});

test('native contact disclosure works on tablet and desktop', async ({ browser }) => {
	test.skip(SIMS, 'only meaningful when the theme bar is enabled');
	for (const width of [768, 1280]) {
		const page = await (await browser.newContext({ viewport: { width, height: 900 } })).newPage();
		await page.goto('/');
		await expect(page.locator('.nt-quickcall')).toBeVisible();
		await page.close();
	}
});
