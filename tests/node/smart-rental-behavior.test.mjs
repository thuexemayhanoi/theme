import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { test } from 'node:test';
import assert from 'node:assert/strict';

const source = readFileSync(new URL('../../plugins/nguyentu-smart-core/assets/rental.js', import.meta.url), 'utf8');

function createCalculator() {
  const handlers = {};
  const option = { dataset: { dayRate: '150000' } };
  const vehicle = {
    selectedOptions: [option],
    addEventListener: (event, callback) => { handlers['vehicle:' + event] = callback; },
  };
  const days = {
    value: '1',
    addEventListener: (event, callback) => { handlers['days:' + event] = callback; },
  };
  const result = { textContent: '' };
  const fields = {
    '[data-ntsc-vehicle]': vehicle,
    '[data-ntsc-days]': days,
    '[data-ntsc-result]': result,
  };
  const panel = { querySelector: (selector) => fields[selector] };
  const document = {
    addEventListener: (event, callback) => {
      assert.equal(event, 'DOMContentLoaded');
      callback();
    },
    querySelectorAll: (selector) => selector === '.ntsc-rental' ? [panel] : [],
  };

  runInNewContext(source, { document, Intl, Number });
  return { option, days, result, handlers };
}

test('shows the verified daily reference price on initial load', () => {
  const { result } = createCalculator();
  assert.match(result.textContent, /150[.,]000đ/);
  assert.match(result.textContent, /chưa gồm cọc và phụ phí/);
});

test('recalculates after changing the number of days', () => {
  const c = createCalculator();
  c.days.value = '3';
  c.handlers['days:input']();
  assert.match(c.result.textContent, /450[.,]000đ/);
  assert.match(c.result.textContent, /3 ngày/);
});

test('never invents prices when the shop displays Contact', () => {
  const c = createCalculator();
  c.option.dataset.dayRate = '';
  c.handlers['vehicle:change']();
  assert.match(c.result.textContent, /liên hệ để nhận báo giá/i);
});

test('rejects negative, zero, non-integer and excessive day counts', () => {
  const c = createCalculator();
  for (const value of ['0', '-1', '1.5', '31', 'not-a-number']) {
    c.days.value = value;
    c.handlers['days:input']();
    assert.match(c.result.textContent, /1 đến 30 ngày/);
  }
});
