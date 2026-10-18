import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { ratio, parseTokens } from '../../tools/qa/contrast.mjs';
const css = readFileSync(new URL('../../assets/css/autopilot.css', import.meta.url), 'utf8');
test('dark palette supports readable text, muted labels and links', () => {
 const dark = css.match(/:root\[data-nt-theme="dark"\]\s*\{([^}]+)\}/s)[1];
 const t = parseTokens(`:root {${dark}}`);
 for (const bg of ['nt-paper','nt-cream','nt-sand']) {
  for (const fg of ['nt-ink','nt-muted','nt-navy']) assert.ok(ratio(t[fg],t[bg]) >= 4.5, `${fg}/${bg}`);
 }
});

import { pathOf, extractPage } from '../../tools/seo/lib.mjs';
test('SEO reports exclude preview and nonce parameters while retaining public query intent', () => {
 assert.equal(pathOf('/blog/?wpvibe_preview=temporary&_wpnonce=private&s=xe'), '/blog/?s=xe');
});

test('full SEO snapshot omits temporary preview values from page and link URLs', () => {
 const result=extractPage({html:'<a href="/blog/?wpvibe_preview=temporary&_wpnonce=private">Blog</a>',url:'https://example.test/?wpvibe_preview=temporary',status:200});
 assert.doesNotMatch(JSON.stringify(result),/temporary|private/);
});
