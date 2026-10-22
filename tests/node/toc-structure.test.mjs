import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { analyzePost, checkNewLayout, isOldLayout, hasNoList, rawProse, oracleCheck, sampleEvenly, parseSitemapLocs, contrastRatio } from '../../tools/qa/lib/toc-structure.mjs';

const items = '<ol class="nt-toc__list"><li><a href="#a" data-nt-toc-link>A</a></li><li><a href="#b" data-nt-toc-link>B</a></li><li><a href="#c" data-nt-toc-link>C</a></li></ol>';
const inline = (placement) => `<details class="nt-toc nt-toc--inline"${placement ? ` data-nt-toc-placement="${placement}"` : ''}><summary class="nt-toc__summary">Mục lục bài viết</summary>${items}</details>`;
const sticky = `<nav class="nt-toc nt-toc--sticky" aria-label="Mục lục bài viết" data-nt-toc><p class="nt-toc__title">Mục lục</p>${items}</nav>`;
const page = ({ before = '', body, aside = sticky, hero = '<figure class="nt-article__hero"><img src="x.webp" alt=""></figure>' }) => `<!doctype html><html><head><link rel="canonical" href="https://example.test/bai/"></head><body><main><article>
<header><h1 class="nt-article__title">Tiêu đề bài</h1><p class="nt-article__meta">Nguyễn Tú · 22/10/2026</p></header>
<div class="nt-article__layout"><div class="nt-article__main">${hero}${before}<div class="nt-prose entry-content">${body}</div></div><aside class="nt-article__aside">${aside}</aside></div></article></main></body></html>`;
const BODY = '<p>Mở đầu.</p><h2 id="a">A</h2>{TOC}<p>x</p><h2 id="b">B</h2><p>y</p><h3 id="c">C</h3><p>z</p>';
const withToc = (placement) => page({ body: BODY.replace('{TOC}', inline(placement)) });
const oldLayout = page({ before: inline(null), body: BODY.replace('{TOC}', '') });

test('the new layout: list right after the first H2, one inline and one sticky variant, nothing wrong', () => {
  const a = analyzePost(withToc('after-first-h2'));
  assert.deepEqual(checkNewLayout(a), []);
  assert.equal(a.toc.placement, 'after-first-h2');
  assert.equal(a.toc.prevIsFirstH2, true);
  assert.equal(a.toc.nextTag, 'p');
  assert.equal(a.toc.inProse, true);
  assert.deepEqual(a.headings, ['h1|Tiêu đề bài|', 'h2|A|a', 'h2|B|b', 'h3|C|c']);
  assert.equal(isOldLayout(a), false);
  assert.equal(a.canonical, 'https://example.test/bai/');
});

test('the old layout is recognised: list in front of the text, no placement attribute', () => {
  const a = analyzePost(oldLayout);
  assert.equal(isOldLayout(a), true);
  assert.ok(checkNewLayout(a).some((m) => /placement attribute/.test(m)), 'the old layout does not pass the new checks');
});

test('a post without a list is fine and is not mistaken for either layout', () => {
  const a = analyzePost(page({ body: '<p>Chỉ có đoạn văn.</p><h2>Một</h2><p>x</p>', aside: '' }));
  assert.equal(hasNoList(a), true);
  assert.equal(isOldLayout(a), false);
  assert.deepEqual(checkNewLayout(a), []);
});

test('the checks catch a wrong place, a second list, a list inside a heading and a missing variant', () => {
  const wrong = page({ body: '<p>Mở đầu.</p><h2 id="a">A</h2><p>x</p>' + inline('after-first-h2') + '<h2 id="b">B</h2><h3 id="c">C</h3>' });
  assert.ok(checkNewLayout(analyzePost(wrong)).some((m) => /not the element right after the first H2/.test(m)));

  const twice = page({ body: BODY.replace('{TOC}', inline('after-first-h2') + inline('after-first-h2')) });
  assert.ok(checkNewLayout(analyzePost(twice)).some((m) => /list variants/.test(m)));

  const inHeading = page({ body: `<p>m</p><h2 id="a">A${inline('after-first-h2')}</h2><h2 id="b">B</h2><h3 id="c">C</h3>` });
  assert.ok(checkNewLayout(analyzePost(inHeading)).some((m) => /inside a heading/.test(m)));

  const noSticky = page({ body: BODY.replace('{TOC}', inline('after-first-h2')), aside: '' });
  assert.ok(checkNewLayout(analyzePost(noSticky)).some((m) => /list variants/.test(m)));

  const twoH1 = page({ body: '<h1>Hai</h1>' + BODY.replace('{TOC}', inline('after-first-h2')) });
  assert.ok(checkNewLayout(analyzePost(twoH1)).some((m) => /H1 count 2/.test(m)));

  const outOfOrder = withToc('after-first-h2').replace(/<header>[\s\S]*?<\/header>/, '<header><p class="nt-article__meta">m</p><h1>t</h1></header>');
  assert.ok(checkNewLayout(analyzePost(outOfOrder)).some((m) => /not in that order/.test(m)));
});

test('after-intro and before-content are accepted only where they are true', () => {
  const nested = page({ body: '<p>Mở đầu.</p>' + inline('after-intro') + '<div class="wp-block-group"><h2 id="a">A</h2><p>x</p></div><h2 id="b">B</h2><h3 id="c">C</h3>' });
  assert.deepEqual(checkNewLayout(analyzePost(nested)), []);
  const buried = page({ body: '<p>Mở đầu.</p><div class="wp-block-group"><h2 id="a">A</h2>' + inline('after-intro') + '</div><h2 id="b">B</h2><h3 id="c">C</h3>' });
  assert.ok(checkNewLayout(analyzePost(buried)).some((m) => /direct child/.test(m)));
  const fallback = page({ before: inline('before-content'), body: BODY.replace('{TOC}', '') });
  assert.deepEqual(checkNewLayout(analyzePost(fallback)), []);
  const lying = page({ body: BODY.replace('{TOC}', inline('before-content')) });
  assert.ok(checkNewLayout(analyzePost(lying)).some((m) => /not in front of the text/.test(m)));
});

test('another contents-list plugin inside the text is reported', () => {
  const a = analyzePost(page({ body: '<div id="ez-toc-container" class="ez-toc-container">x</div>' + BODY.replace('{TOC}', inline('after-first-h2')) }));
  assert.equal(a.embeddedTocMarkers, 1);
  assert.equal(analyzePost(withToc('after-first-h2')).embeddedTocMarkers, 0);
});

test('rawProse returns the bytes of the text container, nested divs included, or null when unbalanced', () => {
  const html = page({ body: '<p>a &amp; b</p><div class="wp-block-group"><div><h2>x</h2></div></div><p>c</p>' });
  assert.equal(rawProse(html), '<p>a &amp; b</p><div class="wp-block-group"><div><h2>x</h2></div></div><p>c</p>');
  assert.equal(rawProse('<div class="nt-prose entry-content"><div><p>unbalanced</p></div>'), null);
  assert.equal(rawProse('<p>no text container</p>'), null);
});

const probe = '<details id="nt-toc-probe"></details>';
test('the oracle accepts a right placement and names every kind of mistake', () => {
  const body = '<p>Mở đầu.</p><h2>Một</h2><p>a</p><h2>Hai</h2><h3>Ba</h3>';
  const right = body.replace('<h2>Một</h2>', `<h2>Một</h2>${probe}`);
  assert.deepEqual(oracleCheck(body, right, 'after-first-h2'), []);
  assert.ok(oracleCheck(body, body.replace('<h2>Hai</h2>', `<h2>Hai</h2>${probe}`), 'after-first-h2').some((m) => /right after the first non-empty H2/.test(m)));
  assert.ok(oracleCheck(body, right.replace('<h2>Một</h2>', '<h2>Một!</h2>'), 'after-first-h2').some((m) => /original bytes|headings changed/.test(m)));
  assert.ok(oracleCheck(body, `<div>${right}</div>`, 'after-first-h2').length > 0, 'a changed body is caught');
  const nestedBody = '<p>i</p><div class="g"><h2>Một</h2></div><h2>Hai</h2>';
  assert.deepEqual(oracleCheck(nestedBody, nestedBody.replace('<div class="g">', `${probe}<div class="g">`), 'after-intro'), []);
  assert.ok(oracleCheck(nestedBody, nestedBody.replace('<h2>Một</h2>', `<h2>Một</h2>${probe}`), 'after-intro').some((m) => /direct child/.test(m)));
  assert.ok(oracleCheck(body, right, 'after-intro').some((m) => /usable top-level H2 exists/.test(m)), 'after-intro although a top-level H2 existed');
  assert.deepEqual(oracleCheck(body, body, 'before-content'), []);
});

const hasPhp = spawnSync('php', ['-v']).status === 0;
test('the theme placement function and the oracle agree on a corpus of awkward bodies', { skip: !hasPhp && 'php is not installed' }, () => {
  const bodies = {
    plain: '<p>Mở đầu.</p>\n<h2 id="a">Một</h2>\n<p>a</p>\n<h2>Hai</h2>\n<h3>Ba</h3>',
    block: '<!-- wp:paragraph -->\n<p>Mở đầu.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class="wp-block-heading">Một &amp; hai</h2>\n<!-- /wp:heading -->\n<p>x</p><h2>Ba</h2>',
    group: '<p>i</p><div class="wp-block-group"><div class="wp-block-group__inner-container"><h2>Một</h2></div></div><h2>Hai</h2><h3>Ba</h3>',
    columns: '<p>i</p><div class="wp-block-columns"><div class="wp-block-column"><h2>Một</h2></div></div><h3>Hai</h3>',
    scriptBefore: '<p>i</p><script>var s="<h2>no</h2>";</script><noscript><img src="x"></noscript><h2>Thật</h2><p>x</p>',
    unclosedParagraph: '<p>Mở đầu<h2>Một</h2><p>a<h2>Hai</h2>',
    onlyH3: '<p>Mở đầu.</p><ul><li>a</li></ul><h3>A</h3><h3>B</h3><h3>C</h3>',
    startsWithH2: '<h2>Một</h2><p>a</p><h2>Hai</h2><h2>Ba</h2>',
    inQuote: '<blockquote><h2>Một</h2></blockquote><h3>Hai</h3>',
  };
  const dir = mkdtempSync(join(tmpdir(), 'toc-cli-'));
  const file = join(dir, 'bodies.json');
  writeFileSync(file, JSON.stringify(bodies));
  const run = spawnSync('php', ['tools/qa/toc-placement-cli.php', file], { encoding: 'utf8' });
  assert.equal(run.status, 0, run.stderr);
  const placed = JSON.parse(run.stdout);
  const labels = Object.fromEntries(Object.entries(placed).map(([k, v]) => [k, v.placement]));
  assert.deepEqual(labels, {
    plain: 'after-first-h2', block: 'after-first-h2', group: 'after-intro', columns: 'after-intro', scriptBefore: 'after-first-h2',
    unclosedParagraph: 'after-first-h2', onlyH3: 'after-intro', startsWithH2: 'after-first-h2', inQuote: 'before-content',
  });
  for (const [key, result] of Object.entries(placed)) assert.deepEqual(oracleCheck(bodies[key], result.html, result.placement), [], key);
});

test('sampling is deterministic and spread over the whole list; sitemap locations are decoded', () => {
  const list = Array.from({ length: 100 }, (_, i) => `/p${i}/`);
  const s = sampleEvenly(list, 10);
  assert.equal(s.length, 10);
  assert.deepEqual(s, sampleEvenly(list, 10));
  assert.ok(s[0] === '/p5/' && s[9] === '/p95/');
  assert.equal(sampleEvenly(list, 500).length, 100);
  assert.equal(sampleEvenly(list, 0).length, 100);
  assert.deepEqual(parseSitemapLocs('<urlset><url><loc>https://x.test/a/?x=1&amp;y=2</loc></url><url><loc> https://x.test/b/ </loc></url></urlset>'), ['https://x.test/a/?x=1&y=2', 'https://x.test/b/']);
});

test('contrast ratio follows WCAG', () => {
  assert.equal(Math.round(contrastRatio('rgb(0, 0, 0)', 'rgb(255, 255, 255)')), 21);
  assert.equal(contrastRatio('rgb(10, 22, 43)', 'rgb(10, 22, 43)'), 1);
  assert.ok(contrastRatio('rgb(10, 22, 43)', 'rgb(16, 24, 39)') < 1.1, 'the dark-mode post title colour is far below 3:1');
});
