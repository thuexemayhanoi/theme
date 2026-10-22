import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { OFFICIAL_SOURCE, PROVISIONS, IDENTITY_PROBES, normalizeLegalText, checkProvision, checkAllProvisions, probeIdentity, assertOfficialUrl } from '../../tools/qa/lib/legal-provisions.mjs';

// Characters that PDF extraction produces, named so the source stays readable.
const NBSP = String.fromCharCode(0xa0);
const SHY = String.fromCharCode(0xad);
const ZWSP = String.fromCharCode(0x200b);
const EN_DASH = String.fromCharCode(0x2013);

// SYNTHETIC fixture shaped like pdftotext output: the wording of the provisions as read from copies of the law
// (not the official PDF; tools/qa/legal-source-check.mjs reads that). Used only to test the matching logic.
const law = `VĂN BẢN HỢP NHẤT
LUẬT TRẬT TỰ, AN TOÀN GIAO THÔNG ĐƯỜNG BỘ
Số: 55/VBHN-VPQH          Hà Nội, ngày 23 tháng 3 năm 2026
Luật số 36/2024/QH15 ngày 27 tháng 6 năm 2024, được sửa đổi bởi Luật số 118/2025/QH15
Điều 34. Phân loại phương tiện giao thông đường bộ
g) Xe gắn máy là xe có hai hoặc ba bánh chạy bằng động cơ, được thiết kế, sản xuất để hoạt động
trên đường bộ, có vận tốc thiết kế không lớn hơn 50 km/h; nếu động cơ dẫn động là động cơ nhiệt thì
dung tích làm việc hoặc dung tích tương đương không lớn hơn 50 cm3; nếu động cơ dẫn động là động cơ
điện thì công suất của động cơ không lớn hơn 04 kW; xe gắn máy không bao gồm xe đạp máy;
b) Xe đạp máy, gồm cả xe đạp điện, là xe đạp có trợ lực từ động cơ, nguồn động lực từ động cơ bị
ngắt khi người lái xe dừng đạp hoặc khi xe đạt tới tốc độ 25 km/h;
Điều 56. Điều kiện của người điều khiển phương tiện tham gia giao thông đường bộ
1. … có giấy phép lái xe đang còn điểm, còn hiệu lực phù hợp với loại xe đang điều khiển do cơ quan có
thẩm quyền cấp, trừ người lái xe gắn máy quy định tại khoản 4 Điều này.
4. Người lái xe gắn máy tham gia giao thông đường bộ phải hiểu biết quy tắc giao thông đường bộ, có kỹ
năng điều khiển phương tiện; đủ điều kiện về độ tuổi, sức khỏe theo quy định tại điểm a khoản 1 và khoản 2 Điều 59 của Luật này.
Điều 57. Giấy phép lái xe
a) Hạng A1 cấp cho người lái xe mô tô hai bánh có dung tích xi-lanh đến 125 cm3 hoặc có công suất động cơ điện đến 11 kW;
b) Hạng A cấp cho người lái xe mô tô hai bánh có dung tích xi-lanh trên 125 cm3 hoặc có công suất động cơ điện trên 11 kW và các loại xe quy định cho giấy phép lái xe hạng A1;
Điều 59. Tuổi, sức khỏe của người điều khiển phương tiện tham gia giao thông đường bộ
a) Người đủ 16 tuổi trở lên được điều khiển xe gắn máy;
b) Người đủ 18 tuổi trở lên được cấp giấy phép lái xe hạng A1, A, B1, B, C1,`;

test('normalization removes the differences PDF extraction introduces', () => {
  assert.equal(normalizeLegalText(`Xe${NBSP}gắn  máy\n\tcó 50${NBSP}cm³`), 'xe gắn máy có 50 cm3');
  assert.equal(normalizeLegalText(`xi${SHY}lanh “đến” ${EN_DASH} ${ZWSP}125`), 'xilanh "đến" - 125');
  assert.equal(normalizeLegalText('Điều 59'.normalize('NFD')), normalizeLegalText('Điều 59'.normalize('NFC')));
  assert.equal(normalizeLegalText('50 cm 3'), '50 cm3');
});

test('every provision is found in a text that carries the law\'s wording, in any line layout or Unicode form', () => {
  for (const raw of [law, law.normalize('NFD'), law.replace(/\n/g, ' ').replace(/ /g, NBSP), law.replace(/cm3/g, 'cm³')]) {
    const results = checkAllProvisions(raw);
    assert.deepEqual(results.filter(r => r.status !== 'FOUND').map(r => [r.id, r.missing]), []);
    assert.equal(results.length, PROVISIONS.length);
    assert.ok(results.every(r => r.excerpts.length > 0));
  }
});

test('a changed number or missing sentence is reported as MISSING with the fragment', () => {
  const faster = checkAllProvisions(law.replace('không lớn hơn 50 km/h', 'không lớn hơn 60 km/h'));
  assert.deepEqual(faster.filter(r => r.status === 'MISSING').map(r => r.id), ['xe-gan-may']);
  assert.match(faster.find(r => r.id === 'xe-gan-may').missing[0], /50 km\/h/);
  const younger = checkAllProvisions(law.replace('Người đủ 16 tuổi', 'Người đủ 15 tuổi'));
  assert.deepEqual(younger.filter(r => r.status === 'MISSING').map(r => r.id), ['tuoi-lai-xe']);
  const noLaw = checkAllProvisions('Văn bản khác hoàn toàn');
  assert.equal(noLaw.every(r => r.status === 'MISSING'), true);
  assert.equal(checkProvision(normalizeLegalText(law), PROVISIONS[0]).status, 'FOUND');
});

test('every page-facing citation is covered: Điều 34, 56, 57 and 59 with their headings', () => {
  const cites = PROVISIONS.map(p => p.cite).join(' | ');
  for (const article of ['Điều 34', 'Điều 56', 'Điều 57', 'Điều 59']) assert.ok(cites.includes(article), article);
  assert.equal(new Set(PROVISIONS.map(p => p.id)).size, PROVISIONS.length, 'unique ids');
  assert.ok(PROVISIONS.every(p => p.fragments.length > 0));
});

test('only the official document host over HTTPS can be read', () => {
  assert.equal(assertOfficialUrl(OFFICIAL_SOURCE), OFFICIAL_SOURCE);
  for (const url of ['http://datafiles.chinhphu.vn/x.pdf', 'https://thuvienphapluat.vn/x', 'https://datafiles.chinhphu.vn.evil.test/x.pdf', 'https://example.org/55-vbhn-vpqh.pdf']) {
    assert.throws(() => assertOfficialUrl(url), /non-official/, url);
  }
});

test('the checked source is the one the live pages are tested to link', () => {
  assert.ok(readFileSync('tools/qa/seo-matrix-live.mjs', 'utf8').includes(OFFICIAL_SOURCE), 'seo-matrix-live asserts the same official link');
});

test('the cover page facts are information only: a text layer without them still passes the provisions', () => {
  const body = law.split('\n').slice(4).join('\n') + '\nLuật số 36/2024/QH15 LUẬT TRẬT TỰ, AN TOÀN GIAO THÔNG ĐƯỜNG BỘ';
  assert.deepEqual(checkAllProvisions(body).filter(r => r.status !== 'FOUND'), []);
  assert.deepEqual(probeIdentity(body).map(r => [r.id, r.status]), [['consolidated-cover', 'MISSING'], ['vbhn-number', 'MISSING'], ['amending-law-118-2025', 'MISSING']]);
  assert.deepEqual(probeIdentity(law).map(r => r.status), ['FOUND', 'FOUND', 'FOUND']);
  assert.equal(IDENTITY_PROBES.length, 3);
});
