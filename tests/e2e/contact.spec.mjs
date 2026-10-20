import { test, expect } from '@playwright/test';

/**
 * NT_SIMS=on  (default): QuickCall + MotoAI simulators are active -> the theme must add NO fixed bar.
 * NT_SIMS=off          : simulators deactivated -> the theme draws its own native QuickCall and data-grounded assistant.
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
		const bars = fixed.filter((f) => /nt-ctabar|moto-quickcall-bar|^nt-qc$/.test(f.cls));
		expect(bars.length, `fixed contact bars: ${JSON.stringify(bars.map((b) => b.id || b.cls))}`).toBe(1);

		if (SIMS) {
			expect(classes).toContain('nt-has-quickcall');
			expect(classes).toContain('nt-has-chat');
			expect(await page.locator('.nt-ctabar').count(), 'theme bar must not exist while QuickCall is active').toBe(0);
			await expect(page.locator('#moto-quickcall-sim')).toBeVisible();
			await expect(page.locator('#motoai-chat-bubble-sim')).toBeVisible();
		} else {
			expect(classes).toContain('nt-has-native-quickcall');
			await expect(page.locator('.nt-ctabar')).toHaveCount(0);
			await expect(page.locator('#nt-native-quickcall .nt-qc__launch')).toBeVisible();
			await page.locator('[data-nt-qc-launch]').click();
			await expect(page.locator('#nt-qc-menu a[href="tel:0942467674"]')).toBeVisible();
			await expect(page.locator('#nt-qc-menu a[href^="https://zalo.me/0942467674"]')).toBeVisible();
			await expect(page.locator('#nt-qc-menu a[href^="https://wa.me/84942467674"]')).toBeVisible();
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
			if (/nt-ctabar|nt-qc|nt-assist|moto-quickcall|motoai/.test(f.id + f.cls)) continue;
			// anything else fixed must be the sticky header's drawer parts, which are hidden when closed
			expect(f.cls + f.id, 'unexpected visible fixed element').toMatch(/skip-link|admin/);
		}
	});
});

test('on tablet and desktop the native QuickCall is accessible, with no duplicate bar', async ({ browser }) => {
	test.skip(SIMS, 'only meaningful when the native launcher is enabled');
	for (const width of [768, 1024, 1440]) {
		const context = await browser.newContext({ viewport: { width, height: 900 } });
		const page = await context.newPage();
		await page.goto('/');
		await expect(page.locator('.nt-ctabar')).toHaveCount(0);
		await expect(page.locator('#nt-native-quickcall')).toBeVisible();
		await page.locator('[data-nt-qc-launch]').click();
		await expect(page.locator('.nt-qc__link--call')).toBeVisible();
		await context.close();
	}
});


for (const width of [360, 390, 768, 1024, 1440]) {
	test('native QuickCall footer reaches actual viewport bottom without a blank strip at ' + width + 'px', async ({ page }) => {
		test.skip(SIMS, 'native contact is not active while legacy simulators run');
		await page.setViewportSize({ width, height: 844 });
		await page.goto('/blog/', { waitUntil: 'networkidle' });
		await expect(page.locator('#nt-native-quickcall')).toBeVisible();
		await expect(page.locator('#site-footer')).toBeVisible();
		await page.evaluate(() => {
			document.documentElement.style.scrollBehavior = 'auto';
			window.scrollTo(0, document.documentElement.scrollHeight);
		});
		await expect.poll(async () => page.evaluate(() => Math.abs(
			document.getElementById('site-footer').getBoundingClientRect().bottom - window.innerHeight
		)), { timeout: 5000 }).toBeLessThanOrEqual(2);

		const geometry = await page.evaluate(() => {
			const footer = document.getElementById('site-footer');
			const copyright = footer.querySelector('.nt-footer__bottom p').getBoundingClientRect();
			const launcher = document.querySelector('#nt-native-quickcall .nt-qc__launch').getBoundingClientRect();
			const covered = !(copyright.right <= launcher.left || copyright.left >= launcher.right ||
				copyright.bottom <= launcher.top || copyright.top >= launcher.bottom);
			const lastVisible = document.elementFromPoint(window.innerWidth / 2, window.innerHeight - 2);
			return {
				bodyPadding: parseFloat(getComputedStyle(document.body).paddingBottom),
				footerAtBottom: Math.abs(footer.getBoundingClientRect().bottom - window.innerHeight),
				footerOwnsScreenEdge: !!lastVisible?.closest('#site-footer'),
				copyrightObscured: covered,
				footerBackground: getComputedStyle(footer).backgroundColor,
				bodyBackground: getComputedStyle(document.body).backgroundColor
			};
		});
		expect(geometry.bodyPadding).toBeLessThanOrEqual(1);
		expect(geometry.footerAtBottom).toBeLessThanOrEqual(2);
		expect(geometry.footerOwnsScreenEdge, 'the last pixels must be the navy footer, not the cream body').toBe(true);
		expect(geometry.footerBackground).not.toBe(geometry.bodyBackground);
		expect(geometry.copyrightObscured, 'the bottom copyright must remain readable beside/above QuickCall').toBe(false);
	});
}


// Regression for post #372: the former Flatsome shortcode layout shipped a fixed
// white panel inside a black section, leaving invisible light-on-light copy in dark mode.
for (const width of [390, 768, 1440]) {
	test('contact legacy panel stays readable in light/dark at ' + width + 'px', async ({ page }) => {
		await page.setViewportSize({ width, height: width === 390 ? 844 : 900 });
		await page.addInitScript(() => localStorage.setItem('nt-color-mode', 'light'));
		await page.goto('/lien-he/');
		await expect(page.locator('#post-372 .nt-lg-section')).toBeVisible();
		await expect(page.locator('#post-372 h1')).toHaveCount(1);
		await expect(page.locator('#post-372 .nt-lg-html iframe')).toHaveCount(2);
		await expect(page.locator('#post-372 .nt-lg-col > .nt-lg-html').last()).toBeHidden();

		const inspect = async () => page.evaluate(() => {
			const card = document.querySelector('#post-372 .nt-lg-section__inner > .nt-lg-row > .nt-lg-col');
			const h1 = card.querySelector('h1');
			const parse = (color) => [...color.matchAll(/[\d.]+/g)].slice(0, 3).map(x => Number(x[0]) / 255);
			const luminance = (rgb) => rgb.map(x => x <= .04045 ? x / 12.92 : ((x + .055) / 1.055) ** 2.4)
				.reduce((s, x, i) => s + x * [.2126, .7152, .0722][i], 0);
			const fg = luminance(parse(getComputedStyle(h1).color));
			const bg = luminance(parse(getComputedStyle(card).backgroundColor));
			return {
				contrast: (Math.max(fg, bg) + .05) / (Math.min(fg, bg) + .05),
				overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
				sectionPadding: parseFloat(getComputedStyle(card).paddingLeft),
				isGapHidden: getComputedStyle(document.querySelector('#post-372 .nt-lg-section__inner > .nt-lg-gap')).display === 'none'
			};
		});
		for (const mode of ['light', 'dark']) {
			if (mode === 'dark') await page.locator('[data-nt-theme-toggle]').click();
			await expect(page.locator('html')).toHaveAttribute('data-nt-color-mode', mode);
			const m = await inspect();
			expect(m.contrast, mode + ' text contrast').toBeGreaterThanOrEqual(4.5);
			expect(m.overflow, mode + ' horizontal overflow').toBe(false);
			expect(m.sectionPadding, mode + ' card padding').toBeLessThanOrEqual(42);
			expect(m.isGapHidden, mode + ' decorative gap').toBe(true);
		}
	});
}
