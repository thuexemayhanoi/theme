/**
 * Archived-baseline comparison for the static-pages live QA. Pure functions (no network, no browser), covered by
 * tests/node/static-pages-baseline.test.mjs.
 *
 * The archived baseline (reports/static-pages/before-2026-10-21.json) is never edited. Two kinds of change are
 * accepted, both with proof, and everything else stays a failure:
 *  1. Menu/footer links that were renamed by the Legacy Compat URL recovery: accepted only through the
 *     `replacements` map that homepage-legacy-parity.mjs builds after observing HTTP 301 + 200 + self canonical.
 *  2. WordPress-managed copy (title, description, H1) edited in WordPress: accepted only through an evidence
 *     ledger entry whose `from` still equals the archived value, so a stale entry can never hide other drift.
 * Canonical, robots, menu and footer destinations can never be approved through the ledger.
 */
import assert from 'node:assert/strict';

export const META_KEYS = ['title', 'description', 'canonical', 'robots', 'h1', 'nav', 'footer'];
export const LEDGER_FIELDS = Object.freeze(['title', 'description', 'h1']);
export const LEDGER_CLASSIFICATIONS = Object.freeze(['VALID_WORDPRESS_CHANGE']);

/**
 * Validate the evidence ledger against the archived baseline. Throws on the first problem.
 *
 * @param {{changes?: object[]}} ledger Ledger file content.
 * @param {{id:number}[]} pages Archived baseline pages.
 * @returns {object[]} The validated changes.
 */
export function validateLedger(ledger, pages) {
	assert.ok(Array.isArray(ledger?.changes), 'Ledger needs a changes array');
	const seen = new Set();
	for (const change of ledger.changes) {
		const label = `ledger ${change?.id}/${change?.field}`;
		const page = pages.find((p) => p.id === change.id);
		assert.ok(page, `${label}: page is not in the archived baseline`);
		assert.ok(LEDGER_FIELDS.includes(change.field), `${label}: only ${LEDGER_FIELDS.join(', ')} may be approved here`);
		assert.ok(LEDGER_CLASSIFICATIONS.includes(change.classification), `${label}: unknown classification`);
		assert.ok(!seen.has(`${change.id}/${change.field}`), `${label}: duplicate entry`);
		seen.add(`${change.id}/${change.field}`);
		assert.deepEqual(change.from, page[change.field], `${label}: "from" no longer equals the archived value (stale entry)`);
		assert.notDeepEqual(change.to, change.from, `${label}: "to" equals "from"`);
		if (change.field === 'h1') {
			assert.ok(Array.isArray(change.to) && change.to.length === 1 && change.to[0].trim() !== '', `${label}: an approved H1 change must leave exactly one non-empty H1`);
		} else {
			assert.ok(typeof change.to === 'string' && change.to.trim() !== '', `${label}: "to" must be a non-empty string`);
		}
		assert.ok(Array.isArray(change.evidence) && change.evidence.length >= 2 && change.evidence.every((e) => typeof e === 'string' && e.trim() !== ''), `${label}: at least two evidence statements are required`);
		assert.ok(/^https:\/\/github\.com\/[^/]+\/[^/]+\/actions\/runs\/\d+$/.test(change.observedIn ?? ''), `${label}: observedIn must be the GitHub Actions run that observed the live value`);
	}
	return ledger.changes;
}

/**
 * Archived page with the approved WordPress-managed changes applied.
 *
 * @param {object} page Archived baseline page.
 * @param {{changes: object[]}} ledger Validated ledger.
 * @returns {object} Expected values for the live page.
 */
export function expectedFor(page, ledger) {
	const expected = { ...page };
	for (const change of ledger.changes.filter((c) => c.id === page.id)) expected[change.field] = change.to;
	return expected;
}

/**
 * Expected link list: archived links with the verified legacy aliases replaced.
 *
 * @param {string[]} archived Archived hrefs.
 * @param {Map<string,string>} replacements Verified alias → canonical map.
 * @returns {string[]} Expected hrefs.
 */
export function expectedLinks(archived, replacements) {
	return archived.map((url) => replacements.get(url) ?? url);
}

/** Order-aware difference of two string lists. */
export function listDiff(expected, actual) {
	const missing = expected.filter((url) => !actual.includes(url));
	const unexpected = actual.filter((url) => !expected.includes(url));
	const sameMembers = missing.length === 0 && unexpected.length === 0;
	return { missing, unexpected, reordered: sameMembers && JSON.stringify(expected) !== JSON.stringify(actual) };
}

/**
 * Every difference between the expected and the live metadata, not only the first one.
 *
 * @param {object} expected Expected page values (ledger applied).
 * @param {object} actual Values read from the live page.
 * @param {Map<string,string>} replacements Verified legacy alias map.
 * @returns {{key:string, expected:unknown, actual:unknown, missing?:string[], unexpected?:string[], reordered?:boolean}[]} Differences.
 */
export function diffMeta(expected, actual, replacements = new Map()) {
	const diffs = [];
	for (const key of META_KEYS) {
		const want = ['nav', 'footer'].includes(key) ? expectedLinks(expected[key], replacements) : expected[key];
		if (JSON.stringify(want) === JSON.stringify(actual[key])) continue;
		const entry = { key, expected: want, actual: actual[key] };
		if (['nav', 'footer'].includes(key)) Object.assign(entry, listDiff(want, actual[key]));
		diffs.push(entry);
	}
	return diffs;
}

/**
 * Compact, log-friendly description of the differences of one page. Link lists show only the changed hrefs.
 *
 * @param {number} id Page id.
 * @param {number} width Viewport width.
 * @param {ReturnType<typeof diffMeta>} diffs Differences.
 * @returns {string} Text for an assertion message.
 */
export function describeDiffs(id, width, diffs) {
	const lines = diffs.map((d) => {
		if (d.missing || d.unexpected) {
			return `  ${d.key}: missing=${JSON.stringify(d.missing)} unexpected=${JSON.stringify(d.unexpected)} reordered=${d.reordered}`;
		}
		return `  ${d.key}: expected=${JSON.stringify(d.expected)} actual=${JSON.stringify(d.actual)}`;
	});
	return `${id} @${width}: ${diffs.length} baseline difference(s)\n${lines.join('\n')}`;
}
