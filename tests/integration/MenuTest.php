<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * The 36-item main menu (#67) must be rendered from the stored WordPress menu, unchanged.
 */
final class MenuTest extends TestCase {

	/** @return array<int,array<string,mixed>> baseline items keyed by db_id */
	private static function baseline_items(): array {
		$items = array();
		foreach ( nt_it_baseline()['menus']['main_items'] as $item ) {
			$items[ $item['db_id'] ] = $item;
		}
		return $items;
	}

	public function test_active_theme_is_the_independent_theme_not_a_child(): void {
		$this->assertSame( 'nguyentu-editorial-commerce', get_stylesheet() );
		$this->assertSame( get_stylesheet(), get_template(), 'The theme must not declare a parent.' );
		$this->assertFalse( wp_get_theme()->parent() );
	}

	public function test_menu_67_keeps_all_36_items_with_identical_hierarchy(): void {
		$stored   = wp_get_nav_menu_items( 67 );
		$baseline = self::baseline_items();
		$this->assertIsArray( $stored );
		$this->assertCount( 36, $stored, 'Menu #67 must keep exactly 36 items' );
		foreach ( $stored as $item ) {
			$this->assertArrayHasKey( $item->ID, $baseline, "unexpected menu item {$item->ID}" );
			$expected = $baseline[ $item->ID ];
			$this->assertSame( (int) $expected['parent'], (int) $item->menu_item_parent, "parent of {$item->ID}" );
			$this->assertSame( (int) $expected['object_id'], (int) $item->object_id, "object_id of {$item->ID}" );
			$this->assertSame( $expected['object'], $item->object, "object type of {$item->ID}" );
			$this->assertSame( (int) $expected['position'], (int) $item->menu_order, "position of {$item->ID}" );
			$this->assertSame( html_entity_decode( $expected['title'] ), html_entity_decode( $item->title ), "title of {$item->ID}" );
		}
	}

	public function test_menu_urls_resolve_to_the_baseline_paths(): void {
		$baseline = self::baseline_items();
		foreach ( wp_get_nav_menu_items( 67 ) as $item ) {
			$this->assertSame( nt_it_path( $baseline[ $item->ID ]['link'] ), nt_it_path( (string) $item->url ), "URL path of {$item->ID} ({$item->title})" );
		}
	}

	public function test_legacy_locations_are_registered_and_mapped_after_activation(): void {
		$registered = array_keys( get_registered_nav_menus() );
		foreach ( array( 'primary', 'primary_mobile', 'secondary', 'footer', 'top_bar_nav', 'my_account', 'vertical' ) as $location ) {
			$this->assertContains( $location, $registered );
		}
		$this->assertSame( 67, nt_resolve_menu_id( 'primary' ) );
		$this->assertSame( 67, nt_resolve_menu_id( 'primary_mobile' ) );
		$assigned = get_nav_menu_locations();
		$this->assertSame( 67, (int) ( $assigned['primary'] ?? 0 ), 'primary must be assigned to menu 67 after activation' );
		$this->assertSame( 67, (int) ( $assigned['primary_mobile'] ?? 0 ) );
		$this->assertFalse( nt_has_distinct_mobile_menu(), 'one DOM copy of the links when both locations share a menu' );
	}

	public function test_secondary_menu_66_is_empty_so_footer_uses_static_links(): void {
		$this->assertSame( 0, nt_resolve_menu_id( 'footer' ), 'An empty menu must not render an empty footer list' );
		$this->assertFalse( nt_menu_has_items( 66 ) );
	}

	public function test_rendered_primary_menu_contains_all_36_items_in_three_levels(): void {
		ob_start();
		$this->assertTrue( nt_render_menu( 'primary' ) );
		$html = (string) ob_get_clean();
		$xp   = nt_it_xpath( $html );

		$this->assertSame( 36, $xp->query( '//li[contains(@class,"nt-menu__item")]' )->length );
		$this->assertSame( 36, $xp->query( '//a[contains(@class,"nt-menu__link")]' )->length );
		$this->assertSame( 6, $xp->query( '//li[contains(@class,"nt-menu__item--d0")]' )->length, 'six top-level items' );
		$this->assertGreaterThan( 0, $xp->query( '//li[contains(@class,"nt-menu__item--d2")]' )->length, 'third level must be rendered' );
		$this->assertSame( 0, $xp->query( '//li[contains(@class,"nt-menu__item--d3")]' )->length );

		// Blog (3 levels) becomes the mega panel; simple dropdowns stay simple.
		$mega = $xp->query( '//li[contains(@class,"nt-has-mega")]/a' );
		$this->assertSame( 1, $mega->length );
		$this->assertSame( 'Cẩm nang', trim( $mega->item( 0 )->textContent ) );

		// Disclosure buttons point at lists that exist.
		foreach ( $xp->query( '//button[@data-nt-sub-toggle]' ) as $btn ) {
			$id = $btn->getAttribute( 'aria-controls' );
			$this->assertSame( 'false', $btn->getAttribute( 'aria-expanded' ) );
			$this->assertSame( 1, $xp->query( '//ul[@id="' . $id . '"]' )->length, "aria-controls={$id}" );
		}
		// Every item is a real link with an href (crawlable).
		foreach ( $xp->query( '//a[contains(@class,"nt-menu__link")]' ) as $a ) {
			$this->assertNotSame( '', $a->getAttribute( 'href' ) );
		}
	}

	public function test_walker_never_adds_or_drops_items_when_menu_is_unassigned(): void {
		$locations = get_nav_menu_locations();
		$this->assertNotEmpty( $locations );
		// Resolution falls back to the legacy menu id when a location is not assigned.
		$this->assertSame( 67, nt_resolve_menu_id( 'primary' ) );
		$this->assertCount( 36, nt_menu_snapshot( 67 ) );
	}

	public function test_short_labels_preserve_every_href_and_stored_menu_value(): void {
		$before = nt_menu_snapshot( 67 );
		ob_start();
		nt_render_menu( 'primary' );
		$html = (string) ob_get_clean();
		$urls = array();
		foreach ( nt_it_xpath( $html )->query( '//a[contains(@class,"nt-menu__link")]' ) as $link ) {
			$urls[] = $link->getAttribute( 'href' );
			$this->assertLessThanOrEqual( 3, nt_word_count( $link->textContent ), $link->textContent );
		}
		$expected = array_column( $before, 'url' );
		sort( $expected );
		sort( $urls );
		$this->assertSame( $expected, $urls, 'No destination added, removed or changed.' );
		$this->assertSame( $before, nt_menu_snapshot( 67 ), 'Rendering must never edit stored menu data.' );
	}
}
