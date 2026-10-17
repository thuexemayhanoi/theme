#!/usr/bin/env bash
# Installs the disposable WordPress sandbox (idempotent; safe to re-run).
#
#   WordPress core (git tag)  + WP-CLI phar + REAL WooCommerce + REAL Yoast SEO (free)
#   MariaDB started by hand (db.sh), site at http://127.0.0.1:$PORT (default 8088)
#
# Usage: setup.sh [--reinstall] [--skip-apt]
#   --reinstall  wipe WordPress root, database and snapshots (keeps the downloads cache), then install again
#   --skip-apt   never call apt-get (fail instead if MariaDB/rsync are missing)
#
# Env (all optional): SANDBOX_DIR WP_ROOT PORT WP_VERSION WC_VERSION YOAST_VERSION WPCLI_VERSION
#                     (each *_VERSION may be "latest"), ADMIN_USER ADMIN_PASS DB_PORT ...
#                     YOAST_SOURCE=release (default: official release zip) | dist (Yoast-dist git clone + composer dump-autoload)
# Downloads: github.com (git clone / release assets) only. wordpress.org is blocked in this environment.
# NOTE: a plain clone of Yoast-dist has no vendor/autoload.php (Composer generates it), so Yoast would silently
#       fail to load; that is why the official release zip is the default source.
set -euo pipefail
# shellcheck source=./lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

REINSTALL=0
SKIP_APT=0
for a in "$@"; do
  case "$a" in
    --reinstall) REINSTALL=1 ;;
    --skip-apt) SKIP_APT=1 ;;
    -h|--help) sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) die "unknown argument: $a (try --help)" ;;
  esac
done

T_TOTAL0="$(now_s)"
STEP_NAME=""
STEP_T0=0
TIMINGS=()
step() { STEP_NAME="$1"; STEP_T0="$(now_s)"; log "==> $1"; }
step_end() {
  local d=$(( $(now_s) - STEP_T0 ))
  TIMINGS+=("$(printf '%-34s %s' "$STEP_NAME" "$(fmt_dur "$d")")")
}

retry() { # retry N cmd...
  local n="$1" i
  shift
  for i in $(seq 1 "$n"); do
    "$@" && return 0
    warn "attempt $i/$n failed: $*"
    sleep $((i * 2))
  done
  return 1
}

latest_tag() { # repo-url ere
  git ls-remote --tags --refs "$1" | awk '{print $2}' | sed 's#refs/tags/##' | grep -E "$2" | sort -V | tail -n1
}

resolve_versions() {
  if [ "$WP_VERSION" = latest ]; then WP_VERSION="$(latest_tag https://github.com/WordPress/WordPress.git '^[0-9]+\.[0-9]+(\.[0-9]+)?$')"; fi
  if [ "$WC_VERSION" = latest ]; then WC_VERSION="$(latest_tag https://github.com/woocommerce/woocommerce.git '^[0-9]+\.[0-9]+\.[0-9]+$')"; fi
  if [ "$YOAST_VERSION" = latest ]; then YOAST_VERSION="$(latest_tag https://github.com/Yoast-dist/wordpress-seo.git '^[0-9]+(\.[0-9]+)+$')"; fi
  if [ "$WPCLI_VERSION" = latest ]; then WPCLI_VERSION="$(latest_tag https://github.com/wp-cli/wp-cli.git '^v[0-9]+\.[0-9]+\.[0-9]+$')"; WPCLI_VERSION="${WPCLI_VERSION#v}"; fi
  if [ -z "$WP_VERSION" ] || [ -z "$WC_VERSION" ] || [ -z "$YOAST_VERSION" ] || [ -z "$WPCLI_VERSION" ]; then
    die "could not resolve a version (network?)"
  fi
}

plugin_version() { # plugin-main-file
  sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' "$1" | head -n1 | tr -d '\r'
}

# ---------------------------------------------------------------------------
install_system_packages() {
  local missing=() c
  for c in mariadbd mariadb mariadb-dump mariadb-admin mariadb-install-db rsync; do
    command -v "$c" >/dev/null 2>&1 || missing+=("$c")
  done
  if [ "${#missing[@]}" -eq 0 ]; then
    log "system packages present (mariadb $(mariadbd --version | sed -n 's/.*Ver \([0-9.]*\).*/\1/p'), rsync)"
    return 0
  fi
  [ "$SKIP_APT" = 0 ] || die "missing commands: ${missing[*]} (and --skip-apt was given)"
  [ "$(id -u)" = 0 ] || die "missing commands: ${missing[*]}; need root to apt-get install mariadb-server mariadb-client rsync"
  need_cmd apt-get
  local made_policy=0
  if [ ! -e /usr/sbin/policy-rc.d ]; then # keep apt from starting a system-wide mariadb service
    printf '#!/bin/sh\nexit 101\n' > /usr/sbin/policy-rc.d
    chmod 755 /usr/sbin/policy-rc.d
    made_policy=1
  fi
  log "apt-get install mariadb-server mariadb-client rsync (missing: ${missing[*]})"
  apt-get update -qq
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --no-install-recommends mariadb-server mariadb-client rsync >"$LOG_DIR/apt-install.log" 2>&1 \
    || { tail -n 20 "$LOG_DIR/apt-install.log" >&2; die "apt-get install failed"; }
  if [ "$made_policy" = 1 ]; then rm -f /usr/sbin/policy-rc.d; fi
}

check_php() {
  need_cmd php
  local m
  for m in mysqli mbstring curl dom xml zip gd; do
    php -m | grep -qix "$m" || { [ "$m" = mysqli ] && die "php-mysqli is required"; warn "php extension '$m' is missing"; }
  done
}

fetch_git_tag() { # url tag dest
  local url="$1" tag="$2" dest="$3"
  if [ -d "$dest/.git" ]; then log "cached: $dest"; return 0; fi
  rm -rf "$dest.partial"
  mkdir -p "$(dirname "$dest")"
  retry 3 git -c advice.detachedHead=false clone --quiet --depth 1 --branch "$tag" "$url" "$dest.partial"
  mv "$dest.partial" "$dest"
}

fetch_wpcli() {
  local dir="$DL_DIR/wp-cli-$WPCLI_VERSION" phar expected actual base
  phar="$dir/wp-cli.phar"
  base="https://github.com/wp-cli/wp-cli/releases/download/v$WPCLI_VERSION/wp-cli-$WPCLI_VERSION.phar"
  mkdir -p "$dir"
  if [ ! -s "$phar" ]; then
    if retry 3 curl -fsSL -o "$phar.part" "$base" && retry 3 curl -fsSL -o "$dir/wp-cli.phar.sha512" "$base.sha512"; then
      expected="$(tr -d ' \r\n' < "$dir/wp-cli.phar.sha512" | cut -c1-128)"
      actual="$(sha512sum "$phar.part" | cut -d' ' -f1)"
      [ "$expected" = "$actual" ] || die "WP-CLI phar checksum mismatch (expected $expected, got $actual)"
      mv -f "$phar.part" "$phar"
    else
      warn "release asset unavailable; falling back to the wp-cli/builds phar (no checksum)"
      retry 3 curl -fsSL -o "$phar.part" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
      mv -f "$phar.part" "$phar"
    fi
  else
    log "cached: $phar"
  fi
  php "$phar" --info >/dev/null 2>&1 || die "downloaded WP-CLI phar does not run under $(command -v php)"
  cmp -s "$phar" "$SANDBOX_DIR/wp-cli.phar" 2>/dev/null || cp -f "$phar" "$SANDBOX_DIR/wp-cli.phar"
}

fetch_woocommerce() {
  local dir="$DL_DIR/woocommerce-$WC_VERSION" zip
  zip="$dir/woocommerce.zip"
  mkdir -p "$dir"
  if [ ! -s "$zip" ]; then
    retry 3 curl -fsSL -o "$zip.part" "https://github.com/woocommerce/woocommerce/releases/download/$WC_VERSION/woocommerce.zip" \
      || die "could not download the real WooCommerce $WC_VERSION release zip (not substituting a stub)"
    mv -f "$zip.part" "$zip"
  else
    log "cached: $zip"
  fi
  unzip -tq "$zip" >/dev/null || { rm -f "$zip"; die "WooCommerce zip is corrupt; deleted, re-run setup.sh"; }
}

fetch_yoast() {
  local dir="$DL_DIR/wordpress-seo-$YOAST_VERSION" zip
  zip="$dir/wordpress-seo.zip"
  mkdir -p "$dir"
  YOAST_SOURCE="${YOAST_SOURCE:-release}"
  if [ "$YOAST_SOURCE" = release ]; then
    if [ ! -s "$zip" ]; then
      if retry 3 curl -fsSL -o "$zip.part" "https://github.com/Yoast/wordpress-seo/releases/download/$YOAST_VERSION/wordpress-seo-$YOAST_VERSION.zip"; then
        mv -f "$zip.part" "$zip"
      else
        warn "Yoast release zip for $YOAST_VERSION unavailable; falling back to the Yoast-dist git clone"
        rm -f "$zip.part"
        YOAST_SOURCE=dist
      fi
    else
      log "cached: $zip"
    fi
    if [ "$YOAST_SOURCE" = release ]; then
      unzip -tq "$zip" >/dev/null || { rm -f "$zip"; die "Yoast zip is corrupt; deleted, re-run setup.sh"; }
    fi
  fi
  if [ "$YOAST_SOURCE" = dist ]; then
    need_cmd composer
    fetch_git_tag https://github.com/Yoast-dist/wordpress-seo.git "$YOAST_VERSION" "$DL_DIR/wordpress-seo-$YOAST_VERSION-src/repo"
  fi
}

install_yoast() {
  local plugdir="$WP_ROOT/wp-content/plugins" dest have=""
  dest="$plugdir/wordpress-seo"
  if [ -f "$dest/wp-seo.php" ] && [ -f "$dest/vendor/autoload.php" ]; then have="$(plugin_version "$dest/wp-seo.php")"; fi
  [ "$have" != "$YOAST_VERSION" ] || return 0
  rm -rf "$dest"
  if [ "${YOAST_SOURCE:-release}" = release ]; then
    unzip -q -o "$DL_DIR/wordpress-seo-$YOAST_VERSION/wordpress-seo.zip" -d "$plugdir"
  else
    mkdir -p "$dest"
    rsync -a --exclude='.git' "$DL_DIR/wordpress-seo-$YOAST_VERSION-src/repo/" "$dest/"
    COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --working-dir="$dest" --no-dev --no-scripts --no-plugins --classmap-authoritative >"$LOG_DIR/yoast-dump-autoload.log" 2>&1 \
      || { tail -n 20 "$LOG_DIR/yoast-dump-autoload.log" >&2; die "composer dump-autoload for Yoast failed"; }
  fi
  [ -f "$dest/vendor/autoload.php" ] || die "Yoast install has no vendor/autoload.php; the plugin would not load"
  log "installed Yoast SEO $YOAST_VERSION (source: ${YOAST_SOURCE:-release})"
}

wipe_install() {
  log "reinstall requested: wiping WordPress root, database and snapshots"
  "$NT_TOOLS_DIR/serve.sh" stop || true
  "$NT_TOOLS_DIR/db.sh" stop || true
  rm -rf "$WP_ROOT" "$DB_DATADIR" "$SNAP_DIR" "$ETC_DIR/db-password" "$SANDBOX_DIR/seed-manifest.json"
  local f
  for f in wp-debug php-error php-server mail mariadb-error mariadb-stdout; do : > "$LOG_DIR/$f.log" 2>/dev/null || true; done
}

# ---------------------------------------------------------------------------
write_wp_config() {
  local salts extra
  # shellcheck disable=SC2016 # PHP code in single quotes on purpose
  salts="$(php -r 'foreach (array("AUTH_KEY","SECURE_AUTH_KEY","LOGGED_IN_KEY","NONCE_KEY","AUTH_SALT","SECURE_AUTH_SALT","LOGGED_IN_SALT","NONCE_SALT") as $k) { printf("define( \x27%s\x27, \x27%s\x27 );\n", $k, bin2hex(random_bytes(32))); }')"
  extra="$ETC_DIR/wp-config-extra.php"
  cat > "$extra" <<EOF
/* ---- Nguyen Tu sandbox (generated by tools/sandbox/setup.sh) - SANDBOX ONLY, never deploy ---- */
$salts

\$nt_sb_port = getenv( 'NT_SANDBOX_PORT' ) ? getenv( 'NT_SANDBOX_PORT' ) : '$PORT';
define( 'WP_HOME', 'http://$SITE_HOST:' . \$nt_sb_port );
define( 'WP_SITEURL', WP_HOME );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', '$LOG_DIR/wp-debug.log' );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISABLE_WP_CRON', ! getenv( 'NT_SANDBOX_WP_CRON' ) ); // run: ./wp.sh cron event run --due-now
define( 'WP_HTTP_BLOCK_EXTERNAL', true );                     // outbound HTTP from PHP is blocked ...
define( 'WP_ACCESSIBLE_HOSTS', '127.0.0.1,localhost' );        // ... except loopback
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'WP_AUTO_UPDATE_CORE', false );
define( 'FS_METHOD', 'direct' );
define( 'WP_MEMORY_LIMIT', '256M' );
define( 'WP_MAX_MEMORY_LIMIT', '512M' );
EOF
  "$NT_TOOLS_DIR/wp.sh" config create \
    --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$(db_password)" \
    --dbhost="$DB_BIND:$DB_PORT" --dbprefix=wp_ --dbcharset=utf8mb4 \
    --skip-salts --skip-check --force --extra-php < "$extra"
}

# ---------------------------------------------------------------------------
main() {
  ensure_dirs
  if [ "$REINSTALL" = 1 ]; then wipe_install; ensure_dirs; fi
  local fresh=0

  step "system packages"
  install_system_packages
  check_php
  need_cmd git curl unzip rsync
  step_end

  step "resolve versions"
  resolve_versions
  log "WordPress $WP_VERSION | WooCommerce $WC_VERSION | Yoast SEO $YOAST_VERSION | WP-CLI $WPCLI_VERSION"
  step_end

  step "downloads (github only)"
  fetch_wpcli
  fetch_git_tag https://github.com/WordPress/WordPress.git "$WP_VERSION" "$DL_DIR/wordpress-$WP_VERSION-src/repo"
  fetch_woocommerce
  fetch_yoast
  step_end

  step "WordPress files"
  if [ ! -f "$WP_ROOT/wp-load.php" ]; then
    mkdir -p "$WP_ROOT"
    rsync -a --exclude='.git' "$DL_DIR/wordpress-$WP_VERSION-src/repo/" "$WP_ROOT/"
    log "copied WordPress $WP_VERSION into $WP_ROOT"
  else
    local have
    have="$(sed -n "s/^\$wp_version = '\(.*\)';/\1/p" "$WP_ROOT/wp-includes/version.php")"
    [ "$have" = "$WP_VERSION" ] || warn "WordPress root has $have but WP_VERSION=$WP_VERSION; use --reinstall to switch"
  fi
  mkdir -p "$WP_ROOT/wp-content/uploads" "$WP_ROOT/wp-content/mu-plugins"
  step_end

  step "theme/plugin/simulator sync"
  "$NT_TOOLS_DIR/sync.sh" --quiet
  step_end

  step "database (MariaDB)"
  "$NT_TOOLS_DIR/db.sh" init
  step_end

  step "wp-config.php"
  if [ ! -f "$WP_ROOT/wp-config.php" ]; then
    write_wp_config
  else
    log "wp-config.php exists; keeping it"
  fi
  step_end

  step "WordPress install"
  if ! "$NT_TOOLS_DIR/wp.sh" core is-installed >/dev/null 2>&1; then
    "$NT_TOOLS_DIR/wp.sh" core install --url="$SITE_URL" \
      --title='Cho Thuê Xe Máy Nguyễn Tú' \
      --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASS" --admin_email="$ADMIN_EMAIL" --skip-email
    fresh=1
  else
    log "WordPress is already installed"
  fi
  step_end

  step "plugins: WooCommerce + Yoast SEO"
  local plugdir="$WP_ROOT/wp-content/plugins" have
  have=""
  [ -f "$plugdir/woocommerce/woocommerce.php" ] && have="$(plugin_version "$plugdir/woocommerce/woocommerce.php")"
  if [ "$have" != "$WC_VERSION" ]; then
    rm -rf "$plugdir/woocommerce"
    unzip -q -o "$DL_DIR/woocommerce-$WC_VERSION/woocommerce.zip" -d "$plugdir"
    log "installed WooCommerce $WC_VERSION"
  fi
  install_yoast
  local p
  for p in woocommerce wordpress-seo; do
    if "$NT_TOOLS_DIR/wp.sh" plugin is-active "$p" >/dev/null 2>&1; then
      log "$p already active"
    else
      "$NT_TOOLS_DIR/wp.sh" plugin activate "$p"
    fi
  done
  step_end

  step "default theme ($DEFAULT_THEME)"
  if ! "$NT_TOOLS_DIR/wp.sh" theme is-active "$DEFAULT_THEME" >/dev/null 2>&1; then
    if [ "$fresh" = 1 ]; then "$NT_TOOLS_DIR/wp.sh" theme activate "$DEFAULT_THEME"; else log "active theme is not $DEFAULT_THEME; leaving it (not a fresh install)"; fi
  fi
  step_end

  step "site configuration"
  if [ "$fresh" = 1 ] || [ -z "$("$NT_TOOLS_DIR/wp.sh" option get nt_sandbox_seeded 2>/dev/null || true)" ]; then
    "$NT_TOOLS_DIR/wp.sh" eval-file "$NT_TOOLS_DIR/php/configure-site.php"
  else
    log "database is already seeded; platform configuration left untouched"
  fi
  step_end

  step "pristine snapshot"
  if [ "$fresh" = 1 ] || [ ! -s "$SNAP_DIR/pristine.sql" ]; then
    if [ -z "$("$NT_TOOLS_DIR/wp.sh" option get nt_sandbox_seeded 2>/dev/null || true)" ]; then
      "$NT_TOOLS_DIR/snapshot.sh" save pristine
    else
      warn "database is already seeded and no 'pristine' snapshot exists; run: setup.sh --reinstall to create one"
    fi
  else
    log "pristine snapshot exists"
  fi
  step_end

  {
    echo "setup.sh finished: $(date -u '+%Y-%m-%dT%H:%M:%SZ')"
    echo "WordPress  $("$NT_TOOLS_DIR/wp.sh" core version)"
    echo "WooCommerce $WC_VERSION"
    echo "Yoast SEO   $YOAST_VERSION"
    echo "WP-CLI      $WPCLI_VERSION"
    echo "MariaDB     $(mariadbd --version | sed -n 's/.*Ver \([^ ]*\).*/\1/p')"
    echo "PHP         $(php -r 'echo PHP_VERSION;')"
  } > "$SANDBOX_DIR/versions.txt"

  log "-------------------------------------------------------------------"
  printf '%s\n' "${TIMINGS[@]}" >&2
  log "total $(fmt_dur $(( $(now_s) - T_TOTAL0 )))"
  log "WordPress is installed at $WP_ROOT"
  log "admin login: $ADMIN_USER / $ADMIN_PASS   (sandbox only)"
  log "next: ./seed.sh   then   ./serve.sh start   (site: $SITE_URL)"
}

main
