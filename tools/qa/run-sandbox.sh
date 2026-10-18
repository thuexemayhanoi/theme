#!/usr/bin/env bash
# Runs every gate that needs the disposable WordPress sandbox (tools/sandbox) and records each result.
#   Usage: bash tools/qa/run-sandbox.sh [--reset] [--skip-e2e] [--only-e2e] [--no-shots]
#     --reset      restore the "seeded" database snapshot first (clean state, simulators ON)
#     --skip-e2e   skip the Playwright runs
#     --only-e2e   run only the Playwright runs (after the cut-over; skips integration, round trip and SEO steps)
#     --no-shots   do not capture screenshots
# The sandbox must already be installed and seeded (tools/sandbox/setup.sh, seed.sh).
set -uo pipefail
cd "$(dirname "$0")/.."/..
SB=tools/sandbox
RESET=0; E2E=1; SHOTS=1; ONLY_E2E=0
for a in "$@"; do
	case "$a" in
		--reset) RESET=1 ;; --skip-e2e) E2E=0 ;; --only-e2e) ONLY_E2E=1 ;; --no-shots) SHOTS=0 ;;
		*) echo "unknown option $a"; exit 2 ;;
	esac
done
# shellcheck source=../sandbox/lib.sh
. "$SB/lib.sh"
WP="bash $SB/wp.sh"
export NT_SITE_URL="$SITE_URL" NT_WP_ROOT="$WP_ROOT"
mkdir -p reports/results/raw reports/screenshots
rec() { node tools/qa/record.mjs "$@" >/dev/null; }

echo "== sandbox up =="
bash $SB/db.sh start >/dev/null
[ "$RESET" = 1 ] && bash $SB/snapshot.sh restore seeded >/dev/null
bash $SB/serve.sh stop >/dev/null 2>&1 || true
bash $SB/sync.sh --quiet
bash $SB/serve.sh start >/dev/null

# Both PHP logs start empty so the "php-runtime-log" gate describes this run only.
for f in wp-debug php-error; do : > "$LOG_DIR/$f.log"; done

echo "== supervised cut-over on the sandbox copy =="
$WP plugin activate nguyentu-legacy-compat >/dev/null 2>&1 || true
$WP nguyentu-compat capture --force
$WP theme activate "$THEME_SLUG"
$WP rewrite flush >/dev/null
$WP cache flush >/dev/null 2>&1 || true
$WP nguyentu status || true

if [ "$ONLY_E2E" != 1 ]; then
	echo "== PHPUnit integration =="
	vendor/bin/phpunit -c phpunit.integration.xml.dist --log-junit reports/results/raw/integration-junit.xml
	IT=$?
	node tools/qa/junit-gates.mjs reports/results/raw/integration-junit.xml
	[ "$IT" -eq 0 ] || echo "integration suite FAILED"

	echo "== featured_item round trip (capture, replay, dormant) =="
	bash tools/qa/compat-roundtrip.sh
	bash $SB/serve.sh restart >/dev/null 2>&1 || true

	echo "== SEO snapshot, rules, links =="
	bash tools/qa/sandbox-urls.sh
	node tools/seo/snapshot.mjs --base "$SITE_URL" --urls reports/seo-urls.sandbox.csv --out reports/seo-after.json --delay 0 --mode sandbox --sitemap-out reports/sitemap-urls-after.json | tail -3
	if node tools/seo/check.mjs reports/seo-after.json --out reports/seo-rules-after.json --md reports/seo-rules-after.md; then
		rec seo-rules-sandbox PASS "no P0 failure across $(node -e "console.log(JSON.parse(require('fs').readFileSync('reports/seo-rules-after.json')).summary.urls)") sandbox URLs; see reports/seo-rules-after.md" reports/seo-rules-after.md
	else
		rec seo-rules-sandbox FAIL "P0 rule failures, see reports/seo-rules-after.md" reports/seo-rules-after.md
	fi
	if node tools/seo/link-check.mjs reports/seo-after.json --base "$SITE_URL" --out reports/link-check-after.json; then
		rec link-check PASS "$(node -e "console.log(JSON.parse(require('fs').readFileSync('reports/link-check-after.json')).meta.checked)") internal link targets answered 2xx" reports/link-check-after.json
	else
		rec link-check FAIL "internal links answering non-2xx, see reports/link-check-after.json" reports/link-check-after.json
	fi
	node tools/seo/links-report.mjs reports/seo-after.json --check reports/link-check-after.json > reports/internal-links.csv
	node tools/seo/diff.mjs reports/seo-before.json reports/seo-after.json --json reports/seo-diff.json --md reports/seo-diff.md || true
fi

if [ "$E2E" = 1 ]; then
	echo "== Playwright (simulators ON) =="
	NT_SIMS=on PLAYWRIGHT_JSON_OUTPUT_FILE=reports/results/raw/pw-main.json npx playwright test layout menu blog woo seo-head a11y contact --reporter=list,json,html
	echo "== Playwright (simulators OFF: theme contact bar) =="
	bash $SB/activate-sims.sh --off >/dev/null 2>&1
	bash $SB/serve.sh restart >/dev/null 2>&1 || true
	NT_SIMS=off PLAYWRIGHT_JSON_OUTPUT_FILE=reports/results/raw/pw-sims-off.json npx playwright test contact --reporter=list,json
	if [ "$SHOTS" = 1 ]; then
		# WordPress moved the previous theme's default widgets (Archives, Categories) into the new footer area on
		# activation; that is a property of the sandbox, so evidence screenshots are taken without them.
		$WP widget reset footer-extra sidebar-blog >/dev/null 2>&1 || true
		echo "== Screenshots 360 / 768 / 1440 (theme alone: no simulators) =="
		NT_SHOTS=1 PLAYWRIGHT_JSON_OUTPUT_FILE=reports/results/raw/pw-shots.json npx playwright test screenshots --reporter=list,json
	fi
	bash $SB/activate-sims.sh >/dev/null 2>&1
	bash $SB/serve.sh restart >/dev/null 2>&1 || true
	if [ "$SHOTS" = 1 ]; then
		echo "== Screenshots with the QuickCall + MotoAI simulators active =="
		NT_SHOTS=1 NT_SHOTS_SUFFIX=-with-plugins NT_SHOTS_ONLY=home,product,contact PLAYWRIGHT_JSON_OUTPUT_FILE=reports/results/raw/pw-shots-plugins.json npx playwright test screenshots --reporter=list,json
	fi
	node tools/qa/playwright-gates.mjs reports/results/raw/pw-main.json reports/results/raw/pw-sims-off.json
fi

node tools/qa/debug-log-gate.mjs || echo "PHP runtime log gate FAILED: see reports/php-runtime-log.md"

node tools/qa/release-gates.mjs | tail -3
