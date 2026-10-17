#!/usr/bin/env bash
# MariaDB lifecycle for the sandbox. No systemd, no /etc/mysql config: mariadbd is
# started by hand with an explicit option list; datadir lives under $SANDBOX_DIR/mysql.
#
# Usage: db.sh {init|start|stop|restart|status|shell|env}
#   init     create datadir (first time), start the server, create DB + user (idempotent)
#   start    start mariadbd in the background and wait until it answers
#   stop     clean shutdown
#   status   exit 0 if running, 3 if stopped
#   shell    open a root SQL shell on the sandbox database (extra args are passed on)
#   env      print `export NT_DB_*=...` lines (host, port, name, user, password, socket)
set -euo pipefail
# shellcheck source=./lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

db_pid() {
  if [ -s "$DB_PID_FILE" ]; then cat "$DB_PID_FILE"; fi
}

db_pid_alive() {
  local p
  p="$(db_pid)"
  [ -n "$p" ] && kill -0 "$p" 2>/dev/null
}

cmd_start() {
  ensure_dirs
  need_cmd mariadbd mariadb-admin
  if db_is_up; then
    log "MariaDB already running (pid $(db_pid), port $DB_PORT)"
    return 0
  fi
  [ -d "$DB_DATADIR/mysql" ] || die "datadir not initialised ($DB_DATADIR); run: $0 init"
  rm -f "$DB_SOCKET"
  log "starting MariaDB on ${DB_BIND}:${DB_PORT} (socket $DB_SOCKET)"
  local args=(
    --no-defaults --user=root
    --datadir="$DB_DATADIR" --socket="$DB_SOCKET"
    --port="$DB_PORT" --bind-address="$DB_BIND"
    --pid-file="$DB_PID_FILE"
    --log-error="$LOG_DIR/mariadb-error.log"
    --skip-name-resolve --skip-log-bin --performance-schema=OFF
    --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_520_ci
    --innodb-buffer-pool-size=256M --innodb-log-file-size=64M
    --innodb-flush-log-at-trx-commit=2
    --max-allowed-packet=256M --max-connections=100
    --tmpdir=/tmp
  )
  setsid bash -c 'exec "$@"' _ mariadbd "${args[@]}" >>"$LOG_DIR/mariadb-stdout.log" 2>&1 </dev/null &
  local i
  for i in $(seq 1 120); do
    if db_is_up; then
      log "MariaDB ready after ~$((i / 2))s (pid $(db_pid))"
      return 0
    fi
    sleep 0.5
  done
  tail -n 30 "$LOG_DIR/mariadb-error.log" >&2 || true
  die "MariaDB did not become ready within 60s (see $LOG_DIR/mariadb-error.log)"
}

cmd_stop() {
  if ! db_is_up && ! db_pid_alive; then
    log "MariaDB is not running"
    return 0
  fi
  log "stopping MariaDB"
  mariadb-admin --no-defaults --protocol=socket --socket="$DB_SOCKET" -uroot shutdown >/dev/null 2>&1 || true
  local i
  for i in $(seq 1 120); do
    db_pid_alive || { log "MariaDB stopped"; return 0; }
    sleep 0.5
  done
  warn "clean shutdown timed out; sending SIGKILL"
  kill -9 "$(db_pid)" 2>/dev/null || true
}

cmd_status() {
  if db_is_up; then
    echo "mariadb: running (pid $(db_pid)) host=${DB_BIND} port=${DB_PORT} socket=${DB_SOCKET} datadir=${DB_DATADIR}"
    return 0
  fi
  echo "mariadb: stopped (datadir=${DB_DATADIR})"
  return 3
}

cmd_provision() {
  local pw sql_name sql_test
  pw="$(db_password)"
  sql_name="${DB_NAME//\`/}"
  sql_test="${DB_TEST_NAME//\`/}"
  # shellcheck disable=SC2119
  mysql_root <<SQL
CREATE DATABASE IF NOT EXISTS \`${sql_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
CREATE DATABASE IF NOT EXISTS \`${sql_test}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${pw}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${pw}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${pw}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${pw}';
GRANT ALL PRIVILEGES ON \`${sql_name}\`.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${sql_name}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${sql_test}\`.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${sql_test}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
  log "database '${DB_NAME}' (+ empty '${DB_TEST_NAME}') and user '${DB_USER}' are provisioned"
}

cmd_init() {
  ensure_dirs
  need_cmd mariadbd mariadb mariadb-admin mariadb-install-db
  if [ ! -d "$DB_DATADIR/mysql" ]; then
    log "initialising MariaDB datadir at $DB_DATADIR"
    mkdir -p "$DB_DATADIR"
    if ! mariadb-install-db --no-defaults --user=root --datadir="$DB_DATADIR" \
        --auth-root-authentication-method=socket --skip-test-db >"$LOG_DIR/mariadb-install.log" 2>&1; then
      tail -n 30 "$LOG_DIR/mariadb-install.log" >&2
      die "mariadb-install-db failed"
    fi
  else
    log "datadir already initialised"
  fi
  cmd_start
  cmd_provision
}

cmd_shell() {
  need_cmd mariadb
  db_is_up || die "MariaDB is not running; run: $0 start"
  exec mariadb --no-defaults --protocol=socket --socket="$DB_SOCKET" -uroot "$DB_NAME" "$@"
}

cmd_env() {
  local pw
  pw="$(db_password)"
  printf 'export NT_DB_HOST=%q\n'      "$DB_BIND"
  printf 'export NT_DB_PORT=%q\n'      "$DB_PORT"
  printf 'export NT_DB_NAME=%q\n'      "$DB_NAME"
  printf 'export NT_DB_TEST_NAME=%q\n' "$DB_TEST_NAME"
  printf 'export NT_DB_USER=%q\n'      "$DB_USER"
  printf 'export NT_DB_PASSWORD=%q\n'  "$pw"
  printf 'export NT_DB_SOCKET=%q\n'    "$DB_SOCKET"
}

case "${1:-}" in
  init)    cmd_init ;;
  start)   cmd_start ;;
  stop)    cmd_stop ;;
  restart) cmd_stop; cmd_start ;;
  status)  cmd_status ;;
  shell)   shift; cmd_shell "$@" ;;
  env)     cmd_env ;;
  *) sed -n '2,12p' "$0" | sed 's/^# \{0,1\}//'; exit 2 ;;
esac
