/**
 * Provisions of the Law on Road Traffic Order and Safety (36/2024/QH15, consolidated text 55/VBHN-VPQH) that the
 * published 50cc and electric-vehicle pages rely on. Pure functions, covered by tests/node/legal-provisions.test.mjs.
 *
 * The check reads the OFFICIAL consolidated text and never edits WordPress. A fragment that is missing means the
 * wording of a page can no longer be shown to follow the source: review the page and the law before changing either.
 */

/** Official source cited by the pages (consolidated law, March 2026). */
export const OFFICIAL_SOURCE = 'https://datafiles.chinhphu.vn/cpp/files/vbpq/2026/3/55-vbhn-vpqh.pdf';

/** Hosts a legal-source check may read; anything else is refused. */
export const OFFICIAL_HOSTS = Object.freeze(['datafiles.chinhphu.vn']);

/**
 * Required wording per provision. Each fragment is a string or a RegExp, compared after normalizeLegalText().
 * Wording was cross-checked against independent copies of the text on 2026-10-22 and is verified against the
 * official PDF by tools/qa/legal-source-check.mjs.
 */
export const PROVISIONS = Object.freeze([
  { id: 'document', cite: 'Văn bản hợp nhất', fragments: ['văn bản hợp nhất', '55/vbhn-vpqh', 'luật trật tự, an toàn giao thông đường bộ'] },
  { id: 'dieu-34-heading', cite: 'Điều 34', fragments: [/điều 34\.\s*phân loại phương tiện giao thông đường bộ/] },
  {
    id: 'xe-gan-may',
    cite: 'Điều 34 khoản 1 điểm g',
    fragments: [
      'xe gắn máy là xe có hai hoặc ba bánh chạy bằng động cơ',
      'vận tốc thiết kế không lớn hơn 50 km/h',
      /dung tích làm việc hoặc dung tích tương đương không lớn hơn 50 cm3/,
      /công suất của động cơ không lớn hơn 0?4 kw/,
    ],
  },
  {
    id: 'xe-dap-may',
    cite: 'Điều 34 khoản 2 điểm b',
    fragments: ['xe đạp máy, gồm cả xe đạp điện, là xe đạp có trợ lực từ động cơ', /ngắt khi người lái xe dừng đạp hoặc khi xe đạt tới tốc độ 25 km\/h/],
  },
  { id: 'dieu-56-heading', cite: 'Điều 56', fragments: [/điều 56\.\s*điều kiện của người điều khiển phương tiện tham gia giao thông đường bộ/] },
  { id: 'gplx-tru-xe-gan-may', cite: 'Điều 56 khoản 1', fragments: ['trừ người lái xe gắn máy quy định tại khoản 4 điều này'] },
  {
    id: 'nguoi-lai-xe-gan-may',
    cite: 'Điều 56 khoản 4',
    fragments: ['người lái xe gắn máy tham gia giao thông đường bộ phải hiểu biết quy tắc giao thông đường bộ, có kỹ năng điều khiển phương tiện'],
  },
  {
    id: 'hang-a1-a',
    cite: 'Điều 57 khoản 1',
    fragments: [
      /hạng a1 cấp cho người lái xe mô tô hai bánh có dung tích xi-lanh đến 125 cm3 hoặc có công suất động cơ điện đến 11 kw/,
      /hạng a cấp cho người lái xe mô tô hai bánh có dung tích xi-lanh trên 125 cm3 hoặc có công suất động cơ điện trên 11 kw/,
    ],
  },
  { id: 'dieu-59-heading', cite: 'Điều 59', fragments: [/điều 59\.\s*tuổi, sức khỏe của người điều khiển phương tiện tham gia giao thông đường bộ/] },
  {
    id: 'tuoi-lai-xe',
    cite: 'Điều 59 khoản 1',
    fragments: ['người đủ 16 tuổi trở lên được điều khiển xe gắn máy', /người đủ 18 tuổi trở lên được cấp giấy phép lái xe hạng a1/],
  },
]);

/**
 * Text in the form used for matching: NFC, one space between words, lower case, no zero-width characters,
 * uniform dashes and quotes, and "cm³" written as "cm3" (PDF extraction varies in all of these).
 *
 * @param {string} text Raw text.
 * @returns {string} Normalized text.
 */
export function normalizeLegalText(text) {
  return String(text)
    .normalize('NFC')
    .replace(/[\u00ad\u200b-\u200d\ufeff]/g, '')
    .replace(/[\u00a0\u2000-\u200a\u202f\u205f\u3000]/g, ' ')
    .replace(/[\u2010-\u2015\u2212]/g, '-')
    .replace(/[\u2018\u2019]/g, "'")
    .replace(/[\u201c\u201d]/g, '"')
    .replace(/cm\s*[3\u00b3]/gi, 'cm3')
    .replace(/\s+/g, ' ')
    .trim()
    .toLowerCase();
}

/**
 * Look for every fragment of one provision.
 *
 * @param {string} normalized Output of normalizeLegalText for the whole document.
 * @param {(typeof PROVISIONS)[number]} provision Provision definition.
 * @returns {{id:string, cite:string, status:'FOUND'|'MISSING', missing:string[], excerpts:string[]}} Result with short excerpts.
 */
export function checkProvision(normalized, provision) {
  const missing = [];
  const excerpts = [];
  for (const fragment of provision.fragments) {
    const text = typeof fragment === 'string' ? normalizeLegalText(fragment) : null;
    const index = text !== null ? normalized.indexOf(text) : normalized.search(fragment);
    if (index < 0) {
      missing.push(String(fragment));
      continue;
    }
    const length = text !== null ? text.length : normalized.slice(index).match(fragment)[0].length;
    excerpts.push(normalized.slice(Math.max(0, index - 40), index + length + 60));
  }
  return { id: provision.id, cite: provision.cite, status: missing.length ? 'MISSING' : 'FOUND', missing, excerpts };
}

/**
 * Check every provision against a document text.
 *
 * @param {string} rawText Text extracted from the official document.
 * @returns {ReturnType<typeof checkProvision>[]} One result per provision.
 */
export function checkAllProvisions(rawText) {
  const normalized = normalizeLegalText(rawText);
  return PROVISIONS.map(provision => checkProvision(normalized, provision));
}

/** Only HTTPS URLs on an official host may be read. */
export function assertOfficialUrl(url) {
  const parsed = new URL(url);
  if (parsed.protocol !== 'https:' || !OFFICIAL_HOSTS.includes(parsed.hostname)) throw new Error(`Refusing to read a non-official source: ${url}`);
  return parsed.href;
}
