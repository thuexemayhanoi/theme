<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Yoast SEO stays the single source of truth; the theme only adds missing verified properties.
 */
final class SeoCoexistenceTest extends TestCase {

	public function test_yoast_is_active_and_owns_the_head(): void {
		$this->assertTrue( defined( 'WPSEO_VERSION' ), 'Yoast SEO must be active in the sandbox' );
		$this->assertSame( 'yoast', nt_seo_plugin() );
		$this->assertTrue( nt_seo_plugin_active() );
	}

	public function test_fallback_head_prints_nothing_while_yoast_is_active(): void {
		ob_start();
		nt_seo_fallback_head();
		$this->assertSame( '', (string) ob_get_clean() );
		$robots = nt_fallback_robots( array( 'max-image-preview' => 'large' ) );
		$this->assertSame( array( 'max-image-preview' => 'large' ), $robots, 'robots untouched while Yoast is active' );
	}

	public function test_theme_enriches_yoast_organization_without_overwriting_or_duplicating(): void {
		$yoast = array(
			'@type'     => 'Organization',
			'@id'       => 'https://example.test/#organization',
			'name'      => 'Tên do Yoast cấu hình',
			'telephone' => '+84900000000',
			'url'       => 'https://example.test/',
		);
		$out = apply_filters( 'wpseo_schema_organization', $yoast, null );
		$this->assertSame( array( 'Organization', 'LocalBusiness' ), $out['@type'] );
		$this->assertSame( 'https://example.test/#organization', $out['@id'], 'same entity id: no second node' );
		$this->assertSame( 'Tên do Yoast cấu hình', $out['name'], 'Yoast values are never overwritten' );
		$this->assertSame( '+84900000000', $out['telephone'], 'an existing telephone is kept' );
		$this->assertSame( 'PostalAddress', $out['address']['@type'] );
		$this->assertSame( '112 Nguyễn Văn Cừ, Bồ Đề', $out['address']['streetAddress'] );
		$this->assertSame( 'Long Biên', $out['address']['addressLocality'] );
		$this->assertSame( 'Hà Nội', $out['address']['addressRegion'] );
		$this->assertSame( 'VN', $out['address']['addressCountry'] );
		$this->assertSame( 'https://maps.google.com/maps?cid=7448790013669858019', $out['hasMap'] );
		$hours = $out['openingHoursSpecification'][0];
		$this->assertSame( '09:00', $hours['opens'] );
		$this->assertSame( '21:00', $hours['closes'] );
		$this->assertCount( 7, $hours['dayOfWeek'] );
		foreach ( array( 'aggregateRating', 'review', 'priceRange', 'geo', 'offers' ) as $forbidden ) {
			$this->assertArrayNotHasKey( $forbidden, $out, "{$forbidden} must never be invented" );
		}
	}

	public function test_enrichment_is_idempotent_and_ignores_non_arrays(): void {
		$once  = nt_yoast_enrich_organization( array( '@type' => 'Organization', '@id' => 'a' ) );
		$twice = nt_yoast_enrich_organization( $once );
		$this->assertSame( $once, $twice );
		$this->assertSame( 'x', nt_yoast_enrich_organization( 'x' ) );
	}

	public function test_enrichment_can_be_switched_off(): void {
		add_filter( 'nguyentu_local_business_schema', '__return_false' );
		$out = nt_yoast_enrich_organization( array( '@type' => 'Organization', '@id' => 'a' ) );
		remove_filter( 'nguyentu_local_business_schema', '__return_false' );
		$this->assertSame( array( '@type' => 'Organization', '@id' => 'a' ), $out );
	}

	public function test_breadcrumb_trail_for_a_nested_category_post_has_full_ancestry(): void {
		$ids = get_posts( array( 'numberposts' => 1, 'fields' => 'ids', 'category' => 166 ) );
		$this->assertNotEmpty( $ids, 'sandbox has a post in blog/xe-dien/xe-dap-dien' );
		global $wp_query;
		$wp_query = new WP_Query( array( 'p' => $ids[0] ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_query->get_posts();
		$trail = nt_breadcrumb_trail();
		$labels = array_column( $trail, 'label' );
		$this->assertSame( 'Trang chủ', $labels[0] );
		$this->assertSame( array( 'Blog', 'Xe Điện', 'Xe đạp điện' ), array_slice( $labels, 1, 3 ) );
		$this->assertSame( '', end( $trail )['url'], 'current page is not a link' );
		wp_reset_query(); // phpcs:ignore WordPress.WP.DiscouragedFunctions.wp_reset_query_wp_reset_query
	}
}
