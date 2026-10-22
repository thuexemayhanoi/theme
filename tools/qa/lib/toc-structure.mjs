/**
 * Pure helpers of the read-only post contents-list check (tools/qa/toc-live.mjs): structure analysis of a rendered
 * single post, the checks for the layout "H1, meta, featured image, opening paragraph, first H2, contents list, text",
 * sampling and an independent oracle for tools/qa/toc-placement-cli.php. No network, no browser.
 */
import { load } from 'cheerio';

/** Markup of well-known contents-list plugins; found inside the text it would mean a second list is not ours. */
export const EMBEDDED_TOC = /\b(ez-toc-container|lwptoc|toc_container|wp-block-yoast-seo-table-of-contents|rank-math-toc|uagb-toc__wrap)\b/i;
export const PLACEMENTS = ['after-first-h2', 'after-intro', 'before-content'];

/** Deterministic, evenly spread sample of at most n items. */
export function sampleEvenly(items, n) {
  if (!Number.isFinite(n) || n <= 0 || n >= items.length) return [...items];
  return Array.from({ length: n }, (_, i) => items[Math.floor((i * items.length) / n + items.length / (2 * n))]);
}

/** All <loc> values of a sitemap, entity-decoded. */
export function parseSitemapLocs(xml) {
  return [...String(xml).matchAll(/<loc>\s*([^<\s]+)\s*<\/loc>/gi)].map((m) => m[1].replace(/&amp;/g, '&'));
}

const text = ($el) => $el.text().replace(/\s+/g, ' ').trim();

/**
 * `tag|text|id` of the post's own headings in document order: its H1 and every h2..h4 inside the text. Related posts, the
 * author box and the like have headings of their own that change from one render to the next and are not the post's.
 */
export function headingList($) {
  const describe = (el) => `${el.tagName.toLowerCase()}|${text($(el))}|${$(el).attr('id') ?? ''}`;
  const h1 = $('h1').first().get(0);
  return [...(h1 ? [h1] : []), ...$('.nt-prose').first().find('h2, h3, h4').toArray()].map(describe);
}

/**
 * Structure of one rendered single post.
 *
 * @param {string} html Full page HTML.
 */
export function analyzePost(html) {
  const $ = load(html);
  const all = $('*').toArray();
  const at = (el) => (el ? all.indexOf(el) : -1);
  const first = (sel) => $(sel).first().get(0);
  const prose = first('.nt-prose');
  const inline = first('.nt-toc--inline');
  const firstH2 = $('.nt-prose h2').toArray().find((el) => text($(el)) !== '');
  const h1 = first('h1');
  const meta = first('.nt-article__meta');
  const hero = first('.nt-article__hero');
  const intro = prose ? $(prose).children('p').first().get(0) : undefined;
  const before = (a, b) => a !== undefined && b !== undefined && at(a) >= 0 && at(b) >= 0 && at(a) < at(b);
  const prev = inline ? $(inline).prev().get(0) : undefined;
  const next = inline ? $(inline).next().get(0) : undefined;
  return {
    hasProse: !!prose,
    h1Count: $('h1').length,
    canonical: $('link[rel="canonical"]').attr('href') ?? '',
    headings: headingList($),
    toc: {
      total: $('.nt-toc').length,
      inlineCount: $('.nt-toc--inline').length,
      stickyCount: $('.nt-toc--sticky').length,
      inProse: !!inline && !!prose && $(inline).closest('.nt-prose').length > 0,
      parentIsProse: !!inline && !!prose && $(inline).parent().get(0) === prose,
      inFrontOfProse: !!inline && !!prose && $(inline).parent().is('.nt-article__main') && at(inline) < at(prose),
      placement: inline ? ($(inline).attr('data-nt-toc-placement') ?? null) : null,
      prevTag: prev ? prev.tagName.toLowerCase() : null,
      nextTag: next ? next.tagName.toLowerCase() : null,
      prevIsFirstH2: !!prev && prev === firstH2,
      inHeading: $('h1 .nt-toc, h2 .nt-toc, h3 .nt-toc, h4 .nt-toc').length > 0,
      headingInList: $('.nt-toc h1, .nt-toc h2, .nt-toc h3, .nt-toc h4, .nt-toc h5, .nt-toc h6').length > 0,
    },
    order: {
      h1BeforeMeta: before(h1, meta),
      metaBeforeHero: hero === undefined || before(meta, hero),
      heroBeforeText: hero === undefined || (!!prose && before(hero, prose)),
      introBeforeList: before(intro, inline),
      firstH2BeforeList: before(firstH2, inline),
    },
    embeddedTocMarkers: prose ? (EMBEDDED_TOC.test($.html(prose)) ? 1 : 0) : 0,
  };
}

/** The old layout: the inline list in front of the text, no placement attribute. */
export function isOldLayout(a) {
  return a.toc.inlineCount === 1 && a.toc.placement === null && a.toc.inFrontOfProse;
}

/** A post without a list (fewer than three headings). */
export function hasNoList(a) {
  return a.toc.total === 0;
}

/**
 * Failures of the new layout for one post (empty array = fine).
 *
 * @param {ReturnType<typeof analyzePost>} a Analysis.
 */
export function checkNewLayout(a) {
  const f = [];
  if (!a.hasProse) f.push('no .nt-prose text container');
  if (a.h1Count !== 1) f.push(`H1 count ${a.h1Count}`);
  if (!a.order.h1BeforeMeta || !a.order.metaBeforeHero || !a.order.heroBeforeText) f.push('H1, meta line, featured image, text are not in that order');
  if (hasNoList(a)) return f;
  if (a.toc.inlineCount !== 1 || a.toc.stickyCount !== 1 || a.toc.total !== 2) f.push(`list variants: inline ${a.toc.inlineCount}, sticky ${a.toc.stickyCount}, total ${a.toc.total} (expected 1, 1, 2)`);
  if (!PLACEMENTS.includes(a.toc.placement)) f.push(`placement attribute ${a.toc.placement}`);
  if (a.toc.placement === 'after-first-h2' && !(a.toc.inProse && a.toc.prevIsFirstH2 && a.order.firstH2BeforeList)) f.push('after-first-h2: the list is not the element right after the first H2');
  if (a.toc.placement === 'after-intro' && !(a.toc.parentIsProse && a.toc.prevTag)) f.push('after-intro: the list is not a direct child of the text after a block');
  if (a.toc.placement === 'before-content' && !a.toc.inFrontOfProse) f.push('before-content: the list is not in front of the text');
  if (a.toc.inHeading || a.toc.headingInList) f.push('a list inside a heading or a heading inside the list');
  return f;
}

/**
 * Raw inner HTML of the first <div class="nt-prose ..."> by counting <div> tags, or null when the markup is unbalanced.
 *
 * @param {string} html Full page HTML.
 */
export function rawProse(html) {
  const start = /<div\b[^>]*class="[^"]*\bnt-prose\b[^"]*"[^>]*>/i.exec(html);
  if (!start) return null;
  const from = start.index + start[0].length;
  const re = /<(\/?)div\b[^>]*>/gi;
  re.lastIndex = from;
  let depth = 1;
  for (let m = re.exec(html); m; m = re.exec(html)) {
    depth += m[1] ? -1 : 1;
    if (depth === 0) return html.slice(from, m.index);
  }
  return null;
}

/**
 * Check the PHP placement of one body independently, with an HTML5 parser (what a browser would build).
 *
 * @param {string} body      Inner HTML of the text, the list removed.
 * @param {string} result    The same body with a probe element inserted by nt_html_insert_at().
 * @param {string} placement Label returned by nt_html_toc_placement().
 * @param {string} probeId   Id of the probe element.
 * @returns {string[]} Problems (empty array = the placement is right and nothing else changed).
 */
export function oracleCheck(body, result, placement, probeId = 'nt-toc-probe') {
  const problems = [];
  const probeTag = `<details id="${probeId}"></details>`;
  if (placement !== 'before-content' && result.replace(probeTag, '') !== body) problems.push('removing the probe does not give back the original bytes');
  if (placement === 'before-content' && result !== body) problems.push('before-content changed the body');
  const doc = (h) => load(`<div id="root">${h}</div>`, null, false);
  const $body = doc(body);
  const $res = doc(result);
  const list = ($) => $('#root').find('h1, h2, h3, h4, h5, h6').toArray().map((el) => `${el.tagName}|${text($(el))}|${$(el).attr('id') ?? ''}`);
  if (JSON.stringify(list($body)) !== JSON.stringify(list($res))) problems.push('the headings changed');
  const root = $res('#root').get(0);
  const probe = $res(`#${probeId}`);
  if (placement === 'before-content') return problems;
  if (probe.length !== 1) return [...problems, `${probe.length} probes`];
  if (probe.parent().get(0) !== root) return [...problems, 'the probe is not a direct child of the text'];
  if (placement === 'after-first-h2') {
    const firstH2 = $res('#root h2').toArray().find((el) => text($res(el)) !== '');
    if (probe.prev().get(0) !== firstH2) problems.push('the probe is not right after the first non-empty H2');
    if (!firstH2 || $res(firstH2).parent().get(0) !== root) problems.push('the first H2 is not a direct child of the text');
  } else if (placement === 'after-intro') {
    const heading = $res('#root h2, #root h3').toArray()[0];
    const anchor = heading ? $res(heading).parents().toArray().concat([heading]).find((el) => $res(el).parent().get(0) === root) : undefined;
    if (!anchor || probe.next().get(0) !== anchor) problems.push('the probe is not right before the block that holds the first section');
    if (!probe.prev().length) problems.push('after-intro without a block before it');
    const firstH2 = $res('#root h2').toArray().find((el) => text($res(el)) !== '');
    if (firstH2 && $res(firstH2).parent().get(0) === root) problems.push('a usable top-level H2 exists but was not used');
  }
  return problems;
}

/** WCAG contrast ratio of two rgb()/rgba() strings. */
export function contrastRatio(a, b) {
  const rgb = (s) => (s.match(/[\d.]+/g) ?? []).slice(0, 3).map(Number);
  const lum = ([r, g, b]) => {
    const f = (v) => {
      const c = v / 255;
      return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };
    return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
  };
  const [hi, lo] = [lum(rgb(a)), lum(rgb(b))].sort((x, y) => y - x);
  return (hi + 0.05) / (lo + 0.05);
}
