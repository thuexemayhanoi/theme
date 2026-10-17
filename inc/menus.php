<?php
/**
 * Navigation: renders the stored WordPress menus exactly as they are.
 *
 * The 36-item "Main chính" menu (ID 67) is never copied, flattened or hard-coded: the header
 * and drawer call wp_nav_menu() so item order, parents, object ids and URLs stay owned by
 * WordPress. The only thing this file adds is presentation hooks and a defensive fallback.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-nt-walker-nav.php';

/**
 * Menu ids that the previous theme used. Used only as a fallback when a location is unassigned
 * and to fill EMPTY locations once on theme activation (never overwrites an assignment).
 *
 * @return array<string,int> location => menu term id
 */
function nt_legacy_menu_map(): array {
	return (array) apply_filters(
		'nguyentu_legacy_menu_map',
		array(
			'primary'        => 67,
			'primary_mobile' => 67,
			'footer'         => 66,
			'top_bar_nav'    => 66,
		)
	);
}

/**
 * Menu term id actually assigned to a location (0 when none).
 *
 * @param string $location Theme location slug.
 */
function nt_assigned_menu_id( string $location ): int {
	$locations = get_nav_menu_locations();
	return (int) ( $locations[ $location ] ?? 0 );
}

/**
 * Whether a menu has at least one item.
 *
 * @param int $menu_id Menu term id.
 */
function nt_menu_has_items( int $menu_id ): bool {
	if ( $menu_id <= 0 || ! wp_get_nav_menu_object( $menu_id ) ) {
		return false;
	}
	$items = wp_get_nav_menu_items( $menu_id );
	return is_array( $items ) && array() !== $items;
}

/**
 * Menu id that should render for a location: the assigned one, otherwise the legacy id when it
 * exists and is not empty, otherwise 0.
 *
 * @param string $location Theme location slug.
 */
function nt_resolve_menu_id( string $location ): int {
	$assigned = nt_assigned_menu_id( $location );
	if ( $assigned > 0 ) {
		return nt_menu_has_items( $assigned ) ? $assigned : 0;
	}
	$legacy = (int) ( nt_legacy_menu_map()[ $location ] ?? 0 );
	return nt_menu_has_items( $legacy ) ? $legacy : 0;
}

/**
 * Echo a menu for a location through the accessible walker.
 *
 * @param string              $location Theme location slug.
 * @param array<string,mixed> $extra    Extra wp_nav_menu() arguments.
 * @return bool Whether a menu with items was rendered.
 */
function nt_render_menu( string $location, array $extra = array() ): bool {
	$menu_id = nt_resolve_menu_id( $location );
	if ( $menu_id <= 0 ) {
		return false;
	}
	$args = array_merge(
		array(
			'menu'           => $menu_id,
			'theme_location' => $location,
			'container'      => false,
			'fallback_cb'    => false,
			'depth'          => 0,
			'menu_class'     => 'nt-menu',
			'menu_id'        => 'nt-menu-' . $location,
			'items_wrap'     => '<ul id="%1$s" class="%2$s">%3$s</ul>',
			'walker'         => new NT_Walker_Nav(),
		),
		$extra
	);
	wp_nav_menu( $args );
	return true;
}

/**
 * Whether the mobile location should render its own copy of the menu. When both locations point
 * at the same menu the single header navigation is restyled as the off-canvas drawer, so the DOM
 * carries one copy of the links and desktop/mobile always show the same items.
 */
function nt_has_distinct_mobile_menu(): bool {
	$primary = nt_resolve_menu_id( 'primary' );
	$mobile  = nt_resolve_menu_id( 'primary_mobile' );
	return $mobile > 0 && $mobile !== $primary;
}

/**
 * Staging aid: absolute links to the production host inside menu items are pointed at the
 * current host so a staging copy can be crawled end to end. A no-op on production.
 *
 * @param array<int,object> $items Menu items.
 * @return array<int,object>
 */
function nt_localize_menu_items( array $items ): array {
	if ( ! apply_filters( 'nguyentu_localize_menu_urls', true ) ) {
		return $items;
	}
	$prod = strtolower( nt_business( 'prod_host' ) );
	$home = wp_parse_url( home_url() );
	if ( empty( $home['host'] ) || in_array( strtolower( $home['host'] ), array( $prod, 'www.' . $prod ), true ) ) {
		return $items;
	}
	$origin = ( $home['scheme'] ?? 'http' ) . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
	foreach ( $items as $item ) {
		$parts = wp_parse_url( (string) $item->url );
		if ( empty( $parts['host'] ) || ! in_array( strtolower( $parts['host'] ), array( $prod, 'www.' . $prod ), true ) ) {
			continue;
		}
		$item->url = $origin . ( $parts['path'] ?? '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' ) . ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );
	}
	return $items;
}
add_filter( 'wp_nav_menu_objects', 'nt_localize_menu_items' );

/**
 * One-time helper on theme activation: fill EMPTY nav locations with the menus the previous
 * theme used. It never replaces an existing assignment, writes only this theme's own
 * theme_mods, and records what it did in the nt_activation_log option for the rollback notes.
 */
function nt_map_legacy_menus_on_switch(): void {
	if ( defined( 'NT_DISABLE_MENU_AUTOMAP' ) && NT_DISABLE_MENU_AUTOMAP ) {
		return;
	}
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	$locations = is_array( $locations ) ? $locations : array();
	$changed   = array();
	foreach ( nt_legacy_menu_map() as $location => $menu_id ) {
		if ( ! empty( $locations[ $location ] ) || ! wp_get_nav_menu_object( (int) $menu_id ) ) {
			continue;
		}
		$locations[ $location ] = (int) $menu_id;
		$changed[ $location ]   = (int) $menu_id;
	}
	if ( array() !== $changed ) {
		set_theme_mod( 'nav_menu_locations', $locations );
	}
	update_option(
		'nt_activation_log',
		array(
			'time'           => gmdate( 'c' ),
			'menu_locations' => $changed,
			'theme_version'  => NT_VERSION,
		),
		false
	);
}
add_action( 'after_switch_theme', 'nt_map_legacy_menus_on_switch' );

/**
 * Read-only snapshot of a menu for tests and the migration report.
 *
 * @param int $menu_id Menu term id.
 * @return array<int,array<string,mixed>>
 */
function nt_menu_snapshot( int $menu_id ): array {
	$out   = array();
	$items = wp_get_nav_menu_items( $menu_id );
	foreach ( is_array( $items ) ? $items : array() as $item ) {
		$out[] = array(
			'id'        => (int) $item->ID,
			'parent'    => (int) $item->menu_item_parent,
			'object'    => (string) $item->object,
			'object_id' => (int) $item->object_id,
			'position'  => (int) $item->menu_order,
			'title'     => (string) $item->title,
			'url'       => (string) $item->url,
		);
	}
	return $out;
}
