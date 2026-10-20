import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { test } from 'node:test';
import assert from 'node:assert/strict';

const source = readFileSync(new URL('../../assets/js/rental.js', import.meta.url), 'utf8');

function createCalculator() {
  const handlers = {};
  const option = { dataset: { dayRate: '150000', weekPrice: '700.000đ', monthPrice: '900.000–1.200.000đ', deposit: '2–3 triệu' } };
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
    '[data-nt-rental-vehicle]': vehicle,
    '[data-nt-rental-days]': days,
    '[data-nt-rental-result]': result,
  };
  const panel = { querySelector: (selector) => fields[selector] };
  const document = {
    addEventListener: (event, callback) => {
      assert.equal(event, 'DOMContentLoaded');
      callback();
    },
    querySelectorAll: (selector) => selector === '[data-nt-native-rental]' ? [panel] : [],
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

test('7 days uses the published weekly rate instead of 7 daily rates', () => {
  const c = createCalculator();
  c.days.value = '7';
  c.handlers['days:input']();
  assert.match(c.result.textContent, /700[.,]000đ/);
  assert.doesNotMatch(c.result.textContent, /1[.,]050[.,]000đ/);
});

test('30 days uses the published monthly range and separates deposit', () => {
  const c = createCalculator();
  c.days.value = '30';
  c.handlers['days:input']();
  assert.match(c.result.textContent, /900[.,]000–1[.,]200[.,]000đ/);
  assert.match(c.result.textContent, /Tiền cọc tham khảo: 2–3 triệu/);
  assert.doesNotMatch(c.result.textContent, /4[.,]500[.,]000đ/);
});

test('unpriced intermediate periods and package prices require confirmation', () => {
  const c = createCalculator();
  c.days.value = '10';
  c.handlers['days:input']();
  assert.match(c.result.textContent, /liên hệ để xác nhận giá theo gói/);
  c.days.value = '7';
  c.option.dataset.weekPrice = 'Báo giá riêng';
  c.handlers['days:input']();
  assert.match(c.result.textContent, /cần liên hệ/);
  c.days.value = '30';
  c.option.dataset.monthPrice = 'Liên hệ';
  c.handlers['days:input']();
  assert.match(c.result.textContent, /cần liên hệ/);
});

test('price confirmation is required for electric vehicle without day rate', () => {
  const c = createCalculator();
  c.option.dataset.dayRate = '';
  c.option.dataset.weekPrice = 'Liên hệ';
  c.option.dataset.monthPrice = 'Liên hệ';
  c.days.value = '3';
  c.handlers['days:input']();
  assert.match(c.result.textContent, /cần liên hệ để nhận báo giá/);
});

test('all five petrol vehicles use their own verified packages and deposits', () => {
  const rows = [
    ['150000', '700.000đ', '900.000–1.200.000đ', '2–3 triệu'],
    ['200000', '1.000.000đ', '1.800.000–2.000.000đ', '4–5 triệu'],
    ['200000', '1.000.000đ', '1.500.000đ', '3 triệu'],
    ['150000', '700.000đ', '900.000–1.200.000đ', '2 triệu'],
    ['200000', 'Báo giá riêng', 'Báo giá riêng', '4 triệu'],
  ];
  for (const [dayRate, weekPrice, monthPrice, deposit] of rows) {
    const c = createCalculator();
    Object.assign(c.option.dataset, { dayRate, weekPrice, monthPrice, deposit });
    for (const count of [1, 3, 7, 30]) {
      c.days.value = String(count);
      c.handlers['days:input']();
      if (count < 7) assert.ok(c.result.textContent.includes(new Intl.NumberFormat('vi-VN').format(Number(dayRate) * count) + 'đ'));
      else if (/\d/.test(count === 7 ? weekPrice : monthPrice)) assert.ok(c.result.textContent.includes(count === 7 ? weekPrice : monthPrice));
      else assert.match(c.result.textContent, /cần liên hệ/);
      assert.ok(c.result.textContent.includes(deposit));
      assert.match(c.result.textContent, /không cộng vào tiền thuê/);
    }
  }
});
