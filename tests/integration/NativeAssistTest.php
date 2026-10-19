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
		$this->assertArrayNotHasKey( 'maps', $links, 'maps must remain off by default' );
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
		$this->assertSame( '', nt_assist_fact_answer( 'Có chắc xe Vision còn hàng lúc 17h?' ) );
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
		$this->assertSame( get_permalink( 484 ), $ok->get_data()['sources'][0]['url'] );
		$this->assertCount( 1, $ok->get_data()['sources'], 'Deposit must not cite unrelated articles.' );
	}

	private function assertWPError( $object ): void {
		$this->assertInstanceOf( WP_Error::class, $object );
	}
}
