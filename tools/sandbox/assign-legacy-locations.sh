#!/usr/bin/env bash
# Assign the audit's nav menus to a theme's `nav_menu_locations` theme_mod, mimicking the OLD theme:
#     primary, primary_mobile -> menu 67 ("Main chính")
#     footer,  top_bar_nav    -> menu 66 ("Secondary", empty)
# Other locations (secondary, my_account, vertical) stay unassigned, as on production.
#
# The seed deliberately does NOT assign locations; seed.sh runs this for `flatsome-child` only,
# as a stand-in for the old state. Running it for another stylesheet is possible (e.g. to
# simulate "locations already mapped" for the new theme) but is never done automatically.
#
# Usage: assign-legacy-locations.sh [stylesheet]      (default: $LEGACY_STYLESHEET = flatsome-child)
# Env:   NT_MAIN_MENU (67), NT_SECONDARY_MENU (66)
set -euo pipefail
# shellcheck source=./lib.sh
. "$(dirname "${BASH_SOURCE[0]}")/lib.sh"

case "${1:-}" in
  -h|--help) sed -n '2,15p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
esac

STYLESHEET="${1:-$LEGACY_STYLESHEET}"
case "$STYLESHEET" in
  ''|*[!A-Za-z0-9_-]*) die "invalid stylesheet name: '$STYLESHEET'" ;;
esac

# shellcheck disable=SC2016 # the PHP snippet is passed in single quotes on purpose
NT_STYLESHEET="$STYLESHEET" \
NT_MAIN_MENU="${NT_MAIN_MENU:-67}" \
NT_SECONDARY_MENU="${NT_SECONDARY_MENU:-66}" \
"$NT_TOOLS_DIR/wp.sh" eval '
$ss   = getenv( "NT_STYLESHEET" );
$main = (int) getenv( "NT_MAIN_MENU" );
$sec  = (int) getenv( "NT_SECONDARY_MENU" );
foreach ( array( $main, $sec ) as $menu_id ) {
	if ( ! term_exists( $menu_id, "nav_menu" ) ) {
		WP_CLI::error( "nav_menu term $menu_id does not exist - run seed.sh first" );
	}
}
$key  = "theme_mods_" . $ss;
$mods = get_option( $key, array() );
if ( ! is_array( $mods ) ) {
	$mods = array();
}
$mods["nav_menu_locations"] = array(
	"primary"        => $main,
	"primary_mobile" => $main,
	"footer"         => $sec,
	"top_bar_nav"    => $sec,
);
update_option( $key, $mods );
WP_CLI::success( sprintf( "%s: nav_menu_locations = %s", $key, wp_json_encode( $mods["nav_menu_locations"] ) ) );
'
