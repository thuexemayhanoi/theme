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

	// ---------------------------------------------------------------------------------------------
	// Issue #62: six chips, contact and address answers, follow-ups, quote-only models, reading suggestions.
	// ---------------------------------------------------------------------------------------------

	private function enable_native_widgets(): void {
		add_filter( 'nguyentu_quickcall_active', '__return_false' );
		add_filter( 'nguyentu_chat_widget_active', '__return_false' );
	}

	/**
	 * One question through the real REST callback, with the context the browser would send.
	 *
	 * @param string               $question Visitor question.
	 * @param array<string,string> $context  Remembered vehicle and period, or empty.
	 * @return array<string,mixed> Response data.
	 */
	private function ask( string $question, array $context = array() ): array {
		$this->enable_native_widgets();
		$request = new WP_REST_Request( 'POST', '/nguyentu/v1/assist' );
		$request->set_param( 'question', $question );
		if ( array() !== $context ) {
			$request->set_param( 'context', $context );
		}
		$response = nt_assist_rest_answer( $request );
		$this->assertInstanceOf( WP_REST_Response::class, $response, $question );
		return $response->get_data();
	}

	public function test_the_six_chips_keep_the_four_old_questions_and_add_contact_and_address(): void {
		$this->enable_native_widgets();
		ob_start();
		nt_assist_render();
		$xp    = nt_it_xpath( (string) ob_get_clean() );
		$chips = $xp->query( '//div[' . nt_it_has_class( 'nt-assist__shortcuts' ) . ']/button' );
		$this->assertSame( 6, $chips->length );
		$seen = array();
		foreach ( $chips as $chip ) {
			$this->assertSame( 'button', $chip->getAttribute( 'type' ), 'a chip never submits a form' );
			$seen[ trim( $chip->textContent ) ] = $chip->getAttribute( 'data-nt-chat-suggest' );
		}
		$this->assertSame(
			array(
				'Bảng giá' => 'Giá thuê Honda Wave',
				'Đặt cọc'  => 'Tiền đặt cọc',
				'Thủ tục'  => 'Thủ tục thuê xe',
				'Xe điện'  => 'Xe máy điện',
				'Liên hệ'  => 'Số điện thoại và Zalo liên hệ cửa hàng?',
				'Địa chỉ'  => 'Cửa hàng Nguyễn Tú ở đâu? Chỉ đường?',
			),
			$seen
		);
	}

	/** @return array<string,array{0:string,1:string,2:int,3:string[]}> Chip => question, intent, cited page, buttons. */
	public static function chip_questions(): array {
		return array(
			'Bảng giá' => array( 'Giá thuê Honda Wave', 'price', 484, array() ),
			'Đặt cọc'  => array( 'Tiền đặt cọc', 'deposit', 484, array() ),
			'Thủ tục'  => array( 'Thủ tục thuê xe', 'documents', 484, array() ),
			'Xe điện'  => array( 'Xe máy điện', 'vehicles', 484, array( 'call', 'zalo' ) ),
			'Liên hệ'  => array( 'Số điện thoại và Zalo liên hệ cửa hàng?', 'contact', 372, array( 'call', 'zalo' ) ),
			'Địa chỉ'  => array( 'Cửa hàng Nguyễn Tú ở đâu? Chỉ đường?', 'location', 372, array( 'maps' ) ),
		);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'chip_questions' )]
	public function test_every_chip_question_is_answered_from_owner_data_with_one_published_source( string $question, string $intent, int $page, array $buttons ): void {
		$data = $this->ask( $question );
		$this->assertSame( array( 'mode', 'intent', 'answer', 'sources', 'context', 'contact', 'actions' ), array_keys( $data ), 'the old keys stay, actions is new' );
		$this->assertSame( 'tra-cuu-noi-dung', $data['mode'] );
		$this->assertSame( $intent, $data['intent'] );
		$this->assertNotSame( '', $data['answer'] );
		$this->assertCount( 1, $data['sources'] );
		$this->assertSame( get_permalink( $page ), $data['sources'][0]['url'] );
		$this->assertSame( $buttons, array_column( $data['actions'], 'type' ) );
		$this->assertSame(
			array(
				'phone' => nt_tel_url(),
				'zalo'  => nt_zalo_url(),
			),
			$data['contact']
		);
	}

	public function test_contact_and_address_answers_use_business_data_and_its_buttons(): void {
		$contact = $this->ask( 'Số điện thoại và Zalo liên hệ cửa hàng?' );
		$this->assertStringContainsString( nt_business( 'phone_display' ), $contact['answer'] );
		$this->assertStringContainsString( nt_hours_range(), $contact['answer'] );
		$this->assertSame(
			array(
				array(
					'type' => 'call',
					'url'  => nt_tel_url(),
				),
				array(
					'type' => 'zalo',
					'url'  => nt_zalo_url(),
				),
			),
			$contact['actions']
		);

		$location = $this->ask( 'Cửa hàng Nguyễn Tú ở đâu? Chỉ đường?' );
		$this->assertStringContainsString( nt_address(), $location['answer'] );
		$this->assertStringContainsString( nt_hours_range(), $location['answer'] );
		$this->assertSame(
			array(
				array(
					'type' => 'maps',
					'url'  => nt_maps_url(),
				),
			),
			$location['actions']
		);
		$this->assertStringNotContainsString( 'share.google', $location['actions'][0]['url'], 'the Quick Call share link is not reused' );
	}

	public function test_follow_up_questions_follow_the_vehicle_through_the_rest_endpoint(): void {
		$first = $this->ask( 'Giá Vision?' );
		$this->assertSame( 'Honda Vision', $first['context']['model'] );
		$this->assertStringContainsString( '1.800.000–2.000.000đ', $first['answer'] );

		$month = $this->ask( 'Tháng thì sao?', $first['context'] );
		$this->assertSame( array( 'model' => 'Honda Vision', 'period' => 'month' ), $month['context'] );
		$this->assertStringContainsString( 'Honda Vision theo tháng: 1.800.000–2.000.000đ', $month['answer'] );
		$this->assertStringNotContainsString( 'theo tuần', $month['answer'] );

		$deposit = $this->ask( 'Cọc bao nhiêu?', $month['context'] );
		$this->assertStringContainsString( 'Honda Vision: 4–5 triệu', $deposit['answer'] );

		$where = $this->ask( 'Địa chỉ ở đâu?', $month['context'] );
		$this->assertStringContainsString( nt_address(), $where['answer'] );
		$this->assertStringNotContainsString( 'Vision', $where['answer'] );
		$this->assertSame( 'Honda Vision', $where['context']['model'], 'an address question leaves the remembered vehicle alone' );

		$wave = $this->ask( 'Wave thì sao?', $month['context'] );
		$this->assertSame( array( 'model' => 'Honda Wave', 'period' => '' ), $wave['context'], 'the month of the Vision question is dropped' );
		$this->assertStringContainsString( '150.000đ', $wave['answer'] );
		$this->assertStringContainsString( '2–3 triệu', $wave['answer'] );
	}

	public function test_a_hostile_context_and_markup_in_the_question_change_nothing(): void {
		$data = $this->ask(
			'<script>alert(1)</script> Cọc bao nhiêu?',
			array(
				'model'    => '<img src=x onerror=alert(1)>',
				'period'   => 'forever',
				'passport' => 'B1234567',
			)
		);
		$this->assertSame( array( 'model' => '', 'period' => '' ), $data['context'] );
		$this->assertStringContainsString( '2.000.000đ đến 5.000.000đ', $data['answer'] );
		$this->assertStringNotContainsString( '<', $data['answer'] );
		$this->assertStringNotContainsString( 'alert', wp_json_encode( $data ) );
	}

	public function test_a_model_without_a_published_price_is_never_given_one(): void {
		foreach ( array( 'xe điện giá bao nhiêu', 'cọc xe điện bao nhiêu', 'xe 50cc thuê tuần giá bao nhiêu' ) as $question ) {
			$data = $this->ask( $question );
			$this->assertSame( array( 'call', 'zalo' ), array_column( $data['actions'], 'type' ), $question );
			$this->assertSame( 1, preg_match( '/liên hệ|xác nhận|báo giá riêng/u', $data['answer'] ), $question );
			if ( false !== strpos( $question, 'điện' ) ) {
				$this->assertSame( 0, preg_match( '/\d{2,3}\.\d{3}|\d\s*triệu/u', $data['answer'] ), $question . ': ' . $data['answer'] );
			}
		}
	}

	public function test_licence_answers_cite_only_pages_that_exist_and_hand_over_to_the_shop(): void {
		$fifty = $this->ask( 'xe 50cc có cần bằng lái không' );
		$this->assertSame( 'licence', $fifty['intent'] );
		$this->assertStringContainsString( '55/VBHN-VPQH', $fifty['answer'] );
		$this->assertStringNotContainsString( 'không cần bằng lái', $fifty['answer'] );
		$this->assertSame( array( get_permalink( 669 ) ), array_column( $fifty['sources'], 'url' ), 'the 50cc page, whose licence wording the owner approved' );
		$this->assertSame( array( 'call', 'zalo' ), array_column( $fifty['actions'], 'type' ) );

		$general = $this->ask( 'thuê xe máy cần bằng lái gì' );
		$this->assertSame( 'licence', $general['intent'] );
		$this->assertSame( array(), $general['sources'], 'no vehicle page is cited for a question that names no vehicle' );
	}

	public function test_a_page_that_is_noindex_passworded_or_not_published_is_never_cited(): void {
		// A page of its own: the seeded pages (contact 372, FAQ 484) are never touched by this test.
		$noindex = '_yoast_wpseo_meta-robots-noindex';
		$id      = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Trang thử nguồn trợ lý',
			)
		);
		try {
			$card = nt_assist_public_post( $id );
			$this->assertSame( get_permalink( $id ), $card['url'] ?? null, 'a public page is cited' );
			$this->assertSame( 'Trang thử nguồn trợ lý', $card['title'] );

			update_post_meta( $id, $noindex, '1' );
			$this->assertNull( nt_assist_public_post( $id ), 'noindex' );
			delete_post_meta( $id, $noindex );
			$this->assertNotNull( nt_assist_public_post( $id ) );

			wp_update_post(
				array(
					'ID'            => $id,
					'post_password' => 'secret',
				)
			);
			$this->assertNull( nt_assist_public_post( $id ), 'password' );
			wp_update_post(
				array(
					'ID'            => $id,
					'post_password' => '',
				)
			);

			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => 'draft',
				)
			);
			$this->assertNull( nt_assist_public_post( $id ), 'draft' );
		} finally {
			wp_delete_post( $id, true );
		}
		$this->assertNull( nt_assist_public_post( $id ), 'a deleted page is gone' );
		$this->assertNull( nt_assist_public_post( 987654321 ), 'an unknown id is not a source' );
	}

	public function test_reading_suggestions_are_few_public_labelled_and_leak_no_ranking_fields(): void {
		$this->enable_native_widgets();
		$ids = array();
		$make = static function ( string $title, array $args = array() ) use ( &$ids ): int {
			$id = wp_insert_post(
				array_merge(
					array(
						'post_type'    => 'post',
						'post_status'  => 'publish',
						'post_title'   => $title,
						'post_content' => 'Zxqvel pinzx chaizx: cách kiểm tra và xử lý. ' . $title,
					),
					$args
				)
			);
			$ids[] = $id;
			return $id;
		};
		try {
			$public = array();
			for ( $i = 1; $i <= 4; $i++ ) {
				$public[] = $make( 'Zxqvel pinzx chaizx hướng dẫn số ' . $i );
			}
			$draft    = $make( 'Zxqvel pinzx chaizx bản nháp', array( 'post_status' => 'draft' ) );
			$private  = $make( 'Zxqvel pinzx chaizx riêng tư', array( 'post_status' => 'private' ) );
			$locked   = $make( 'Zxqvel pinzx chaizx có mật khẩu', array( 'post_password' => 'secret' ) );
			$hidden   = $make( 'Zxqvel pinzx chaizx noindex' );
			update_post_meta( $hidden, '_yoast_wpseo_meta-robots-noindex', '1' );

			$found = nt_assist_public_search( 'zxqvel pinzx chaizx' );
			$this->assertCount( 3, $found, 'at most three suggestions' );
			$allowed = array_map( 'get_permalink', $public );
			foreach ( $found as $item ) {
				$this->assertContains( $item['url'], $allowed );
				$this->assertSame( array( 'excerpt', 'title', 'url' ), $this->sorted_keys( $item ), 'no score, rank text or BM25 value leaves the server' );
				$this->assertLessThanOrEqual( 160, mb_strlen( $item['excerpt'], 'UTF-8' ) );
			}
			foreach ( array( $draft, $private, $locked, $hidden ) as $excluded ) {
				$this->assertNotContains( get_permalink( $excluded ), array_column( $found, 'url' ) );
			}

			$data = $this->ask( 'zxqvel pinzx chaizx' );
			$this->assertSame( 'search', $data['intent'] );
			$this->assertCount( 3, $data['sources'] );
			$this->assertStringContainsString( 'gợi ý đọc', $data['answer'] );
			$this->assertStringContainsString( 'không phải câu trả lời chính thức', $data['answer'] );
			$this->assertSame( array(), $data['actions'], 'suggestions are not a hand-over to the shop' );
		} finally {
			foreach ( $ids as $id ) {
				wp_delete_post( $id, true );
			}
		}
	}

	public function test_a_question_with_no_fact_and_no_source_sends_the_visitor_to_the_shop(): void {
		$data = $this->ask( 'qwzxv thời tiết zzyxw' );
		$this->assertSame( 'search', $data['intent'] );
		$this->assertSame( array(), $data['sources'] );
		$this->assertStringContainsString( 'chưa tìm được nguồn', $data['answer'] );
		$this->assertSame( array( 'call', 'zalo' ), array_column( $data['actions'], 'type' ) );
	}

	/**
	 * @param array<string,mixed> $item Source card.
	 * @return string[]
	 */
	private function sorted_keys( array $item ): array {
		$keys = array_keys( $item );
		sort( $keys );
		return $keys;
	}

	private function assertWPError( $object ): void {
		$this->assertInstanceOf( WP_Error::class, $object );
	}
}
