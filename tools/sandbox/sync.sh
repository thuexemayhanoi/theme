#!/usr/bin/env bash
# Copy the working tree into the sandbox WordPress (fast, repeatable, rsync based).
#
#   repo root                          -> wp-content/themes/<THEME_SLUG>/
#   plugins/nguyentu-legacy-compat/    -> wp-content/plugins/nguyentu-legacy-compat/   (skipped if absent)
#   tools/sandbox/fixtures/plugins/*   -> wp-content/plugins/                           (simulator plugins)
#   tools/sandbox/fixtures/mu-plugins/ -> wp-content/mu-plugins/
#        nt-sim-flatsome-registration.php is copied ONLY when SIM_FLATSOME=1 (default),
#        otherwise it is removed (and rewrite rules are flushed) so the companion plugin's
#        capture-then-replay behaviour can be tested with the simulator off.
#
# Theme excludes: .git .github node_modules /tests /tools /docs /reports /plugins /vendor *.md
# (readme.txt is kept), plus everything the repo's own .gitignore ignores (build output such as /dist,
# *.zip, caches, .env ...) via rsync's dir-merge filter. Add more with SYNC_EXTRA_EXCLUDES="pat1 pat2".
#
# Usage: sync.sh [--quiet]
set -euo pipefail
# shellcheck source=./lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

QUIET=0
[ "${1:-}" = "--quiet" ] && QUIET=1
say() { [ "$QUIET" = 1 ] || log "$@"; }

need_cmd rsync
[ -d "$WP_ROOT/wp-content" ] || die "no WordPress at $WP_ROOT; run setup.sh first"

THEME_DEST="$WP_ROOT/wp-content/themes/$THEME_SLUG"
PLUGIN_SRC="$REPO_ROOT/plugins/$PLUGIN_SLUG"
PLUGIN_DEST="$WP_ROOT/wp-content/plugins/$PLUGIN_SLUG"
FIXTURES="$NT_TOOLS_DIR/fixtures"
MU_DEST="$WP_ROOT/wp-content/mu-plugins"
SIM_MU="nt-sim-flatsome-registration.php"

# rsync_dir SRC DEST LABEL [rsync args...]  -> prints "LABEL: N changed"
rsync_dir() {
  local src="$1" dest="$2" label="$3" out changed
  shift 3
  mkdir -p "$dest"
  out="$(rsync -a --delete --itemize-changes "$@" "$src/" "$dest/")"
  changed="$(printf '%s\n' "$out" | grep -Ec '^(>f|\*deleting|cL|hf)' || true)"
  say "$label: $changed change(s)"
}

# ---- theme -----------------------------------------------------------------
theme_excludes=(
  --exclude='.git' --exclude='.github' --exclude='node_modules'
  --exclude='/tests' --exclude='/tools' --exclude='/docs' --exclude='/reports'
  --exclude='/plugins' --exclude='/vendor'
  --exclude='*.md' --exclude='.DS_Store'
  --filter=':- .gitignore'
  --delete-excluded
)
for pat in ${SYNC_EXTRA_EXCLUDES:-}; do theme_excludes+=(--exclude="$pat"); done
[ -f "$REPO_ROOT/style.css" ] || warn "repo root has no style.css yet - the synced theme will not be a valid WordPress theme"
rsync_dir "$REPO_ROOT" "$THEME_DEST" "theme  -> themes/$THEME_SLUG" "${theme_excludes[@]}"

# ---- companion plugin (may not exist yet) ----------------------------------
if [ -d "$PLUGIN_SRC" ]; then
  rsync_dir "$PLUGIN_SRC" "$PLUGIN_DEST" "plugin -> plugins/$PLUGIN_SLUG" \
    --exclude='.git' --exclude='node_modules' --exclude='*.md' --exclude='.DS_Store' --delete-excluded
else
  say "plugin: $PLUGIN_SRC not present - skipped"
fi

# ---- simulator / infrastructure plugins ------------------------------------
if [ -d "$FIXTURES/plugins" ]; then
  for d in "$FIXTURES"/plugins/*/; do
    [ -d "$d" ] || continue
    name="$(basename "$d")"
    rsync_dir "${d%/}" "$WP_ROOT/wp-content/plugins/$name" "sim plugin -> plugins/$name"
  done
fi

# ---- mu-plugins -------------------------------------------------------------
mkdir -p "$MU_DEST"
mu_changed=0
if [ -d "$FIXTURES/mu-plugins" ]; then
  for f in "$FIXTURES"/mu-plugins/*.php; do
    [ -f "$f" ] || continue
    base="$(basename "$f")"
    if [ "$base" = "$SIM_MU" ] && [ "$SIM_FLATSOME" != 1 ]; then
      if [ -e "$MU_DEST/$base" ]; then
        rm -f "$MU_DEST/$base"
        mu_changed=1
        say "mu-plugin: removed $base (SIM_FLATSOME=$SIM_FLATSOME)"
      fi
      continue
    fi
    if ! cmp -s "$f" "$MU_DEST/$base" 2>/dev/null; then
      cp -f "$f" "$MU_DEST/$base"
      [ "$base" = "$SIM_MU" ] && mu_changed=1
      say "mu-plugin: installed $base"
    fi
  done
fi

# Rewrite rules depend on the simulator: flush once when it was added/removed.
if [ "$mu_changed" = 1 ] && wp_is_installed; then
  if "$NT_TOOLS_DIR/wp.sh" rewrite flush >/dev/null 2>&1; then
    say "rewrite rules flushed (simulator state changed)"
  else
    warn "could not flush rewrite rules; run: $NT_TOOLS_DIR/wp.sh rewrite flush"
  fi
fi

say "sync done (SIM_FLATSOME=$SIM_FLATSOME)"
