<?php
/**
 * Pure layout decisions shared by the templates (no WordPress state, unit-testable without a
 * database): how a page-like template opens its body, and where the home quick facts go.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a rendered body supplies its own wide layout (native service page or legacy bridge sections)
 * that supplies its own full-width structure.
 *
 * @param string $html Rendered page content.
 */
function nt_content_is_wide( string $html ): bool {
	// New native service pages also provide their own hero and responsive grid.
	return (bool) preg_match( '/nt-lg-(?:section|banner|row)|class=["\x27][^"\x27]*\\bnt-service-page\\b/', $html );
}

/**
 * How a page-like template (page.php, single-featured_item.php) opens its body.
 *
 *  - "full":   the usual header, a breadcrumb plus the title as <h1> unless the body already has one.
 *  - "crumbs": the body is a wide legacy layout that brings its own <h1>. Only a compact breadcrumb
 *              strip is printed, so the authored heading stays the single H1 and the page keeps a
 *              visible breadcrumb (the bridge removes [ux_breadcrumbs]).
 *
 * @param bool $print_h1 Whether the template would print its own H1 (nt_should_print_h1()).
 * @param bool $is_wide  Whether the rendered body is a wide legacy layout (nt_content_is_wide()).
 * @return string "full" or "crumbs".
 */
function nt_pagehead_mode( bool $print_h1, bool $is_wide ): string {
	return ( $print_h1 || ! $is_wide ) ? 'full' : 'crumbs';
}

/**
 * Where the quick-facts list goes on the home page.
 *
 *  - "hero":  inside the native hero (only printed when the page content has no <h1> of its own).
 *  - "strip": a slim strip under the page content, for content that supplies its own hero.
 *  - "none":  not printed.
 *
 * "off" never prints it. "auto" keeps it inside the native hero only: next to authored content it would
 * repeat the opening hours that the local section already shows. "on" always prints it, so the setting
 * also works when the content owns the hero and its H1.
 *
 * @param bool   $content_has_h1 Whether the rendered page content already has an <h1>.
 * @param string $mode           Setting home.quickfacts: "auto", "on" or "off".
 * @return string "hero", "strip" or "none".
 */
function nt_home_facts_placement( bool $content_has_h1, string $mode ): string {
	if ( 'off' === $mode ) {
		return 'none';
	}
	if ( ! $content_has_h1 ) {
		return 'hero';
	}
	return 'on' === $mode ? 'strip' : 'none';
}
