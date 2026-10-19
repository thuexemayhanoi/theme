import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const css = (name) => readFileSync(new URL('../../assets/css/' + name, import.meta.url), 'utf8');
const main = css('main.css');
const assist = css('assist.css');

function block(source, selector) {
  const start = source.indexOf(selector + ' {');
  assert.notEqual(start, -1, 'Missing selector: ' + selector);
  return source.slice(start + selector.length + 2).split('}')[0];
}

test('footer contact actions use the same semantic color tokens as Quick Call', () => {
  for (const name of ['call', 'zalo', 'maps']) {
    assert.ok(main.includes('--nt-contact-' + name + ':'), name + ' token exists');
    assert.ok(assist.includes('.nt-qc__link--' + name + ' { background: var(--nt-contact-' + name), name + ' quick call uses token');
  }
  for (const [variant, color] of [['call', 'call'], ['zalo', 'zalo'], ['ghost', 'maps']]) {
    const style = block(main, '.nt-footer__contact .nt-btn--' + variant);
    assert.ok(style.includes('background: var(--nt-contact-' + color + ')'));
    assert.ok(style.includes('color: #fff'));
  }
});

test('footer pills match social buttons without changing global CTA variants', () => {
  for (const selector of ['.nt-footer__social a', '.nt-footer__contact .nt-btn']) {
    const rule = block(main, selector);
    assert.ok(rule.includes('min-height: 44px'));
    assert.ok(rule.includes('border-radius: 999px'));
  }
  assert.ok(block(main, '.nt-btn--ghost').includes('var(--nt-navy)'), 'Original global ghost button remains');
});
