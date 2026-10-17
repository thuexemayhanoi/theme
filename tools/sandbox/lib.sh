#!/usr/bin/env bash
# shellcheck shell=bash
#
# Shared settings and helpers for the Nguyen Tu WordPress sandbox scripts.
# SOURCE this file (". lib.sh"); it is not meant to be executed.
#
# Every path/port below can be overridden from the environment.

if [ -n "${NT_SB_LIB_LOADED:-}" ]; then
  return 0
fi
NT_SB_LIB_LOADED=1

NT_TOOLS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="${REPO_ROOT:-$(cd "$NT_TOOLS_DIR/../.." && pwd)}"

# ---- locations -------------------------------------------------------------
SANDBOX_DIR="${SANDBOX_DIR:-${TMPDIR:-/tmp}/nguyentu-sandbox}"
WP_ROOT="${WP_ROOT:-$SANDBOX_DIR/wp}"
DL_DIR="${DL_DIR:-$SANDBOX_DIR/downloads}"
RUN_DIR="${RUN_DIR:-$SANDBOX_DIR/run}"
LOG_DIR="${LOG_DIR:-$SANDBOX_DIR/logs}"
SNAP_DIR="${SNAP_DIR:-$SANDBOX_DIR/snapshots}"
ETC_DIR="${ETC_DIR:-$SANDBOX_DIR/etc}"
CACHE_DIR="${CACHE_DIR:-$SANDBOX_DIR/cache}"

# ---- site ------------------------------------------------------------------
PORT="${PORT:-8088}"
SITE_HOST="${SITE_HOST:-127.0.0.1}"
SITE_URL="http://${SITE_HOST}:${PORT}"
export SITE_URL
THEME_SLUG="${THEME_SLUG:-nguyentu-editorial-commerce}"
PLUGIN_SLUG="${PLUGIN_SLUG:-nguyentu-legacy-compat}"
SIM_FLATSOME="${SIM_FLATSOME:-1}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS_FILE="${ADMIN_PASS_FILE:-$ETC_DIR/admin-password}"   # generated at install time (ADMIN_PASS env overrides)
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.invalid}"
DEFAULT_THEME="${DEFAULT_THEME:-twentytwentyfive}"
LEGACY_STYLESHEET="${LEGACY_STYLESHEET:-flatsome-child}"

# ---- pinned versions (override with env, or WC_VERSION=latest etc.) --------
WP_VERSION="${WP_VERSION:-7.1.3}"
WC_VERSION="${WC_VERSION:-11.2.1}"
YOAST_VERSION="${YOAST_VERSION:-28.7}"
WPCLI_VERSION="${WPCLI_VERSION:-2.12.0}"

# ---- database (MariaDB started by db.sh, no systemd) -----------------------
DB_NAME="${DB_NAME:-nguyentu_sandbox}"
DB_TEST_NAME="${DB_TEST_NAME:-${DB_NAME}_tests}"   # empty DB for a WP PHPUnit suite
DB_USER="${DB_USER:-nt_sandbox}"
DB_PORT="${DB_PORT:-3307}"
DB_BIND="${DB_BIND:-127.0.0.1}"
DB_DATADIR="${DB_DATADIR:-$SANDBOX_DIR/mysql}"
DB_PASS_FILE="${DB_PASS_FILE:-$ETC_DIR/db-password}"
if [ -z "${DB_SOCKET:-}" ]; then
  DB_SOCKET="$RUN_DIR/mysqld.sock"
  # AF_UNIX paths are limited to ~107 bytes; fall back to a short /tmp path.
  if [ "${#DB_SOCKET}" -gt 100 ]; then
    DB_SOCKET="/tmp/nt-sandbox-$(printf '%s' "$SANDBOX_DIR" | md5sum | cut -c1-10).sock"
  fi
fi
DB_PID_FILE="${DB_PID_FILE:-$RUN_DIR/mariadb.pid}"

# ---- helpers -----------------------------------------------------------------
log()  { printf '[sandbox] %s\n' "$*" >&2; }
warn() { printf '[sandbox] WARNING: %s\n' "$*" >&2; }
die()  { printf '[sandbox] ERROR: %s\n' "$*" >&2; exit 1; }

need_cmd() {
  local c
  for c in "$@"; do
    command -v "$c" >/dev/null 2>&1 || die "required command not found: $c (run setup.sh, or install it)"
  done
}

ensure_dirs() {
  mkdir -p "$SANDBOX_DIR" "$DL_DIR" "$RUN_DIR" "$LOG_DIR" "$SNAP_DIR" "$ETC_DIR" "$CACHE_DIR"
}

# Prints the wp-admin password: $ADMIN_PASS if set, else the generated one kept in $ADMIN_PASS_FILE
# (created on first use, random, outside the repo).
admin_password() {
  if [ -n "${ADMIN_PASS:-}" ]; then
    printf '%s' "$ADMIN_PASS"
    return 0
  fi
  if [ ! -s "$ADMIN_PASS_FILE" ]; then
    mkdir -p "$ETC_DIR"
    (umask 077; od -An -N12 -tx1 /dev/urandom | tr -d ' \n' > "$ADMIN_PASS_FILE")
  fi
  cat "$ADMIN_PASS_FILE"
}

# Prints the DB password; generates a random one on first use (kept outside the repo).
db_password() {
  if [ -n "${DB_PASS:-}" ]; then
    printf '%s' "$DB_PASS"
    return 0
  fi
  if [ ! -s "$DB_PASS_FILE" ]; then
    mkdir -p "$ETC_DIR"
    (umask 077; od -An -N18 -tx1 /dev/urandom | tr -d ' \n' > "$DB_PASS_FILE")
  fi
  cat "$DB_PASS_FILE"
}

# Root client over the unix socket (OS root == DB root via unix_socket auth).
mysql_root() {
  mariadb --no-defaults --protocol=socket --socket="$DB_SOCKET" -uroot "$@"
}

db_is_up() {
  [ -S "$DB_SOCKET" ] && mariadb-admin --no-defaults --protocol=socket --socket="$DB_SOCKET" -uroot ping >/dev/null 2>&1
}

# Start MariaDB if it is initialised but not running (used by wp.sh, serve.sh, snapshot.sh, verify-seed.sh).
ensure_db_up() {
  db_is_up && return 0
  [ -d "$DB_DATADIR/mysql" ] || die "database is not initialised ($DB_DATADIR); run setup.sh"
  "$NT_TOOLS_DIR/db.sh" start >&2
}

wp_is_installed() {
  [ -f "$WP_ROOT/wp-config.php" ] && db_is_up && "$NT_TOOLS_DIR/wp.sh" core is-installed >/dev/null 2>&1
}

now_s() { date +%s; }
fmt_dur() { # seconds -> "1m23s"
  local s="$1"
  if [ "$s" -ge 60 ]; then printf '%dm%02ds' $((s / 60)) $((s % 60)); else printf '%ds' "$s"; fi
}

# Exported so wp-config.php / PHP code can follow the chosen port & paths.
export NT_SANDBOX_PORT="$PORT"
export NT_SANDBOX_DIR="$SANDBOX_DIR"
