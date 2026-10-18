#!/usr/bin/env bash
# PHP built-in web server for the sandbox (php -S + router.php => pretty permalinks).
#
# Usage: serve.sh {start|stop|restart|status|logs|wait-ready}
#   start        start in the background (PHP_CLI_SERVER_WORKERS workers, default 4) and wait until ready
#   stop         stop the server (whole process group)
#   status       exit 0 if running, 3 if stopped
#   logs [-f] [N]  tail server access log + PHP/WordPress error logs (default last 40 lines)
#   wait-ready [seconds]  block until the site answers HTTP (default 60s)
#
# Env: PORT (8088), WP_ROOT, SANDBOX_DIR, PHP_CLI_SERVER_WORKERS (4), WAIT_TIMEOUT (60)
#      OPCACHE=1  enable opcache (validate_timestamps=1, revalidate_freq=0, so edits are still picked up
#                 immediately). Default is opcache.enable=0 as specified; OPCACHE=1 serves pages ~3x faster.
set -euo pipefail
# shellcheck source=./lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

PID_FILE="${SERVE_PID_FILE:-$RUN_DIR/php-server.pid}"
SERVER_LOG="$LOG_DIR/php-server.log"
PHP_ERROR_LOG="$LOG_DIR/php-error.log"
WP_DEBUG_LOG_FILE="$LOG_DIR/wp-debug.log"
ROUTER="$NT_TOOLS_DIR/router.php"

server_pid() {
  if [ -s "$PID_FILE" ]; then cat "$PID_FILE"; fi
}

server_alive() {
  local p
  p="$(server_pid)"
  [ -n "$p" ] && kill -0 "$p" 2>/dev/null
}

http_code() {
  curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$1" 2>/dev/null || true
}

cmd_wait_ready() {
  local timeout="${1:-${WAIT_TIMEOUT:-60}}" i code
  need_cmd curl
  for i in $(seq 1 $((timeout * 2))); do
    code="$(http_code "$SITE_URL/wp-login.php")"
    case "$code" in
      2??|3??) log "ready: $SITE_URL answered HTTP $code after ~$((i / 2))s"; return 0 ;;
    esac
    server_alive || { tail -n 20 "$SERVER_LOG" >&2 || true; die "server process exited while waiting; see $SERVER_LOG"; }
    sleep 0.5
  done
  die "server did not answer on $SITE_URL within ${timeout}s (see $SERVER_LOG)"
}

cmd_start() {
  ensure_dirs
  need_cmd php curl setsid
  [ -f "$WP_ROOT/index.php" ] || die "no WordPress at $WP_ROOT; run setup.sh first"
  ensure_db_up
  if server_alive; then
    log "server already running (pid $(server_pid)) at $SITE_URL"
    cmd_wait_ready 30
    return 0
  fi
  if [ -n "$(http_code "$SITE_URL/")" ] && [ "$(http_code "$SITE_URL/")" != "000" ]; then
    die "something else already answers on $SITE_URL; stop it or use PORT=<other>"
  fi
  : >> "$SERVER_LOG"
  local opcache_args=(-d opcache.enable=0 -d opcache.enable_cli=0)
  if [ "${OPCACHE:-0}" = 1 ]; then
    opcache_args=(-d opcache.enable=1 -d opcache.validate_timestamps=1 -d opcache.revalidate_freq=0 -d opcache.file_update_protection=0
      -d opcache.memory_consumption=256 -d opcache.interned_strings_buffer=32 -d opcache.max_accelerated_files=20000)
  fi
  log "starting php -S ${SITE_HOST}:${PORT} (workers=${PHP_CLI_SERVER_WORKERS:-4}, opcache=${OPCACHE:-0}, docroot $WP_ROOT)"
  # shellcheck disable=SC2016 # $$ must expand inside the child shell
  PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}" \
  NT_SANDBOX_PORT="$PORT" \
  setsid bash -c 'echo $$ > "$1"; shift; exec "$@"' _ "$PID_FILE" \
    php \
      -d memory_limit=512M -d max_execution_time=120 \
      -d upload_max_filesize=64M -d post_max_size=64M -d max_input_vars=5000 \
      "${opcache_args[@]}" \
      -d cli_server.color=0 \
      -d display_errors=0 -d html_errors=0 -d log_errors=1 -d error_log="$PHP_ERROR_LOG" \
      -d default_charset=UTF-8 \
      -S "${SITE_HOST}:${PORT}" -t "$WP_ROOT" "$ROUTER" \
    >>"$SERVER_LOG" 2>&1 </dev/null &
  sleep 0.3
  cmd_wait_ready "${WAIT_TIMEOUT:-60}"
}

cmd_stop() {
  if ! server_alive; then
    rm -f "$PID_FILE"
    log "server is not running"
    return 0
  fi
  local pid i
  pid="$(server_pid)"
  log "stopping server (process group of pid $pid)"
  kill -TERM -- "-$pid" 2>/dev/null || kill -TERM "$pid" 2>/dev/null || true
  for i in $(seq 1 40); do
    server_alive || break
    sleep 0.25
  done
  if server_alive; then
    warn "server ignored SIGTERM; sending SIGKILL"
    kill -KILL -- "-$pid" 2>/dev/null || kill -KILL "$pid" 2>/dev/null || true
  fi
  rm -f "$PID_FILE"
  log "server stopped"
}

cmd_status() {
  if server_alive; then
    echo "server: running (pid $(server_pid)) $SITE_URL  HTTP $(http_code "$SITE_URL/wp-login.php")"
    return 0
  fi
  echo "server: stopped ($SITE_URL)"
  return 3
}

cmd_logs() {
  local follow=0 n=40 a
  for a in "$@"; do
    case "$a" in
      -f) follow=1 ;;
      *[!0-9]*) die "usage: $0 logs [-f] [N]" ;;
      *) n="$a" ;;
    esac
  done
  touch "$SERVER_LOG" "$PHP_ERROR_LOG" "$WP_DEBUG_LOG_FILE"
  if [ "$follow" = 1 ]; then
    exec tail -n "$n" -F "$SERVER_LOG" "$PHP_ERROR_LOG" "$WP_DEBUG_LOG_FILE"
  fi
  local f
  for f in "$SERVER_LOG" "$PHP_ERROR_LOG" "$WP_DEBUG_LOG_FILE"; do
    printf '==> %s <==\n' "$f"
    tail -n "$n" "$f"
  done
}

case "${1:-}" in
  start)      cmd_start ;;
  stop)       cmd_stop ;;
  restart)    cmd_stop; cmd_start ;;
  status)     cmd_status ;;
  logs)       shift; cmd_logs "$@" ;;
  wait-ready) shift; cmd_wait_ready "${1:-}" ;;
  *) sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'; exit 2 ;;
esac
