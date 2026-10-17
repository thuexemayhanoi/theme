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

	protected function tearDown(): void {
		while ( nt_legacy_frame()['depth'] > 0 ) {
			nt_legacy_frame( 'pop' ); // a failed assertion must not leak frames into the next test.
		}
		$GLOBALS['nt_lg_parked'] = array();
		parent::tearDown();
	}

	public function test_lcp_slot_is_claimed_once_until_reset(): void {
		nt_lg_claim_lcp( false, true );
		$this->assertTrue( nt_lg_claim_lcp() );
		$this->assertFalse( nt_lg_claim_lcp() );
		nt_lg_claim_lcp( false, true );
		$this->assertTrue( nt_lg_claim_lcp(), 'a reset starts a new request' );
		nt_lg_claim_lcp( false, true );
	}

	public function test_content_frames_are_a_stack_so_nested_runs_do_not_consume_the_outer_state(): void {
		$this->assertSame( array( 'wrap' => false, 'autop' => false, 'depth' => 0 ), nt_legacy_frame(), 'empty stack reads as defaults' );
		nt_legacy_frame( 'push', array( 'wrap' => true, 'autop' => true ) );
		nt_legacy_frame( 'push', array( 'wrap' => false, 'autop' => false ) );
		$this->assertSame( array( 'wrap' => false, 'autop' => false, 'depth' => 2 ), nt_legacy_frame( 'pop' ), 'inner run pops its own frame' );
		$this->assertSame( array( 'wrap' => true, 'autop' => true, 'depth' => 1 ), nt_legacy_frame(), 'outer frame is intact' );
		$this->assertSame( array( 'wrap' => true, 'autop' => true, 'depth' => 1 ), nt_legacy_frame( 'pop' ) );
		$this->assertSame( array( 'wrap' => false, 'autop' => false, 'depth' => 0 ), nt_legacy_frame( 'pop' ), 'popping an empty stack is harmless' );
	}

	public function test_generated_markup_is_parked_inside_a_run_and_restored_even_when_wpautop_wrapped_the_placeholder(): void {
		$card = "<article>\n\t<a href=\"/x/\">\n\t\t<img alt=\"\">\n\t</a>\n\t<div>Thân</div>\n</article>";
		$this->assertSame( $card, nt_lg_protect( $card ), 'outside a the_content run nothing is parked (direct do_shortcode)' );

		nt_legacy_frame( 'push', array( 'wrap' => true, 'autop' => true ) );
		$placeholder = nt_lg_protect( $card );
		$this->assertStringNotContainsString( 'article', $placeholder );
		$this->assertStringContainsString( 'data-nt-parked', $placeholder );
		$this->assertSame( '  ', nt_lg_protect( '  ' ), 'blank output is returned as is, nothing is parked' );
		$this->assertSame( "<div>a</div>\n" . $card . "\n<div>b</div>", nt_lg_restore( "<div>a</div>\n<p>" . $placeholder . "</p>\n<div>b</div>" ), 'the paragraph wpautop added around the placeholder is dropped' );
		$this->assertSame( 'plain', nt_lg_restore( 'plain' ) );
		nt_legacy_frame( 'pop' );
		$GLOBALS['nt_lg_parked'] = array();
	}

	public function test_card_link_label_prefers_heading_then_text_then_alt_then_url(): void {
		$this->assertSame( 'Xe số', nt_lg_link_label( '<h3>Xe <em>số</em></h3><p>Honda Wave</p>', '<img alt="x">', '/danh-muc/xe-so/' ) );
		$this->assertSame( 'Honda Wave Blade Sirius Cub và nhiều xe', nt_lg_link_label( '<p>Honda Wave Blade Sirius Cub và nhiều xe khác nữa ở đây</p>', '', '/x/' ) );
		$this->assertSame( 'Ảnh Wave', nt_lg_link_label( '', '<img alt="Ảnh Wave" src="a">', '/x/' ) );
		$this->assertSame( 'Xe ga', nt_lg_link_label( '', '', 'https://example.test/danh-muc-san-pham/loai-xe/xe-ga/?a=1#b' ) );
		$this->assertSame( 'Xem chi tiết', nt_lg_link_label( '', '', '/' ) );
	}

	public function test_content_to_text_keeps_text_between_shortcodes_for_excerpts(): void {
		$text = nt_content_to_text( '<!-- wp:paragraph --><p>Xin chào [button text="Gọi" link="x"]Gọi ngay[/button] thế giới</p><!-- /wp:paragraph -->[row][col span="4"]Nội dung <b>đậm</b>[/col][/row] [Cập nhật 2026]' );
		$this->assertSame( 'Xin chào Gọi ngay thế giới Nội dung đậm [Cập nhật 2026]', $text );
	}
}
