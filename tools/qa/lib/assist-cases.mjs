/**
 * What the assistant has to answer, as data: the six chips and the questions of Issue #62, each with the checks a good
 * answer passes. Shared by tools/qa/assist-live.mjs (read-only check of a running site) and its Node tests.
 *
 * Prices are read from the price table the home page serves, so a price the owner edits in the admin never makes a
 * correct answer fail; phone, address, hours and map are the verified NAP that the theme pins in its own tests.
 */
import * as cheerio from 'cheerio';

export const NAP = Object.freeze({
  phone: '0942 467 674',
  tel: 'tel:0942467674',
  zalo: 'https://zalo.me/0942467674',
  address: '112 Nguyễn Văn Cừ, Bồ Đề, Long Biên, Hà Nội',
  hours: '09:00–21:00',
  maps: 'https://maps.google.com/maps?cid=7448790013669858019',
  quickCallMaps: 'https://share.google/KHz2FVQKhtFilL01j',
});

/** The six quick questions, in the order they are shown. The first four are the old chips with their old questions. */
export const CHIPS = Object.freeze([
  { label: 'Bảng giá', question: 'Giá thuê Honda Wave', intent: 'price' },
  { label: 'Đặt cọc', question: 'Tiền đặt cọc', intent: 'deposit' },
  { label: 'Thủ tục', question: 'Thủ tục thuê xe', intent: 'documents' },
  { label: 'Xe điện', question: 'Xe máy điện', intent: 'vehicles' },
  { label: 'Liên hệ', question: 'Số điện thoại và Zalo liên hệ cửa hàng?', intent: 'contact' },
  { label: 'Địa chỉ', question: 'Cửa hàng Nguyễn Tú ở đâu? Chỉ đường?', intent: 'location' },
]);

/** A price in digits (150.000đ, 4 triệu): its presence in an answer about a quote-only model is a fabricated price. */
export const MONEY = /\d{2,3}\.\d{3}|\d\s*triệu/u;

const MAPS_HOSTS = /^(maps\.google\.com|(www\.)?google\.com|share\.google|maps\.app\.goo\.gl|goo\.gl)$/;

export const hasAmount = (cell) => /\d/.test(String(cell ?? ''));

/** Rows of the price table in the served home page: [{ name, day, week, month, deposit }]. */
export function parsePriceRows(html) {
  const $ = cheerio.load(html);
  const rows = [];
  $('table.nt-table--prices tbody tr').each((_, tr) => {
    const name = $(tr).find('th').first().text().trim();
    const cells = $(tr).find('td').map((__, td) => $(td).text().trim()).get();
    if (name && cells.length >= 4) rows.push({ name, day: cells[0], week: cells[1], month: cells[2], deposit: cells[3] });
  });
  return rows;
}

/** Whether a button URL is one the shop owns: its phone, its Zalo chat, or a Google Maps / share link. */
export function safeAction(action) {
  try {
    if (!action || typeof action !== 'object') return false;
    if (action.type === 'call') return /^tel:\+?\d{6,15}$/.test(String(action.url));
    const url = new URL(String(action.url));
    if (url.protocol !== 'https:' || url.username || url.password) return false;
    if (action.type === 'zalo') return url.hostname === 'zalo.me';
    if (action.type === 'maps') return MAPS_HOSTS.test(url.hostname);
    return false;
  } catch {
    return false;
  }
}

/**
 * Check one REST answer against what it should be. Returns the list of failed checks; empty means it passes.
 *
 * @param {object} turn   REST response: answer, intent, sources, actions, context.
 * @param {object} expect include / exclude texts, noMoney, intent, actions (exact types), mapsUrl, sources {min,max},
 *                        reading (suggestions must be labelled), context.
 * @param {string} origin Site origin; every source must be on it.
 */
export function judge(turn, expect = {}, origin = '') {
  const fails = [];
  const answer = String(turn?.answer ?? '');
  const actions = Array.isArray(turn?.actions) ? turn.actions : [];
  const sources = Array.isArray(turn?.sources) ? turn.sources : [];
  const types = actions.map((a) => a?.type);

  if (answer.trim() === '') fails.push('empty answer');
  if (expect.intent && turn?.intent !== expect.intent) fails.push(`intent "${turn?.intent}" instead of "${expect.intent}"`);
  for (const text of expect.include ?? []) if (!answer.includes(text)) fails.push(`answer lacks "${text}"`);
  for (const text of expect.exclude ?? []) if (answer.includes(text)) fails.push(`answer must not contain "${text}"`);
  if (expect.noMoney && MONEY.test(answer)) fails.push('answer quotes a price the table does not hold');
  if (expect.actions && JSON.stringify(types) !== JSON.stringify(expect.actions)) fails.push(`buttons [${types}] instead of [${expect.actions}]`);
  if (expect.noActions && types.length > 0) fails.push(`unexpected buttons [${types}]`);
  for (const action of actions) if (!safeAction(action)) fails.push(`unsafe button ${JSON.stringify(action)}`);
  if (expect.mapsUrl) {
    const maps = actions.find((a) => a?.type === 'maps');
    if (!maps || maps.url !== expect.mapsUrl) fails.push(`directions button is not ${expect.mapsUrl}`);
  }
  if (expect.tel && actions.find((a) => a?.type === 'call')?.url !== expect.tel) fails.push('call button is not the shop phone');
  if (expect.zalo && actions.find((a) => a?.type === 'zalo')?.url !== expect.zalo) fails.push('Zalo button is not the shop Zalo');

  if (sources.length > 3) fails.push(`${sources.length} sources (maximum 3)`);
  if (expect.sources) {
    if (sources.length < (expect.sources.min ?? 0)) fails.push(`${sources.length} sources (at least ${expect.sources.min})`);
    if (sources.length > (expect.sources.max ?? 3)) fails.push(`${sources.length} sources (at most ${expect.sources.max})`);
  }
  for (const source of sources) {
    try {
      const url = new URL(source.url);
      if (origin && url.origin !== origin) fails.push(`source off site: ${source.url}`);
      if (!source.title) fails.push(`source without title: ${source.url}`);
    } catch {
      fails.push(`source URL is not a URL: ${source?.url}`);
    }
  }
  if (expect.reading && sources.length > 0 && !/gợi ý đọc/i.test(answer)) fails.push('reading suggestions are not labelled as such');
  for (const [key, value] of Object.entries(expect.context ?? {})) {
    if ((turn?.context ?? {})[key] !== value) fails.push(`context.${key} is "${(turn?.context ?? {})[key]}" instead of "${value}"`);
  }
  return fails;
}

/**
 * The question list of Issue #62, with expectations drawn from the price table `rows` (parsePriceRows).
 * Each case is one conversation: turns are asked in order and the context of each answer is sent with the next question.
 * A case that needs a table row the site does not have is returned with `skip` set, never silently dropped.
 */
export function buildCases(rows) {
  const find = (name) => rows.find((r) => r.name.toLowerCase() === name.toLowerCase());
  const wave = find('Honda Wave');
  const vision = find('Honda Vision');
  const blade = find('Honda Air Blade');
  const electric = rows.find((r) => /điện/i.test(r.name));
  const fifty = rows.find((r) => /50cc/i.test(r.name));
  const need = (row, label) => (row ? null : `${label} is not in the price table served by the site`);
  const fact = { sources: { min: 1, max: 1 } };
  const shop = { actions: ['call', 'zalo'], tel: NAP.tel, zalo: NAP.zalo, include: [NAP.phone], exclude: ['Honda'], intent: 'contact', ...fact };
  const where = { actions: ['maps'], mapsUrl: NAP.maps, include: [NAP.address, NAP.hours], exclude: ['Honda'], intent: 'location', ...fact };
  const cases = [];
  const add = (id, group, turns, skip = null) => cases.push({ id, group, turns, ...(skip ? { skip } : {}) });

  // The six chips, as the browser sends them.
  add('chip-bang-gia', 'chip', [{ q: CHIPS[0].question, expect: { intent: 'price', include: [wave?.day ?? '', wave?.week ?? '', wave?.month ?? ''].filter(Boolean), exclude: ['Vision'], ...fact } }], need(wave, 'Honda Wave'));
  add('chip-dat-coc', 'chip', [{ q: CHIPS[1].question, expect: { intent: 'deposit', include: ['2.000.000đ đến 5.000.000đ'], exclude: ['Honda'], ...fact } }]);
  add('chip-thu-tuc', 'chip', [{ q: CHIPS[2].question, expect: { intent: 'documents', include: ['giấy tờ bảo đảm'], noActions: true, ...fact } }]);
  add('chip-xe-dien', 'chip', [{ q: CHIPS[3].question, expect: { intent: 'vehicles', include: ['xe điện'], noMoney: electric ? !hasAmount(electric.day + electric.week + electric.month + electric.deposit) : false, ...fact } }]);
  add('chip-lien-he', 'chip', [{ q: CHIPS[4].question, expect: shop }]);
  add('chip-dia-chi', 'chip', [{ q: CHIPS[5].question, expect: where }]);

  // Contact and address wording: Vietnamese with and without marks, abbreviations, English.
  for (const q of ['hotline', 'so dien thoai', 'sdt cửa hàng', 'gọi cho cửa hàng', 'đặt xe', 'how can I contact you']) add('contact-' + q.replace(/\W+/gu, '-'), 'contact', [{ q, expect: shop }]);
  for (const q of ['địa chỉ ở đâu', 'mấy giờ mở cửa', 'where is your shop', 'chỉ đường']) add('address-' + q.replace(/\W+/gu, '-'), 'address', [{ q, expect: where }]);

  // Typos, abbreviations and everyday wording; a "how much" about people, weight or distance is not a price question.
  add('colloquial-price', 'colloquial', [{ q: 'wave bao nhiu', expect: { intent: 'price', include: [wave?.day ?? '', wave?.week ?? '', wave?.month ?? ''].filter(Boolean), exclude: ['Vision'], ...fact } }], need(wave, 'Honda Wave'));
  add('colloquial-contact', 'colloquial', [{ q: 'lh shop thế nào', expect: shop }]);
  add('colloquial-address', 'colloquial', [{ q: 'cửa hàng nằm đâu', expect: where }]);
  add('colloquial-catalog', 'colloquial', [{ q: 'có xe để thuê không', expect: { intent: 'vehicles', include: ['xe điện'], noMoney: true, ...fact } }]);
  add('colloquial-not-price', 'colloquial', [{ q: 'xe chở được bao nhiêu người', expect: { intent: 'search', noMoney: true } }]);

  // Follow-up questions follow the vehicle and drop the period when the vehicle changes.
  add('flow-vision-month-deposit', 'context', [
    { q: 'Giá Vision?', expect: { intent: 'price', include: [vision?.day ?? '', vision?.week ?? '', vision?.month ?? ''].filter(Boolean), context: { model: vision?.name ?? '', period: '' } } },
    { q: 'Tháng thì sao?', expect: { intent: 'price', include: [vision?.month ?? ''], exclude: vision && vision.week !== vision.month ? [vision.week] : [], context: { model: vision?.name ?? '', period: 'month' } } },
    { q: 'Cọc bao nhiêu?', expect: { intent: 'deposit', include: [vision?.deposit ?? ''], exclude: ['Wave'], context: { model: vision?.name ?? '' } } },
  ], need(vision, 'Honda Vision'));
  add('flow-switch-vehicle', 'context', [
    { q: 'Giá Vision tháng', expect: { intent: 'price', include: [vision?.month ?? ''], context: { period: 'month' } } },
    { q: 'Wave thì sao?', expect: { intent: 'price', include: [wave?.day ?? '', wave?.week ?? '', wave?.month ?? ''].filter(Boolean), exclude: ['Vision'], context: { model: wave?.name ?? '', period: '' } } },
    { q: 'Air Blade?', expect: { intent: 'price', include: [blade?.day ?? '', blade?.deposit ?? ''].filter(Boolean), exclude: ['Wave'], context: { model: blade?.name ?? '', period: '' } } },
  ], need(vision && wave && blade, 'Vision, Wave and Air Blade'));
  add('flow-address-between', 'context', [
    { q: 'Giá Vision?', expect: { intent: 'price', context: { model: vision?.name ?? '' } } },
    { q: 'Địa chỉ ở đâu?', expect: { ...where, context: { model: vision?.name ?? '' } } },
    { q: 'Số điện thoại?', expect: { ...shop, context: { model: vision?.name ?? '' } } },
    { q: 'Tháng thì sao?', expect: { intent: 'price', include: [vision?.month ?? ''], context: { model: vision?.name ?? '', period: 'month' } } },
  ], need(vision, 'Honda Vision'));
  add('combined-price-deposit', 'context', [{ q: 'Giá Vision thuê tháng và cọc bao nhiêu?', expect: { intent: 'price', include: [vision?.month ?? '', vision?.deposit ?? ''].filter(Boolean), exclude: vision && vision.week !== vision.month ? [vision.week] : [] } }], need(vision, 'Honda Vision'));

  // Models without a published price are never given one; the visitor is sent to the shop.
  const electricQuote = electric && !hasAmount(electric.day + electric.week + electric.month);
  add('electric-price', 'quote', [{ q: 'xe điện giá bao nhiêu', expect: { intent: 'price', noMoney: Boolean(electricQuote), actions: ['call', 'zalo'] } }], need(electricQuote, 'a quote-only electric row'));
  add('electric-deposit', 'quote', [{ q: 'cọc xe điện bao nhiêu', expect: { intent: 'deposit', noMoney: Boolean(electric && !hasAmount(electric.deposit)), actions: ['call', 'zalo'] } }], need(electric && !hasAmount(electric.deposit), 'a quote-only electric deposit'));
  const fiftyWeekQuote = fifty && !hasAmount(fifty.week);
  add('fifty-week', 'quote', [{ q: 'xe 50cc thuê tuần giá bao nhiêu', expect: { intent: 'price', noMoney: Boolean(fiftyWeekQuote), actions: ['call', 'zalo'] } }], need(fiftyWeekQuote, 'a quote-only 50cc week'));

  // Licence: only the official text, by vehicle class, and the shop confirms.
  add('licence-50cc', 'legal', [{ q: 'xe 50cc có cần bằng lái không', expect: { intent: 'licence', include: ['55/VBHN-VPQH', 'không suy ra từ tên gọi'], exclude: ['không cần bằng lái'], actions: ['call', 'zalo'], sources: { min: 0, max: 2 } } }]);
  add('stock', 'availability', [{ q: 'còn Vision không?', expect: { intent: 'availability', include: ['không có dữ liệu tồn kho'], actions: ['call', 'zalo'] } }]);

  // Look-alike wording must not become contact, and article suggestions stay few, same-site and labelled.
  add('not-contact-goi-y', 'false-positive', [{ q: 'gợi ý xe cho 2 người đi chơi', expect: { intent: 'search', exclude: [NAP.phone] } }]);
  add('not-contact-goi-la', 'false-positive', [{ q: 'xe tay ga 50cc gọi là gì', expect: { intent: 'search', exclude: [NAP.phone] } }]);
  add('search-pin', 'search', [{ q: 'pin xe điện bị chai', expect: { intent: 'search', reading: true } }]);
  add('search-unrelated', 'search', [{ q: 'thời tiết hôm nay thế nào', expect: { intent: 'search', sources: { min: 0, max: 0 }, actions: ['call', 'zalo'], include: ['chưa tìm được nguồn'] } }]);
  return cases;
}
