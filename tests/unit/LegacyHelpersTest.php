<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LegacyHelpersTest extends TestCase {

	public function test_bool_values(): void {
		foreach ( array( 'true', '1', 'yes', 'ON', ' True ' ) as $v ) {
			$this->assertTrue( nt_lg_bool( $v ), $v );
		}
		foreach ( array( '', 'false', '0', 'no', 'x' ) as $v ) {
			$this->assertFalse( nt_lg_bool( $v ), $v );
		}
	}

	public function test_visibility_maps_only_known_tokens(): void {
		$this->assertSame( array( 'nt-lg-hide-sm', 'nt-lg-show-md' ), nt_lg_visibility_classes( 'hide-for-small show-for-medium bogus' ) );
		$this->assertSame( array( 'nt-lg-hidden' ), nt_lg_visibility_classes( 'hidden' ) );
		$this->assertSame( array(), nt_lg_visibility_classes( '' ) );
	}

	public function test_font_size_accepts_scale_and_percent_only(): void {
		$this->assertSame( '1.5em', nt_lg_font_size( '1.5' ) );
		$this->assertSame( '120%', nt_lg_font_size( '120%' ) );
		$this->assertSame( '18px', nt_lg_font_size( '18px' ) );
		$this->assertSame( '', nt_lg_font_size( 'x-large' ) );
		$this->assertSame( '', nt_lg_font_size( '1em;color:red' ) );
	}

	public function test_icon_keys(): void {
		$this->assertSame( 'phone', nt_lg_icon_key( 'icon-phone' ) );
		$this->assertSame( 'arrow-right', nt_lg_icon_key( 'ICON-ANGLE-RIGHT' ) );
		$this->assertSame( '', nt_lg_icon_key( 'icon-unknown' ) );
	}

	public function test_id_and_slug_lists(): void {
		$this->assertSame( array( 'ids' => array( 70, 77 ), 'slugs' => array( 'xe-so' ) ), nt_lg_id_slug_list( '70, xe-so ,77,, ../etc' ) );
	}

	public function test_attributes_are_normalised_to_lower_case_and_defaults(): void {
		$a = nt_lg_atts( array( 'span' => '12', 'class' => '' ), array( 'SPAN' => '6', 'evil' => 'x' ), 'col' );
		$this->assertSame( array( 'span' => '6', 'class' => '' ), $a );
	}

	public function test_class_and_id_helpers_escape(): void {
		$this->assertSame( 'a b', nt_lg_class( array( 'a', 'b', 'a', '' ) ) );
		$this->assertSame( ' id="hero"', nt_lg_id_attr( 'hero' ) );
		$this->assertSame( '', nt_lg_id_attr( '"><script>' ) );
		$this->assertSame( ' target="_blank" rel="nofollow noopener"', nt_lg_link_attrs( '_blank', 'nofollow' ) );
		$this->assertSame( '', nt_lg_link_attrs( '' ) );
	}

	public function test_lcp_slot_is_claimed_once(): void {
		$this->assertTrue( nt_lg_claim_lcp() );
		$this->assertFalse( nt_lg_claim_lcp() );
	}
}
