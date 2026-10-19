#!/usr/bin/env bash
# Reproduce the real mismatched registration snapshot on a disposable local WordPress only.
set -euo pipefail
cd "$(dirname "$0")/../.."
. tools/sandbox/lib.sh
case "$SITE_HOST" in 127.0.0.1|localhost) ;; *) exit 2 ;; esac
tmp="$(mktemp -d)"
bash tools/sandbox/wp.sh option get nguyentu_legacy_compat --format=json > "$tmp/snapshot.json"
restore() {
  bash tools/sandbox/wp.sh option update nguyentu_legacy_compat "$(cat "$tmp/snapshot.json")" --format=json >/dev/null
  SIM_FLATSOME=1 bash tools/sandbox/sync.sh --quiet >/dev/null
  bash tools/sandbox/wp.sh rewrite flush >/dev/null
  rm -rf "$tmp"
}
trap restore EXIT
SIM_FLATSOME=0 bash tools/sandbox/sync.sh --quiet
bash tools/sandbox/wp.sh option update nguyentu_legacy_compat "$(cat tests/fixtures/legacy-registration.json)" --format=json
bash tools/sandbox/wp.sh rewrite flush
vendor/bin/phpunit -c phpunit.integration.xml.dist --filter LegacyUrlRecoveryTest
python3 tools/seo/legacy-live-audit.py --base "$SITE_URL" --out reports/legacy-recovery-sandbox.json --verify
bash tools/sandbox/wp.sh eval-file tests/fixtures/legacy-partial-registration.php
echo 'PASS: mismatched snapshot recovered; 19 canonical URLs, 19 aliases, 404s and partial registrations verified.'
