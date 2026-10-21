import { test } from 'node:test';
import assert from 'node:assert/strict';
import { legacyTermPairs, assertPreservedLinks, verifyLiveTermAliases } from '../../tools/qa/homepage-legacy-parity.mjs';

const origin = 'https://thuexemaynguyentu.com/';
const pairs = legacyTermPairs(origin);

test('only the four known legacy taxonomy paths map to canonical /bo-suu-tap/', () => {
  assert.equal(pairs.length, 4);
  assert.deepEqual(pairs.map(p => p.old.split('/').at(-2)), ['bang-gia', 'thue-ngay', 'thue-tuan', 'thue-thang']);
  assert.ok(pairs.every(p => p.canonical.startsWith(origin + 'bo-suu-tap/')));
});

test('historical menu/footer URL changes are accepted ONLY with verified alias mapping', () => {
  const old = [origin, pairs[0].old, pairs[1].old, origin + 'lien-he/'];
  const changed = [origin, pairs[0].canonical, pairs[1].canonical, origin + 'lien-he/'];
  assert.throws(() => assertPreservedLinks(changed, old, new Map()), /Menu\/footer changed/);
  const evidence = new Map(pairs.map(p => [p.old, p.canonical]));
  assert.doesNotThrow(() => assertPreservedLinks(changed, old, evidence));
  assert.throws(() => assertPreservedLinks([...changed.slice(0, 3), origin + 'faq/'], old, evidence));
});

test('live adapter requires HTTP 301, exact Location, destination 200 and self canonical', async () => {
  const calls = [];
  const request = { get: async (url, options) => {
    calls.push({ url, options });
    const pair = pairs.find(p => p.old === url || p.canonical === url);
    assert.ok(pair, url);
    return url === pair.old
      ? { status: () => 301, headers: () => ({ location: pair.canonical }) }
      : { status: () => 200, text: async () => '<head><link rel="canonical" href="' + pair.canonical + '"></head>' };
  }};
  const result = await verifyLiveTermAliases(request, origin);
  assert.equal(result.replacements.size, 4);
  assert.equal(result.evidence.length, 4);
  assert.equal(calls.length, 8);
  assert.ok(calls.every(x => x.options.maxRedirects === 0));
});

test('HTTP 200 on legacy URL cannot be mislabeled as a redirect', async () => {
  const request = { get: async url => ({ status: () => 200, headers: () => ({}), text: async () => '<link rel="canonical" href="' + pairs[0].canonical + '">' }) };
  await assert.rejects(() => verifyLiveTermAliases(request, origin), /must be a permanent HTTP 301/);
});

test('wrong redirects and duplicate canonicals fail closed', async () => {
  const wrongTarget = { get: async () => ({ status: () => 301, headers: () => ({ location: origin }) }) };
  await assert.rejects(() => verifyLiveTermAliases(wrongTarget, origin), /redirects to unexpected URL/);
  const doubleCanonical = { get: async url => url === pairs[0].old
    ? { status: () => 301, headers: () => ({ location: pairs[0].canonical }) }
    : { status: () => 200, text: async () => '<link rel="canonical" href="' + pairs[0].canonical + '"><link rel="canonical" href="' + pairs[0].canonical + '">' } };
  await assert.rejects(() => verifyLiveTermAliases(doubleCanonical, origin), /exactly one rel=canonical/);
});
