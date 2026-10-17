#!/usr/bin/env bash
# Seed the sandbox with the 2026-10-17 baseline (exact IDs). Re-runnable: it starts from the
# "pristine" snapshot (state right after setup.sh) every time.
#
# Usage: seed.sh [--no-reset] [--no-index] [--no-snapshot]
#   --no-reset     do NOT restore the pristine snapshot first (seed.php then refuses an already seeded DB)
#   --no-index     skip the Yoast SEO indexable rebuild
#   --no-snapshot  do not save the "seeded" snapshot at the end
#
# Steps: sync -> restore pristine -> seed.php -> legacy menu locations -> rewrite flush ->
#        Yoast reindex -> activate simulator plugins -> snapshot "seeded"
# Env:   SIM_FLATSOME=1|0 (featured_item simulator), NT_PAGES_FIXTURE_DIR, NT_BASELINE_JSON, NT_SEED_ANCHOR
set -euo pipefail
# shellcheck source=./lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

RESET=1
INDEX=1
SNAPSHOT=1
for a in "$@"; do
  case "$a" in
    --no-reset) RESET=0 ;;
    --no-index) INDEX=0 ;;
    --no-snapshot) SNAPSHOT=0 ;;
    -h|--help) sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) die "unknown argument: $a (try --help)" ;;
  esac
done

T0="$(now_s)"
TIMINGS=()
STEP_NAME=""
STEP_T0=0
step() { STEP_NAME="$1"; STEP_T0="$(now_s)"; log "==> $1"; }
step_end() { TIMINGS+=("$(printf '%-36s %s' "$STEP_NAME" "$(fmt_dur $(( $(now_s) - STEP_T0 )))")"); }

[ -d "$WP_ROOT/wp-content" ] || die "no WordPress at $WP_ROOT; run setup.sh first"
"$NT_TOOLS_DIR/db.sh" start

export NT_REPO_ROOT="$REPO_ROOT"
export NT_BASELINE_JSON="${NT_BASELINE_JSON:-$REPO_ROOT/docs/wordpress-baseline-2026-10-17.json}"
export NT_PAGES_FIXTURE_DIR="${NT_PAGES_FIXTURE_DIR:-$REPO_ROOT/tests/fixtures/pages}"
export NT_SEED_MANIFEST="${NT_SEED_MANIFEST:-$SANDBOX_DIR/seed-manifest.json}"

step "sync theme/plugin/simulators"
"$NT_TOOLS_DIR/sync.sh" --quiet
step_end

if [ "$RESET" = 1 ]; then
  step "restore pristine snapshot"
  [ -s "$SNAP_DIR/pristine.sql" ] || die "snapshot 'pristine' is missing; run setup.sh --reinstall"
  "$NT_TOOLS_DIR/snapshot.sh" restore pristine
  step_end
fi

step "seed.php (fixtures)"
"$NT_TOOLS_DIR/wp.sh" eval-file "$NT_TOOLS_DIR/seed.php"
step_end

step "legacy menu locations ($LEGACY_STYLESHEET)"
"$NT_TOOLS_DIR/assign-legacy-locations.sh" "$LEGACY_STYLESHEET"
step_end

step "flush rewrite rules (fresh process)"
"$NT_TOOLS_DIR/wp.sh" rewrite flush
step_end

if [ "$INDEX" = 1 ]; then
  step "Yoast indexables (rebuild)"
  "$NT_TOOLS_DIR/wp.sh" yoast index --reindex --skip-confirmation
  step_end
fi

step "activate simulator plugins"
"$NT_TOOLS_DIR/activate-sims.sh"
step_end

if [ "$SNAPSHOT" = 1 ]; then
  step "snapshot 'seeded'"
  "$NT_TOOLS_DIR/snapshot.sh" save seeded
  step_end
fi

log "-------------------------------------------------------------------"
printf '%s\n' "${TIMINGS[@]}" >&2
log "seed total $(fmt_dur $(( $(now_s) - T0 )))   manifest: $NT_SEED_MANIFEST"
log "next: ./verify-seed.sh   and   ./serve.sh start"
