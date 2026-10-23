import { test } from 'node:test';
import assert from 'node:assert/strict';
import { CHIPS, MONEY, NAP, buildCases, hasAmount, judge, parsePriceRows, safeAction } from '../../tools/qa/lib/assist-cases.mjs';

const ORIGIN = 'https://thuexemaynguyentu.com';
const table = (rows) => `<html><body><table class="nt-table nt-table--prices"><thead><tr><th>Loại xe</th></tr></thead><tbody>${rows
  .map((r) => `<tr><th scope="row" data-label="Loại xe">${r[0]}</th>${r.slice(1).map((c) => `<td>${c}</td>`).join('')}</tr>`)
  .join('')}</tbody></table></body></html>`;
const ROWS = [
  ['Honda Wave', '150.000đ', '700.000đ', '900.000–1.200.000đ', '2–3 triệu'],
  ['Honda Vision', '200.000đ', '1.000.000đ', '1.800.000–2.000.000đ', '4–5 triệu'],
  ['Honda Air Blade', '200.000đ', '1.000.000đ', '1.500.000đ', '3 triệu'],
  ['Xe tay ga 50cc', '200.000đ', 'Báo giá riêng', 'Báo giá riêng', '4 triệu'],
  ['Xe máy điện / Xe đạp điện', 'Liên hệ', 'Liên hệ', 'Liên hệ', 'Xác nhận theo mẫu xe'],
];
const rows = () => parsePriceRows(table(ROWS));

test('the six chips are the four old ones with their old questions, then contact and address', () => {
  assert.deepEqual(CHIPS.map((c) => c.label), ['Bảng giá', 'Đặt cọc', 'Thủ tục', 'Xe điện', 'Liên hệ', 'Địa chỉ']);
  assert.deepEqual(CHIPS.slice(0, 4).map((c) => c.question), ['Giá thuê Honda Wave', 'Tiền đặt cọc', 'Thủ tục thuê xe', 'Xe máy điện']);
  assert.equal(CHIPS[4].question, 'Số điện thoại và Zalo liên hệ cửa hàng?');
  assert.equal(CHIPS[5].question, 'Cửa hàng Nguyễn Tú ở đâu? Chỉ đường?');
});

test('the price table of the served home page is read row by row', () => {
  const parsed = rows();
  assert.equal(parsed.length, 5);
  assert.deepEqual(parsed[1], { name: 'Honda Vision', day: '200.000đ', week: '1.000.000đ', month: '1.800.000–2.000.000đ', deposit: '4–5 triệu' });
  assert.deepEqual(parsePriceRows('<html><body><p>no table</p></body></html>'), []);
  assert.equal(hasAmount('Báo giá riêng'), false);
  assert.equal(hasAmount('4 triệu'), true);
});

test('only the shop phone, Zalo chat and a Maps link count as safe buttons', () => {
  assert.equal(safeAction({ type: 'call', url: NAP.tel }), true);
  assert.equal(safeAction({ type: 'zalo', url: NAP.zalo }), true);
  assert.equal(safeAction({ type: 'maps', url: NAP.maps }), true);
  assert.equal(safeAction({ type: 'maps', url: NAP.quickCallMaps }), true);
  for (const bad of [
    { type: 'call', url: 'tel:abc' }, { type: 'call', url: 'javascript:alert(1)' }, { type: 'call', url: 'tel:0942467674;ext=1' },
    { type: 'zalo', url: 'https://zalo.me.evil.example/x' }, { type: 'zalo', url: 'http://zalo.me/0942467674' }, { type: 'zalo', url: 'https://u:p@zalo.me/x' },
    { type: 'maps', url: 'https://maps.google.com.evil.example/' }, { type: 'maps', url: 'data:text/html,x' },
    { type: 'exec', url: 'tel:0942467674' }, null, 'string', { type: 'call' },
  ]) assert.equal(safeAction(bad), false, JSON.stringify(bad));
});

test('judge passes a correct contact answer and names each defect of a wrong one', () => {
  const good = { intent: 'contact', answer: 'Liên hệ Nguyễn Tú qua điện thoại hoặc Zalo 0942 467 674.', actions: [{ type: 'call', url: NAP.tel }, { type: 'zalo', url: NAP.zalo }], sources: [{ title: 'Liên hệ', url: `${ORIGIN}/lien-he/` }] };
  const expect = { intent: 'contact', include: [NAP.phone], exclude: ['Honda'], actions: ['call', 'zalo'], tel: NAP.tel, zalo: NAP.zalo, sources: { min: 1, max: 1 } };
  assert.deepEqual(judge(good, expect, ORIGIN), []);

  const bad = { intent: 'search', answer: 'Honda Wave 150.000đ', actions: [{ type: 'call', url: 'tel:123' }], sources: [{ title: 'x', url: 'https://evil.example/' }, { title: '', url: `${ORIGIN}/a/` }] };
  const fails = judge(bad, expect, ORIGIN).join(' | ');
  for (const part of ['intent "search"', 'lacks "0942 467 674"', 'must not contain "Honda"', 'buttons [call]', 'unsafe button', 'call button is not the shop phone', 'source off site', 'source without title']) {
    assert.ok(fails.includes(part), `expected a failure about: ${part}\n${fails}`);
  }
});

test('judge catches a price quoted for a model that has none, and unlabelled reading suggestions', () => {
  assert.deepEqual(judge({ intent: 'price', answer: 'Giá thuê Xe máy điện cần liên hệ báo giá.', actions: [] }, { noMoney: true }), []);
  assert.match(judge({ intent: 'price', answer: 'Giá thuê: 150.000đ', actions: [] }, { noMoney: true }).join(), /price the table does not hold/);
  assert.ok(MONEY.test('2 triệu') && MONEY.test('1.000.000đ') && !MONEY.test('xe 50cc'));
  const sources = [{ title: 'Pin xe điện', url: `${ORIGIN}/pin/` }];
  assert.match(judge({ intent: 'search', answer: 'Các bài sau có thể liên quan.', sources, actions: [] }, { reading: true }, ORIGIN).join(), /not labelled/);
  assert.deepEqual(judge({ intent: 'search', answer: 'Các bài công khai dưới đây có thể liên quan (gợi ý đọc, không phải câu trả lời chính thức).', sources, actions: [] }, { reading: true }, ORIGIN), []);
  assert.match(judge({ intent: 'search', answer: 'x', sources: Array.from({ length: 4 }, (_, i) => ({ title: `t${i}`, url: `${ORIGIN}/${i}/` })), actions: [] }, {}, ORIGIN).join(), /4 sources \(maximum 3\)/);
  assert.match(judge({ answer: '' }, {}).join(), /empty answer/);
});

test('judge compares the remembered vehicle and period of a follow-up', () => {
  const turn = { intent: 'price', answer: 'Honda Vision theo tháng', context: { model: 'Honda Vision', period: 'month' }, actions: [] };
  assert.deepEqual(judge(turn, { context: { model: 'Honda Vision', period: 'month' } }), []);
  assert.match(judge(turn, { context: { period: '' } }).join(), /context\.period is "month" instead of ""/);
});

test('the case list covers Issue #62 and builds its expectations from the price table', () => {
  const cases = buildCases(rows());
  assert.ok(cases.length >= 25, `${cases.length} cases`);
  assert.equal(cases.filter((c) => c.skip).length, 0);
  const ids = cases.map((c) => c.id);
  assert.equal(new Set(ids).size, ids.length, 'case ids are unique');
  const chipCases = cases.filter((c) => c.group === 'chip');
  assert.deepEqual(chipCases.map((c) => c.turns[0].q), CHIPS.map((c) => c.question), 'the chip cases ask exactly what the chips send');
  const flow = cases.find((c) => c.id === 'flow-vision-month-deposit');
  assert.deepEqual(flow.turns.map((t) => t.q), ['Giá Vision?', 'Tháng thì sao?', 'Cọc bao nhiêu?']);
  assert.deepEqual(flow.turns[1].expect.include, ['1.800.000–2.000.000đ']);
  assert.deepEqual(flow.turns[1].expect.exclude, ['1.000.000đ'], 'the week price must not appear in a month answer');
  assert.deepEqual(flow.turns[2].expect.include, ['4–5 triệu']);
  assert.equal(cases.find((c) => c.id === 'electric-price').turns[0].expect.noMoney, true);
  assert.deepEqual(cases.find((c) => c.id === 'chip-dia-chi').turns[0].expect.mapsUrl, NAP.maps);
  for (const group of ['contact', 'address', 'colloquial', 'context', 'quote', 'legal', 'false-positive', 'search']) assert.ok(cases.some((c) => c.group === group), `group ${group}`);
});

test('a case that needs a price row the site does not serve is reported as not tested, never dropped', () => {
  const cases = buildCases(parsePriceRows(table([ROWS[0]])));
  const skipped = cases.filter((c) => c.skip).map((c) => c.id);
  for (const id of ['flow-vision-month-deposit', 'flow-switch-vehicle', 'flow-address-between', 'combined-price-deposit', 'electric-price', 'fifty-week']) assert.ok(skipped.includes(id), id);
  assert.ok(!skipped.includes('chip-bang-gia'), 'Honda Wave is in this table');
  assert.equal(buildCases([]).length, cases.length, 'the same cases exist with an empty table');
});

test('a price the owner changed in the admin changes the expectation, not the verdict', () => {
  const edited = ROWS.map((r) => (r[0] === 'Honda Vision' ? ['Honda Vision', '250.000đ', '1.100.000đ', '1.900.000đ', '5 triệu'] : r));
  const flow = buildCases(parsePriceRows(table(edited))).find((c) => c.id === 'flow-vision-month-deposit');
  assert.deepEqual(flow.turns[0].expect.include, ['250.000đ', '1.100.000đ', '1.900.000đ']);
  assert.deepEqual(flow.turns[2].expect.include, ['5 triệu']);
  const good = { intent: 'deposit', answer: 'Tiền cọc tham khảo Honda Vision: 5 triệu.', context: { model: 'Honda Vision', period: 'month' }, actions: [] };
  assert.deepEqual(judge(good, flow.turns[2].expect), []);
});

test('a 50cc week price that the owner publishes is expected as a number, not as a quote', () => {
  const priced = ROWS.map((r) => (r[0] === 'Xe tay ga 50cc' ? ['Xe tay ga 50cc', '200.000đ', '1.100.000đ', '3.500.000đ', '4 triệu'] : r));
  const fifty = buildCases(parsePriceRows(table(priced))).find((c) => c.id === 'fifty-week');
  assert.ok(fifty.skip, 'the quote-only expectation no longer applies');
});
