/**
 * Read-only, PR-scoped public HTTP audit for the four Legacy Compat category aliases.
 * Writes evidence in an ephemeral GitHub Actions artifact only (never to WordPress).
 */
import { mkdirSync, writeFileSync } from 'node:fs';
import { request } from '@playwright/test';
import { verifyLiveTermAliases } from './homepage-legacy-parity.mjs';

const dir = 'reports/live-legacy-redirect-pr';
mkdirSync(dir, { recursive: true });
const evidence = {
  collectedAt: new Date().toISOString(),
  branchSha: process.env.GITHUB_SHA ?? '',
  production: 'https://thuexemaynguyentu.com/',
  checks: [],
  status: 'NOT_TESTED',
};
const ctx = await request.newContext({ extraHTTPHeaders: { 'User-Agent': 'NguyenTu-Legacy-Redirect-QA/1.0' } });
try {
  const proof = await verifyLiveTermAliases(ctx, evidence.production);
  evidence.checks = proof.evidence;
  evidence.status = 'PASS';
  console.log('Four legacy aliases: HTTP 301 -> exact published canonical 200 + self canonical');
} catch (error) {
  evidence.status = 'FAIL';
  evidence.error = String(error?.stack ?? error);
  console.error('Legacy redirect verification FAILED:', evidence.error);
  process.exitCode = 1;
} finally {
  await ctx.dispose();
  evidence.finishedAt = new Date().toISOString();
  writeFileSync(dir + '/evidence.json', JSON.stringify(evidence, null, 2));
  console.log(JSON.stringify(evidence, null, 2));
}
