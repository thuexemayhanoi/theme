<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Integration coverage for the optional Smart Core plugin.
 *
 * Loads the code from the repository WITHOUT activating it on production or
 * changing the sandbox's real plugin inventory.
 */
final class SmartCoreIntegrationTest extends TestCase {

	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__, 2 ) . '/plugins/nguyentu-smart-core/nguyentu-smart-core.php';
		NT_Smart_Schema::register();
	}

	public function test_verified_business_uses_the_active_theme_as_its_authority(): void {
		$this->assertSame( nt_business( 'brand' ), nt_smart_business()['brand'] );
		$this->assertSame( nt_business( 'phone_intl' ), nt_smart_business()['phone_intl'] );
		$this->assertSame( nt_business( 'maps_url' ), nt_smart_business()['maps_url'] );
		$this->assertSame( nt_price_rows(), nt_smart_reference_prices() );
	}

	public function test_daily_rate_uses_real_vnd_reference_prices(): void {
		$this->assertSame( 150000, NT_Smart_Rental::daily_rate( '150.000đ' ) );
		$this->assertSame( 200000, NT_Smart_Rental::daily_rate( '200.000đ' ) );
		$this->assertNull( NT_Smart_Rental::daily_rate( 'Liên hệ' ) );
	}

	public function test_audit_counts_only_distinct_links_on_this_host(): void {
		$url = home_url( '/test-post/' );
		$html = '<a href="/blog/first/">one</a><a href="/blog/first/">repeat</a><a href="https://example.com/x/">external</a><a href="/test-post/">self</a>';
		$this->assertSame( 1, NT_Smart_Audit::count_internal_links( $html, $url ) );
	}

	public function test_legacy_homepage_price_table_gets_one_non_destructive_calculator(): void {
		add_filter( 'nt_smart_site_allowed', '__return_true' );
		$table = '<h3>1. Bảng giá thuê xe máy</h3><div style="overflow-x:auto;"><div class="nt-table-wrap"><table><caption>Giá thuê tham khảo tại Nguyễn Tú</caption><tbody><tr><td>Honda Wave</td><td>150.000đ</td></tr></tbody></table></div></div><p>Nội dung sau bảng giá không thay đổi.</p>';
		$result = NT_Smart_Rental::attach_to_existing_table( $table );
		$this->assertSame( 1, substr_count( $result, 'class="ntsc-rental"' ) );
		$this->assertStringContainsString( '</table></div></div>' . "\n" . '<section class="ntsc-rental"', $result );
		$this->assertStringContainsString( '<p>Nội dung sau bảng giá không thay đổi.</p>', $result );
		$this->assertSame( $result, NT_Smart_Rental::attach_to_existing_table( $result ), 'No duplicate calculator.' );
		$this->assertSame( 'Không phải bảng giá.', NT_Smart_Rental::attach_to_existing_table( 'Không phải bảng giá.' ) );
		remove_filter( 'nt_smart_site_allowed', '__return_true' );
	}

	public function test_read_only_audit_is_bounded_to_twenty_posts(): void {
		$rows = NT_Smart_Audit::latest_posts();
		$this->assertLessThanOrEqual( 20, count( $rows ) );
		foreach ( $rows as $row ) {
			$this->assertArrayHasKey( 'links', $row );
			$this->assertGreaterThanOrEqual( 0, $row['links'] );
		}
	}

	public function test_non_target_localhost_is_blocked_by_default(): void {
		$this->assertFalse( nt_smart_site_allowed() );
	}

	public function test_yoast_and_current_theme_are_authoritative_when_site_is_allowed(): void {
		add_filter( 'ntsc_site_allowed', '__return_true' );
		$original = array(
			'@type'     => array( 'Organization', 'LocalBusiness' ),
			'@id'       => home_url( '/#organization' ),
			'telephone' => '+84900000000',
		);
		$this->assertSame( $original, NT_Smart_Schema::enrich_yoast_organization( $original ) );

		ob_start();
		NT_Smart_Schema::print_fallback();
		$this->assertSame( '', (string) ob_get_clean(), 'Yoast must own the entire graph' );
		remove_filter( 'ntsc_site_allowed', '__return_true' );
	}

	public function test_verified_local_business_properties_do_not_invent_social_proof(): void {
		$p = NT_Smart_Schema::local_properties();
		$this->assertSame( 'PostalAddress', $p['address']['@type'] );
		$this->assertSame( '112 Nguyễn Văn Cừ, Bồ Đề', $p['address']['streetAddress'] );
		$this->assertSame( '09:00', $p['openingHoursSpecification'][0]['opens'] );
		$this->assertSame( '21:00', $p['openingHoursSpecification'][0]['closes'] );
		foreach ( array( 'aggregateRating', 'review', 'offers', 'priceRange', 'geo' ) as $forbidden ) {
			$this->assertArrayNotHasKey( $forbidden, $p );
		}
	}
}
