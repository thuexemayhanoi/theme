#!/usr/bin/env node
/**
 * Records one gate result for the release-gates report.
 *   node tools/qa/record.mjs <gate-id> <PASS|FAIL|NOT_TESTED> "<detail>" [evidence-path]
 *   node tools/qa/record.mjs --clear <gate-id>...    forget earlier results: a gate whose step never reports then
 *                                                    reads NOT_TESTED instead of keeping a PASS left by an old run
 */
import { mkdir, rm, writeFile } from 'node:fs/promises';

const args = process.argv.slice(2);
const dir = new URL('../../reports/results/', import.meta.url);
const validGate = (g) => typeof g === 'string' && /^[a-z0-9-]+$/.test(g);

if (args[0] === '--clear') {
	const gates = args.slice(1);
	if (!gates.every(validGate)) {
		console.error('usage: record.mjs --clear <gate-id>...  (ids use a-z, 0-9 and "-")');
		process.exit(2);
	}
	for (const gate of gates) await rm(new URL(`${gate}.json`, dir), { force: true });
	process.exit(0);
}

const [gate, status, detail = '', evidence = ''] = args;
if (!validGate(gate) || !['PASS', 'FAIL', 'NOT_TESTED'].includes(status)) {
	console.error('usage: record.mjs <gate-id> <PASS|FAIL|NOT_TESTED> "<detail>" [evidence]');
	process.exit(2);
}
await mkdir(dir, { recursive: true });
await writeFile(new URL(`${gate}.json`, dir), `${JSON.stringify({ gate, status, detail, evidence, at: new Date().toISOString() }, null, 2)}\n`);
console.log(`${status}  ${gate}  ${detail}`);
