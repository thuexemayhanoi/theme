#!/usr/bin/env bash
# Verifies that the sandbox matches the seeded baseline and prints a PASS/FAIL table.
#   - database / option / permalink assertions through WP-CLI  (php/verify-seed.php)
#   - HTTP assertions through curl against the PHP server       (key URLs, sitemap, robots, 404)
# Exit code 1 when any row is FAIL. INFO rows are informational only.
#
# HTTP checks are made under a core default theme ($VERIFY_THEME, default twentytwentyfive) even if
# another theme is active: the request carries `X-Sandbox-Theme: <theme>`, honoured by the sandbox
# mu-plugin (no DB change). Use VERIFY_THEME=active to test whatever theme is currently active.
#
# Usage: verify-seed.sh [--no-http] [--keep-server]
#   --no-http      only the WP-CLI part
#   --keep-server  leave the PHP server running if this script had to start it
set -euo pipefail
# shellcheck source=./lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

HTTP=1
KEEP=0
for a in "$@"; do
  case "$a" in
    --no-http) HTTP=0 ;;
    --keep-server) KEEP=1 ;;
    -h|--help) sed -n '2,14p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) die "unknown argument: $a (try --help)" ;;
  esac
done

need_cmd curl
[ -f "$WP_ROOT/wp-config.php" ] || die "no WordPress at $WP_ROOT; run setup.sh"
ensure_db_up

TMP="$(mktemp -d)"
ROWS="$TMP/rows.tsv"
URLS="$TMP/urls.tsv"
: > "$ROWS"
: > "$URLS"
STARTED=0
cleanup() {
  if [ "$STARTED" = 1 ] && [ "$KEEP" = 0 ]; then "$NT_TOOLS_DIR/serve.sh" stop >/dev/null 2>&1 || true; fi
  rm -rf "$TMP"
}
trap cleanup EXIT

row() { # status section check expected actual
  printf '%s\t%s\t%s\t%s\t%s\n' "$1" "$2" "$3" "$4" "$5" >> "$ROWS"
}

# ---- 1. WP-CLI part ------------------------------------------------------------------------
export NT_REPO_ROOT="$REPO_ROOT"
export NT_BASELINE_JSON="${NT_BASELINE_JSON:-$REPO_ROOT/docs/wordpress-baseline-2026-10-17.json}"
export NT_PAGES_FIXTURE_DIR="${NT_PAGES_FIXTURE_DIR:-$REPO_ROOT/tests/fixtures/pages}"
if "$NT_TOOLS_DIR/wp.sh" eval-file "$NT_TOOLS_DIR/php/verify-seed.php" >"$TMP/php.out" 2>"$TMP/php.err"; then
  grep -a '^ROW'$'\t' "$TMP/php.out" | cut -f2- >> "$ROWS" || true
  grep -a '^URL'$'\t' "$TMP/php.out" | cut -f2- >> "$URLS" || true
else
  row FAIL wp-cli "php/verify-seed.php ran to completion" "exit 0" "crashed: $(tail -n 3 "$TMP/php.err" | tr '\n\t' '  ' | cut -c1-200)"
fi

# ---- 2. HTTP part --------------------------------------------------------------------------
if [ "$HTTP" = 1 ]; then
  if ! "$NT_TOOLS_DIR/serve.sh" status >/dev/null 2>&1; then
    log "server is not running; starting it for the HTTP checks (OPCACHE=1: code does not change during the run)"
    OPCACHE=1 "$NT_TOOLS_DIR/serve.sh" start >&2
    STARTED=1
  fi

  ACTIVE_THEME="$("$NT_TOOLS_DIR/wp.sh" option get stylesheet 2>/dev/null || echo '?')"
  VERIFY_THEME="${VERIFY_THEME:-twentytwentyfive}"
  HDR=()
  if [ "$VERIFY_THEME" = active ] || [ "$VERIFY_THEME" = "$ACTIVE_THEME" ]; then
    row INFO http "theme used for HTTP checks" "-" "$ACTIVE_THEME (active theme)"
  elif [ -f "$WP_ROOT/wp-content/mu-plugins/nt-sandbox-infra.php" ] && [ -d "$WP_ROOT/wp-content/themes/$VERIFY_THEME" ]; then
    HDR=(-H "X-Sandbox-Theme: $VERIFY_THEME")
    row INFO http "theme used for HTTP checks" "-" "$VERIFY_THEME (header override; active theme is $ACTIVE_THEME)"
  else
    row INFO http "theme used for HTTP checks" "-" "$ACTIVE_THEME (override unavailable)"
  fi

  code() { curl -s -o /dev/null -w '%{http_code}' --max-time 90 "${HDR[@]}" "$SITE_URL$1" 2>/dev/null || echo 000; }
  body() { curl -s --max-time 90 "${HDR[@]}" "$SITE_URL$1" 2>/dev/null || true; }

  # http_codes path...  -> CODES[] (same order), 4 requests in parallel (the server has 4 workers)
  CODES=()
  http_codes() {
    local i=0 j p running=0
    CODES=()
    for p in "$@"; do
      ( code "$p" > "$TMP/c.$i" ) &
      i=$((i + 1))
      running=$((running + 1))
      if [ "$running" -ge 4 ]; then wait -n || true; running=$((running - 1)); fi
    done
    wait
    for ((j = 0; j < i; j++)); do CODES+=("$(cat "$TMP/c.$j")"); done
  }

  expect_status() { # path expected
    local got
    got="$(code "$1")"
    if [ "$got" = "$2" ]; then row PASS http "GET $1" "$2" "$got"; else row FAIL http "GET $1" "$2" "$got"; fi
  }

  KEY_URLS=(
    / /blog/ /blog/xe-dien/ /blog/xe-dien/xe-dap-dien/ /gioi-thieu/ve-chung-toi/ /lien-he/ /faq/ /shop/
    /bo-suu-tap/ /bo-suu-tap/bang-gia/ /bo-suu-tap/thue-ngay/ /san-pham/honda-wave/
    /danh-muc-san-pham/loai-xe/xe-so/ /robots.txt /sitemap_index.xml
  )
  while IFS=$'\t' read -r label path; do
    case "$label" in
      "blog post"|"featured_item single") KEY_URLS+=("$path") ;;
    esac
  done < "$URLS"
  KEY_URLS+=(/404-invalid-nonexistent/)
  http_codes "${KEY_URLS[@]}"
  for i in "${!KEY_URLS[@]}"; do
    want=200
    [ "${KEY_URLS[$i]}" = /404-invalid-nonexistent/ ] && want=404
    if [ "${CODES[$i]}" = "$want" ]; then row PASS http "GET ${KEY_URLS[$i]}" "$want" "${CODES[$i]}"; else row FAIL http "GET ${KEY_URLS[$i]}" "$want" "${CODES[$i]}"; fi
  done

  # grouped coverage (every URL must answer 200)
  group_check() { # label path...
    local label="$1" bad=() n=0 i
    shift
    if [ "$#" -eq 0 ]; then
      row FAIL http "$label" "a non-empty URL list" "no URLs could be listed (WP-CLI failed?)"
      return 0
    fi
    http_codes "$@"
    local paths=("$@")
    for i in "${!paths[@]}"; do
      n=$((n + 1))
      [ "${CODES[$i]}" = 200 ] || bad+=("${paths[$i]}=${CODES[$i]}")
    done
    if [ "${#bad[@]}" -eq 0 ]; then row PASS http "$label: all $n return 200" "$n x 200" "$n x 200"; else row FAIL http "$label: all $n return 200" "$n x 200" "${bad[*]:0:5}"; fi
  }
  mapfile -t GROUP_LABELS < <(awk -F'\t' '$1 ~ /^g:/ {print $1}' "$URLS" | awk '!seen[$0]++')
  for g in "${GROUP_LABELS[@]}"; do
    mapfile -t group_paths < <(awk -F'\t' -v g="$g" '$1 == g {print $2}' "$URLS")
    group_check "${g#g:}" "${group_paths[@]}"
  done
  group_check "extra term/cart URLs" /danh-muc-san-pham/loai-xe/ /danh-muc-san-pham/loai-xe/xe-ga/ /danh-muc-san-pham/loai-xe/xe-dien/ /bo-suu-tap/thue-tuan/ /bo-suu-tap/thue-thang/ /cart/ /my-account/

  # semantic checks
  HOME_HTML="$(body /)"
  if grep -qE '<html[^>]*lang="vi"' <<<"$HOME_HTML"; then row PASS http 'home <html lang="vi">' 'lang="vi"' 'lang="vi"'; else row FAIL http 'home <html lang="vi">' 'lang="vi"' 'missing'; fi
  if grep -qF "<link rel=\"canonical\" href=\"$SITE_URL/\" />" <<<"$HOME_HTML"; then row PASS http "home canonical is $SITE_URL/ (Yoast)" present present; else row FAIL http "home canonical is $SITE_URL/ (Yoast)" present "$(grep -o '<link rel="canonical"[^>]*>' <<<"$HOME_HTML" | head -n1)"; fi
  if grep -q 'id="moto-quickcall-sim"' <<<"$HOME_HTML" && grep -q 'id="motoai-chat-bubble-sim"' <<<"$HOME_HTML"; then row PASS http "home footer prints both simulator widgets" "both" "both"; else row FAIL http "home footer prints both simulator widgets" "both" "missing"; fi
  CAT_HTML="$(body /blog/xe-dien/)"
  if grep -qF "<link rel=\"canonical\" href=\"$SITE_URL/blog/xe-dien/\" />" <<<"$CAT_HTML"; then row PASS http "/blog/xe-dien/ canonical (no /category/ base)" present present; else row FAIL http "/blog/xe-dien/ canonical (no /category/ base)" present "$(grep -o '<link rel="canonical"[^>]*>' <<<"$CAT_HTML" | head -n1)"; fi
  CONTACT_HTML="$(body /lien-he/)"
  if grep -q '<meta name="description" content="\[Sandbox\]' <<<"$CONTACT_HTML"; then row PASS http "/lien-he/ outputs the seeded Yoast meta description" present present; else row FAIL http "/lien-he/ outputs the seeded Yoast meta description" present "$(grep -o '<meta name="description"[^>]*>' <<<"$CONTACT_HTML" | head -n1)"; fi
  LOC="$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' --max-time 60 "${HDR[@]}" "$SITE_URL/category/blog/" || true)"
  row INFO http "/category/blog/ (old base, Yoast redirect)" "-" "$LOC"

  # sitemap + robots
  SITEMAP="$(body /sitemap_index.xml)"
  SM_COUNT="$(grep -o '<sitemap>' <<<"$SITEMAP" | wc -l | tr -d ' ')"
  SM_NAMES="$(grep -o '[a-z_]*-sitemap[0-9]*\.xml' <<<"$SITEMAP" | sort -u | tr '\n' ' ')"
  if grep -q 'post-sitemap.xml' <<<"$SITEMAP" && grep -q 'product-sitemap.xml' <<<"$SITEMAP"; then row PASS http "sitemap_index.xml lists post + product sitemaps" present present; else row FAIL http "sitemap_index.xml lists post + product sitemaps" present "$SM_NAMES"; fi
  row INFO http "sitemap_index.xml children ($SM_COUNT)" "-" "$SM_NAMES"
  for sm in post page product category featured_item featured_item_category product_cat; do
    n="$(body "/$sm-sitemap.xml" | grep -o '<url>' | wc -l | tr -d ' ')"
    case "$sm" in
      post) if [ "$n" = 160 ]; then row PASS http "post-sitemap.xml lists all 160 posts" 160 "$n"; else row FAIL http "post-sitemap.xml lists all 160 posts" 160 "$n"; fi ;;
      *) row INFO http "$sm-sitemap.xml <url> entries" "-" "$n" ;;
    esac
  done
  ROBOTS="$(body /robots.txt)"
  if grep -qi '^User-agent:' <<<"$ROBOTS" && grep -q "Sitemap: $SITE_URL/sitemap_index.xml" <<<"$ROBOTS"; then row PASS http "robots.txt has User-agent + Yoast Sitemap line" present present; else row FAIL http "robots.txt has User-agent + Yoast Sitemap line" present "$(head -n 3 <<<"$ROBOTS" | tr '\n' ' ')"; fi
fi

# ---- 3. report -----------------------------------------------------------------------------
PASS="$(grep -c '^PASS' "$ROWS" || true)"
FAIL="$(grep -c '^FAIL' "$ROWS" || true)"
INFO="$(grep -c '^INFO' "$ROWS" || true)"
printf '\n%-5s %-10s %-86s %s\n' STATUS SECTION CHECK RESULT
printf '%s\n' "$(printf '%0.s-' $(seq 1 150))"
while IFS=$'\t' read -r st sec chk exp act; do
  case "$st" in
    PASS) res="$act" ;;
    FAIL) res="got: $act   | want: $exp" ;;
    *) res="$act" ;;
  esac
  printf '%-5s %-10s %-86s %s\n' "$st" "$sec" "${chk:0:86}" "$res"
done < "$ROWS"
printf '%s\n' "$(printf '%0.s-' $(seq 1 150))"
printf 'PASS %s   FAIL %s   INFO %s   (site %s)\n' "$PASS" "$FAIL" "$INFO" "$SITE_URL"
if [ "$FAIL" -gt 0 ]; then
  exit 1
fi
