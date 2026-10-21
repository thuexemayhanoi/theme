/**
 * Homepage QA adapter for the four pre-2026-10-19 featured_item taxonomy URLs.
 *
 * This file does NOT rewrite WordPress permalinks or the historical baseline.
 * It only accepts a changed menu/footer link when the old URL has been observed
 * to return HTTP 301 to the exact, published self-canonical replacement.
 */
import assert from 'node:assert/strict';

export const VERIFIED_TERM_SLUGS = Object.freeze(['bang-gia', 'thue-ngay', 'thue-tuan', 'thue-thang']);

export function legacyTermPairs(origin) {
  const root = new URL(origin).origin;
  return VERIFIED_TERM_SLUGS.map(slug => ({
    old: new URL('/featured_item_category/' + slug + '/', root).href,
    canonical: new URL('/bo-suu-tap/' + slug + '/', root).href,
  }));
}

export function assertPreservedLinks(actual, archived, replacements) {
  assert.ok(Array.isArray(actual) && Array.isArray(archived), 'Link inventory must be arrays');
  const expected = archived.map(url => replacements.get(url) ?? url);
  assert.deepEqual(actual, expected, 'Menu/footer changed outside verified 301 legacy aliases');
}

function canonicalFromHead(html, pageUrl) {
  const linkTags = html.match(/<link\b[^>]*>/gi) ?? [];
  const canonicalTags = linkTags.filter(tag => /\brel\s*=\s*["']canonical["']/i.test(tag));
  assert.equal(canonicalTags.length, 1, pageUrl + ' must have exactly one rel=canonical');
  const href = canonicalTags[0].match(/\bhref\s*=\s*["']([^"']+)["']/i)?.[1];
  assert.ok(href, pageUrl + ' canonical href missing');
  return new URL(href, pageUrl).href;
}

/**
 * Read-only HTTP preflight. Never infer a 301 from matching HTML, a previous test,
 * a WordPress rewrite rule, or the canonical tag on an HTTP 200 alias.
 * @param {import('@playwright/test').APIRequestContext} request Playwright request.
 */
export async function verifyLiveTermAliases(request, baseUrl) {
  const replacements = new Map();
  const evidence = [];
  for (const pair of legacyTermPairs(baseUrl)) {
    const oldResponse = await request.get(pair.old, { maxRedirects: 0, timeout: 20000 });
    const status = oldResponse.status();
    assert.equal(status, 301, pair.old + ' must be a permanent HTTP 301 redirect, received ' + status);
    const location = oldResponse.headers()['location'];
    assert.ok(location, pair.old + ' has no Location header');
    const target = new URL(location, pair.old).href;
    assert.equal(target, pair.canonical, pair.old + ' redirects to unexpected URL');
    const targetResponse = await request.get(pair.canonical, { maxRedirects: 0, timeout: 20000 });
    assert.equal(targetResponse.status(), 200, pair.canonical + ' must be a live HTTP 200 page');
    const selfCanonical = canonicalFromHead(await targetResponse.text(), pair.canonical);
    assert.equal(selfCanonical, pair.canonical, pair.canonical + ' has unexpected canonical');
    replacements.set(pair.old, pair.canonical);
    evidence.push({ old: pair.old, status, location: target, destinationStatus: 200, canonical: selfCanonical });
  }
  return { replacements, evidence };
}
