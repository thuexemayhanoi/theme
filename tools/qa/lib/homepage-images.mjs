/**
 * Homepage image audit used by tools/qa/homepage-live.mjs: what the browser reports about every image in <main>,
 * and the pure rules that judge it. Only rules that hold for the published markup are failures; observations that
 * depend on the network (LCP time) or on content choices (duplicates) are warnings. Covered by
 * tests/node/homepage-images.test.mjs.
 */

/**
 * Markup zones of the WordPress media that template-parts/home/editorial.php prints (PR #54): one hero image
 * (attachment 4990) and two service visuals (4987, 4978). wp_get_attachment_image() adds no wp-image-ID class, so
 * the images are recognised by the theme's own wrapper classes, not by attachment id.
 */
export const EDITORIAL_ZONES = Object.freeze({ hero: 'nt-home-mag__hero-visual', service: 'nt-home-mag__visual-card' });

/** Runs in the page before any script: records layout shifts (and which elements moved) and the LCP element. */
export function observeVitals() {
  const state = { cls: 0, clsFromImages: 0, shifts: [], lcp: null };
  window.__ntVitals = state;
  try {
    new PerformanceObserver(list => {
      for (const entry of list.getEntries()) {
        if (entry.hadRecentInput) continue;
        state.cls += entry.value;
        const sources = (entry.sources || []).map(s => ({ tag: s.node ? s.node.nodeName : '', className: s.node && s.node.className ? String(s.node.className).slice(0, 60) : '' }));
        if (sources.some(s => s.tag === 'IMG')) state.clsFromImages += entry.value;
        if (state.shifts.length < 20) state.shifts.push({ value: Number(entry.value.toFixed(4)), at: Math.round(entry.startTime), sources });
      }
    }).observe({ type: 'layout-shift', buffered: true });
    new PerformanceObserver(list => {
      const entry = list.getEntries().at(-1);
      const el = entry.element;
      state.lcp = { time: Math.round(entry.startTime), tag: el ? el.nodeName : '', className: el && el.className ? String(el.className).slice(0, 60) : '', url: entry.url || '', lazy: Boolean(el && el.getAttribute && el.getAttribute('loading') === 'lazy') };
    }).observe({ type: 'largest-contentful-paint', buffered: true });
  } catch (error) {
    state.error = String(error);
  }
}

/**
 * Runs in the page: scrolls through the document so lazy images load, then describes every image in <main>.
 * Tops are measured before scrolling, so "above the fold" means the first screen at scroll position zero.
 */
export async function collectImages() {
  const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
  const list = [...document.querySelectorAll('main img')];
  const firstScreenTop = new Map(list.map(img => [img, Math.round(img.getBoundingClientRect().top + scrollY)]));
  const step = Math.max(200, Math.floor(innerHeight * 0.7));
  for (let y = 0; y < document.documentElement.scrollHeight; y += step) {
    scrollTo(0, y);
    await wait(150);
  }
  scrollTo(0, document.documentElement.scrollHeight);
  await wait(300);
  await Promise.all(list.map(img => Promise.race([img.decode().catch(() => {}), wait(5000)])));
  scrollTo(0, 0);
  await wait(300);
  const headingFor = img => {
    const holder = img.closest('section, article, figure') || img.parentElement;
    const label = holder && holder.getAttribute('aria-labelledby');
    const byLabel = label ? document.getElementById(label.split(' ')[0]) : null;
    const heading = byLabel || (holder && holder.querySelector('h1, h2, h3'));
    return heading ? heading.textContent.replace(/\s+/g, ' ').trim().slice(0, 80) : '';
  };
  return list.map(img => {
    const rect = img.getBoundingClientRect();
    const source = img.currentSrc || img.getAttribute('src') || '';
    const file = source.split('/').pop().split('?')[0];
    return {
      attachmentId: (img.className.match(/wp-image-(\d+)/) || [])[1] || '',
      file,
      baseFile: file.replace(/-\d+x\d+(?=\.[a-z0-9]+$)/i, '').replace(/-scaled(?=\.[a-z0-9]+$)/i, ''),
      isUpload: /\/wp-content\/uploads\//.test(source),
      alt: img.getAttribute('alt'),
      widthAttr: img.getAttribute('width'),
      heightAttr: img.getAttribute('height'),
      loading: img.getAttribute('loading') || '',
      decoding: img.getAttribute('decoding') || '',
      fetchpriority: img.getAttribute('fetchpriority') || '',
      srcset: Boolean(img.getAttribute('srcset')),
      sizes: img.getAttribute('sizes') || '',
      complete: img.complete,
      natural: [img.naturalWidth, img.naturalHeight],
      rendered: [Math.round(rect.width), Math.round(rect.height)],
      visible: rect.width > 0 && rect.height > 0,
      firstScreenTop: firstScreenTop.get(img),
      section: headingFor(img),
      zone: ((img.parentElement && img.parentElement.closest('[class*="nt-home-mag__"]')) || { className: '' }).className.toString().split(' ').find(c => c.startsWith('nt-home-mag__')) || '',
      inForm: Boolean(img.closest('form')),
    };
  });
}

const words = text => (text.toLowerCase().match(/[\p{L}\p{N}]{4,}/gu) || []);

/**
 * Judge the collected images.
 *
 * @param {object} input Input.
 * @param {ReturnType<typeof collectImages> extends Promise<infer T> ? T : never} input.images Images of <main>.
 * @param {{cls:number, clsFromImages:number, shifts:object[], lcp:object|null}|undefined} input.vitals Layout shifts and LCP.
 * @param {number} input.width Viewport width.
 * @param {number} input.height Viewport height.
 * @returns {{failures:string[], warnings:string[], summary:object}} Failures break the QA; warnings are evidence.
 */
export function assessImages({ images, vitals, width, height }) {
  const failures = [];
  const warnings = [];
  const shown = images.filter(i => i.visible && !i.inForm);
  const uploads = shown.filter(i => i.isUpload);
  const label = image => `${image.attachmentId ? 'wp-image-' + image.attachmentId + ' ' : ''}${image.file} (${image.zone || image.section || 'main'})`;
  for (const image of shown) {
    if (!image.complete || image.natural[0] === 0) failures.push(`${label(image)}: did not load`);
    if (image.rendered[0] > width + 1) failures.push(`${label(image)}: ${image.rendered[0]}px wide overflows the ${width}px viewport`);
  }
  for (const image of uploads) {
    if (!image.widthAttr || !image.heightAttr) failures.push(`${label(image)}: missing width/height attributes (layout shift)`);
    if (image.alt === null) failures.push(`${label(image)}: no alt attribute`);
    else {
      if (image.alt.length > 125) failures.push(`${label(image)}: alt text is ${image.alt.length} characters`);
      const counts = {};
      for (const word of words(image.alt)) counts[word] = (counts[word] || 0) + 1;
      if (Object.values(counts).some(n => n >= 4) || (image.alt.match(/,/g) || []).length > 4) failures.push(`${label(image)}: alt text looks keyword-stuffed`);
      if (image.alt.trim() === '') warnings.push(`${label(image)}: empty alt (treated as decorative)`);
    }
    const aboveFold = image.firstScreenTop < height;
    if (aboveFold && image.loading === 'lazy') failures.push(`${label(image)}: first-screen image is lazy loaded`);
    // What is below the first screen on a phone can be above it on a desktop, so an eager image there is a note, not a failure.
    if (!aboveFold && image.loading !== 'lazy' && image.fetchpriority !== 'high') warnings.push(`${label(image)}: below the first screen (top ${image.firstScreenTop}px) but not lazy loaded`);
  }
  const hero = shown.filter(i => i.zone === EDITORIAL_ZONES.hero);
  const cards = shown.filter(i => i.zone === EDITORIAL_ZONES.service);
  if (hero.length !== 1) failures.push(`hero visual: ${hero.length} images, expected 1`);
  if (cards.length !== 2) failures.push(`service visuals: ${cards.length} images, expected 2`);
  for (const [role, image] of [...hero.map(i => ['hero', i]), ...cards.map(i => ['service', i])]) {
    const ratio = image.rendered[1] ? image.rendered[0] / image.rendered[1] : 0;
    if (Math.abs(ratio - 16 / 9) > 0.03) failures.push(`${label(image)}: rendered ratio ${ratio.toFixed(3)} is not 16:9`);
    if (image.rendered[0] < 240) failures.push(`${label(image)}: rendered only ${image.rendered[0]}px wide`);
    if (!image.isUpload) failures.push(`${label(image)}: ${role} visual is not a WordPress upload`);
    if (role === 'hero' && (image.loading === 'lazy' || image.fetchpriority !== 'high')) failures.push(`${label(image)}: the hero must be eager with fetchpriority=high`);
    if (role === 'service' && image.loading !== 'lazy') failures.push(`${label(image)}: service visual must be lazy loaded`);
  }
  if (new Set(cards.map(i => i.baseFile)).size !== cards.length) failures.push('the two service visuals are the same file');
  if (hero.length === 1 && cards.some(i => i.baseFile === hero[0].baseFile)) failures.push('a service visual repeats the hero file');
  const byFile = {};
  for (const image of shown) byFile[image.baseFile] = (byFile[image.baseFile] || 0) + 1;
  const duplicates = Object.entries(byFile).filter(([, n]) => n > 1).map(([file, n]) => `${file} x${n}`);
  if (duplicates.length) warnings.push(`same image shown more than once: ${duplicates.join(', ')}`);
  if (vitals) {
    if (vitals.clsFromImages > 0) failures.push(`images moved the layout: ${vitals.clsFromImages.toFixed(4)} CLS from image elements`);
    if (vitals.cls > 0.1) warnings.push(`total CLS ${vitals.cls.toFixed(3)} is above the 0.1 "good" threshold: ${JSON.stringify(vitals.shifts.slice(0, 3))}`);
    if (vitals.lcp && vitals.lcp.lazy) failures.push('the LCP element is a lazy loaded image');
    if (vitals.lcp && vitals.lcp.time > 2500) warnings.push(`LCP ${vitals.lcp.time} ms on a GitHub runner (informational, network dependent)`);
  } else warnings.push('layout-shift and LCP were not observable in this browser');
  return {
    failures,
    warnings,
    summary: {
      imagesInMain: images.length,
      visible: shown.length,
      uploads: uploads.length,
      distinctUploads: new Set(uploads.map(i => i.baseFile)).size,
      cls: vitals ? Number(vitals.cls.toFixed(4)) : null,
      clsFromImages: vitals ? Number(vitals.clsFromImages.toFixed(4)) : null,
      lcp: vitals ? vitals.lcp : null,
    },
  };
}

/** One log line per image so a live run is a readable inventory. */
export function formatImageTable(images, width) {
  return images.map((i, n) => `${String(n + 1).padStart(2)} @${width} ${i.visible ? '' : '[hidden] '}${i.isUpload ? 'upload' : 'other'} ${i.file} | ${i.rendered.join('x')} (natural ${i.natural.join('x')}, attrs ${i.widthAttr}x${i.heightAttr}) | ${i.loading || 'auto'}${i.fetchpriority ? '+' + i.fetchpriority : ''} | top ${i.firstScreenTop} | alt=${JSON.stringify(i.alt)} | ${i.section || i.zone}`).join('\n');
}
