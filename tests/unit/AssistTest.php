<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The rule-based assistant (inc/assist.php) without WordPress: which subjects a question touches, what the
 * answer says, what the browser remembers and which buttons go with it. Nothing here reads a database; the
 * REST endpoint, the published-page sources and the markup are covered by tests/integration/NativeAssistTest.php.
 *
 * Every answer must come from the owner's price table and policy text, so a figure that the table does not
 * hold ("Liên hệ", "Báo giá riêng") may never turn into a number.
 */
final class AssistTest extends TestCase {

	/** Context of a visitor who has not asked anything yet. */
	private static function blank(): array {
		return array(
			'model'  => '',
			'period' => '',
		);
	}

	/**
	 * One turn through the pure part of the REST path. $context is what the browser keeps afterwards, so the
	 * next call carries it exactly as assets/js/assist.js does.
	 *
	 * @param string               $question Visitor question.
	 * @param array<string,string> $context  Browser memory; replaced by the memory after this turn.
	 */
	private function ask( string $question, array &$context ): array {
		$reply   = nt_assist_reply( $question, $context );
		$context = $reply['context'];
		return $reply;
	}

	private function topics( string $question ): array {
		return nt_assist_topics( $question, nt_assist_model_row( $question ) );
	}

	/** A price in digits (150.000đ, 4 triệu): the proof that a quote-only cell was not turned into a number. */
	private const MONEY = '/\d{2,3}\.\d{3}|\d\s*triệu/u';

	// ---------------------------------------------------------------------------------------------
	// The six chips.
	// ---------------------------------------------------------------------------------------------

	/**
	 * Chip question => subjects, text that must / must not be in the answer, buttons.
	 *
	 * @return array<string,array{0:string,1:string[],2:string[],3:string[],4:string[]}>
	 */
	public static function chips(): array {
		return array(
			'Bảng giá'  => array( 'Giá thuê Honda Wave', array( 'price' ), array( '150.000đ', '700.000đ', '900.000–1.200.000đ', 'giá tham khảo' ), array( 'Tiền cọc' ), array() ),
			'Đặt cọc'   => array( 'Tiền đặt cọc', array( 'deposit' ), array( '2.000.000đ đến 5.000.000đ' ), array( 'Honda' ), array() ),
			'Thủ tục'   => array( 'Thủ tục thuê xe', array( 'documents' ), array( 'phương án giấy tờ bảo đảm' ), array( 'Honda' ), array() ),
			'Xe điện'   => array( 'Xe máy điện', array( 'vehicles' ), array( 'xe điện', 'liên hệ báo giá' ), array( 'Tiền cọc: ' ), array( 'call', 'zalo' ) ),
			'Liên hệ'   => array( 'Số điện thoại và Zalo liên hệ cửa hàng?', array( 'contact' ), array( '0942 467 674', '09:00–21:00' ), array( 'Honda', 'Giá tham khảo' ), array( 'call', 'zalo' ) ),
			'Địa chỉ'   => array( 'Cửa hàng Nguyễn Tú ở đâu? Chỉ đường?', array( 'location' ), array( '112 Nguyễn Văn Cừ, Bồ Đề, Long Biên, Hà Nội', '09:00–21:00 hằng ngày' ), array( 'Honda', 'Giá tham khảo' ), array( 'maps' ) ),
		);
	}

	#[DataProvider( 'chips' )]
	public function test_each_chip_gets_a_real_answer_from_owner_data( string $question, array $topics, array $must, array $must_not, array $buttons ): void {
		$context = self::blank();
		$reply   = $this->ask( $question, $context );
		$this->assertSame( $topics, $reply['topics'] );
		foreach ( $must as $text ) {
			$this->assertStringContainsString( $text, $reply['answer'] );
		}
		foreach ( $must_not as $text ) {
			$this->assertStringNotContainsString( $text, $reply['answer'] );
		}
		$this->assertSame( $buttons, array_column( nt_assist_actions( $reply['topics'], $reply['quote'] ), 'type' ) );
	}

	// ---------------------------------------------------------------------------------------------
	// Contact and address: Vietnamese with and without marks, abbreviations, English.
	// ---------------------------------------------------------------------------------------------

	/** @return array<string,array{0:string}> */
	public static function contact_phrases(): array {
		$phrases = array(
			'Số điện thoại và Zalo liên hệ cửa hàng?', 'số điện thoại cửa hàng', 'so dien thoai', 'SĐT shop cho mình xin', 'sdt', 'hotline',
			'Zalo', 'zalo của shop là gì', 'liên hệ', 'lien he', 'cho mình xin liên hệ với bên em', 'gọi điện cho cửa hàng', 'gọi cho bên em',
			'goi', 'gọi ngay', 'nhắn tin cho shop', 'đặt xe', 'đặt lịch thuê xe', 'contact', 'phone number please', 'how can I contact you',
			'WhatsApp', 'booking', 'lh shop thế nào', 'số đt cửa hàng', 'gọi shop kiểu gì', 'cho xin zalo', 'liên hê',
		);
		return array_combine( $phrases, array_map( static fn( string $p ): array => array( $p ), $phrases ) );
	}

	#[DataProvider( 'contact_phrases' )]
	public function test_contact_wording_is_recognised( string $question ): void {
		$this->assertContains( 'contact', $this->topics( $question ), $question );
		$context = self::blank();
		$answer  = $this->ask( $question, $context )['answer'];
		$this->assertStringContainsString( '0942 467 674', $answer );
		$this->assertStringNotContainsString( 'Honda', $answer, 'contact must not pull an old model or price' );
	}

	/** @return array<string,array{0:string}> */
	public static function location_phrases(): array {
		$phrases = array(
			'địa chỉ', 'dia chi cua hang', 'địa chỉ ở đâu', 'cửa hàng ở đâu', 'Cua hang Nguyen Tu o dau', 'Cửa hàng Nguyễn Tú ở đâu? Chỉ đường?',
			'chỉ đường', 'chỉ đường đến cửa hàng', 'mấy giờ mở cửa', 'giờ mở cửa', 'giờ làm việc của shop', 'shop mở cửa đến mấy giờ', 'bản đồ',
			'google maps', 'nằm ở đâu', 'where is your shop', 'what are your opening hours', 'address', 'directions',
			'cửa hàng nằm đâu', 'shop ở đâu vậy', 'dc shop ở đâu', 'chủ nhật có mở cửa không', 'mấy h mở cửa', 'cho hỏi ở đâu vậy', 'bạn ở đâu', 'thuê xe ở đâu',
		);
		return array_combine( $phrases, array_map( static fn( string $p ): array => array( $p ), $phrases ) );
	}

	#[DataProvider( 'location_phrases' )]
	public function test_address_wording_is_recognised( string $question ): void {
		$this->assertContains( 'location', $this->topics( $question ), $question );
		$context = self::blank();
		$answer  = $this->ask( $question, $context )['answer'];
		$this->assertStringContainsString( '112 Nguyễn Văn Cừ, Bồ Đề, Long Biên, Hà Nội', $answer );
		$this->assertStringNotContainsString( 'Honda', $answer, 'an address must not pull an old model or price' );
	}

	/**
	 * Words that look like contact or address but are not. Each must stay out of both subjects.
	 *
	 * @return array<string,array{0:string}>
	 */
	public static function not_contact_or_address(): array {
		$phrases = array(
			'gợi ý xe cho 2 người đi chơi', 'xe tay ga 50cc gọi là gì', 'gửi xe máy ở đâu khi đi Văn Miếu', 'chỉ đường đến Văn Miếu',
			'gửi xe ở Văn Miếu', 'pin xe điện bị chai', 'Giá Vision?', 'Bảng giá', 'Tiền đặt cọc', 'thuê xe máy ở Hà Nội', 'xe này cân bằng không', 'xe cân bằng tốt không',
			'gửi xe ở đâu', 'đổ xăng ở đâu', 'sửa xe ở đâu', 'rửa xe ở đâu', 'mua xe ở đâu', 'chỉ đường đến sân bay',
			'xe điện đi được bao xa mỗi lần sạc', 'con nhỏ 5 tuổi đi cùng được không', 'Vision có tiết kiệm xăng không',
		);
		return array_combine( $phrases, array_map( static fn( string $p ): array => array( $p ), $phrases ) );
	}

	#[DataProvider( 'not_contact_or_address' )]
	public function test_similar_words_do_not_trigger_contact_or_address( string $question ): void {
		$topics = $this->topics( $question );
		$this->assertNotContains( 'contact', $topics, $question );
		$this->assertNotContains( 'location', $topics, $question );
	}

	// ---------------------------------------------------------------------------------------------
	// Price and deposit together; the figures are the owner's table, nothing else.
	// ---------------------------------------------------------------------------------------------

	/** @return array<string,array{0:string,1:string[],2:string[]}> */
	public static function money_questions(): array {
		return array(
			// Cases 1 and 2 are the expectations of PR #49 (combined price and deposit, deposit of another model).
			'Vision tháng và cọc'    => array( 'Giá Vision tháng và cọc bao nhiêu?', array( 'Honda Vision', 'theo tháng: 1.800.000–2.000.000đ', 'Tiền cọc: 4–5 triệu' ), array( '700.000đ', 'theo ngày', 'theo tuần' ) ),
			'cọc Air Blade'          => array( 'Cọc Honda Air Blade?', array( 'Honda Air Blade', '3 triệu' ), array( 'theo ngày' ) ),
			'1 tuần Wave và cọc'     => array( '1 tuần Wave và cọc', array( 'Honda Wave theo tuần: 700.000đ', 'Tiền cọc: 2–3 triệu' ), array( '150.000đ' ) ),
			'Vision cọc mấy triệu'   => array( 'Vision cọc mấy triệu', array( 'Honda Vision: 4–5 triệu' ), array( 'theo ngày' ) ),
			'viết tắt bn'            => array( 'Wave bn 1 tuần', array( 'Honda Wave theo tuần: 700.000đ' ), array( 'Tiền cọc' ) ),
			'English price'          => array( 'Vision price per month', array( 'Honda Vision theo tháng: 1.800.000–2.000.000đ' ), array( 'Tiền cọc' ) ),
			'English deposit'        => array( 'how much is the deposit for Vision', array( 'Honda Vision: 4–5 triệu' ), array( 'theo ngày' ) ),
			'gõ sai bao nhiu'        => array( 'wave bao nhiu', array( 'Honda Wave: theo ngày 150.000đ' ), array( 'Vision' ) ),
			'gõ tắt nhiu tiền'       => array( 'wave nhiu tiền', array( 'Honda Wave: theo ngày 150.000đ' ), array( 'Vision' ) ),
			'giá wave bn'            => array( 'giá wave bn', array( 'Honda Wave: theo ngày 150.000đ' ), array( 'Tiền cọc' ) ),
			'cọc ntn, không xe'      => array( 'cọc ntn', array( '2.000.000đ đến 5.000.000đ' ), array( 'Honda' ) ),
			'giá chung, không xe'    => array( 'Giá thuê xe máy bao nhiêu?', array( 'Giá thuê tùy mẫu xe và thời hạn' ), array( 'Honda' ) ),
		);
	}

	#[DataProvider( 'money_questions' )]
	public function test_price_and_deposit_come_from_the_price_table( string $question, array $must, array $must_not ): void {
		$context = self::blank();
		$answer  = $this->ask( $question, $context )['answer'];
		foreach ( $must as $text ) {
			$this->assertStringContainsString( $text, $answer );
		}
		foreach ( $must_not as $text ) {
			$this->assertStringNotContainsString( $text, $answer );
		}
	}

	public function test_a_price_answer_carries_the_reference_price_note(): void {
		$context = self::blank();
		$this->assertStringContainsString( nt_policy()['price_note'], $this->ask( 'Giá Honda Wave?', $context )['answer'] );
	}

	public function test_refund_question_quotes_the_contract_wording_and_ignores_the_word_day(): void {
		$context = self::blank();
		$this->ask( 'Giá Vision?', $context );
		$reply = $this->ask( 'hoàn cọc sau mấy ngày', $context );
		$this->assertStringContainsString( nt_policy()['refund'], $reply['answer'] );
		$this->assertSame( '', $reply['context']['period'], '"mấy ngày" in a refund question is not a rental period' );
		$this->assertSame( 'Honda Vision', $reply['context']['model'] );
	}

	public function test_questions_about_distance_weight_people_or_fuel_are_not_price_questions(): void {
		foreach ( array( 'xe chở được bao nhiêu người', 'wave chạy được bao nhiêu km', 'vision tốn bao nhiêu xăng', 'xe nặng bao nhiêu kg', 'pin đi được bao nhiêu km mỗi lần sạc', 'loại xe nào tiết kiệm xăng' ) as $question ) {
			$this->assertSame( array(), $this->topics( $question ), $question );
		}
		$this->assertSame( array( 'price' ), $this->topics( 'bao nhiêu một ngày' ) );
	}

	public function test_a_question_about_which_vehicles_exist_gets_the_overview_without_prices(): void {
		foreach ( array( 'có xe để thuê không', 'bên bạn có những xe nào', 'có những loại xe nào' ) as $question ) {
			$context = self::blank();
			$reply   = $this->ask( $question, $context );
			$this->assertSame( array( 'vehicles' ), $reply['topics'], $question );
			$this->assertStringContainsString( 'Bảng giá tham khảo hiện có: Honda Wave', $reply['answer'] );
			$this->assertDoesNotMatchRegularExpression( self::MONEY, $reply['answer'], $question );
		}
	}

	public function test_the_vehicle_overview_gives_way_to_the_subject_the_visitor_asked_about(): void {
		// Found by the differential run against main: "thuê xe 50cc cần gì" was answered with the overview, the 50cc
		// price and deposit and then the papers. A vehicle type next to another subject names the vehicle, not the overview.
		$expected = array(
			'thuê xe 50cc cần gì'        => array( 'documents' ),
			'xe 50cc cần giấy tờ gì'     => array( 'documents' ),
			'còn xe 50cc không'          => array( 'availability' ),
			'xe 50cc đặt trước được không' => array( 'availability' ),
			'xe 50cc giao tận nơi không' => array( 'delivery' ),
			'xe 50cc bằng lái'           => array( 'licence' ),
			'cọc xe 50cc bao nhiêu'      => array( 'deposit' ),
			'giá xe máy điện'            => array( 'price' ),
			'giá xe số'                  => array( 'price' ),
		);
		foreach ( $expected as $question => $topics ) {
			$context = self::blank();
			$reply   = $this->ask( $question, $context );
			$this->assertSame( $topics, $reply['topics'], $question );
			$this->assertStringNotContainsString( 'Website Nguyễn Tú có thông tin về', $reply['answer'], $question );
		}

		$context = self::blank();
		$this->assertDoesNotMatchRegularExpression( self::MONEY, $this->ask( 'thuê xe 50cc cần gì', $context )['answer'], 'papers are not a price answer' );
		$this->assertSame( '', $context['model'], 'a question about papers does not choose a vehicle' );
		$this->assertDoesNotMatchRegularExpression( self::MONEY, $this->ask( 'còn xe 50cc không', $context )['answer'], 'stock is not a price answer' );
		$this->assertSame( 'Xe tay ga 50cc', $context['model'], 'a stock question names the vehicle the next question is about' );

		// A vehicle type on its own still asks for the overview, with the price and deposit of that vehicle.
		foreach ( array( 'Xe máy điện', 'thuê xe 50cc', 'xe tay ga 50cc', 'xe số', 'xe ga' ) as $question ) {
			$this->assertSame( array( 'vehicles' ), $this->topics( $question ), $question );
		}
	}

	public function test_buying_a_vehicle_is_not_a_rental_price_question(): void {
		foreach ( array( 'giá mua xe điện mới', 'giá xe điện cũ', 'cần bán xe wave' ) as $question ) {
			$this->assertNotContains( 'price', $this->topics( $question ), $question );
		}
	}

	// ---------------------------------------------------------------------------------------------
	// Follow-up questions keep, replace or drop the vehicle and the period.
	// ---------------------------------------------------------------------------------------------

	public function test_follow_up_flow_price_month_deposit(): void {
		$context = self::blank();

		$first = $this->ask( 'Giá Vision?', $context );
		$this->assertStringContainsString( 'theo ngày 200.000đ, theo tuần 1.000.000đ, theo tháng 1.800.000–2.000.000đ', $first['answer'] );
		$this->assertSame( array( 'model' => 'Honda Vision', 'period' => '' ), $context );

		$month = $this->ask( 'Tháng thì sao?', $context );
		$this->assertStringContainsString( 'Honda Vision theo tháng: 1.800.000–2.000.000đ', $month['answer'] );
		$this->assertStringNotContainsString( 'theo tuần', $month['answer'] );
		$this->assertSame( array( 'model' => 'Honda Vision', 'period' => 'month' ), $context );

		$deposit = $this->ask( 'Cọc bao nhiêu?', $context );
		$this->assertStringContainsString( 'Tiền cọc tham khảo Honda Vision: 4–5 triệu', $deposit['answer'] );
		$this->assertStringNotContainsString( 'Wave', $deposit['answer'] );
	}

	public function test_switching_vehicle_never_keeps_the_old_vehicle_or_period(): void {
		$context = self::blank();
		$this->ask( 'Giá Vision tháng', $context );
		$this->assertSame( 'month', $context['period'] );

		$wave = $this->ask( 'Wave thì sao?', $context );
		$this->assertStringContainsString( 'Honda Wave: theo ngày 150.000đ, theo tuần 700.000đ, theo tháng 900.000–1.200.000đ', $wave['answer'] );
		$this->assertStringContainsString( 'Tiền cọc: 2–3 triệu', $wave['answer'] );
		$this->assertStringNotContainsString( 'Vision', $wave['answer'] );
		$this->assertSame( array( 'model' => 'Honda Wave', 'period' => '' ), $context, 'the month belonged to the Vision question' );

		$week = $this->ask( 'Tuần thì sao?', $context );
		$this->assertStringContainsString( 'Honda Wave theo tuần: 700.000đ', $week['answer'] );

		$blade = $this->ask( 'Air Blade?', $context );
		$this->assertStringContainsString( 'Honda Air Blade: theo ngày 200.000đ', $blade['answer'] );
		$this->assertStringContainsString( 'Tiền cọc: 3 triệu', $blade['answer'] );
		$this->assertStringNotContainsString( 'Wave', $blade['answer'] );
		$this->assertSame( '', $context['period'] );
	}

	public function test_naming_the_remembered_vehicle_again_keeps_the_period(): void {
		// Codex review of the pull request: "Vision cọc bao nhiêu?" must not forget that the visitor was looking at the month.
		$context = self::blank();
		$this->ask( 'Giá Vision tháng', $context );
		$deposit = $this->ask( 'Vision cọc bao nhiêu?', $context );
		$this->assertStringContainsString( 'Honda Vision: 4–5 triệu', $deposit['answer'] );
		$this->assertSame( array( 'model' => 'Honda Vision', 'period' => 'month' ), $context );

		$price = $this->ask( 'Giá bao nhiêu?', $context );
		$this->assertStringContainsString( 'Honda Vision theo tháng: 1.800.000–2.000.000đ', $price['answer'] );
		$this->assertStringNotContainsString( 'theo tuần', $price['answer'] );

		$this->ask( 'Wave thì sao?', $context );
		$this->assertSame( array( 'model' => 'Honda Wave', 'period' => '' ), $context, 'another vehicle still starts without the old period' );
	}

	/** @return array<string,array{0:string}> */
	public static function delivery_cost_questions(): array {
		$phrases = array( 'Chi phí giao xe bao nhiêu?', 'Giá giao xe bao nhiêu', 'Giao xe có mất phí không?', 'Phí giao xe tận nơi?', 'phí ship xe tới khách sạn', 'delivery fee', 'how much is delivery' );
		return array_combine( $phrases, array_map( static fn( string $p ): array => array( $p ), $phrases ) );
	}

	#[DataProvider( 'delivery_cost_questions' )]
	public function test_a_delivery_cost_question_does_not_quote_the_remembered_rental_price( string $question ): void {
		// Codex review of the pull request: "Chi phí giao xe bao nhiêu?" asks about delivery, not about the Vision month.
		$context = array(
			'model'  => 'Honda Vision',
			'period' => 'month',
		);
		$reply = $this->ask( $question, $context );
		$this->assertSame( array( 'delivery' ), $reply['topics'], $question );
		$this->assertStringNotContainsString( '1.800.000', $reply['answer'] );
		$this->assertStringNotContainsString( 'Honda Vision', $reply['answer'] );
		$this->assertStringContainsString( 'cần được cửa hàng xác nhận', $reply['answer'] );
		$this->assertSame( array( 'model' => 'Honda Vision', 'period' => 'month' ), $context, 'the remembered vehicle and period are left alone' );
	}

	/** @return array<string,array{0:string}> */
	public static function rental_and_delivery_questions(): array {
		$phrases = array( 'Giá thuê và phí giao xe', 'Giá Vision và phí giao xe?', 'Giá thuê Vision và phí giao xe?', 'Bảng giá và chi phí giao xe', 'giá wave và giao xe', 'Giá Vision và giao xe tận nơi được không?' );
		return array_combine( $phrases, array_map( static fn( string $p ): array => array( $p ), $phrases ) );
	}

	#[DataProvider( 'rental_and_delivery_questions' )]
	public function test_a_question_that_asks_for_both_the_rental_price_and_delivery_gets_both( string $question ): void {
		$context = self::blank();
		$reply   = $this->ask( $question, $context );
		$this->assertContains( 'price', $reply['topics'], $question );
		$this->assertContains( 'delivery', $reply['topics'], $question );
		$this->assertMatchesRegularExpression( '/Giá thuê (?:trong bảng là giá tham khảo|tùy mẫu xe và thời hạn)/u', $reply['answer'], 'the rental part: a vehicle price, or the general statement when no vehicle is known' );
		$this->assertStringContainsString( 'giao nhận xe', $reply['answer'], 'the delivery part' );
	}

	public function test_address_and_contact_between_price_questions_pull_no_old_price(): void {
		$context = self::blank();
		$this->ask( 'Giá Vision?', $context );

		$where = $this->ask( 'Địa chỉ ở đâu?', $context );
		$this->assertStringContainsString( '112 Nguyễn Văn Cừ', $where['answer'] );
		$this->assertStringNotContainsString( 'Vision', $where['answer'] );
		$this->assertStringNotContainsString( '1.800.000', $where['answer'] );

		$call = $this->ask( 'Số điện thoại?', $context );
		$this->assertStringNotContainsString( 'Vision', $call['answer'] );
		$this->assertStringNotContainsString( '1.000.000', $call['answer'] );

		$this->assertSame( 'Honda Vision', $context['model'], 'the remembered vehicle survives an unrelated question' );
		$this->assertStringContainsString( 'Honda Vision theo tháng: 1.800.000–2.000.000đ', $this->ask( 'Tháng thì sao?', $context )['answer'] );
	}

	public function test_a_period_without_any_vehicle_asks_which_one_and_remembers_the_period(): void {
		$context = self::blank();
		$ask     = $this->ask( 'Tháng thì sao?', $context );
		$this->assertStringContainsString( 'mẫu xe nào', $ask['answer'] );
		$this->assertDoesNotMatchRegularExpression( self::MONEY, $ask['answer'] );
		$this->assertSame( array( 'model' => '', 'period' => 'month' ), $context );

		$vision = $this->ask( 'Vision', $context );
		$this->assertStringContainsString( 'Honda Vision theo tháng: 1.800.000–2.000.000đ', $vision['answer'] );
		$this->assertStringContainsString( 'Tiền cọc: 4–5 triệu', $vision['answer'] );
	}

	public function test_clearing_memory_forgets_the_vehicle(): void {
		$context = self::blank();
		$this->ask( 'Giá Air Blade?', $context );
		$cleared = self::blank();
		$answer  = $this->ask( 'Cọc bao nhiêu?', $cleared )['answer'];
		$this->assertStringContainsString( '2.000.000đ đến 5.000.000đ', $answer );
		$this->assertStringNotContainsString( 'Air Blade', $answer );
	}

	public function test_a_follow_up_without_a_remembered_vehicle_asks_nothing_unverified(): void {
		$context = self::blank();
		$reply   = $this->ask( 'Cọc bao nhiêu?', $context );
		$this->assertSame( array( 'deposit' ), $reply['topics'] );
		$this->assertSame( self::blank(), $context );
	}

	// ---------------------------------------------------------------------------------------------
	// Quote-only cells (electric, 50cc week) and licence: no invented figure, no legal guess.
	// ---------------------------------------------------------------------------------------------

	/** @return array<string,array{0:string,1:string}> */
	public static function quote_only(): array {
		return array(
			'giá xe điện'       => array( 'xe điện giá bao nhiêu', 'liên hệ báo giá' ),
			'giá electric bike' => array( 'electric bike price', 'liên hệ báo giá' ),
			'cọc xe điện'       => array( 'cọc xe điện bao nhiêu', 'xác nhận theo từng mẫu xe' ),
			'50cc thuê tuần'    => array( 'xe 50cc thuê tuần giá bao nhiêu', 'báo giá riêng' ),
			'chip Xe máy điện'  => array( 'Xe máy điện', 'liên hệ báo giá' ),
		);
	}

	#[DataProvider( 'quote_only' )]
	public function test_a_quote_only_figure_never_becomes_a_number( string $question, string $says ): void {
		$context = self::blank();
		$reply   = $this->ask( $question, $context );
		$this->assertStringContainsString( $says, $reply['answer'] );
		$this->assertTrue( $reply['quote'] );
		$this->assertSame( array( 'call', 'zalo' ), array_column( nt_assist_actions( $reply['topics'], $reply['quote'] ), 'type' ), 'the visitor is sent to the shop' );
		if ( 1 === preg_match( '/điện|electric/u', $question ) ) {
			$this->assertDoesNotMatchRegularExpression( self::MONEY, $reply['answer'], 'no price for a model without a published price' );
		}
	}

	public function test_a_figure_that_is_in_the_table_needs_no_quote(): void {
		$context = self::blank();
		$reply   = $this->ask( 'cọc xe 50cc bao nhiêu', $context );
		$this->assertStringContainsString( 'Xe tay ga 50cc: 4 triệu', $reply['answer'] );
		$this->assertFalse( $reply['quote'] );
		$this->assertSame( array(), nt_assist_actions( $reply['topics'], $reply['quote'] ) );
	}

	/** @return array<string,array{0:string}> */
	public static function licence_questions(): array {
		$phrases = array( 'xe 50cc có cần bằng lái không', 'bao nhiêu tuổi được thuê xe 50cc', 'xe đạp điện có cần bằng lái không', 'thuê xe máy cần bằng lái gì', 'do I need a license for 50cc', 'thuê xe điện cần bằng không', 'có cần bằng không' );
		return array_combine( $phrases, array_map( static fn( string $p ): array => array( $p ), $phrases ) );
	}

	#[DataProvider( 'licence_questions' )]
	public function test_licence_answer_cites_the_official_text_and_hands_over_to_the_shop( string $question ): void {
		$context = self::blank();
		$reply   = $this->ask( $question, $context );
		$this->assertSame( array( 'licence' ), $reply['topics'], 'licence is not mixed with the vehicle overview' );
		$this->assertStringContainsString( '55/VBHN-VPQH', $reply['answer'] );
		$this->assertStringContainsString( 'không suy ra từ tên gọi như “50cc”', $reply['answer'] );
		$this->assertStringContainsString( 'cửa hàng xác nhận', $reply['answer'] );
		$this->assertStringNotContainsString( 'không cần bằng lái', $reply['answer'] );
		$this->assertSame( array( 'call', 'zalo' ), array_column( nt_assist_actions( $reply['topics'], $reply['quote'] ), 'type' ) );
	}

	public function test_electric_licence_answer_separates_bicycle_from_motorbike_by_the_engine(): void {
		$context = self::blank();
		$answer  = $this->ask( 'xe đạp điện có cần bằng lái không', $context )['answer'];
		$this->assertStringContainsString( 'xe đạp điện hay xe máy điện phụ thuộc đặc tính động cơ', $answer );
	}

	// ---------------------------------------------------------------------------------------------
	// Everything else a visitor can ask about the shop.
	// ---------------------------------------------------------------------------------------------

	public function test_stock_and_promotion_questions_admit_there_is_no_live_data(): void {
		foreach ( array( 'còn Vision không?', 'xe còn không', 'is the Wave available tomorrow', 'có khuyến mãi không' ) as $question ) {
			$context = self::blank();
			$reply   = $this->ask( $question, $context );
			$this->assertContains( 'availability', $reply['topics'], $question );
			$this->assertStringContainsString( 'không có dữ liệu tồn kho', $reply['answer'] );
			$this->assertSame( array( 'call', 'zalo' ), array_column( nt_assist_actions( $reply['topics'], $reply['quote'] ), 'type' ), $question );
		}
	}

	public function test_delivery_question_promises_nothing_about_area_or_fee(): void {
		$context = self::blank();
		$answer  = $this->ask( 'giao xe tận nơi được không', $context )['answer'];
		$this->assertStringContainsString( 'cần được cửa hàng xác nhận', $answer );
		$this->assertDoesNotMatchRegularExpression( '/\d\s*km|miễn phí|free/ui', $answer );
	}

	public function test_documents_question_uses_the_owner_wording_only(): void {
		$context = self::blank();
		$answer  = $this->ask( 'what documents do I need', $context )['answer'];
		$this->assertStringContainsString( nt_policy()['documents'], $answer );
		$this->assertStringNotContainsString( 'không cần đặt cọc', $answer );
	}

	public function test_combined_address_and_contact_gets_both_and_all_three_buttons(): void {
		$context = self::blank();
		$reply   = $this->ask( 'địa chỉ và số điện thoại', $context );
		$this->assertSame( array( 'contact', 'location' ), $reply['topics'] );
		$this->assertStringContainsString( '0942 467 674', $reply['answer'] );
		$this->assertStringContainsString( '112 Nguyễn Văn Cừ', $reply['answer'] );
		$this->assertSame( array( 'call', 'zalo', 'maps' ), array_column( nt_assist_actions( $reply['topics'] ), 'type' ) );
	}

	public function test_address_and_price_in_one_question_answers_both(): void {
		$context = self::blank();
		$reply   = $this->ask( 'Địa chỉ và giá Vision tháng', $context );
		$this->assertSame( array( 'location', 'price' ), $reply['topics'] );
		$this->assertStringContainsString( '112 Nguyễn Văn Cừ', $reply['answer'] );
		$this->assertStringContainsString( 'Honda Vision theo tháng: 1.800.000–2.000.000đ', $reply['answer'] );
	}

	public function test_a_question_without_a_fact_falls_to_the_public_search(): void {
		foreach ( array( 'pin xe điện bị chai', 'thời tiết hôm nay thế nào', 'gợi ý xe cho 2 người đi chơi', '' ) as $question ) {
			$context = self::blank();
			$reply   = $this->ask( $question, $context );
			$this->assertSame( array(), $reply['topics'], $question );
			$this->assertSame( '', $reply['answer'], $question );
		}
	}

	public function test_the_answer_never_repeats_the_question_text(): void {
		$context = self::blank();
		$reply   = $this->ask( '<script>alert(1)</script> Giá Wave <img src=x onerror=alert(2)>', $context );
		$this->assertSame( array( 'price' ), $reply['topics'] );
		$this->assertStringNotContainsString( '<', $reply['answer'] );
		$this->assertStringNotContainsString( 'alert', $reply['answer'] );
	}

	// ---------------------------------------------------------------------------------------------
	// Buttons: only the owner's phone, Zalo and Maps profile.
	// ---------------------------------------------------------------------------------------------

	public function test_buttons_use_the_verified_business_urls(): void {
		$buttons = array_column( nt_assist_actions( array( 'contact', 'location' ) ), 'url', 'type' );
		$this->assertSame( 'tel:0942467674', $buttons['call'] );
		$this->assertSame( 'https://zalo.me/0942467674', $buttons['zalo'] );
		$this->assertSame( 'https://maps.google.com/maps?cid=7448790013669858019', $buttons['maps'], 'the canonical business Maps profile, not the QuickCall share link' );
	}

	/** @return array<string,array{0:string[],1:bool,2:string[]}> */
	public static function button_rules(): array {
		return array(
			'contact'            => array( array( 'contact' ), false, array( 'call', 'zalo' ) ),
			'location'           => array( array( 'location' ), false, array( 'maps' ) ),
			'availability'       => array( array( 'availability' ), false, array( 'call', 'zalo' ) ),
			'delivery'           => array( array( 'delivery' ), false, array( 'call', 'zalo' ) ),
			'licence'            => array( array( 'licence' ), false, array( 'call', 'zalo' ) ),
			'price with figures' => array( array( 'price' ), false, array() ),
			'price quote-only'   => array( array( 'price' ), true, array( 'call', 'zalo' ) ),
			'documents'          => array( array( 'documents' ), false, array() ),
			'nothing found'      => array( array(), true, array( 'call', 'zalo' ) ),
		);
	}

	#[DataProvider( 'button_rules' )]
	public function test_which_buttons_go_with_which_answer( array $topics, bool $handoff, array $expected ): void {
		$this->assertSame( $expected, array_column( nt_assist_actions( $topics, $handoff ), 'type' ) );
	}

	// ---------------------------------------------------------------------------------------------
	// What the browser may send back, and the helpers underneath.
	// ---------------------------------------------------------------------------------------------

	public function test_browser_context_is_reduced_to_a_verified_vehicle_and_a_period(): void {
		$this->assertSame( array( 'model' => 'Honda Vision', 'period' => 'month' ), nt_assist_context( array( 'model' => 'Vision', 'period' => 'month', 'passport' => 'X1234567' ) ) );
		$this->assertSame( self::blank(), nt_assist_context( array( 'model' => '<script>alert(1)</script>', 'period' => 'forever' ) ) );
		$this->assertSame( self::blank(), nt_assist_context( 'model=Wave' ) );
		$this->assertSame( self::blank(), nt_assist_context( null ) );
	}

	public function test_vehicle_names_resolve_to_price_table_rows_only(): void {
		$this->assertSame( 'Honda Vision', nt_assist_model_row( 'Vision' )['name'] );
		$this->assertSame( 'Honda Wave', nt_assist_model_row( 'xe Wave RSX' )['name'] );
		$this->assertSame( 'Honda Air Blade', nt_assist_model_row( 'airblade' )['name'] );
		$this->assertSame( 'Honda Click / Yamaha Mio', nt_assist_model_row( 'Mio' )['name'] );
		$this->assertSame( 'Xe tay ga 50cc', nt_assist_model_row( 'xe 50cc' )['name'] );
		$this->assertSame( 'Xe máy điện / Xe đạp điện', nt_assist_model_row( 'electric bike' )['name'] );
		$this->assertNull( nt_assist_model_row( 'Xe Toyota Prius' ) );
		$this->assertNull( nt_assist_model_row( 'wavefront' ) );
	}

	public function test_text_is_compared_without_marks_case_or_combining_characters(): void {
		$this->assertSame( 'dia chi cua hang o dau', nt_assist_normalize( 'ĐỊA CHỈ cửa hàng Ở ĐÂU?' ) );
		$this->assertSame( 'dia chi', nt_assist_normalize( "Đi\u{0323}a chi\u{0309}" ), 'letters typed as base + combining mark' );
		$this->assertSame( '', nt_assist_normalize( '?!…' ) );
		$this->assertSame( 'month', nt_assist_period( 'Giá theo tháng' ) );
		$this->assertSame( 'week', nt_assist_period( 'tuan' ) );
		$this->assertSame( 'day', nt_assist_period( 'per day' ) );
		$this->assertSame( '', nt_assist_period( 'Giá Vision' ) );
	}

	public function test_intent_is_the_first_subject_or_search(): void {
		$this->assertSame( 'search', nt_assist_intent( 'Pin xe điện bị chai nghĩa là gì?' ) );
		$this->assertSame( 'availability', nt_assist_intent( 'Vision còn hàng hôm nay không?' ) );
		$this->assertSame( 'contact', nt_assist_intent( 'Zalo' ) );
		$this->assertSame( 'location', nt_assist_intent( 'địa chỉ' ) );
		$this->assertSame( 'price', nt_assist_intent( 'Giá Honda Wave?' ) );
	}

	// ---------------------------------------------------------------------------------------------
	// Reading suggestions: at most three, relevant, no ranking fields; mini BM25 ported from PR #49.
	// ---------------------------------------------------------------------------------------------

	/** @return array<int,array{title:string,url:string,summary:string}> */
	private static function articles(): array {
		return array(
			array(
				'title'   => 'Giới thiệu dịch vụ',
				'url'     => 'https://example.test/gioi-thieu/',
				'summary' => 'Thông tin cửa hàng. Pin và chai là những từ khóa phụ trong danh sách dài.',
			),
			array(
				'title'   => 'Pin xe điện bị chai: dấu hiệu và cách kiểm tra',
				'url'     => 'https://example.test/pin-xe-dien-bi-chai/',
				'summary' => 'Hướng dẫn kiểm tra và xử lý pin xe điện bị chai.',
			),
			array(
				'title'   => 'Xe điện giá rẻ: có nên mua không',
				'url'     => 'https://example.test/xe-dien-gia-re/',
				'summary' => 'So sánh giá xe điện mới và cũ.',
			),
		);
	}

	public function test_bm25_prefers_the_exact_relevant_title(): void {
		// The test of PR #49, unchanged: same title weight, the second document answers the question.
		$documents = array(
			array(
				'title'     => 'Giới thiệu dịch vụ',
				'excerpt'   => 'Thông tin cửa hàng',
				'rank_text' => 'Pin và chai là những từ khóa phụ trong danh sách dài',
				'score'     => 6,
			),
			array(
				'title'     => 'Pin xe điện bị chai',
				'excerpt'   => 'Kiểm tra pin xe điện',
				'rank_text' => 'Hướng dẫn kiểm tra và xử lý pin xe điện bị chai',
				'score'     => 6,
			),
		);
		$ranked = nt_assist_bm25_rerank( $documents, array( 'pin', 'chai' ) );
		$this->assertSame( 'Pin xe điện bị chai', $ranked[0]['title'] );
		$this->assertCount( 2, $ranked );
	}

	public function test_bm25_leaves_one_or_no_candidate_alone(): void {
		$one = array(
			array(
				'title'     => 'A',
				'rank_text' => 'a',
				'score'     => 1,
			),
		);
		$this->assertSame( $one, nt_assist_bm25_rerank( $one, array( 'pin' ) ) );
		$this->assertSame( array(), nt_assist_bm25_rerank( array(), array( 'pin' ) ) );
	}

	public function test_relevant_article_comes_first_and_ranking_fields_never_leak(): void {
		$found = nt_assist_rank_sources( self::articles(), nt_assist_terms( 'pin xe điện bị chai' ) );
		$this->assertSame( 'Pin xe điện bị chai: dấu hiệu và cách kiểm tra', $found[0]['title'] );
		foreach ( $found as $item ) {
			$this->assertSame( array( 'excerpt', 'title', 'url' ), $this->sorted_keys( $item ) );
		}
	}

	public function test_generic_words_alone_never_make_an_article_relevant(): void {
		$this->assertSame( array( 'gia', 'dien' ), nt_assist_terms( 'giá xe điện' ) );
		$this->assertSame( array(), nt_assist_rank_sources( self::articles(), nt_assist_terms( 'giá xe điện' ) ) );
		$this->assertSame( array(), nt_assist_rank_sources( self::articles(), nt_assist_terms( 'thời tiết hôm nay thế nào' ) ) );
	}

	public function test_at_most_three_suggestions_are_offered(): void {
		$many = array();
		for ( $i = 1; $i <= 6; $i++ ) {
			$many[] = array(
				'title'   => 'Pin xe điện bị chai số ' . $i,
				'url'     => 'https://example.test/pin-' . $i . '/',
				'summary' => 'Cách kiểm tra pin xe điện bị chai.',
			);
		}
		$this->assertCount( 3, nt_assist_rank_sources( $many, nt_assist_terms( 'pin xe điện bị chai' ) ) );
	}

	public function test_question_terms_drop_filler_and_cap_at_seven(): void {
		$this->assertSame( array( 'dien', 'pin', 'bi', 'chai' ), nt_assist_terms( 'Thuê xe điện: pin bị chai là gì?' ) );
		$this->assertCount( 7, nt_assist_terms( 'alpha beta gamma delta epsilon zeta eta theta iota kappa' ) );
		$this->assertSame( array(), nt_assist_terms( 'thuê xe và' ), 'filler only' );
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
}
