import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const gateSource = readFileSync(new URL('../../tools/qa/release-gates.mjs', import.meta.url), 'utf8');

test('live unchanged cannot PASS merely because historical PROGRESS documentation says TRUE', () => {
	assert.doesNotMatch(gateSource, /readFile\(new URL\('docs\/PROGRESS\.md'/);
	assert.match(gateSource, /results\['live-unchanged'\]\?\.status === 'PASS'/);
	assert.match(gateSource, /recorded\.evidence/);
	assert.match(gateSource, /Date\.parse\(recorded\.at/);
	assert.match(gateSource, /NOT_TESTED/);
	assert.match(gateSource, /'live-unchanged': 'Not verified in this run/);
});
