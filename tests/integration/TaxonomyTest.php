<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TaxonomyTest extends TestCase {

	public function test_26_blog_categories_keep_ids_slugs_and_parents(): void {
		$baseline = nt_it_baseline()['taxonomies']['category'];
		$this->assertCount( 26, $baseline );
		foreach ( $baseline as $expected ) {
			$term = get_term( (int) $expected['id'], 'category' );
			$this->assertInstanceOf( WP_Term::class, $term, "category {$expected['id']} exists" );
			$this->assertSame( $expected['slug'], $term->slug );
			$this->assertSame( (int) $expected['parent'], (int) $term->parent, "parent of {$expected['slug']}" );
			$this->assertSame( html_entity_decode( $expected['name'] ), html_entity_decode( $term->name ), "name of {$expected['slug']}" );
		}
		$all = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'ids' ) );
		$this->assertCount( 26, $all );
	}

	public function test_category_urls_are_the_baseline_urls_without_a_category_base(): void {
		foreach ( nt_it_baseline()['taxonomies']['category'] as $expected ) {
			$this->assertSame( nt_it_path( $expected['link'] ), nt_it_path( (string) get_category_link( (int) $expected['id'] ) ), "URL of category {$expected['slug']}" );
		}
		$this->assertSame( '/blog/', nt_it_path( (string) get_category_link( 69 ) ), '/blog/ is category #69, not a page' );
		$this->assertSame( 0, (int) get_option( 'page_for_posts' ) );
	}

	public function test_blog_root_helper_finds_category_69(): void {
		$this->assertSame( 69, nt_blog_root_id() );
		$hubs = nt_blog_hubs( 20 );
		$this->assertCount( 7, $hubs, 'seven top-level blog hubs under /blog/' );
	}

	public function test_product_category_tree_70_71_72_73(): void {
		$expected = array( 70 => 0, 71 => 70, 72 => 70, 73 => 70 );
		foreach ( $expected as $id => $parent ) {
			$term = get_term( $id, 'product_cat' );
			$this->assertInstanceOf( WP_Term::class, $term, "product_cat {$id}" );
			$this->assertSame( $parent, (int) $term->parent );
		}
		$this->assertSame( '/danh-muc-san-pham/loai-xe/xe-so/', nt_it_path( (string) get_term_link( 71, 'product_cat' ) ) );
		$this->assertSame( '/danh-muc-san-pham/loai-xe/xe-dien/', nt_it_path( (string) get_term_link( 73, 'product_cat' ) ) );
	}

	public function test_featured_item_terms_77_to_80_with_hierarchy_and_urls(): void {
		$this->assertTrue( taxonomy_exists( 'featured_item_category' ), 'featured_item_category must be registered (old theme simulator or companion plugin)' );
		$expected = array( 77 => 0, 78 => 77, 79 => 77, 80 => 77 );
		foreach ( $expected as $id => $parent ) {
			$term = get_term( $id, 'featured_item_category' );
			$this->assertInstanceOf( WP_Term::class, $term, "featured term {$id}" );
			$this->assertSame( $parent, (int) $term->parent );
		}
		foreach ( nt_it_baseline()['taxonomies']['featured_item_category'] as $term ) {
			$link = get_term_link( (int) $term['term_id'], 'featured_item_category' );
			$this->assertSame( '/bo-suu-tap/' . $term['slug'] . '/', nt_it_path( (string) $link ), "URL of featured term {$term['slug']}" );
		}
	}
}
