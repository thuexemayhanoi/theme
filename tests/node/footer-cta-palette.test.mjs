import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const css = (name) => readFileSync(new URL('../../assets/css/' + name, import.meta.url), 'utf8');
const main = css('main.css');
const assist = css('assist.css');

test('footer contact actions use the same semantic color tokens as Quick Call', () => {
  for (const [name, expected] of [['call', '--nt-contact-call'], ['zalo', '--nt-contact-zalo'], ['maps', '--nt-contact-maps']]) {
    assert.match(main, new RegExp(expected.replaceAll('-', '\\-') + ':'));
    assert.match(assist, new RegExp('\\.nt-qc__link--' + name + ' \\{ background: var\\(' + expected));
  }
  for (const [className, variable] of [['call', 'call'], ['zalo', 'zalo'], ['ghost', 'maps']]) {
    assert.match(main, new RegExp('\\.nt-footer__contact \\.nt-btn--' + className + ' \\{[^}]*background: var\\(--nt-contact-' + variable + '\\)'));
  }
});

test('all footer contact pills match social button sizing, without changing global CTAs', () => {
  assert.match(main, /\\.nt-footer__social a \\{[^}]*min-height: 44px;[^}]*border-radius: 999px;/);
  assert.match(main, /\\.nt-footer__contact \\.nt-btn \\{[^}]*min-height: 44px;[^}]*border-radius: 999px;/s);
  assert.doesNotMatch(main, /\\.nt-btn--ghost \\{[^}]*--nt-contact-maps/);
});
