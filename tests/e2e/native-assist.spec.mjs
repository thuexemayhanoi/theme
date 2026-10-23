import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { expectReadable, noHorizontalScroll } from './helpers.mjs';
import { CHIPS, MONEY, NAP } from '../../tools/qa/lib/assist-cases.mjs';

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
		// Inspect the open panel: a closed dialog hides its visible-label and target-size defects.
		const accessibility = await new AxeBuilder({ page })
			.withRules(['target-size', 'label-content-name-mismatch'])
			.analyze();
		expect(accessibility.violations.map(item => ({ id: item.id, targets: item.nodes.map(node => node.target) }))).toEqual([]);

		if (width < 768) {
			await expect(page.locator('[data-nt-chat-close]')).toBeFocused();
		} else {
			await expect(page.locator('.nt-assist__form input')).toBeFocused();
		}
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



test('chat remains inside the visible viewport after keyboard-like resize and rotation', async ({ page }) => {
	test.skip(SIMS, 'native widget is hidden by MotoAI simulator');
	await page.setViewportSize({ width: 390, height: 844 });
	await page.goto('/', { waitUntil: 'networkidle' });
	await page.locator('[data-nt-qc-launch]').click();
	await page.locator('[data-nt-chat-open]').first().click();
	const fits = async () => page.evaluate(() => {
		const panel = document.getElementById('nt-assist-dialog').getBoundingClientRect();
		const composer = document.querySelector('.nt-assist__form').getBoundingClientRect();
		const viewport = window.visualViewport;
		const visibleTop = viewport ? viewport.offsetTop : 0;
		const visibleBottom = visibleTop + (viewport ? viewport.height : window.innerHeight);
		return panel.top >= visibleTop + 5 && panel.bottom <= visibleBottom - 5 &&
			composer.bottom <= panel.bottom && composer.top >= panel.top &&
			panel.left >= -1 && panel.right <= window.innerWidth + 1;
	});
	await expect.poll(fits).toBe(true);
	await page.setViewportSize({ width: 390, height: 420 });
	await expect.poll(fits).toBe(true);
	await page.locator('#nt-assist-question').fill('Thuê xe Honda Vision');
	await expect.poll(fits).toBe(true);
	await noHorizontalScroll(page);
	await page.setViewportSize({ width: 844, height: 390 });
	await expect.poll(fits).toBe(true);
	await page.setViewportSize({ width: 390, height: 844 });
	await expect.poll(fits).toBe(true);
});

test('iPhone keyboard regression: CSS contains the full composer while VisualViewport shrinks', async ({ page }) => {
	test.skip(SIMS, 'native assistant is hidden by the legacy plugin simulator');
	await page.setViewportSize({ width: 390, height: 844 });
	await page.goto('/', { waitUntil: 'networkidle' });
	await page.locator('[data-nt-qc-launch]').click();
	await page.locator('[data-nt-chat-open]').first().click();
	await page.locator('#nt-assist-question').focus();
	await page.evaluate(() => {
		// Chromium headless has no native software keyboard. Model iPhone's
		// keyboard-induced VisualViewport shrink without changing layout width.
		const original = window.visualViewport;
		Object.defineProperty(window, 'visualViewport', {
			configurable: true,
			value: { width: 390, height: 330, offsetTop: 0, offsetLeft: 0 }
		});
		original.dispatchEvent(new Event('resize'));
	});
	await expect(page.locator('#nt-assist-dialog')).toHaveClass(/nt-assist__panel--keyboard/);
	await expect(page.locator('#nt-assist-dialog')).toHaveClass(/nt-assist__panel--compact/);
	await expect(page.locator('.nt-assist__privacy')).toBeHidden();
	await expect.poll(() => page.evaluate(() => {
		const panel = document.getElementById('nt-assist-dialog').getBoundingClientRect();
		const input = document.getElementById('nt-assist-question').getBoundingClientRect();
		const send = document.querySelector('.nt-assist__form button').getBoundingClientRect();
		return panel.left >= 7 && panel.right <= innerWidth - 7 &&
			panel.top >= 7 && panel.bottom <= 330 - 7 &&
			input.left >= panel.left && send.right <= panel.right &&
			input.bottom <= panel.bottom && send.bottom <= panel.bottom;
	})).toBe(true);
	await noHorizontalScroll(page);
	await page.locator('[data-nt-chat-close]').click();
	await expect(page.locator('body')).not.toHaveClass(/nt-chat-keyboard-open/);
});

test('clearing memory cancels in-flight answers and cannot restore old context', async ({ page }) => {
	test.skip(SIMS, 'native widget is hidden by MotoAI simulator');
	await page.addInitScript(() => {
		const normalFetch = window.fetch;
		window.fetch = function (url, options) {
			if (String(url).includes('/nguyentu/v1/assist')) {
				return new Promise((resolve) => {
					window.__resolveChat = () => resolve({
						ok: true,
						json: async () => ({
							answer: 'OLD_PENDING_RESPONSE',
							intent: 'price',
							context: { model: 'Honda Vision', period: 'month' },
							sources: []
						})
					});
				});
			}
			return normalFetch.call(window, url, options);
		};
	});
	await page.setViewportSize({ width: 390, height: 844 });
	await page.goto('/');
	await page.locator('[data-nt-qc-launch]').click();
	await page.locator('[data-nt-chat-open]').first().click();
	await page.locator('#nt-assist-question').fill('Giá thuê Honda Vision');
	await page.locator('.nt-assist__form button').click();
	await expect.poll(() => page.evaluate(() => typeof window.__resolveChat)).toBe('function');
	await page.locator('[data-nt-chat-clear]').click();
	await page.evaluate(() => window.__resolveChat());
	await expect(page.locator('.nt-assist__message--bot').last()).not.toContainText('OLD_PENDING_RESPONSE');
	await expect.poll(() => page.evaluate(() => localStorage.getItem('nt-assist-topic-v2'))).toBeNull();
	await expect(page.locator('.nt-assist__status')).toBeHidden();
});

test('prices cite the published FAQ instead of unrelated electric articles', async ({ request }) => {
	test.skip(SIMS, 'native endpoint is disabled while MotoAI simulation runs');
	const response = await request.post('/wp-json/nguyentu/v1/assist', {
		data: { question: 'Giá thuê xe máy bao nhiêu?' }
	});
	expect(response.status()).toBe(200);
	const result = await response.json();
	expect(result.intent).toBe('price');
	expect(result.sources).toHaveLength(1);
	expect(new URL(result.sources[0].url).pathname).toBe('/faq/');
});

test('chatbot remembers verified vehicle topic, not message text, then clears it', async ({ page }) => {
	test.skip(SIMS, 'native widget is hidden by MotoAI simulator');
	await page.setViewportSize({ width: 390, height: 844 });
	await page.goto('/', { waitUntil: 'networkidle' });
	await page.locator('[data-nt-qc-launch]').click();
	await page.locator('[data-nt-chat-open]').first().click();
	await expect(page.locator('[data-nt-chat-clear]')).toBeVisible();

	await page.locator('#nt-assist-question').fill('Giá Honda Vision?');
	await page.locator('.nt-assist__form button').click();
	await expect(page.locator('.nt-assist__message--bot').last()).toContainText('Honda Vision', { timeout: 20000 });
	await expect(page.locator('.nt-assist__status')).toBeHidden();

	const stored = await page.evaluate(() => {
		const memory = localStorage.getItem('nt-assist-topic-v2') || '';
		return { memory, json: JSON.parse(memory) };
	});
	expect(stored.memory.length).toBeLessThan(512);
	expect(stored.json.model).toBe('Honda Vision');
	expect(stored.memory).not.toContain('Giá Honda Vision?');

	await page.reload({ waitUntil: 'networkidle' });
	await page.locator('[data-nt-qc-launch]').click();
	await page.locator('[data-nt-chat-open]').first().click();
	await page.locator('#nt-assist-question').fill('Tháng thì sao?');
	await page.locator('.nt-assist__form button').click();
	await expect(page.locator('.nt-assist__message--bot').last()).toContainText('1.800.000', { timeout: 20000 });
	await expect(page.locator('.nt-assist__message--bot').last().locator('.nt-assist__sources a')).toHaveAttribute('href', /\/faq\/$/);
	await page.locator('[data-nt-chat-clear]').click();
	await expect(page.locator('.nt-assist__message--bot').last()).toContainText('được xóa');
	const keys = await page.evaluate(() => ({
		topic: localStorage.getItem('nt-assist-topic-v2'),
		sources: localStorage.getItem('nt-assist-sources-v2')
	}));
	expect(keys.topic).toBeNull();
	expect(keys.sources).toBeNull();
	await page.keyboard.press('Escape');
	await expect(page.locator('[data-nt-qc-launch]')).toBeFocused();
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


// ---------------------------------------------------------------------------------------------------------------------
// Issue #62: six quick questions, contact and address answers with safe buttons, no script or foreign link from an answer.
// ---------------------------------------------------------------------------------------------------------------------

async function openChat(page) {
	await page.locator('[data-nt-qc-launch]').click();
	await page.locator('[data-nt-chat-open]').first().click();
	await expect(page.locator('#nt-assist-dialog')).toBeVisible();
}

/** Boxes of everything a visitor touches in the open panel, and what overlaps what. */
function chatGeometry(page) {
	return page.evaluate(() => {
		const q = (selector) => document.querySelector(selector);
		const rect = (el) => { const b = el.getBoundingClientRect(); return { x: b.x, y: b.y, r: b.right, b: b.bottom, w: b.width, h: b.height }; };
		const overlap = (a, b) => Math.max(0, Math.min(a.r, b.r) - Math.max(a.x, b.x)) * Math.max(0, Math.min(a.b, b.b) - Math.max(a.y, b.y));
		const panel = rect(q('#nt-assist-dialog'));
		const chips = [...document.querySelectorAll('.nt-assist__shortcuts button')].map((el) => ({ label: el.textContent.trim(), ...rect(el), clipped: el.scrollWidth > el.clientWidth + 1 }));
		const controls = { input: rect(q('#nt-assist-question')), send: rect(q('.nt-assist__form button')), close: rect(q('[data-nt-chat-close]')), clear: rect(q('[data-nt-chat-clear]')) };
		const overlaps = [];
		for (const chip of chips) for (const [name, box] of Object.entries(controls)) if (overlap(chip, box) > 2) overlaps.push(chip.label + ' x ' + name);
		const vw = document.documentElement.clientWidth;
		return {
			chips, controls, overlaps, panel,
			panelInside: panel.x >= -0.5 && panel.r <= vw + 0.5 && panel.y >= -0.5 && panel.b <= window.innerHeight + 0.5,
			logHeight: rect(q('[data-nt-chat-log]')).h,
			quickCall: getComputedStyle(q('.nt-qc')).visibility,
		};
	});
}

for (const mode of ['light', 'dark']) {
	for (const width of [360, 390, 768, 1024, 1440]) {
		test(`six quick questions fit above the input at ${width}px in ${mode} mode`, async ({ page }) => {
			test.skip(SIMS, 'native widget is hidden by MotoAI simulator');
			await page.addInitScript((value) => localStorage.setItem('nt-color-mode', value), mode);
			await page.setViewportSize({ width, height: 844 });
			await page.goto('/', { waitUntil: 'networkidle' });
			await expect(page.locator('html')).toHaveAttribute('data-nt-color-mode', mode);
			await openChat(page);

			const g = await chatGeometry(page);
			expect(g.chips.map((c) => c.label)).toEqual(CHIPS.map((c) => c.label));
			await expect(page.locator('.nt-assist__shortcuts button')).toHaveText(CHIPS.map((c) => c.label));
			for (const c of g.chips) {
				expect(c.h, `${c.label} height`).toBeGreaterThanOrEqual(44);
				expect(c.w, `${c.label} width`).toBeGreaterThanOrEqual(44);
				expect(c.clipped, `${c.label} text is clipped`).toBe(false);
			}
			expect(g.overlaps, 'chips must not overlap the input, send, close or clear buttons').toEqual([]);
			expect(g.panelInside).toBe(true);
			expect(g.logHeight, 'the answer area keeps room for an answer').toBeGreaterThanOrEqual(120);
			expect(g.quickCall, 'Quick Call steps aside while the chat is open').toBe('hidden');
			await noHorizontalScroll(page);
			for (const chip of CHIPS) await expectReadable(page.locator('.nt-assist__shortcuts button', { hasText: chip.label }), `chip "${chip.label}" ${mode} ${width}px`);
			if (width === 390) {
				// Target size, accessible name containing the visible label, and contrast of the open panel in this colour mode.
				const axe = await new AxeBuilder({ page }).include('#nt-assist-dialog').withRules(['target-size', 'label-content-name-mismatch', 'color-contrast']).analyze();
				expect(axe.violations.map((item) => ({ id: item.id, targets: item.nodes.map((node) => node.target) }))).toEqual([]);
				expect(axe.incomplete.filter((item) => item.id !== 'color-contrast').map((item) => item.id), 'axe could not decide target size or label').toEqual([]);
			}
		});
	}
}

for (const mode of ['light', 'dark']) {
	test(`each of the six chips sends its own question and shows a real answer, ${mode} mode`, async ({ page }, testInfo) => {
		test.skip(SIMS, 'native widget is hidden by MotoAI simulator');
		test.setTimeout(120_000);
		await page.addInitScript((value) => localStorage.setItem('nt-color-mode', value), mode);
		await page.setViewportSize({ width: 390, height: 844 });
		await page.goto('/', { waitUntil: 'networkidle' });
		await openChat(page);
		const checks = {
			'Bảng giá': async (bot) => {
				await expect(bot).toContainText('150.000đ');
				await expect(bot.locator('.nt-assist__cta')).toHaveCount(0);
			},
			'Đặt cọc': async (bot) => {
				await expect(bot).toContainText('2.000.000đ đến 5.000.000đ');
				await expect(bot).not.toContainText('Honda');
			},
			'Thủ tục': async (bot) => {
				await expect(bot).toContainText('giấy tờ bảo đảm');
				await expect(bot.locator('.nt-assist__cta')).toHaveCount(0);
			},
			'Xe điện': async (bot) => {
				await expect(bot).toContainText('xe điện');
				expect(await bot.locator('p').first().innerText(), 'no price for a model without one').not.toMatch(MONEY);
				await expect(bot.locator('.nt-assist__cta--call')).toHaveAttribute('href', NAP.tel);
				await expect(bot.locator('.nt-assist__cta--zalo')).toHaveAttribute('href', NAP.zalo);
			},
			'Liên hệ': async (bot) => {
				await expect(bot).toContainText(NAP.phone);
				await expect(bot).not.toContainText('Honda');
				const call = bot.locator('.nt-assist__cta--call');
				const zalo = bot.locator('.nt-assist__cta--zalo');
				await expect(call).toHaveText('Gọi điện');
				await expect(call).toHaveAttribute('href', NAP.tel);
				await expect(zalo).toHaveText('Nhắn Zalo');
				await expect(zalo).toHaveAttribute('href', NAP.zalo);
				await expect(zalo).toHaveAttribute('rel', /noopener/);
				await expect(bot.locator('.nt-assist__cta--maps')).toHaveCount(0);
				for (const button of [call, zalo]) {
					expect((await button.boundingBox()).height, 'button target').toBeGreaterThanOrEqual(44);
					await expectReadable(button, `${mode} button`);
				}
			},
			'Địa chỉ': async (bot) => {
				await expect(bot).toContainText(NAP.address);
				await expect(bot).toContainText(NAP.hours);
				const maps = bot.locator('.nt-assist__cta--maps');
				await expect(maps).toHaveText('Chỉ đường');
				await expect(maps).toHaveAttribute('href', NAP.maps);
				await expect(maps).toHaveAttribute('target', '_blank');
				await expect(maps).toHaveAttribute('rel', /noopener/);
				await expect(bot.locator('.nt-assist__cta--call')).toHaveCount(0);
				await expectReadable(maps, `${mode} directions button`);
			},
		};
		for (const chip of CHIPS) {
			await page.locator('[data-nt-chat-clear]').click(); // every chip starts from an empty memory
			const before = await page.locator('.nt-assist__message--bot').count();
			await page.locator('.nt-assist__shortcuts button', { hasText: chip.label }).click();
			await expect(page.locator('.nt-assist__message--user').last()).toHaveText(chip.question);
			await expect(page.locator('.nt-assist__message--bot')).toHaveCount(before + 1, { timeout: 20_000 });
			await expect(page.locator('.nt-assist__status')).toBeHidden();
			const bot = page.locator('.nt-assist__message--bot').last();
			await expect(bot).not.toContainText('Chưa tra cứu được');
			await checks[chip.label](bot);
			await noHorizontalScroll(page);
			if (['Liên hệ', 'Địa chỉ'].includes(chip.label)) {
				const file = testInfo.outputPath(`chip-${chip.label === 'Liên hệ' ? 'lien-he' : 'dia-chi'}-390-${mode}.png`);
				await page.screenshot({ path: file });
				await testInfo.attach(`chip ${chip.label} 390px ${mode}`, { path: file, contentType: 'image/png' });
			}
		}
		// Quick Call is untouched by all of this: its own directions link is still the share link the owner approved.
		await page.locator('[data-nt-chat-close]').click();
		await page.locator('[data-nt-qc-launch]').click();
		await expect(page.locator('.nt-qc__link--maps')).toHaveAttribute('href', NAP.quickCallMaps);
		await expect(page.locator('.nt-qc__link--call')).toHaveAttribute('href', NAP.tel);
	});
}

test('chips are reached with Tab in order and sent with Enter or Space', async ({ page }) => {
	test.skip(SIMS, 'native widget is hidden by MotoAI simulator');
	await page.setViewportSize({ width: 1440, height: 900 });
	await page.goto('/', { waitUntil: 'networkidle' });
	await openChat(page);
	await page.locator('[data-nt-chat-close]').focus();
	const order = [];
	for (let i = 0; i < CHIPS.length; i += 1) {
		await page.keyboard.press('Tab');
		order.push(await page.evaluate(() => document.activeElement.textContent.trim()));
	}
	expect(order).toEqual(CHIPS.map((c) => c.label));
	await expect(page.locator('.nt-assist__shortcuts button:focus-visible')).toHaveCSS('outline-style', 'solid');

	await page.locator('.nt-assist__shortcuts button', { hasText: 'Liên hệ' }).focus();
	await page.keyboard.press('Enter');
	await expect(page.locator('.nt-assist__message--bot').last()).toContainText(NAP.phone, { timeout: 20_000 });
	await page.locator('.nt-assist__shortcuts button', { hasText: 'Địa chỉ' }).focus();
	await page.keyboard.press('Space');
	const answer = page.locator('.nt-assist__message--bot').last();
	await expect(answer).toContainText(NAP.address, { timeout: 20_000 });
	// The buttons that came with the answer are real links, reachable by keyboard.
	await answer.locator('.nt-assist__cta--maps').focus();
	await expect(answer.locator('.nt-assist__cta--maps')).toBeFocused();
	await page.keyboard.press('Escape');
	await expect(page.locator('#nt-assist-dialog')).toBeHidden();
	await expect(page.locator('[data-nt-qc-launch]')).toBeFocused();
});

test('an address question between two price questions pulls no old model or price, and the price follow-up still works', async ({ page }) => {
	test.skip(SIMS, 'native widget is hidden by MotoAI simulator');
	test.setTimeout(90_000);
	await page.setViewportSize({ width: 390, height: 844 });
	await page.goto('/', { waitUntil: 'networkidle' });
	await openChat(page);
	const lastAnswer = () => page.locator('.nt-assist__message--bot').last();
	const ask = async (question, expected) => {
		const before = await page.locator('.nt-assist__message--bot').count();
		await page.locator('#nt-assist-question').fill(question);
		await page.locator('.nt-assist__form button').click();
		await expect(page.locator('.nt-assist__message--bot')).toHaveCount(before + 1, { timeout: 20_000 });
		await expect(lastAnswer()).toContainText(expected);
	};
	await ask('Giá Vision?', 'Honda Vision');
	await page.locator('.nt-assist__shortcuts button', { hasText: 'Địa chỉ' }).click();
	await expect(page.locator('.nt-assist__message--bot')).toHaveCount(2, { timeout: 20_000 });
	await expect(lastAnswer()).toContainText(NAP.address);
	await expect(lastAnswer()).not.toContainText('Vision');
	await expect(lastAnswer()).not.toContainText('1.800.000');
	await ask('Tháng thì sao?', 'Honda Vision theo tháng');
	await ask('Cọc bao nhiêu?', 'Tiền cọc tham khảo Honda Vision');
	await ask('Wave thì sao?', 'Honda Wave');
	await expect(lastAnswer()).not.toContainText('Vision');
});

test('a hostile answer creates no script, no foreign link and no unsafe button', async ({ page }) => {
	test.skip(SIMS, 'native widget is hidden by MotoAI simulator');
	await page.addInitScript(() => { window.__xss = 0; });
	await page.route('**/wp-json/nguyentu/v1/assist', (route) => route.fulfill({
		status: 200,
		contentType: 'application/json',
		body: JSON.stringify({
			mode: 'tra-cuu-noi-dung',
			intent: 'contact',
			answer: '<img src=x onerror="window.__xss=1"><script>window.__xss=2</script> [x](javascript:window.__xss=3)',
			sources: [
				{ title: 'Liên hệ', url: new URL('/lien-he/', page.url()).href },
				{ title: 'js', url: 'javascript:window.__xss=4' },
				{ title: 'data', url: 'data:text/html,<script>window.__xss=5</script>' },
				{ title: 'evil', url: 'https://evil.example/phish' },
				{ title: 'lookalike', url: 'https://127.0.0.1.evil.example/x' },
			],
			actions: [
				{ type: 'call', url: 'javascript:window.__xss=6' }, { type: 'zalo', url: 'https://zalo.me.evil.example/x' },
				{ type: 'maps', url: 'https://evil.example/maps' }, { type: 'exec', url: 'tel:0942467674' }, null, 'text',
			],
			context: { model: '', period: '' },
			contact: {},
		}),
	}));
	await page.setViewportSize({ width: 390, height: 844 });
	await page.goto('/', { waitUntil: 'networkidle' });
	await openChat(page);
	await page.locator('#nt-assist-question').fill('thử payload độc hại');
	await page.locator('.nt-assist__form button').click();
	const bot = page.locator('.nt-assist__message--bot').last();
	await expect(bot).toContainText('<script>');
	expect(await page.evaluate(() => window.__xss), 'nothing from the answer ran').toBe(0);
	await expect(bot.locator('img, script, iframe')).toHaveCount(0);
	await expect(bot.locator('.nt-assist__cta')).toHaveCount(0);
	const links = await bot.locator('a').evaluateAll((anchors) => anchors.map((a) => a.href));
	expect(links.filter((href) => !href.startsWith(new URL(page.url()).origin))).toEqual([]);
	expect(links.some((href) => href.includes('javascript:') || href.startsWith('data:'))).toBe(false);
});

for (const [label, handler] of [
	['the network fails', (route) => route.abort('failed')],
	['the server answers 500', (route) => route.fulfill({ status: 500, contentType: 'application/json', body: '{"code":"x"}' })],
	['the answer is not JSON', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: '<<not json' })],
]) {
	test(`when ${label} a chip shows a calm fallback and the chat stays usable`, async ({ page }) => {
		test.skip(SIMS, 'native widget is hidden by MotoAI simulator');
		await page.route('**/wp-json/nguyentu/v1/assist', handler);
		await page.setViewportSize({ width: 390, height: 844 });
		await page.goto('/', { waitUntil: 'networkidle' });
		await openChat(page);
		await page.locator('.nt-assist__shortcuts button', { hasText: 'Liên hệ' }).click();
		await expect(page.locator('.nt-assist__message--bot').last()).toContainText('gọi Nguyễn Tú hoặc nhắn Zalo');
		await expect(page.locator('.nt-assist__form button')).toBeEnabled();
		await expect(page.locator('.nt-assist__status')).toBeHidden();
		await expect(page.locator('.nt-assist__shortcuts button')).toHaveCount(6);
		await noHorizontalScroll(page);
	});
}

test('a request that never answers is given up after 12 seconds and the chat is usable again', async ({ page }) => {
	test.skip(SIMS, 'native widget is hidden by MotoAI simulator');
	test.setTimeout(60_000);
	await page.route('**/wp-json/nguyentu/v1/assist', () => { /* never answered */ });
	await page.setViewportSize({ width: 390, height: 844 });
	await page.goto('/', { waitUntil: 'networkidle' });
	await openChat(page);
	await page.locator('.nt-assist__shortcuts button', { hasText: 'Địa chỉ' }).click();
	await expect(page.locator('.nt-assist__status')).toBeVisible();
	await expect(page.locator('.nt-assist__message--bot').last()).toContainText('Chưa tra cứu được', { timeout: 20_000 });
	await expect(page.locator('.nt-assist__form button')).toBeEnabled();
	await expect(page.locator('.nt-assist__status')).toBeHidden();
});

test('on a short landscape phone the six chips stay reachable and the panel stays inside the screen', async ({ page }) => {
	test.skip(SIMS, 'native widget is hidden by MotoAI simulator');
	await page.setViewportSize({ width: 844, height: 390 });
	await page.goto('/', { waitUntil: 'networkidle' });
	await openChat(page);
	const g = await chatGeometry(page);
	expect(g.chips).toHaveLength(6);
	expect(g.panelInside).toBe(true);
	expect(g.overlaps).toEqual([]);
	expect(g.logHeight, 'room for at least a few lines of answer').toBeGreaterThanOrEqual(60);
	for (const chip of CHIPS) {
		const button = page.locator('.nt-assist__shortcuts button', { hasText: chip.label });
		await button.scrollIntoViewIfNeeded();
		await expect(button).toBeVisible();
		expect((await button.boundingBox()).height, `${chip.label} target`).toBeGreaterThanOrEqual(36);
	}
	await page.locator('.nt-assist__shortcuts button', { hasText: 'Địa chỉ' }).click();
	await expect(page.locator('.nt-assist__message--bot').last()).toContainText(NAP.address, { timeout: 20_000 });
	await noHorizontalScroll(page);
	expect((await chatGeometry(page)).panelInside).toBe(true);
});

test('the chips make room for the iPhone keyboard and come back when it closes', async ({ page }) => {
	test.skip(SIMS, 'native widget is hidden by MotoAI simulator');
	await page.setViewportSize({ width: 390, height: 844 });
	await page.goto('/', { waitUntil: 'networkidle' });
	await openChat(page);
	await expect(page.locator('.nt-assist__shortcuts')).toBeVisible();
	await page.locator('#nt-assist-question').focus();
	await page.evaluate(() => {
		// Chromium headless has no software keyboard; model the VisualViewport shrink of an iPhone (as the test above does).
		window.__realViewport = window.visualViewport;
		Object.defineProperty(window, 'visualViewport', { configurable: true, value: { width: 390, height: 508, offsetTop: 0, offsetLeft: 0 } });
		window.__realViewport.dispatchEvent(new Event('resize'));
	});
	await expect(page.locator('#nt-assist-dialog')).toHaveClass(/nt-assist__panel--keyboard/);
	await expect(page.locator('.nt-assist__shortcuts')).toBeHidden();
	await expect.poll(() => page.evaluate(() => {
		const panel = document.getElementById('nt-assist-dialog').getBoundingClientRect();
		const input = document.getElementById('nt-assist-question').getBoundingClientRect();
		const send = document.querySelector('.nt-assist__form button').getBoundingClientRect();
		const close = document.querySelector('[data-nt-chat-close]').getBoundingClientRect();
		const log = document.querySelector('[data-nt-chat-log]').getBoundingClientRect();
		return panel.top >= 7 && panel.bottom <= 508 - 7 && input.bottom <= panel.bottom && send.bottom <= panel.bottom && send.right <= panel.right && close.top >= panel.top && log.height >= 120;
	})).toBe(true);
	await noHorizontalScroll(page);
	await page.locator('#nt-assist-question').blur();
	await page.evaluate(() => {
		Object.defineProperty(window, 'visualViewport', { configurable: true, value: { width: 390, height: 844, offsetTop: 0, offsetLeft: 0 } });
		window.__realViewport.dispatchEvent(new Event('resize'));
	});
	await expect(page.locator('#nt-assist-dialog')).not.toHaveClass(/nt-assist__panel--keyboard/);
	await expect(page.locator('.nt-assist__shortcuts')).toBeVisible();
	await expect(page.locator('.nt-assist__shortcuts button')).toHaveCount(6);
});
