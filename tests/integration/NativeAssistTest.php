<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * WordPress integration checks for safe QuickCall and read-only grounded replies.
 */
final class NativeAssistTest extends TestCase {

	protected function tearDown(): void {
		remove_all_filters( 'nguyentu_quickcall_active' );
		remove_all_filters( 'nguyentu_chat_widget_active' );
		parent::tearDown();
	}

	public function test_plugin_precedence_suppresses_all_duplicate_native_bubbles(): void {
		add_filter( 'nguyentu_quickcall_active', '__return_true' );
		add_filter( 'nguyentu_chat_widget_active', '__return_true' );
		$this->assertFalse( nt_native_quickcall_enabled() );
		$this->assertFalse( nt_native_assistant_enabled() );
		$this->assertFalse( nt_show_cta_bar() );
		ob_start();
		nt_assist_render();
		$this->assertSame( '', trim( (string) ob_get_clean() ) );
	}

	public function test_native_quickcall_takes_over_without_old_plugins_or_duplicate_bar(): void {
		add_filter( 'nguyentu_quickcall_active', '__return_false' );
		add_filter( 'nguyentu_chat_widget_active', '__return_false' );
		$this->assertTrue( nt_native_quickcall_enabled() );
		$this->assertTrue( nt_native_assistant_enabled() );
		$this->assertFalse( nt_show_cta_bar() );
		$links = nt_assist_contact_links();
		$this->assertSame( nt_tel_url(), $links['call']['url'] );
		$this->assertSame( nt_zalo_url(), $links['zalo']['url'] );
		$this->assertSame( nt_whatsapp_url(), $links['whatsapp']['url'] );
		$this->assertArrayHasKey( 'maps', $links, 'owner-approved directions link is enabled by default' );
		$this->assertSame( 'https://share.google/KHz2FVQKhtFilL01j', $links['maps']['url'] );
		$this->assertSame( 'https://maps.google.com/maps?cid=7448790013669858019', nt_maps_url(), 'Keep the canonical business Maps profile unchanged.' );
		ob_start();
		nt_assist_render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'id="nt-native-quickcall"', $html );
		$this->assertStringContainsString( 'id="nt-native-assistant"', $html );
		$this->assertStringContainsString( 'aria-controls="nt-assist-dialog"', $html );
		$this->assertStringContainsString( esc_url( nt_zalo_url() ), $html );
		$this->assertStringNotContainsString( 'Google Analytics', $html );
	}

	public function test_answer_uses_only_confirmed_business_facts(): void {
		$this->assertStringContainsString( nt_address(), nt_assist_fact_answer( 'Địa chỉ cửa hàng ở đâu?' ) );
		$this->assertStringContainsString( '2.000.000', nt_assist_fact_answer( 'Tiền đặt cọc bao nhiêu?' ) );
		$this->assertStringContainsString( 'Honda Wave', nt_assist_fact_answer( 'Giá Honda Wave?' ) );
		$this->assertStringContainsString( 'không có dữ liệu tồn kho', nt_assist_fact_answer( 'Có chắc xe Vision còn hàng lúc 17h?' ) );
	}

	public function test_public_search_excludes_private_and_passworded_content(): void {
		add_filter( 'nguyentu_chat_widget_active', '__return_false' );
		$sources = nt_assist_public_search( 'xe' );
		$this->assertLessThanOrEqual( 4, count( $sources ) );
		foreach ( $sources as $s ) {
			$this->assertStringStartsWith( home_url( '/' ), $s['url'] );
			$this->assertNotSame( '', $s['title'] );
			$this->assertLessThanOrEqual( 175, mb_strlen( $s['excerpt'], 'UTF-8' ) );
		}
	}

	public function test_rest_endpoint_blocks_when_legacy_chat_is_running_and_limits_length(): void {
		add_filter( 'nguyentu_chat_widget_active', '__return_true' );
		$req = new WP_REST_Request( 'POST', '/nguyentu/v1/assist' );
		$req->set_param( 'question', 'Giá Honda Wave?' );
		$blocked = nt_assist_rest_answer( $req );
		$this->assertWPError( $blocked );
		$this->assertSame( 'nt_assistant_off', $blocked->get_error_code() );
		remove_all_filters( 'nguyentu_chat_widget_active' );
		add_filter( 'nguyentu_chat_widget_active', '__return_false' );
		$req->set_param( 'question', str_repeat( 'x', 181 ) );
		$bad = nt_assist_rest_answer( $req );
		$this->assertWPError( $bad );
		$req->set_param( 'question', 'Tiền đặt cọc?' );
		$ok = nt_assist_rest_answer( $req );
		$this->assertInstanceOf( WP_REST_Response::class, $ok );
		$this->assertSame( 'tra-cuu-noi-dung', $ok->get_data()['mode'] );
		$this->assertStringContainsString( '2.000.000', $ok->get_data()['answer'] );
	}

	public function test_fact_answers_only_cite_published_relevant_pages(): void {
		add_filter( 'nguyentu_chat_widget_active', '__return_false' );
		$req = new WP_REST_Request( 'POST', '/nguyentu/v1/assist' );
		$req->set_param( 'question', 'Giá thuê xe máy bao nhiêu?' );
		$result = nt_assist_rest_answer( $req );
		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$data = $result->get_data();
		$this->assertSame( 'price', $data['intent'] );
		$this->assertCount( 1, $data['sources'], 'No unrelated electric-bike articles for generic prices.' );
		$this->assertSame( get_permalink( 484 ), $data['sources'][0]['url'] );
		$this->assertSame( array( 'model' => '', 'period' => '' ), $data['context'] );

		$req->set_param( 'question', 'Cửa hàng ở đâu?' );
		$location = nt_assist_rest_answer( $req )->get_data();
		$this->assertSame( get_permalink( 372 ), $location['sources'][0]['url'] );
		$this->assertStringContainsString( nt_address(), $location['answer'] );
	}

	public function test_context_remembers_only_verified_model_and_duration(): void {
		add_filter( 'nguyentu_chat_widget_active', '__return_false' );
		$this->assertSame( 'Honda Vision', nt_assist_model_row( 'Vision' )['name'] );
		$this->assertSame( 'Honda Wave', nt_assist_model_row( 'xe Wave RSX' )['name'] );
		$this->assertNull( nt_assist_model_row( 'Xe Toyota Prius' ) );
		$this->assertSame( 'search', nt_assist_intent( 'Pin xe điện bị chai nghĩa là gì?' ) );
		$this->assertSame( 'availability', nt_assist_intent( 'Vision còn hàng hôm nay không?' ) );

		$request = new WP_REST_Request( 'POST', '/nguyentu/v1/assist' );
		$request->set_param( 'question', 'Giá thuê Honda Vision?' );
		$first = nt_assist_rest_answer( $request )->get_data();
		$this->assertSame( 'Honda Vision', $first['context']['model'] );
		$this->assertSame( get_permalink( 484 ), $first['sources'][0]['url'] );

		$request->set_param( 'question', 'Tháng thì sao?' );
		$request->set_param( 'context', $first['context'] );
		$follow = nt_assist_rest_answer( $request )->get_data();
		$this->assertStringContainsString( 'Honda Vision', $follow['answer'] );
		$this->assertStringContainsString( '1.800.000', $follow['answer'] );
		$this->assertSame( 'month', $follow['context']['period'] );
		$this->assertSame( get_permalink( 484 ), $follow['sources'][0]['url'] );

		$this->assertSame(
			array( 'model' => '', 'period' => '' ),
			nt_assist_context( array( 'model' => 'unverified scooter', 'period' => 'forever', 'passport' => 'sensitive' ) )
		);
	}

	private function assertWPError( $object ): void {
		$this->assertInstanceOf( WP_Error::class, $object );
	}
}
