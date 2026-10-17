#!/usr/bin/env bash
# Database (and uploads) snapshots so tests can reset the sandbox in seconds.
#
# Usage: snapshot.sh save <name> | restore <name> | list | delete <name>
#   save     mysqldump of the sandbox DB  -> $SANDBOX_DIR/snapshots/<name>.sql
#            plus wp-content/uploads       -> $SANDBOX_DIR/snapshots/<name>.uploads.tar  (SNAPSHOT_UPLOADS=0 to skip)
#   restore  drop + recreate the DB from <name>.sql, and reset uploads from <name>.uploads.tar
#   list     show snapshots
#   delete   remove a snapshot
#
# Plugin/theme activation state lives in the DB, so a restore also restores it.
set -euo pipefail
# shellcheck source=./lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

SNAPSHOT_UPLOADS="${SNAPSHOT_UPLOADS:-1}"
UPLOADS_DIR="$WP_ROOT/wp-content/uploads"

valid_name() {
  case "$1" in
    ''|*[!A-Za-z0-9._-]*|.*) die "invalid snapshot name '$1' (use letters, digits, dot, dash, underscore)" ;;
  esac
}

cmd_save() {
  local name="${1:-}" t0 sql tmp
  valid_name "$name"
  need_cmd mariadb-dump
  ensure_db_up
  mkdir -p "$SNAP_DIR"
  t0="$(now_s)"
  sql="$SNAP_DIR/$name.sql"
  tmp="$sql.tmp.$$"
  mariadb-dump --no-defaults --protocol=socket --socket="$DB_SOCKET" -uroot \
    --single-transaction --quick --add-drop-table --routines --triggers \
    --default-character-set=utf8mb4 "$DB_NAME" > "$tmp"
  mv -f "$tmp" "$sql"
  if [ "$SNAPSHOT_UPLOADS" = 1 ] && [ -d "$UPLOADS_DIR" ]; then
    tar -cf "$SNAP_DIR/$name.uploads.tar.tmp.$$" -C "$UPLOADS_DIR" .
    mv -f "$SNAP_DIR/$name.uploads.tar.tmp.$$" "$SNAP_DIR/$name.uploads.tar"
  fi
  log "saved snapshot '$name' ($(du -h "$sql" | cut -f1) sql) in $(fmt_dur $(( $(now_s) - t0 )))"
}

cmd_restore() {
  local name="${1:-}" t0 sql
  valid_name "$name"
  need_cmd mariadb
  ensure_db_up
  sql="$SNAP_DIR/$name.sql"
  [ -s "$sql" ] || die "no such snapshot: $sql (try: $0 list)"
  t0="$(now_s)"
  mysql_root --max-allowed-packet=512M --default-character-set=utf8mb4 <<SQL
DROP DATABASE IF EXISTS \`${DB_NAME}\`;
CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;
SQL
  mysql_root --max-allowed-packet=512M --default-character-set=utf8mb4 "$DB_NAME" < "$sql"
  if [ "$SNAPSHOT_UPLOADS" = 1 ] && [ -f "$SNAP_DIR/$name.uploads.tar" ]; then
    mkdir -p "$UPLOADS_DIR"
    find "$UPLOADS_DIR" -mindepth 1 -delete
    tar -xf "$SNAP_DIR/$name.uploads.tar" -C "$UPLOADS_DIR"
  fi
  # the admin password is sandbox infrastructure, not a fixture: keep it equal to $ADMIN_PASS_FILE
  if [ -f "$WP_ROOT/wp-config.php" ]; then
    "$NT_TOOLS_DIR/wp.sh" user update "$ADMIN_USER" --user_pass="$(admin_password)" --skip-email >/dev/null 2>&1 || warn "could not reset the admin password after restore"
  fi
  log "restored snapshot '$name' in $(fmt_dur $(( $(now_s) - t0 )))"
}

cmd_list() {
  local f n any=0
  for f in "$SNAP_DIR"/*.sql; do
    [ -e "$f" ] || continue
    any=1
    n="$(basename "$f" .sql)"
    printf '%-20s %8s sql  %8s uploads  %s\n' "$n" \
      "$(du -h "$f" | cut -f1)" \
      "$( [ -f "$SNAP_DIR/$n.uploads.tar" ] && du -h "$SNAP_DIR/$n.uploads.tar" | cut -f1 || echo -)" \
      "$(date -r "$f" '+%Y-%m-%d %H:%M:%S')"
  done
  [ "$any" = 1 ] || echo "(no snapshots in $SNAP_DIR)"
}

cmd_delete() {
  local name="${1:-}"
  valid_name "$name"
  rm -f "$SNAP_DIR/$name.sql" "$SNAP_DIR/$name.uploads.tar"
  log "deleted snapshot '$name'"
}

case "${1:-}" in
  save)    shift; cmd_save "${1:-}" ;;
  restore) shift; cmd_restore "${1:-}" ;;
  list)    cmd_list ;;
  delete)  shift; cmd_delete "${1:-}" ;;
  *) sed -n '2,12p' "$0" | sed 's/^# \{0,1\}//'; exit 2 ;;
esac
