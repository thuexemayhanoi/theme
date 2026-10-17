#!/usr/bin/env bash
# Runs every automated gate that does NOT need the WordPress sandbox and records each result.
# Usage: bash tools/qa/run-all.sh
set -uo pipefail
cd "$(dirname "$0")/../.."

rec() { node tools/qa/record.mjs "$@" >/dev/null; }
step() { # step <gate> <title> <command...>
	local gate="$1" title="$2"; shift 2
	local out
	if out="$("$@" 2>&1)"; then
		rec "$gate" PASS "$title"
		echo "PASS  $gate"
	else
		rec "$gate" FAIL "$title — $(echo "$out" | tail -3 | tr '\n' ' ' | cut -c1-200)"
		echo "FAIL  $gate"; echo "$out" | tail -15
	fi
}

php_lint() {
	local n=0
	while IFS= read -r -d '' f; do php -l "$f" >/dev/null || return 1; n=$((n + 1)); done < <(find . -name '*.php' -not -path './vendor/*' -not -path './node_modules/*' -print0)
	echo "$n files"
}
export -f php_lint 2>/dev/null || true

step php-lint "all PHP files pass php -l" bash -c 'n=0; while IFS= read -r -d "" f; do php -l "$f" >/dev/null || exit 1; n=$((n+1)); done < <(find . -name "*.php" -not -path "./vendor/*" -not -path "./node_modules/*" -print0); echo "$n files"'
step phpcs "WordPress coding standards, 0 errors 0 warnings" vendor/bin/phpcs --report=summary
step unit "PHPUnit unit suite" vendor/bin/phpunit --testsuite unit
step js-lint "eslint" npx --no-install eslint assets/js tools tests/e2e tests/node --no-warn-ignored
step node-tests "node:test SEO tooling" node --test "tests/node/*.test.mjs"
step css-valid "css-check" node tools/qa/css-check.mjs
step budgets "asset budgets" node tools/qa/budgets.mjs
step contrast "WCAG contrast of tokens" node tools/qa/contrast.mjs

node tools/qa/release-gates.mjs | tail -2
