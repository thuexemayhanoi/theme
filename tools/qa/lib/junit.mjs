/**
 * PHPUnit JUnit parsing and gate evaluation. Pure functions, covered by tests/node/junit.test.mjs.
 */

/**
 * Test cases of a PHPUnit JUnit log.
 *
 * @param {string} xml JUnit XML.
 * @returns {{cls:string,name:string,failed:boolean,skipped:boolean,message:string}[]}
 */
export function parseJUnit(xml) {
	return [...xml.matchAll(/<testcase\b([^>]*?)(\/>|>([\s\S]*?)<\/testcase>)/g)].map((m) => {
		const attrs = Object.fromEntries([...m[1].matchAll(/(\w+)="([^"]*)"/g)].map((a) => [a[1], a[2]]));
		const body = m[3] ?? '';
		return {
			cls: attrs.class ?? attrs.classname ?? '',
			name: attrs.name,
			failed: /<(failure|error)\b/.test(body),
			skipped: /<skipped\b/.test(body),
			message: body.match(/<(?:failure|error)[^>]*>([\s\S]*?)<\/(?:failure|error)>/)?.[1]?.trim().slice(0, 300) ?? '',
		};
	});
}

/**
 * Status of one release gate from the test cases that feed it.
 *
 * A skipped case ran no assertion, so it is never a pass: only executed cases count, and a gate that has
 * none (it matches no test, or every matching test was skipped) is NOT_TESTED.
 *
 * @param {ReturnType<typeof parseJUnit>} cases All cases of the log.
 * @param {(c:ReturnType<typeof parseJUnit>[number]) => boolean} pick Selects the cases behind the gate.
 * @returns {{status:'PASS'|'FAIL'|'NOT_TESTED',detail:string}}
 */
export function gateResult(cases, pick) {
	const set = cases.filter(pick);
	const executed = set.filter((c) => !c.skipped);
	const failed = executed.filter((c) => c.failed);
	const skipped = set.length - executed.length;
	const skippedNote = skipped > 0 ? `, ${skipped} skipped` : '';
	if (executed.length === 0) {
		return { status: 'NOT_TESTED', detail: set.length === 0 ? 'no matching tests in the JUnit file' : `all ${set.length} matching test(s) were skipped, none ran` };
	}
	if (failed.length > 0) {
		const names = failed.map((f) => `${f.cls.split('\\').pop()}::${f.name}: ${f.message}`).join(' | ').slice(0, 400);
		return { status: 'FAIL', detail: `${failed.length}/${executed.length} failed${skippedNote}: ${names}` };
	}
	return { status: 'PASS', detail: `${executed.length} test(s) passed${skippedNote}` };
}
