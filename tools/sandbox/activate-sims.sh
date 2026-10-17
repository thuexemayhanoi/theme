#!/usr/bin/env bash
# Activate (or with --off deactivate) the two SIMULATION plugins that stand in for the production
# QuickCall bar and MotoAI chat bubble. They exist only to test layout overlap / duplicate-bar
# behaviour of a theme. See fixtures/README.md.
#
# Usage: activate-sims.sh [--off]
set -euo pipefail
# shellcheck source=./lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

SIMS=(moto-quickcall-flatsome-safe-v4 motoai-chat-widget)
MODE=on
case "${1:-}" in
  --off) MODE=off ;;
  ''|--on) ;;
  -h|--help) sed -n '2,8p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
  *) die "unknown argument: $1 (use --off or no argument)" ;;
esac

for p in "${SIMS[@]}"; do
  if [ ! -d "$WP_ROOT/wp-content/plugins/$p" ]; then
    "$NT_TOOLS_DIR/sync.sh" --quiet
    break
  fi
done

for p in "${SIMS[@]}"; do
  if "$NT_TOOLS_DIR/wp.sh" plugin is-active "$p" >/dev/null 2>&1; then
    if [ "$MODE" = off ]; then "$NT_TOOLS_DIR/wp.sh" plugin deactivate "$p"; else log "$p already active"; fi
  else
    if [ "$MODE" = on ]; then "$NT_TOOLS_DIR/wp.sh" plugin activate "$p"; else log "$p already inactive"; fi
  fi
done
