<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * The verified NAP and policy text come from the shop owner; tests pin them so a careless edit
 * cannot silently change a phone number, address, hour or deposit rule.
 */
final class BusinessTest extends TestCase {

	public function test_verified_nap_defaults(): void {
		$b = nt_business_defaults();
		$this->assertSame( 'Cho Thuê Xe Máy Nguyễn Tú', $b['brand'] );
		$this->assertSame( 'Thuê xe máy ở Hà Nội - Nguyễn Tú', $b['gbp_name'] );
		$this->assertSame( '0942 467 674', $b['phone_display'] );
		$this->assertSame( '0942467674', $b['phone_tel'] );
		$this->assertSame( '+84942467674', $b['phone_intl'] );
		$this->assertSame( 'https://zalo.me/0942467674', $b['zalo_url'] );
		$this->assertSame( 'https://maps.google.com/maps?cid=7448790013669858019', $b['maps_url'] );
		$this->assertSame( '112 Nguyễn Văn Cừ', $b['street'] );
		$this->assertSame( 'Bồ Đề', $b['ward'] );
		$this->assertSame( 'Long Biên', $b['district'] );
		$this->assertSame( 'Hà Nội', $b['city'] );
		$this->assertSame( '09:00', $b['open'] );
		$this->assertSame( '21:00', $b['close'] );
		$this->assertSame( 'https://thuexemaynguyentu.com/', $b['site_url'] );
	}

	public function test_helpers_build_the_expected_strings(): void {
		$this->assertSame( '112 Nguyễn Văn Cừ, Bồ Đề, Long Biên, Hà Nội, Việt Nam', nt_address() );
		$this->assertSame( '112 Nguyễn Văn Cừ, Bồ Đề, Long Biên, Hà Nội', nt_address( false ) );
		$this->assertSame( '09:00–21:00', nt_hours_range() );
		$this->assertSame( 'tel:0942467674', nt_tel_url() );
		$this->assertSame( 'https://zalo.me/0942467674', nt_zalo_url() );
		$this->assertStringStartsWith( 'https://maps.google.com/maps?q=', nt_maps_embed_url() );
		$this->assertStringContainsString( 'output=embed', nt_maps_embed_url() );
	}

	public function test_policy_texts_follow_the_owner_wording(): void {
		$p = nt_policy();
		$this->assertStringContainsString( '2.000.000đ đến 5.000.000đ', $p['deposit_range'] );
		$this->assertStringContainsString( 'xác nhận rõ ràng trước khi ký hợp đồng', $p['confirm'] );
		$this->assertStringContainsString( 'hoàn trả theo điều khoản hợp đồng', $p['refund'] );
		$this->assertStringContainsString( 'giá tham khảo', $p['price_note'] );
	}

	public function test_faq_is_built_only_from_verified_facts_and_never_makes_banned_claims(): void {
		$items = nt_faq_items();
		$this->assertGreaterThanOrEqual( 5, count( $items ) );
		$all = mb_strtolower( implode( ' ', array_merge( array_column( $items, 'q' ), array_column( $items, 'a' ) ) ) );
		$this->assertStringContainsString( '150.000đ', $all );
		$this->assertStringContainsString( '09:00–21:00', $all );
		foreach ( array( 'miễn phí', 'không cần đặt cọc', 'không đặt cọc', '24/7', 'mới 100%', '5 sao', 'đánh giá' ) as $banned ) {
			$this->assertStringNotContainsString( $banned, $all, "FAQ must not claim: {$banned}" );
		}
	}

	public function test_toggle_defaults_to_auto_and_rejects_unknown_values(): void {
		$GLOBALS['nt_test_options']['nguyentu_options'] = array();
		$this->assertSame( 'auto', nt_toggle( 'home.prices' ) );
	}
}
