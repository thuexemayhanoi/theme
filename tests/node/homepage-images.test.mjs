import { test } from 'node:test';
import assert from 'node:assert/strict';
import { EDITORIAL_ZONES, assessImages, formatImageTable, formatCards, isStalePageCache, inventoryDiff } from '../../tools/qa/lib/homepage-images.mjs';

const view = { width: 390, height: 844 };
const img = (extra = {}) => ({
  attachmentId: '', file: 'a-1024x576.webp', baseFile: 'a.webp', isUpload: true, alt: 'Xe máy trong phố cổ', widthAttr: '1024', heightAttr: '576',
  loading: 'lazy', decoding: 'async', fetchpriority: '', srcset: true, sizes: '100vw', complete: true, natural: [1024, 576], rendered: [366, 206],
  visible: true, firstScreenTop: 2400, section: 'Giá thuê', zone: '', inForm: false, ...extra,
});
const hero = img({ file: 'hero.webp', baseFile: 'hero.webp', zone: EDITORIAL_ZONES.hero, loading: 'eager', fetchpriority: 'high', firstScreenTop: 520 });
const cardA = img({ file: 'card-a.webp', baseFile: 'card-a.webp', zone: EDITORIAL_ZONES.service });
const cardB = img({ file: 'card-b.webp', baseFile: 'card-b.webp', zone: EDITORIAL_ZONES.service, firstScreenTop: 2700 });
const vitals = { cls: 0.02, clsFromImages: 0, shifts: [], lcp: { time: 1800, tag: 'IMG', lazy: false } };
const assess = (images, v = vitals) => assessImages({ images, vitals: v, ...view });

test('the published hero and two service visuals pass', () => {
  const result = assess([hero, cardA, cardB, img({ file: 'p1.webp', baseFile: 'p1.webp', firstScreenTop: 3000 })]);
  assert.deepEqual(result.failures, []);
  assert.deepEqual(result.warnings, []);
  assert.deepEqual([result.summary.visible, result.summary.uploads, result.summary.distinctUploads, result.summary.cls], [4, 4, 4, 0.02]);
});

test('images that did not load, overflow or lack dimensions or alt text fail', () => {
  const broken = assess([hero, cardA, cardB, img({ file: 'x.webp', baseFile: 'x.webp', complete: false, natural: [0, 0] })]);
  assert.match(broken.failures.join('\n'), /x\.webp .*did not load/);
  assert.match(assess([hero, cardA, cardB, img({ rendered: [500, 280], file: 'w.webp', baseFile: 'w.webp' })]).failures.join('\n'), /500px wide overflows the 390px viewport/);
  assert.match(assess([hero, cardA, cardB, img({ widthAttr: null, file: 'd.webp', baseFile: 'd.webp' })]).failures.join('\n'), /missing width\/height/);
  assert.match(assess([hero, cardA, cardB, img({ alt: null, file: 'n.webp', baseFile: 'n.webp' })]).failures.join('\n'), /no alt attribute/);
});

test('alt text must stay short and must not stuff keywords; an empty alt is only a warning', () => {
  const long = assess([hero, cardA, cardB, img({ alt: 'x'.repeat(126), file: 'l.webp', baseFile: 'l.webp' })]);
  assert.match(long.failures.join('\n'), /126 characters/);
  const stuffed = assess([hero, cardA, cardB, img({ alt: 'thuê xe máy hà nội, thuê xe máy giá rẻ, thuê xe máy rẻ nhất, thuê xe máy tốt', file: 's.webp', baseFile: 's.webp' })]);
  assert.match(stuffed.failures.join('\n'), /keyword-stuffed/);
  const empty = assess([hero, cardA, cardB, img({ alt: '', file: 'e.webp', baseFile: 'e.webp' })]);
  assert.deepEqual(empty.failures, []);
  assert.match(empty.warnings.join('\n'), /empty alt/);
});

test('lazy loading findings are notes; only a lazy LCP element or a lazy hero fails', () => {
  const lazyFirst = assess([hero, cardA, cardB, img({ firstScreenTop: 300, file: 'f.webp', baseFile: 'f.webp' })]);
  assert.deepEqual(lazyFirst.failures, []);
  assert.match(lazyFirst.warnings.join('\n'), /f\.webp.*first-screen image \(top 300px\) is lazy loaded/);
  const eager = assess([hero, cardA, cardB, img({ loading: 'eager', firstScreenTop: 942, file: 'g.webp', baseFile: 'g.webp' })]);
  assert.deepEqual(eager.failures, []);
  assert.match(eager.warnings.join('\n'), /g\.webp.*below the first screen \(top 942px\) but not lazy loaded/);
  assert.deepEqual(assess([hero, cardA, cardB, img({ loading: 'eager', fetchpriority: 'high', file: 'h.webp', baseFile: 'h.webp' })]).warnings, []);
  assert.deepEqual(assess([hero, cardA, cardB, img({ isUpload: false, loading: 'eager', file: 'avatar.png', baseFile: 'avatar.png' })]).failures, [], 'not a WordPress upload: no lazy rule');
});

test('hero and service visuals: one hero, two cards, 16:9, distinct files, hero eager with high priority', () => {
  assert.match(assess([cardA, cardB]).failures.join('\n'), /hero visual: 0 images, expected 1/);
  assert.match(assess([hero, cardA]).failures.join('\n'), /service visuals: 1 images, expected 2/);
  assert.match(assess([{ ...hero, loading: 'lazy' }, cardA, cardB]).failures.join('\n'), /hero must not be lazy loaded/);
  const noPriority = assess([{ ...hero, fetchpriority: '' }, cardA, cardB]);
  assert.deepEqual(noPriority.failures, [], 'a missing fetchpriority is observed, not failed');
  assert.match(noPriority.warnings.join('\n'), /served hero has no fetchpriority=high/);
  assert.match(assess([hero, { ...cardA, loading: 'eager', fetchpriority: 'high', firstScreenTop: 900 }, cardB]).failures.join('\n'), /service visual must be lazy loaded/);
  assert.match(assess([hero, { ...cardA, rendered: [366, 366] }, cardB]).failures.join('\n'), /is not 16:9/);
  assert.match(assess([hero, { ...cardA, rendered: [200, 112] }, cardB]).failures.join('\n'), /rendered only 200px wide/);
  assert.match(assess([hero, cardA, { ...cardB, baseFile: cardA.baseFile }]).failures.join('\n'), /same file/);
  assert.match(assess([hero, { ...cardA, baseFile: hero.baseFile }, cardB]).failures.join('\n'), /repeats the hero file/);
  assert.match(assess([hero, { ...cardA, isUpload: false }, cardB]).failures.join('\n'), /not a WordPress upload/);
});

test('layout shifts caused by images and a lazy LCP image fail; everything else is a warning', () => {
  assert.match(assess([hero, cardA, cardB], { ...vitals, clsFromImages: 0.03, cls: 0.03 }).failures.join('\n'), /images moved the layout/);
  assert.match(assess([hero, cardA, cardB], { ...vitals, lcp: { time: 900, tag: 'IMG', lazy: true } }).failures.join('\n'), /LCP element is a lazy loaded image/);
  const noisy = assess([hero, cardA, cardB], { cls: 0.18, clsFromImages: 0, shifts: [{ value: 0.18, sources: [{ tag: 'DIV' }] }], lcp: { time: 3900, tag: 'H1', lazy: false } });
  assert.deepEqual(noisy.failures, []);
  assert.match(noisy.warnings.join('\n'), /total CLS 0\.180/);
  assert.match(noisy.warnings.join('\n'), /LCP 3900 ms/);
  assert.match(assessImages({ images: [hero, cardA, cardB], vitals: undefined, ...view }).warnings.join('\n'), /not observable/);
});

test('duplicates are reported once per file; hidden and form images are ignored', () => {
  const twice = assess([hero, cardA, cardB, img({ file: 'p-300x200.webp', baseFile: 'p.webp', firstScreenTop: 3000 }), img({ file: 'p-768x512.webp', baseFile: 'p.webp', firstScreenTop: 3600 })]);
  assert.deepEqual(twice.failures, []);
  assert.match(twice.warnings.join('\n'), /p\.webp x2/);
  const ignored = assess([hero, cardA, cardB, img({ visible: false, complete: false, natural: [0, 0], file: 'hidden.webp', baseFile: 'hidden.webp' }), img({ inForm: true, complete: false, natural: [0, 0], file: 'captcha.png', baseFile: 'captcha.png' })]);
  assert.deepEqual(ignored.failures, []);
  assert.equal(ignored.summary.imagesInMain, 5);
  assert.equal(ignored.summary.visible, 3);
});

test('the inventory prints one readable line per image', () => {
  const table = formatImageTable([hero, { ...cardA, visible: false }], 390).split('\n');
  assert.equal(table.length, 2);
  assert.match(table[0], /^ 1 @390 upload hero\.webp \| 366x206 \(natural 1024x576, attrs 1024x576\) \| eager\+high \| top 520 \| alt="Xe máy trong phố cổ"/);
  assert.match(table[1], /\[hidden\]/);
});

test('a served page that only lacks the editorial images while the origin passes is a stale page cache', () => {
  const missing = ['hero visual: 0 images, expected 1', 'service visuals: 0 images, expected 2'];
  assert.equal(isStalePageCache(missing, []), true);
  assert.equal(isStalePageCache(missing.slice(0, 1), []), true);
  assert.equal(isStalePageCache([...missing, 'a.webp: did not load'], []), false, 'another failure is real');
  assert.equal(isStalePageCache(missing, ['x']), false, 'the origin render must pass');
  assert.equal(isStalePageCache([], []), false, 'nothing is stale when nothing fails');
});

test('the inventory diff names the images only one render shows, ignoring size suffixes', () => {
  const served = [img({ baseFile: 'a.webp' }), img({ baseFile: 'b.webp' }), img({ baseFile: 'hidden.webp', visible: false })];
  const origin = [img({ baseFile: 'a.webp' }), img({ baseFile: 'hero.webp' }), img({ baseFile: 'c.webp' })];
  assert.deepEqual(inventoryDiff(served, origin), { onlyServed: ['b.webp'], onlyOrigin: ['c.webp', 'hero.webp'] });
  assert.deepEqual(inventoryDiff(origin, origin), { onlyServed: [], onlyOrigin: [] });
});

test('the card inventory shows how many cards of each zone have an image', () => {
  const text = formatCards([{ zone: 'nt-home-mag__latest', title: 'A', hasImage: true }, { zone: 'nt-home-mag__latest', title: 'B', hasImage: false }, { zone: '', title: 'C', hasImage: false }], 390);
  assert.match(text, /^cards @390: \{"nt-home-mag__latest":\{"cards":2,"withImage":1\},"other":\{"cards":1,"withImage":0\}\}/);
  assert.match(text, / 2 @390 NO-IMG nt-home-mag__latest \| B/);
});
