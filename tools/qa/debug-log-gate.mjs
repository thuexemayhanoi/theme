#!/usr/bin/env node
/**
 * Release gate "php-runtime-log": no PHP warning, notice or deprecation raised by the theme or the
 * companion plugin while the sandbox pipeline ran. Reads the sandbox logs and counts only entries whose
 * message names a file of this repository's theme or plugin; entries from WordPress, WooCommerce,
 * WP-CLI or the sandbox harness (blocked outbound HTTP, restore races) are listed but not blamed.
 *
 *   node tools/qa/debug-log-gate.mjs         (SANDBOX_DIR from the environment, as the other sandbox tools)
 * run-sandbox.sh truncates both logs before the run, so the gate describes that run only.
 */
import { readFile, writeFile } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const dir = join(process.env.SANDBOX_DIR || join(process.env.TMPDIR || tmpdir(), 'nguyentu-sandbox'), 'logs');
const OURS = /\/(?:themes\/nguyentu-editorial-commerce|plugins\/nguyentu-legacy-compat)\//;
const entries = [];
for (const name of ['wp-debug.log', 'php-error.log']) {
	const text = await readFile(join(dir, name), 'utf8').catch(() => null);
	if (text === null) {
		console.error(`missing ${join(dir, name)}: gate not recorded`);
		process.exit(3);
	}
	for (const line of text.split('\n').filter((l) => /PHP (Warning|Notice|Deprecated|Fatal error|Parse error)|WordPress database error|Uncaught/i.test(l))) entries.push({ name, line });
}
const ours = entries.filter((e) => OURS.test(e.line));
const others = entries.length - ours.length;
const detail = ours.length === 0 ? `0 entries name a theme or plugin file (${others} entries from WordPress, WooCommerce, WP-CLI or the sandbox harness are not blamed)` : `${ours.length} entries name a theme or plugin file: ${ours.slice(0, 3).map((e) => e.line.slice(0, 160)).join(' | ')}`;
await writeFile('reports/php-runtime-log.md', ['# PHP runtime log of the sandbox run', '', `Result: **${ours.length === 0 ? 'PASS' : 'FAIL'}** — ${detail}`, '', `Logs: \`wp-debug.log\` and \`php-error.log\` of the sandbox (WP_DEBUG and WP_DEBUG_LOG on, display off), truncated before the run.`, '', ...(ours.length ? ['## Entries naming theme or plugin files', '', ...ours.map((e) => `- ${e.name}: ${e.line}`)] : []), ''].join('\n'));
execFileSync('node', ['tools/qa/record.mjs', 'php-runtime-log', ours.length === 0 ? 'PASS' : 'FAIL', detail, 'reports/php-runtime-log.md'], { stdio: 'inherit' });
process.exit(ours.length === 0 ? 0 : 1);
