<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Runtime regression checks for the permanent footer and its curated nav graph.
 */
final class FooterTest extends TestCase {

	public function test_footer_manifest_is_stable_and_contains_only_selected_objects(): void {
		$manifest = nt_footer_manifest();
		$this->assertSame( array( 'rental', 'hubs', 'info' ), array_keys( $manifest ) );
		$this->assertCount( 9, $manifest['rental'] );
		$this->assertCount( 7, $manifest['hubs'] );
		$this->assertCount( 5, $manifest['info'] );
		$this->assertSame( 77, $manifest['rental'][0]['id'] );
		$this->assertSame( 162, $manifest['hubs'][0]['id'] );
	}

	public function test_all_curated_links_resolve_only_published_public_objects(): void {
		$groups = nt_footer_groups();
		$this->assertCount( 9, $groups['rental'] );
		$this->assertCount( 7, $groups['hubs'] );
		$this->assertCount( 5, $groups['info'] );
		$rental_labels = array_column( $groups['rental'], 'label' );
		$this->assertContains( 'Thuê xe số', $rental_labels );
		$this->assertContains( 'Thuê xe ga', $rental_labels );
		$this->assertContains( 'Thuê xe điện', $rental_labels );
		$this->assertNotContains( 'Xe số', $rental_labels );
		$this->assertNotContains( 'Xe ga', $rental_labels );
		$this->assertNotContains( 'Xe máy điện', $rental_labels );
		$this->assertContains( 'Xe điện', array_column( $groups['hubs'], 'label' ) );
		$urls   = array();
		$labels = array();
		foreach ( $groups as $items ) {
			foreach ( $items as $item ) {
				$this->assertNotSame( '', $item['label'] );
				$this->assertLessThanOrEqual( 3, nt_word_count( $item['label'] ) );
				$this->assertStringStartsWith( home_url( '/' ), $item['url'] );
				$urls[]   = $item['url'];
				$labels[] = $item['label'];
			}
		}
		$this->assertCount( 21, array_unique( $urls ), 'no repeated URLs' );
		$this->assertCount( 21, array_unique( $labels ), 'no repeated anchors' );
		$this->assertStringContainsString( 'tư vấn nhu cầu thuê theo giờ', nt_footer_tagline_default() );
	}

	public function test_footer_link_resolver_rejects_nonpublic_unknown_or_draft_objects(): void {
		$this->assertNull( nt_footer_resolve_link( array(
				'type'  => 'post',
				'id'    => 987654,
				'label' => 'Không tồn tại',
			) ) );
		$this->assertNull( nt_footer_resolve_link( array(
				'type'     => 'term',
				'id'       => 987654,
				'taxonomy' => 'category',
				'label'    => 'Không tồn tại',
			) ) );
		$this->assertNull( nt_footer_resolve_link( array(
				'type'     => 'term',
				'id'       => 77,
				'taxonomy' => 'invalid_taxonomy',
				'label'    => 'Sai',
			) ) );
		$this->assertNull( nt_footer_resolve_link( array(
				'type'  => 'post',
				'id'    => 310,
				'label' => '',
			) ) );
	}

	public function test_footer_only_uses_actual_wordpress_permalink_functions(): void {
		$found = nt_footer_resolve_link(
			array(
				'type'  => 'post',
				'id'    => 372,
				'label' => 'Liên hệ',
			)
		);
		$this->assertSame( get_permalink( 372 ), $found['url'] );
		$term = nt_footer_resolve_link(
			array(
				'type'     => 'term',
				'id'       => 162,
				'taxonomy' => 'category',
				'label'    => 'Hướng dẫn thuê xe',
			)
		);
		$this->assertSame( get_term_link( 162, 'category' ), $term['url'] );
	}
}
