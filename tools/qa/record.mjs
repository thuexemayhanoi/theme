#!/usr/bin/env node
/**
 * Records one gate result for the release-gates report.
 *   node tools/qa/record.mjs <gate-id> <PASS|FAIL|NOT_TESTED> "<detail>" [evidence-path]
 */
import { mkdir, writeFile } from 'node:fs/promises';

const [gate, status, detail = '', evidence = ''] = process.argv.slice(2);
if (!gate || !['PASS', 'FAIL', 'NOT_TESTED'].includes(status)) {
	console.error('usage: record.mjs <gate-id> <PASS|FAIL|NOT_TESTED> "<detail>" [evidence]');
	process.exit(2);
}
const dir = new URL('../../reports/results/', import.meta.url);
await mkdir(dir, { recursive: true });
await writeFile(new URL(`${gate}.json`, dir), `${JSON.stringify({ gate, status, detail, evidence, at: new Date().toISOString() }, null, 2)}\n`);
console.log(`${status}  ${gate}  ${detail}`);
