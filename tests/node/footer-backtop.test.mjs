import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import assert from 'node:assert/strict';

const source = (path) => readFileSync(new URL('../../' + path, import.meta.url), 'utf8');
const header = source('header.php');
const footer = source('footer.php');
const css = source('assets/css/main.css');

test('back to top is a native link, not duplicate floating Quick Call or JavaScript', () => {
  assert.ok(header.includes('<body id="nt-page-top" <?php body_class(); ?>>'));
  assert.ok(footer.includes('href="#nt-page-top"'));
  assert.ok(footer.includes("nt_the_icon( 'arrow-up'"));
  assert.ok(footer.includes('aria-label="Lên đầu trang"'));
  assert.ok(footer.includes('<span>Lên đầu trang</span>'));
  assert.doesNotMatch(footer, /Giá thuê và điều kiện đặt cọc được xác nhận/);
  assert.match(css, /\.nt-footer__backtop \{/);
  assert.ok(!css.slice(css.indexOf('.nt-footer__backtop {'), css.indexOf('.nt-footer__backtop .nt-icon')).includes('position: fixed'));
});
test('footer back-to-top has readable outlined touch target, mobile fallback and motion respect', () => {
  const section = css.slice(css.indexOf('.nt-footer__bottom {'), css.indexOf('@media (min-width: 640px)', css.indexOf('.nt-footer__bottom {')));
  assert.ok(section.includes('display: grid'));
  assert.ok(section.includes('min-width: 44px; min-height: 44px'));
  assert.ok(section.includes('border: 1px solid rgb(255 255 255 / 38%)'));
  assert.ok(section.includes('background: transparent; color: #fff'));
  assert.ok(section.includes('@media (max-width: 479.98px)'));
  assert.ok(section.includes('prefers-reduced-motion: reduce'));
});
