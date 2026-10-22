#!/usr/bin/env node
/**
 * Read-only: download the official consolidated Law on Road Traffic Order and Safety from the government's
 * document host and check that the provisions the 50cc and electric-vehicle pages rely on are in it (Điều 34,
 * 56, 57, 59). One GET request; nothing is written to WordPress. Needs `pdftotext` (poppler-utils).
 *
 *   node tools/qa/legal-source-check.mjs [--url https://datafiles.chinhphu.vn/cpp/files/vbpq/2026/3/55-vbhn-vpqh.pdf]
 */
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { mkdirSync, writeFileSync } from 'node:fs';
import { parseArgs } from 'node:util';
import { OFFICIAL_SOURCE, assertOfficialUrl, checkAllProvisions } from './lib/legal-provisions.mjs';

const { values } = parseArgs({ options: { url: { type: 'string', default: OFFICIAL_SOURCE } } });
const url = assertOfficialUrl(values.url);
const out = 'reports/legal-source';
mkdirSync(out, { recursive: true });
const proof = { checkedAt: new Date().toISOString(), source: url, status: 'NOT_TESTED', http: null, bytes: 0, sha256: '', textCharacters: 0, provisions: [] };
try {
  const response = await fetch(url, { headers: { 'user-agent': 'NguyenTu-LegalSourceCheck/1.0 (read-only; one GET)' }, signal: AbortSignal.timeout(90000) });
  proof.http = { status: response.status, contentType: response.headers.get('content-type'), lastModified: response.headers.get('last-modified'), contentLength: response.headers.get('content-length') };
  assert.equal(response.status, 200, `Official source answered HTTP ${response.status}`);
  const bytes = Buffer.from(await response.arrayBuffer());
  assert.ok(bytes.subarray(0, 5).toString('latin1') === '%PDF-', 'The response is not a PDF');
  assert.ok(bytes.length < 25 * 1024 * 1024, 'Unexpectedly large document');
  Object.assign(proof, { bytes: bytes.length, sha256: createHash('sha256').update(bytes).digest('hex') });
  writeFileSync(`${out}/source.pdf`, bytes);
  const text = execFileSync('pdftotext', ['-layout', `${out}/source.pdf`, '-'], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
  proof.textCharacters = text.length;
  assert.ok(text.length > 20000, `Only ${text.length} characters could be extracted; the PDF may be scanned images`);
  proof.provisions = checkAllProvisions(text);
  for (const p of proof.provisions) {
    console.log(`${p.status === 'FOUND' ? 'FOUND  ' : 'MISSING'} ${p.id} (${p.cite})`);
    for (const excerpt of p.excerpts) console.log(`        …${excerpt}…`);
    for (const fragment of p.missing) console.log(`        missing fragment: ${fragment}`);
  }
  const missing = proof.provisions.filter(p => p.status !== 'FOUND');
  proof.status = missing.length ? 'FAIL' : 'PASS';
  assert.equal(missing.length, 0, `${missing.length} provision(s) not found in the official text: ${missing.map(p => p.id).join(', ')}`);
} catch (error) {
  if (proof.status === 'NOT_TESTED') proof.status = 'FAIL';
  proof.error = String(error?.message ?? error);
  console.error('Legal source check failed:', proof.error);
  process.exitCode = 1;
} finally {
  writeFileSync(`${out}/evidence.json`, JSON.stringify(proof, null, 2) + '\n');
  console.log(JSON.stringify({ status: proof.status, source: proof.source, http: proof.http, bytes: proof.bytes, sha256: proof.sha256, found: proof.provisions.filter(p => p.status === 'FOUND').length, of: proof.provisions.length }, null, 2));
}
