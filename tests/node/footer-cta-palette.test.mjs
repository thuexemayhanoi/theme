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

test('four social and three footer CTAs have same outline and transparent background', () => {
  for (const selector of ['.nt-footer__social a', '.nt-footer__contact .nt-btn']) {
    const rule = block(main, selector);
    for (const declaration of ['min-height: 44px', 'padding: 9px 12px', 'border: 1px solid rgb(255 255 255 / 38%)', 'border-radius: 999px', 'font-size: .8125rem', 'font-weight: 700']) {
      assert.ok(rule.includes(declaration), selector + ' needs ' + declaration);
    }
  }
  const actions = block(main, '.nt-footer__contact .nt-btn');
  assert.ok(actions.includes('background: transparent'));
  const variants = block(main, '.nt-footer__contact .nt-btn:is(.nt-btn--call, .nt-btn--zalo, .nt-btn--ghost)');
  assert.ok(variants.includes('background: transparent'));
  assert.ok(variants.includes('border-color: rgb(255 255 255 / 38%)'));
});

test('native Quick Call colors are unchanged, and global button colors are untouched', () => {
  for (const name of ['call', 'zalo', 'maps']) {
    assert.ok(main.includes('--nt-contact-' + name + ':'), name + ' token exists');
    assert.ok(assist.includes('.nt-qc__link--' + name + ' { background: var(--nt-contact-' + name), name + ' Quick Call retains token');
  }
  assert.ok(block(main, '.nt-btn--call').includes('var(--nt-accent)'));
  assert.ok(block(main, '.nt-btn--zalo').includes('var(--nt-zalo)'));
  assert.ok(block(main, '.nt-btn--ghost').includes('var(--nt-navy)'));
});
