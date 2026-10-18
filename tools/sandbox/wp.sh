#!/usr/bin/env bash
# WP-CLI wrapper bound to the sandbox install.
#   ./wp.sh option get siteurl
#   ./wp.sh eval-file some.php
# Equivalent to:
#   php -d memory_limit=512M $SANDBOX_DIR/wp-cli.phar --path=<wp-root> --allow-root --url=http://127.0.0.1:$PORT "$@"
set -euo pipefail
# shellcheck source=./lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

[ -f "$SANDBOX_DIR/wp-cli.phar" ] || die "wp-cli.phar not found in $SANDBOX_DIR; run setup.sh first"

# MariaDB is not a system service here: bring it up on demand once it has been initialised.
if [ "${NT_NO_AUTODB:-0}" != 1 ] && [ -d "$DB_DATADIR/mysql" ] && ! db_is_up; then
  "$NT_TOOLS_DIR/db.sh" start >&2
fi

mkdir -p "$ETC_DIR" "$CACHE_DIR/wp-cli"
[ -f "$ETC_DIR/wp-cli.yml" ] || : > "$ETC_DIR/wp-cli.yml"   # empty config: ignore ~/.wp-cli/config.yml
export WP_CLI_CONFIG_PATH="$ETC_DIR/wp-cli.yml"
export WP_CLI_CACHE_DIR="$CACHE_DIR/wp-cli"
export WP_CLI_PACKAGES_DIR="$CACHE_DIR/wp-cli-packages"
export WP_CLI_DISABLE_AUTO_CHECK_UPDATE=1

exec php -d memory_limit=512M "$SANDBOX_DIR/wp-cli.phar" \
  --path="$WP_ROOT" --allow-root --url="$SITE_URL" "$@"
