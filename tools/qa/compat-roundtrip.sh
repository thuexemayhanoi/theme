#!/usr/bin/env bash
# Capture-then-replay round trip of the companion plugin against the disposable WordPress.
#
#   1. Old-theme simulator ON  -> plugin captures the live registration, URLs exported (before)
#   2. Simulator OFF (= old theme gone) -> supervised `wp rewrite flush`, plugin replays, URLs exported (after)
#   3. compare before/after (must be identical), verify rewrite resolution + HTTP 200 for every URL
#   4. Simulator ON again -> plugin must go dormant (replay_active: no)
#
# Writes reports/compat-roundtrip.md; exit 1 on any failure. Sandbox only.
set -uo pipefail
cd "$(dirname "$0")/../.."
SB=tools/sandbox
WP="bash $SB/wp.sh"
OUT=reports/compat-roundtrip.md
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
fail=0
{
	echo "# Companion plugin round trip (capture, replay, dormant)"
	echo
	echo "Sandbox only. Old-theme registration is simulated by \`tools/sandbox/fixtures/mu-plugins/nt-sim-flatsome-registration.php\`"
	echo "(the real Flatsome theme is proprietary and not obtainable here), so this proves the MECHANISM, not the real slugs."
	echo
} > "$OUT"
step() { # step <title> <command...>
	local title="$1"; shift
	local out
	if out="$("$@" 2>&1)"; then
		printf '## PASS - %s\n\n```\n%s\n```\n\n' "$title" "$out" >> "$OUT"
		echo "PASS  $title"
	else
		fail=1
		printf '## FAIL - %s\n\n```\n%s\n```\n\n' "$title" "$out" >> "$OUT"
		echo "FAIL  $title"; echo "$out" | tail -8
	fi
}

SIM_FLATSOME=1 bash $SB/sync.sh --quiet >/dev/null
$WP plugin activate nguyentu-legacy-compat >/dev/null 2>&1 || true
$WP rewrite flush >/dev/null 2>&1

step "1a simulator active: featured_item is registered by another component" bash -c "$WP nguyentu-compat status --format=csv | tee /dev/stderr | grep -q 'registered_by_other_component,yes'"
step "1b capture the registration (runtime snapshot)" bash -c "$WP nguyentu-compat capture --force && $WP nguyentu-compat status --format=csv | grep -q 'snapshot_source,runtime'"
step "1c export URLs before (15 items + 4 terms)" bash -c "$WP nguyentu-compat export-urls > $TMP/before.csv && test \$(grep -c '^item,' $TMP/before.csv) -eq 15 && test \$(grep -c '^term,' $TMP/before.csv) -eq 4 && cat $TMP/before.csv"

SIM_FLATSOME=0 bash $SB/sync.sh --quiet >/dev/null
$WP rewrite flush >/dev/null 2>&1

step "2a simulator removed: the plugin now registers them itself (replay)" bash -c "$WP nguyentu-compat status --format=csv | tee /dev/stderr | grep -q 'replay_active,yes'"
step "2b export URLs after" bash -c "$WP nguyentu-compat export-urls > $TMP/after.csv && cat $TMP/after.csv"
step "3a compare: every URL path is identical" $WP nguyentu-compat compare "$TMP/before.csv"
step "3b verify: rewrite rules resolve every URL to itself and HTTP answers 200" $WP nguyentu-compat verify --probe
step "3c a featured single and a term answer 200 over HTTP without the old theme" bash -c "
	item=\$(grep '^item,' $TMP/after.csv | head -1 | cut -d, -f5); term=\$(grep '^term,' $TMP/after.csv | head -1 | cut -d, -f5)
	for p in \"\$item\" \"\$term\"; do code=\$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:\${PORT:-8088}\$p); echo \"\$p -> \$code\"; test \"\$code\" = 200 || exit 1; done"

SIM_FLATSOME=1 bash $SB/sync.sh --quiet >/dev/null
$WP rewrite flush >/dev/null 2>&1
step "4 old theme back (simulator ON): the plugin stays dormant" bash -c "$WP nguyentu-compat status --format=csv | tee /dev/stderr | grep -q 'replay_active,no'"

if [ "$fail" -eq 0 ]; then
	node ../../tools/qa/record.mjs featured-roundtrip PASS "capture, replay, compare, verify and dormant checks passed" reports/compat-roundtrip.md 2>/dev/null || node tools/qa/record.mjs featured-roundtrip PASS "capture, replay, compare, verify and dormant checks passed" reports/compat-roundtrip.md
else
	node tools/qa/record.mjs featured-roundtrip FAIL "see reports/compat-roundtrip.md" reports/compat-roundtrip.md
fi
exit "$fail"
