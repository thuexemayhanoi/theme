import { test, expect } from '@playwright/test';

/** Head signals read from the rendered DOM (what a crawler's renderer sees). */
async function head(page) {
	return page.evaluate(() => ({
		canonicals: [...document.querySelectorAll('link[rel="canonical"]')].map((l) => l.href),
		descriptions: [...document.querySelectorAll('meta[name="description"]')].map((m) => m.content),
		robots: [...document.querySelectorAll('meta[name="robots"]')].map((m) => m.content),
		titles: [...document.querySelectorAll('title')].map((t) => t.textContent),
		ld: [...document.querySelectorAll('script[type="application/ld+json"]')].map((s) => {
			try {
				return JSON.parse(s.textContent);
			} catch {
				return null;
			}
		}),
		lang: document.documentElement.lang,
	}));
}

const nodes = (ld) => ld.flatMap((b) => (b && b['@graph'] ? b['@graph'] : b ? [b] : []));
const typesOf = (n) => [].concat(n['@type'] ?? []);

test.describe('Yoast stays the single source of head and schema', () => {
	test('home: one title, description, canonical = /, index; LocalBusiness is merged into the Organization node', async ({ page }) => {
		await page.goto('/');
		const h = await head(page);
		expect(h.lang).toMatch(/^vi/);
		expect(h.titles).toHaveLength(1);
		expect(h.descriptions.length).toBeLessThanOrEqual(1);
		expect(h.canonicals).toHaveLength(1);
		expect(new URL(h.canonicals[0]).pathname).toBe('/');
		expect(h.robots.join(' ')).not.toMatch(/noindex/);
		expect(h.ld.every(Boolean), 'all JSON-LD blocks parse').toBe(true);

		const graph = nodes(h.ld);
		const orgs = graph.filter((n) => typesOf(n).includes('Organization'));
		const locals = graph.filter((n) => typesOf(n).includes('LocalBusiness'));
		expect(orgs.length, 'one Organization entity').toBe(1);
		expect(locals.length, 'one LocalBusiness entity').toBe(1);
		expect(locals[0]['@id']).toBe(orgs[0]['@id']);
		expect(locals[0].telephone).toBe('+84942467674');
		expect(locals[0].address.streetAddress).toBe('112 Nguyễn Văn Cừ, Bồ Đề');
		expect(locals[0].openingHoursSpecification[0].opens).toBe('09:00');
		const ids = graph.map((n) => n['@id']).filter(Boolean);
		expect(new Set(ids).size, 'no duplicated @id').toBe(ids.length);
		expect(JSON.stringify(h.ld)).not.toMatch(/AggregateRating|"Review"/);
	});

	for (const path of ['/gioi-thieu/', '/lien-he/', '/blog/', '/blog/xe-dien/', '/shop/', '/san-pham/honda-wave/', '/bo-suu-tap/thue-ngay/']) {
		test(`${path}: canonical points at itself, indexable, no duplicate entities`, async ({ page }) => {
			await page.goto(path);
			const h = await head(page);
			expect(h.canonicals).toHaveLength(1);
			expect(new URL(h.canonicals[0]).pathname).toBe(path);
			expect(h.robots.join(' ')).not.toMatch(/noindex/);
			const ids = nodes(h.ld).map((n) => n['@id']).filter(Boolean);
			expect(new Set(ids).size).toBe(ids.length);
			expect(nodes(h.ld).filter((n) => typesOf(n).includes('LocalBusiness')).length).toBeLessThanOrEqual(1);
		});
	}

	test('internal search and 404 are noindex; 404 keeps its status', async ({ page }) => {
		await page.goto('/?s=xe');
		expect((await head(page)).robots.join(' ')).toMatch(/noindex/);
		const res = await page.goto('/404-invalid-nonexistent/');
		expect(res.status()).toBe(404);
		expect((await head(page)).robots.join(' ')).toMatch(/noindex/);
		await expect(page.locator('h1')).toHaveCount(1);
		await expect(page.locator('.nt-search')).toHaveCount(1);
	});

	test('robots.txt and the sitemap index are served and untouched by the theme', async ({ request }) => {
		const robots = await request.get('/robots.txt');
		expect(robots.status()).toBe(200);
		expect(await robots.text()).not.toMatch(/Disallow:\s*\/\s*$/m);
		const sm = await request.get('/sitemap_index.xml');
		expect(sm.status()).toBe(200);
		expect(await sm.text()).toContain('<sitemapindex');
	});

	test('RSS feed still works', async ({ request }) => {
		const feed = await request.get('/feed/');
		expect(feed.status()).toBe(200);
		expect(feed.headers()['content-type']).toMatch(/xml/);
	});
});
