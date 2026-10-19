#!/usr/bin/env bash
# Runs every gate that needs the disposable WordPress sandbox (tools/sandbox) and records each result.
#   Usage: bash tools/qa/run-sandbox.sh [--reset] [--skip-e2e] [--only-e2e] [--no-shots]
#     --reset      restore the "seeded" database snapshot first (clean state, simulators ON)
#     --skip-e2e   skip the Playwright runs
#     --only-e2e   run only the Playwright runs (after the cut-over; skips integration, round trip and SEO steps)
#     --no-shots   do not capture screenshots
# The sandbox must already be installed and seeded (tools/sandbox/setup.sh, seed.sh).
#
# Exit status: 0 only when every step succeeded and no recorded gate is FAIL. A failing gate never stops the run
# (every report is still written); it is listed at the end and makes the script exit 1. A sandbox that cannot be
# brought up or cut over stops the run at once, because nothing after that point would describe the theme.
set -uo pipefail
cd "$(dirname "$0")/../.." || exit 1
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
FAILED=()
fail() { FAILED+=("$1"); echo "FAILED: $1"; }
abort() { echo "ABORT: $1"; exit 1; }

# What this run is going to report is forgotten first: a step that dies before it records must read
# NOT_TESTED, never a PASS left behind by an earlier run (those result files are committed).
INTEGRATION_GATES=(integration menu-36 categories-26 product-terms featured-items-phpunit legacy-render featured-roundtrip seo-rules-sandbox link-check)
E2E_GATES=(e2e-layout e2e-menu e2e-contact e2e-woo e2e-blog e2e-seo-head a11y-axe e2e-search-404)
CLEAR=(php-runtime-log)
[ "$ONLY_E2E" = 1 ] || CLEAR+=("${INTEGRATION_GATES[@]}")
[ "$E2E" = 1 ] && CLEAR+=("${E2E_GATES[@]}")
node tools/qa/record.mjs --clear "${CLEAR[@]}"
[ "$ONLY_E2E" = 1 ] || rm -f reports/results/raw/integration-junit.xml
[ "$E2E" = 1 ] && rm -f reports/results/raw/pw-*.json

echo "== sandbox up =="
bash $SB/db.sh start >/dev/null || abort "the sandbox database did not start"
if [ "$RESET" = 1 ]; then bash $SB/snapshot.sh restore seeded >/dev/null || abort "the seeded snapshot could not be restored"; fi
bash $SB/serve.sh stop >/dev/null 2>&1 || true
bash $SB/sync.sh --quiet || abort "the theme could not be synced into the sandbox"
bash $SB/serve.sh start >/dev/null || abort "the sandbox web server did not start"

# Both PHP logs start empty so the "php-runtime-log" gate describes this run only.
for f in wp-debug php-error; do : > "$LOG_DIR/$f.log"; done

echo "== supervised cut-over on the sandbox copy =="
$WP plugin activate nguyentu-legacy-compat >/dev/null 2>&1 || true
$WP nguyentu-compat capture --force || abort "the companion plugin could not capture the registration"
$WP theme activate "$THEME_SLUG" || abort "the theme could not be activated in the sandbox"
$WP rewrite flush >/dev/null
$WP cache flush >/dev/null 2>&1 || true
$WP nguyentu status || true

if [ "$ONLY_E2E" != 1 ]; then
	echo "== PHPUnit integration =="
	vendor/bin/phpunit -c phpunit.integration.xml.dist --log-junit reports/results/raw/integration-junit.xml || fail "PHPUnit integration suite"
	node tools/qa/junit-gates.mjs reports/results/raw/integration-junit.xml || fail "integration gates were not recorded (no JUnit file)"

	echo "== actual legacy snapshot recovery, aliases and 404s =="
	bash tools/qa/legacy-recovery.sh || fail "actual legacy URL recovery"

	echo "== featured_item round trip (capture, replay, dormant) =="
	bash tools/qa/compat-roundtrip.sh || fail "featured_item round trip"
	# Round trip ends with the legacy provider ON to prove dormancy. Run every remaining
	# functional/SEO/browser check with the independent plugin providing the real routes.
	SIM_FLATSOME=0 bash $SB/sync.sh --quiet || abort "legacy registration simulator could not be removed"
	bash $SB/serve.sh restart >/dev/null 2>&1 || true

	echo "== SEO snapshot, rules, links =="
	bash tools/qa/sandbox-urls.sh || fail "sandbox URL list"
	node tools/seo/snapshot.mjs --base "$SITE_URL" --urls reports/seo-urls.sandbox.csv --out reports/seo-after.json --delay 0 --mode sandbox --sitemap-out reports/sitemap-urls-after.json | tail -3 || fail "SEO snapshot"
	if node tools/seo/check.mjs reports/seo-after.json --out reports/seo-rules-after.json --md reports/seo-rules-after.md; then
		rec seo-rules-sandbox PASS "no P0 failure across $(node -e "console.log(JSON.parse(require('fs').readFileSync('reports/seo-rules-after.json')).summary.urls)") sandbox URLs; see reports/seo-rules-after.md" reports/seo-rules-after.md
	else
		rec seo-rules-sandbox FAIL "P0 rule failures, see reports/seo-rules-after.md" reports/seo-rules-after.md
		fail "SEO absolute rules (P0)"
	fi
	if node tools/seo/link-check.mjs reports/seo-after.json --base "$SITE_URL" --out reports/link-check-after.json; then
		rec link-check PASS "$(node -e "console.log(JSON.parse(require('fs').readFileSync('reports/link-check-after.json')).meta.checked)") internal link targets answered 2xx" reports/link-check-after.json
	else
		rec link-check FAIL "internal links answering non-2xx, see reports/link-check-after.json" reports/link-check-after.json
		fail "internal link check"
	fi
	node tools/seo/links-report.mjs reports/seo-after.json --check reports/link-check-after.json > reports/internal-links.csv || fail "internal links report"
	# Informational: the "before" side is UNVERIFIED until a snapshot of the live site exists (gate seo-diff-live).
	node tools/seo/diff.mjs reports/seo-before.json reports/seo-after.json --json reports/seo-diff.json --md reports/seo-diff.md || true
fi

if [ "$E2E" = 1 ]; then
	echo "== Playwright (simulators ON) =="
	NT_SIMS=on PLAYWRIGHT_JSON_OUTPUT_FILE=reports/results/raw/pw-main.json npx playwright test layout menu blog woo seo-head a11y contact native-assist --reporter=list,json,html || fail "Playwright (simulators on)"
	echo "== Playwright (simulators OFF: theme contact bar) =="
	bash $SB/activate-sims.sh --off >/dev/null 2>&1
	bash $SB/serve.sh restart >/dev/null 2>&1 || true
	NT_SIMS=off PLAYWRIGHT_JSON_OUTPUT_FILE=reports/results/raw/pw-sims-off.json npx playwright test contact native-assist --reporter=list,json || fail "Playwright (simulators off)"
	if [ "$SHOTS" = 1 ]; then
		# WordPress moved the previous theme's default widgets (Archives, Categories) into the new footer area on
		# activation; that is a property of the sandbox, so evidence screenshots are taken without them.
		$WP widget reset footer-extra sidebar-blog >/dev/null 2>&1 || true
		echo "== Screenshots 360 / 768 / 1440 (theme alone: no simulators) =="
		NT_SHOTS=1 PLAYWRIGHT_JSON_OUTPUT_FILE=reports/results/raw/pw-shots.json npx playwright test screenshots --reporter=list,json || fail "screenshots (theme alone)"
	fi
	bash $SB/activate-sims.sh >/dev/null 2>&1
	bash $SB/serve.sh restart >/dev/null 2>&1 || true
	if [ "$SHOTS" = 1 ]; then
		echo "== Screenshots with the QuickCall + MotoAI simulators active =="
		NT_SHOTS=1 NT_SHOTS_SUFFIX=-with-plugins NT_SHOTS_ONLY=home,product,contact PLAYWRIGHT_JSON_OUTPUT_FILE=reports/results/raw/pw-shots-plugins.json npx playwright test screenshots --reporter=list,json || fail "screenshots (with plugin simulators)"
	fi
	node tools/qa/playwright-gates.mjs reports/results/raw/pw-main.json reports/results/raw/pw-sims-off.json || fail "browser gates were not recorded (no Playwright report)"
fi

node tools/qa/debug-log-gate.mjs || fail "PHP runtime log gate (see reports/php-runtime-log.md)"

# Also fails when any recorded gate is FAIL, whatever step recorded it.
node tools/qa/release-gates.mjs || fail "a recorded gate is FAIL (see reports/release-gates.md)"

if [ "${#FAILED[@]}" -gt 0 ]; then
	echo
	echo "SANDBOX RUN FAILED (${#FAILED[@]}):"
	printf '  - %s\n' "${FAILED[@]}"
	exit 1
fi
echo "Sandbox run finished: every step succeeded and no recorded gate is FAIL."
