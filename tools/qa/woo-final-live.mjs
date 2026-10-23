/**
 * Read-only production WooCommerce QA. No cart, forms, admin or state-changing requests.
 * Uses Chromium viewport emulation, never claims to be native Safari.
 */
import { mkdirSync, writeFileSync } from 'node:fs';
import { chromium } from '@playwright/test';
import { load } from 'cheerio';

const site = 'https://thuexemaynguyentu.com';
const output = 'reports/woo-final-live';
const mode = process.env.GITHUB_EVENT_NAME === 'push' ? 'deployed' : 'baseline';
const products = [
  'xe-so-honda-blade', 'yamaha-mio-classico', 'honda-click', 'honda-air-blade',
  'xe-ga-50cc', 'yamaha-mio-ultimo', 'honda-cub', 'yamaha-sirius',
  'bang-gia-thue-xe-dien', 'xe-dap-dien', 'xe-may-dien', 'honda-vision', 'honda-wave'
];
const routes = ['/shop/', '/danh-muc-san-pham/loai-xe/xe-dien/', '/san-pham/honda-vision/', '/san-pham/honda-wave/'];
const viewports = [{ width:390,height:844 }, { width:768,height:1024 }, { width:1440,height:900 }];
const report = { startedAt:new Date().toISOString(), mode, commit:process.env.GITHUB_SHA || null, products:[], visits:[], errors:[] };
mkdirSync(output, { recursive:true });
const browser = await chromium.launch();
try {
  const ctx = await browser.newContext();
  for (const slug of products) {
    try {
      const response = await ctx.request.get(site + '/san-pham/' + slug + '/', { timeout:30000 });
      const $ = load(await response.text());
      const product = {
        slug, status:response.status(),
        headings:$('main h1').map((_, el) => $(el).text().trim()).get(),
        hasImage:$('main .woocommerce-product-gallery img').length > 0,
        hasPhone:$('main .nt-wc-contact a[href^="tel:"]').length > 0,
        hasZalo:$('main .nt-wc-contact a[href^="https://zalo.me/"]').length > 0,
        canonical:$('link[rel="canonical"]').attr('href') || '',
        hasCartButton:$('main button.single_add_to_cart_button').length > 0
      };
      report.products.push(product);
      if (product.status !== 200 || product.headings.length !== 1 || !product.hasImage || !product.hasPhone || !product.hasZalo) report.errors.push('Product check failed: ' + slug);
    } catch (error) {
      report.products.push({ slug, error:String(error) });
      report.errors.push('Product request failed: ' + slug);
    }
  }
  await ctx.close();
  for (const size of viewports) {
    for (const theme of ['light', 'dark']) {
      const context = await browser.newContext({ viewport:size, colorScheme:theme, locale:'vi-VN' });
      await context.addInitScript(value => {
        localStorage.setItem('nt-color-mode', value);
        window.__ntWooLayoutShifts = [];
        try {
          new PerformanceObserver(list => {
            for (const entry of list.getEntries()) {
              if (entry.hadRecentInput) continue;
              window.__ntWooLayoutShifts.push({
                value:entry.value, startTime:entry.startTime,
                sources:(entry.sources || []).slice(0, 4).map(source => ({
                  node:source.node ? (source.node.tagName + '.' + String(source.node.className).replace(/\s+/g,'.').slice(0,80)) : '',
                  previous:source.previousRect ? { x:source.previousRect.x, y:source.previousRect.y } : null,
                  current:source.currentRect ? { x:source.currentRect.x, y:source.currentRect.y } : null
                }))
              });
            }
          }).observe({ type:'layout-shift', buffered:true });
        } catch { /* report captures unavailable metrics as zero */ }
      }, theme);
      const page = await context.newPage();
      for (const route of routes) {
        const entry = { route, width:size.width, theme, consoleErrors:[] };
        const onConsole = message => {
          if (message.type() === 'error') entry.consoleErrors.push({ text:message.text().slice(0,250), location:message.location().url });
        };
        const onError = error => entry.consoleErrors.push({ text:'pageerror: ' + String(error).slice(0,250) });
        page.on('console', onConsole);
        page.on('pageerror', onError);
        try {
          const res = await page.goto(site + route, { waitUntil:'domcontentloaded', timeout:45000 });
          entry.http = res.status();
          await page.waitForLoadState('networkidle', { timeout:9000 }).catch(() => {});
          await page.waitForTimeout(650);
          const state = await page.evaluate(() => {
            const shifts = window.__ntWooLayoutShifts || [];
            let session = 0, maximum = 0, first = -10000, previous = -10000;
            for (const shift of shifts) {
              if (shift.startTime - previous > 1000 || shift.startTime - first > 5000) {
                session = 0; first = shift.startTime;
              }
              session += shift.value; maximum = Math.max(maximum, session); previous = shift.startTime;
            }
            const buttons = [...document.querySelectorAll('main ul.products li.product a.button')].map(a => {
              const visible = (a.textContent || '').trim().replace(/\s+/g,' ');
              const aria = a.getAttribute('aria-label') || visible;
              return { visible, aria, matches:aria.toLocaleLowerCase('vi-VN').includes(visible.toLocaleLowerCase('vi-VN')) };
            });
            const gallery = document.querySelector('main .woocommerce-product-gallery img');
            return {
              headings:[...document.querySelectorAll('main h1')].map(x => x.textContent.trim()),
              productCount:document.querySelectorAll('main ul.products > li.product').length,
              buttons, cls:Number(maximum.toFixed(4)), shifts:shifts.slice(0,15),
              overflow:document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
              activeTheme:document.documentElement.getAttribute('data-nt-color-mode'),
              gallery:gallery ? { loaded:gallery.complete && gallery.naturalWidth > 0, src:gallery.currentSrc.slice(0,180) } : null,
              smallTargets:[...document.querySelectorAll('main a,main button,main select,main input')].filter(el => {
                const box=el.getBoundingClientRect();
                return box.width > 0 && box.height > 0 && (box.width < 24 || box.height < 24);
              }).slice(0,15).map(el => {
                const box=el.getBoundingClientRect();
                return { tag:el.tagName, label:(el.textContent || el.getAttribute('aria-label') || '').trim().slice(0,60), size:[Math.round(box.width),Math.round(box.height)], className:String(el.className).slice(0,60) };
              })
            };
          });
          Object.assign(entry,state);
          if (entry.http !== 200 || entry.headings.length !== 1 || entry.activeTheme !== theme || entry.overflow) {
            report.errors.push('Render invariant: ' + route + '/' + size.width + '/' + theme);
          }
          if (route === '/shop/' && entry.productCount !== 13) report.errors.push('Shop product count: ' + entry.productCount);
          if (size.width === 390 && theme === 'light' && route === '/shop/') await page.screenshot({ path:output+'/shop-mobile.png', fullPage:true });
        } catch (error) {
          entry.error = String(error);
          report.errors.push('Render request: ' + route + '/' + size.width + '/' + theme + ': ' + error);
        }
        report.visits.push(entry);
        page.off('console',onConsole);
        page.off('pageerror',onError);
      }
      await context.close();
    }
  }
} finally {
  await browser.close();
}
const mismatches = report.visits.flatMap(v => (v.buttons || []).filter(b => !b.matches).map(b => ({ route:v.route, width:v.width, theme:v.theme, text:b.visible, aria:b.aria })));
const shiftSummary = report.visits.filter(v => v.cls > 0.1).map(v => ({ route:v.route, width:v.width, theme:v.theme, cls:v.cls, sources:v.shifts }));
report.summary = {
  productsChecked:report.products.length, visits:report.visits.length,
  labelMismatches:mismatches.length, mismatchExamples:mismatches.slice(0,15),
  notableShifts:shiftSummary, errors:report.errors.length,
  consoleErrors:report.visits.flatMap(v => v.consoleErrors || []).slice(0,25)
};
writeFileSync(output+'/report.json',JSON.stringify(report,null,2));
writeFileSync(output+'/summary.md',[
  '# WooCommerce final live audit', '',
  'Mode: '+mode+' | SHA: '+(report.commit || 'unknown'),
  'Read-only requests; Chromium viewport emulation (real Safari NOT_TESTED).',
  'Products: '+report.products.length+'/13. Visits: '+report.visits.length+'.',
  'Accessible-name mismatches: '+mismatches.length+'. Notable CLS >0.1: '+shiftSummary.length+'.',
  'Invariant errors: '+report.errors.length+'.', '',
  '## Layout shifts', ...shiftSummary.map(x => '- '+x.route+' '+x.width+'/'+x.theme+': '+x.cls+' (source: report.json)'),
  '## Accessible label examples', ...mismatches.slice(0,10).map(x => '- '+x.route+': '+x.text+' / '+x.aria),
  '## Errors', ...report.errors.map(x => '- '+x)
].join('\n'));
console.log(JSON.stringify(report.summary,null,2));
if (report.errors.length) process.exitCode = 1;
// Before merge the live site still runs old code. Require fixed label after deploy only.
if (mode === 'deployed' && mismatches.length) {
  console.error('MERGED_NOT_LIVE_VERIFIED: old accessible names still served');
  process.exitCode = 1;
}
