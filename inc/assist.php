<?php
/**
 * Nguyễn Tú native contact launcher + privacy-friendly WordPress content assistant.
 *
 * Contact buttons share inc/business.php data. No third-party AI, no browser crawler,
 * no chat history, no analytics, and no persistent search index to become stale.
 * The REST endpoint queries published WordPress content only, on demand.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Never render a second floating launcher while Moto QuickCall is activated.
 */
function nt_native_quickcall_enabled(): bool {
	return ! nt_quickcall_active() && 'off' !== nt_toggle( 'native_quickcall' );
}

/**
 * MotoAI takes precedence until its plugin is explicitly deactivated.
 */
function nt_native_assistant_enabled(): bool {
	return ! nt_chat_widget_active() &&
		'off' !== nt_toggle( 'native_assistant' ) &&
		'off' !== nt_option( 'assist_buttons.chat', 'on' );
}

/**
 * Contact button config is presentation only. The links always come from business.php.
 *
 * @param string $button call|zalo|whatsapp|chat|maps.
 */
function nt_assist_button_enabled( string $button ): bool {
	$defaults = array(
		'call'     => 'on',
		'zalo'     => 'on',
		'whatsapp' => 'on',
		'chat'     => 'on',
		'maps'     => 'on',
	);
	return isset( $defaults[ $button ] ) && 'off' !== nt_option( 'assist_buttons.' . $button, $defaults[ $button ] );
}

/**
 * Native contact targets. The chat button triggers the current MotoAI until replaced.
 *
 * @return array<string,array{label:string,icon:string,url:string}>
 */
function nt_assist_contact_links(): array {
	$items = array(
		'call'     => array(
			'label' => 'Gọi điện',
			'icon'  => 'phone',
			'url'   => nt_tel_url(),
		),
		'zalo'     => array(
			'label' => 'Zalo',
			'icon'  => 'chat',
			'url'   => nt_zalo_url(),
		),
		'whatsapp' => array(
			'label' => 'WhatsApp',
			'icon'  => 'chat',
			'url'   => nt_whatsapp_url(),
		),
		'maps'     => array(
			'label' => 'Chỉ đường',
			'icon'  => 'map',
			'url'   => nt_business( 'quick_maps_url' ) ?: nt_maps_url(),
		),
	);
	return array_filter(
		$items,
		static function ( $item, $key ) {
			return nt_assist_button_enabled( (string) $key ) && '' !== $item['url'];
		},
		ARRAY_FILTER_USE_BOTH
	);
}

/**
 * Render native QuickCall and native knowledge assistant, never duplicates of active plugins.
 * The assistant panel is reached from the QuickCall menu when both features are enabled.
 */
function nt_assist_render(): void {
	if ( is_admin() || is_feed() ) {
		return;
	}
	$quickcall = nt_native_quickcall_enabled();
	$assistant = nt_native_assistant_enabled();
	if ( ! $quickcall && ! $assistant ) {
		return;
	}
	?>
	<?php if ( $quickcall ) : ?>
		<div id="nt-native-quickcall" class="nt-qc" role="region" aria-label="Liên hệ nhanh Nguyễn Tú">
			<button type="button" class="nt-qc__launch" data-nt-qc-launch aria-expanded="false" aria-controls="nt-qc-menu" aria-label="Mở các cách liên hệ">
				<?php nt_the_icon( 'phone-call', array( 'size' => 23 ) ); ?>
				<span class="screen-reader-text">Liên hệ nhanh</span>
			</button>
			<div id="nt-qc-menu" class="nt-qc__menu" hidden>
				<?php foreach ( nt_assist_contact_links() as $key => $item ) : ?>
					<a class="nt-qc__link nt-qc__link--<?php echo esc_attr( $key ); ?>" href="<?php echo esc_url( $item['url'] ); ?>" <?php echo 'call' === $key ? '' : 'target="_blank" rel="noopener noreferrer"'; ?>>
						<?php nt_the_icon( $item['icon'], array( 'size' => 19 ) ); ?>
						<span><?php echo esc_html( $item['label'] ); ?></span>
					</a>
				<?php endforeach; ?>
				<?php if ( nt_assist_button_enabled( 'chat' ) && ( $assistant || nt_chat_widget_active() ) ) : ?>
					<button type="button" class="nt-qc__link nt-qc__link--chat" data-nt-chat-open aria-controls="nt-assist-dialog" aria-expanded="false">
						<?php nt_the_icon( 'chat', array( 'size' => 19 ) ); ?>
						<span>Trợ lý tư vấn</span>
					</button>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
	<?php if ( $assistant ) : ?>
		<div id="nt-native-assistant" class="nt-assist" data-nt-assistant data-endpoint="<?php echo esc_url( rest_url( 'nguyentu/v1/assist' ) ); ?>">
			<?php if ( ! $quickcall ) : ?>
				<button type="button" class="nt-assist__launch" data-nt-chat-open aria-controls="nt-assist-dialog" aria-expanded="false">
					<?php nt_the_icon( 'chat', array( 'size' => 21 ) ); ?><span>Hỏi Nguyễn Tú</span>
				</button>
			<?php endif; ?>
			<section id="nt-assist-dialog" class="nt-assist__panel" role="dialog" aria-modal="false" aria-label="Trợ lý tra cứu Nguyễn Tú" hidden>
				<header class="nt-assist__head">
					<div><strong>Trợ lý Nguyễn Tú</strong><span>Tra cứu nội dung công khai, không phải AI tạo sinh</span></div>
					<button type="button" class="nt-assist__close" data-nt-chat-close aria-label="Đóng cửa sổ tư vấn"><?php nt_the_icon( 'close', array( 'size' => 21 ) ); ?></button>
				</header>
				<div class="nt-assist__scroll" data-nt-chat-log role="log" aria-live="polite" aria-relevant="additions">
					<p class="nt-assist__bot">Xin chào! Tôi giúp tìm thông tin thuê xe, giá tham khảo, tiền cọc và thủ tục từ website Nguyễn Tú. Bạn cần tìm gì?</p>
				</div>
				<div class="nt-assist__shortcuts" aria-label="Câu hỏi gợi ý">
					<button type="button" data-nt-chat-suggest="Giá thuê Honda Wave">Bảng giá</button>
					<button type="button" data-nt-chat-suggest="Tiền đặt cọc">Đặt cọc</button>
					<button type="button" data-nt-chat-suggest="Thủ tục thuê xe">Thủ tục</button>
					<button type="button" data-nt-chat-suggest="Xe máy điện">Xe điện</button>
					<button type="button" data-nt-chat-suggest="Số điện thoại và Zalo liên hệ cửa hàng?">Liên hệ</button>
					<button type="button" data-nt-chat-suggest="Cửa hàng Nguyễn Tú ở đâu? Chỉ đường?">Địa chỉ</button>
				</div>
				<form class="nt-assist__form" data-nt-chat-form>
					<label class="screen-reader-text" for="nt-assist-question">Câu hỏi tư vấn</label>
					<input id="nt-assist-question" name="question" maxlength="180" minlength="2" required autocomplete="off" placeholder="Ví dụ: Thuê xe Vision giá bao nhiêu?">
					<button type="submit" aria-label="Gửi câu hỏi"><?php nt_the_icon( 'arrow-right', array( 'size' => 21 ) ); ?></button>
				</form>
				<p class="nt-assist__status" data-nt-chat-status role="status" aria-live="polite" hidden>Đang tìm thông tin phù hợp…</p>
				<div class="nt-assist__privacy">
					<span>Chỉ nhớ chủ đề và tối đa 5 nguồn công khai trong trình duyệt, tự hết hạn. Không lưu lịch sử chat.</span>
					<button type="button" data-nt-chat-clear aria-label="Xóa ghi nhớ tư vấn trên thiết bị">Xóa ghi nhớ</button>
				</div>
				<p class="nt-assist__note">Không nhập giấy tờ hay dữ liệu riêng tư. Giá và xe còn cần xác nhận. <a href="<?php echo esc_url( nt_zalo_url() ); ?>" target="_blank" rel="noopener noreferrer">Nhắn Zalo</a></p>
			</section>
		</div>
	<?php endif; ?>
	<?php
}
add_action( 'wp_footer', 'nt_assist_render', 15 );

/**
 * Register an unauthenticated, read-only, on-demand WordPress search endpoint.
 * No conversation is written into postmeta, options or visitor localStorage.
 */
function nt_assist_rest_routes(): void {
	register_rest_route(
		'nguyentu/v1',
		'/assist',
		array(
			'methods'             => 'POST',
			'callback'            => 'nt_assist_rest_answer',
			'permission_callback' => '__return_true',
			'args'                => array(
				'context'  => array(
					'type'     => 'object',
					'required' => false,
				),
				'question' => array(
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'nt_assist_rest_routes' );

/**
 * Normalize Vietnamese words for intent detection and title-first scoring.
 * The text is never used in the SQL query or exposed as an HTML fragment.
 *
 * @param string $text Public source content or visitor question.
 */
function nt_assist_normalize( string $text ): string {
	$text = remove_accents( $text );
	// A letter sent as base + combining mark (some keyboards do) keeps its mark after remove_accents().
	$text = (string) preg_replace( '/\p{Mn}+/u', '', $text );
	$text = mb_strtolower( $text, 'UTF-8' );
	return trim( (string) preg_replace( '/[^a-z0-9]+/u', ' ', $text ) );
}

/**
 * Short relevant terms (not full sentences) for lightweight title matching.
 *
 * @param string $query Visitor query.
 * @return string[]
 */
function nt_assist_terms( string $query ): array {
	$skip  = array_flip( array( 'anh', 'ban', 'bao', 'can', 'cho', 'co', 'cua', 'da', 'duoc', 'em', 'gi', 'ha', 'hay', 'khi', 'khong', 'la', 'may', 'mot', 'nao', 'nhieu', 'noi', 'o', 'the', 'thi', 'thue', 'toi', 'tu', 'va', 've', 'voi', 'xe' ) );
	$words = explode( ' ', nt_assist_normalize( $query ) );
	$terms = array();
	foreach ( $words as $word ) {
		if ( strlen( $word ) > 1 && ! isset( $skip[ $word ] ) ) {
			$terms[ $word ] = $word;
		}
	}
	return array_slice( array_values( $terms ), 0, 7 );
}

/**
 * Familiar short names for the rows of the owner's price table (normalized, so without accents).
 *
 * @return array<string,string[]> Normalized row name => accepted aliases.
 */
function nt_assist_model_aliases(): array {
	return array(
		'honda wave'              => array( 'wave', 'rsx' ),
		'honda vision'            => array( 'vision' ),
		'honda air blade'         => array( 'air blade', 'airblade' ),
		'honda click yamaha mio'  => array( 'click', 'mio' ),
		'xe tay ga 50cc'          => array( '50cc' ),
		'xe may dien xe dap dien' => array( 'xe may dien', 'xe dap dien', 'xe dien', 'electric bike', 'electric scooter', 'electric motorbike', 'ebike', 'e bike' ),
	);
}

/**
 * Match only price-table vehicles that exist in owner-controlled WordPress data.
 * Accept familiar short model names for conversational follow-up.
 *
 * @param string $question Question text or a previously verified context model.
 * @return array|null A row from the business price table.
 */
function nt_assist_model_row( string $question ): ?array {
	$q       = ' ' . nt_assist_normalize( $question ) . ' ';
	$aliases = nt_assist_model_aliases();
	foreach ( nt_price_rows() as $row ) {
		$name = nt_assist_normalize( (string) $row['name'] );
		foreach ( $aliases[ $name ] ?? array( $name ) as $alias ) {
			if ( false !== strpos( $q, ' ' . $alias . ' ' ) ) {
				return $row;
			}
		}
	}
	return null;
}

/**
 * Whether a normalized question is only a vehicle name plus filler ("Wave thì sao?", "Air Blade?").
 *
 * @param string $normalized Question after nt_assist_normalize().
 */
function nt_assist_is_bare_model( string $normalized ): bool {
	$known = array( 'xe', 'honda', 'yamaha', 'thi', 'sao', 'con', 'vay', 'the', 'nhe', 'nha', 'luon', 'xem', 'nua', 'a', 'ak', 'ah', 'di', 'mau', 'loai', 'dong', 'toi', 'minh', 'em', 'anh', 'chi', 'cho', 'va', 'hong' );
	foreach ( nt_assist_model_aliases() as $aliases ) {
		foreach ( $aliases as $alias ) {
			$known = array_merge( $known, explode( ' ', $alias ) );
		}
	}
	foreach ( nt_price_rows() as $row ) {
		$known = array_merge( $known, explode( ' ', nt_assist_normalize( (string) $row['name'] ) ) );
	}
	return array() === array_diff( array_filter( explode( ' ', $normalized ), 'strlen' ), $known );
}

/**
 * Find the duration explicitly named in this question.
 *
 * @param string $question Visitor query.
 */
function nt_assist_period( string $question ): string {
	$q = nt_assist_normalize( $question );
	if ( preg_match( '/\b(thang|month|monthly)\b/', $q ) ) {
		return 'month';
	}
	if ( preg_match( '/\b(tuan|week|weekly)\b/', $q ) ) {
		return 'week';
	}
	if ( preg_match( '/\b(ngay|day|daily)\b/', $q ) ) {
		return 'day';
	}
	return '';
}

/**
 * The subjects a question touches. A combined question ("giá Vision tháng và cọc") has several. Word lists
 * are matched on accent-free text, so "cọc", "coc" and "cóc" are one word. Nothing here reads the database.
 *
 * @param string     $question Visitor question.
 * @param array|null $named    Price-table row named in the question itself, if any.
 * @return string[] Any of availability, contact, location, price, deposit, licence, documents, delivery, vehicles (that order).
 */
function nt_assist_topics( string $question, ?array $named = null ): array {
	$q     = nt_assist_normalize( $question );
	$words = count( array_filter( explode( ' ', $q ), 'strlen' ) );
	if ( 0 === $words ) {
		return array();
	}
	$has = static function ( string $pattern ) use ( $q ): bool {
		return 1 === preg_match( '/\b(?:' . $pattern . ')\b/', $q );
	};

	$stock = $has( 'con hang|con xe|het xe|het hang|san xe|ton kho|dat truoc|giu xe|giu cho|khuyen mai|giam gia|uu dai|voucher|available|availability|in stock|discount' )
		|| ( $has( 'con' ) && $has( 'khong|ko|chua' ) && ( null !== $named || $has( 'xe|hang|may' ) ) );
	// "gọi" (call) and "gợi ý" (suggestion) lose their marks alike, so a bare "goi" counts only in a very short question.
	$contact = $has( 'lien he|lien lac|lh|so dien thoai|so dt|dien thoai|sdt|hotline|zalo|whatsapp|nhan tin|dat xe|dat lich|gap nhan vien|contact|phone|call|booking|book|reserve|reservation|goi dien|goi cho|goi ngay|goi hotline|goi so|goi lai|goi cua hang|goi shop|goi ben|goi tiem' )
		|| ( $words <= 3 && $has( 'goi' ) && ! $has( 'goi y|goi la' ) );
	$store   = $has( 'cua hang|nguyen tu|chi nhanh|tiem|shop|cho thue|noi thue|ben ban|ben minh|ben em' );
	// "ở đâu" and "chỉ đường" mean the shop when the shop is named, or when the question is short and names no errand or other
	// place ("gửi xe ở đâu" is parking, "chỉ đường đến Văn Miếu" is another destination).
	$away    = $has( 'gui xe|do xang|do xe|sua xe|rua xe|ve sinh xe|mua xe|ban xe|bai do|ben xe|san bay|khach san|nha ga|sieu thi|an uong|nha hang|quan an|ca phe|cafe|atm' );
	$core    = count( array_diff( array_filter( explode( ' ', $q ), 'strlen' ), array( 'cho', 'hoi', 'vay', 'a', 'ak', 'ah', 'nhe', 'nha', 'the', 'oi', 'minh', 'xin', 'ban', 'em', 'anh', 'chi' ) ) );
	$where   = $has( 'dia chi|gio mo cua|gio hoat dong|gio lam viec|mo cua|dong cua|ban do|google map|maps|toa do|address|opening hours|business hours|opening times|open hours|directions|where are you|where is your shop|where is your store|where is your office|how to find you' )
		|| ( $store && $has( 'o dau|nam o dau|nam dau|chi duong|duong di|cach di|cach den|di den|may gio|cho nao|dau vay' ) )
		|| ( ! $store && ! $away && $core <= 4 && ( $has( 'o dau|nam o dau|may gio' ) || ( $has( 'chi duong|duong di|cach di|cach den|di den' ) && ! $has( 'den|toi|ra|vao|qua|ve' ) ) ) );
	$licence = $has( 'bang lai|gplx|giay phep lai|bang a1|a1|do tuoi|bao nhieu tuoi|may tuoi|du tuoi|hoc sinh|duoi 18|18 tuoi|16 tuoi|licen[cs]e|driving permit|minimum age|age limit|how old' )
		// "cần bằng không" asks about the licence only in a rental question: without marks it is also "cân bằng không" (balance).
		|| ( $has( '(?:can|co|chua co|ko co|khong co) bang (?:khong|ko)' ) && $has( 'thue|muon|rent|co can|cho phep' ) )
		|| ( $has( 'phap luat|quy dinh|luat|nghi dinh' ) && $has( 'xe may|xe dien|xe dap dien|50cc|xe gan may|xe ga|xe so|moto|mo to' ) );
	$papers   = $has( 'giay to|thu tuc|ho chieu|passport|cmnd|cccd|can gi|can chuan bi|can mang|visa|dieu kien thue|documents|requirements|paperwork|id card|what do i need' );
	$delivery = $has( 'giao xe|giao tan|ship xe|giao nhan|nhan xe|giao den|giao tai|tra xe|tra tan|delivery|deliver|drop off|pick up|pickup' );
	$other    = $stock || $contact || $where || $licence || $papers || $delivery;

	// Buying or selling a vehicle is not a rental price question.
	$trade   = $has( 'mua|can ban|ban xe|thanh ly|nhuong|tra gop|cu(?! the)|second hand|secondhand' ) && ! $has( 'thue|cho thue|muon|rent' );
	$deposit = ! $trade && $has( 'coc|the chap|ky quy|deposit' );
	$refund  = $deposit && $has( 'hoan|tra lai|lay lai|mat coc' );
	$period  = nt_assist_period( $question );
	$counted = 1 === preg_match( '/\b(?:\d+|theo|thue|moi|tung|mot|nua)\s+(?:ngay|tuan|thang|week|month|day)\b/', $q );
	// "Tháng thì sao?" is a price follow-up; "hoàn cọc sau 3 ngày" and "mở cửa hằng ngày" are not.
	$span = '' !== $period && ! $refund && ( null !== $named || $counted || ( $words <= 4 && ! $other ) );
	// "Chi phí giao xe", "giá giao xe" and "giao xe có mất phí không" ask what delivery costs, not what a vehicle costs. A rental price
	// is still added when it is asked for in so many words ("giá thuê", "bảng giá", "theo tháng") or a vehicle is named.
	$delivery_cost = $delivery && $has( '(?:chi phi|phi|gia|tien|cost|fee|fees|price) (?:giao|ship|nhan|tra|delivery|deliver|drop|pick)|(?:giao xe|giao tan noi|giao tan|giao nhan|ship xe|nhan xe|tra xe|delivery|deliver)(?: [a-z0-9]+){0,3} (?:chi phi|phi|gia|tien|cost|fee|fees|price)' );
	$rental_price  = null !== $named || $has( 'gia thue|bang gia|tien thue|phi thue|tinh tien|theo gio|theo ngay|theo tuan|theo thang|per day|per week|per month' );
	$price         = ! $trade && (
		( $has( 'gia|bang gia|chi phi|tien thue|phi thue|tinh tien|bao gia|thue theo|theo gio|theo ngay|theo tuan|theo thang|price|prices|cost|rate|rates|fee|fees|per day|per week|per month' ) && ! ( $delivery_cost && ! $rental_price ) )
		|| $span
		|| ( ! $other && ! $deposit && ! $has( 'km|kg|nguoi|phut|lit|met|lan|cc|xang|toc do|quang duong' ) && $has( 'bao nhieu|bao nhiu|bn|nhieu tien|nhiu tien|may trieu|how much' ) )
	);

	$technical = $has( 'pin|chai|sac|ngap|nuoc|phanh|loi|hong|ip67|bao duong|bao hanh|sua chua|xang|tiet kiem|toc do|quang duong|bao xa|km' );
	// A vehicle type named in a short question ("Xe máy điện", "thuê xe 50cc") asks for the overview; a longer one is an article question.
	$catalog  = $has( 'co xe de thue|co cho thue xe|co nhung xe|nhung xe nao|loai xe nao|dong xe nao|cac loai xe|cac dong xe|co xe gi|what bikes|which bikes|what scooters' );
	$vehicles = $words <= 6 && ! $trade && ! $technical && ! $licence && ( $catalog || ( $words <= 5 && $has( 'xe dien|xe may dien|xe dap dien|dap dien|50cc|xe so|xe ga|xe tay ga|electric bikes?|electric scooters?|ebikes?|e bikes?|scooters?' ) ) );
	if ( null !== $named && ! ( $other || $price || $deposit || $vehicles ) && nt_assist_is_bare_model( $q ) ) {
		// A vehicle name alone ("Wave thì sao?") asks for that vehicle's price and deposit.
		$price   = true;
		$deposit = true;
	}

	return array_keys(
		array_filter(
			array(
				'availability' => $stock,
				'contact'      => $contact,
				'location'     => $where,
				'price'        => $price,
				'deposit'      => $deposit,
				'licence'      => $licence,
				'documents'    => $papers,
				'delivery'     => $delivery,
				'vehicles'     => $vehicles,
			)
		)
	);
}

/**
 * Intent controls the evidence source and never offers unverified inventory.
 * It is the first (most specific) subject of the question, or "search" when no fact applies.
 *
 * @param string $question User question.
 */
function nt_assist_intent( string $question ): string {
	$topics = nt_assist_topics( $question, nt_assist_model_row( $question ) );
	return $topics[0] ?? 'search';
}

/**
 * Validate the small, non-personal context supplied by the browser. Never trust
 * arbitrary price or policy strings from the client.
 *
 * @param mixed $value Optional REST context.
 * @return array{model:string,period:string}
 */
function nt_assist_context( $value ): array {
	$input = is_array( $value ) ? $value : array();
	$model = sanitize_text_field( (string) ( $input['model'] ?? '' ) );
	$row   = '' !== $model ? nt_assist_model_row( $model ) : null;
	$term  = sanitize_key( (string) ( $input['period'] ?? '' ) );
	return array(
		'model'  => $row ? (string) $row['name'] : '',
		'period' => in_array( $term, array( 'day', 'week', 'month' ), true ) ? $term : '',
	);
}

/**
 * Context after this question: a vehicle named in a price, deposit, stock or licence question replaces the
 * remembered one and starts a fresh price question (the old duration never carries over to another vehicle, but it stays
 * when the same vehicle is named again); a duration
 * counts only in a price question. Other subjects (address, contact, papers, an article search) leave the
 * remembered vehicle and duration alone.
 *
 * @param array{model:string,period:string} $context Validated context from the browser.
 * @param string                            $question Visitor question.
 * @param array|null                        $named    Row named in the question.
 * @param string[]                          $topics   nt_assist_topics() of the question.
 * @return array{model:string,period:string}
 */
function nt_assist_next_context( array $context, string $question, ?array $named, array $topics ): array {
	if ( null !== $named && array() !== array_intersect( $topics, array( 'price', 'deposit', 'vehicles', 'availability', 'licence' ) ) ) {
		if ( '' !== $context['model'] && $context['model'] !== (string) $named['name'] ) {
			$context['period'] = '';
		}
		$context['model'] = (string) $named['name'];
	}
	$period = nt_assist_period( $question );
	if ( '' !== $period && in_array( 'price', $topics, true ) ) {
		$context['period'] = $period;
	}
	return $context;
}

/**
 * One published, non-passworded, indexable page, as a source card. The admin may change the page URL and
 * get_permalink() follows it.
 *
 * @param int $id Post ID.
 * @return array{title:string,url:string,excerpt:string}|null
 */
function nt_assist_public_post( int $id ): ?array {
	$post = get_post( $id );
	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || '' !== $post->post_password ) {
		return null;
	}
	if ( '1' === (string) get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true ) ) {
		return null;
	}
	$url = get_permalink( $post );
	if ( ! is_string( $url ) || '' === $url ) {
		return null;
	}
	return array(
		'title'   => get_the_title( $post ),
		'url'     => $url,
		'excerpt' => '',
	);
}

/**
 * Pages that back a fact answer: the contact page (#372) for contact, address, delivery and stock
 * questions, the FAQ (#484) for prices, deposit, papers and vehicles, and the 50cc product page (#669,
 * whose licence wording the owner approved) for a 50cc licence question. At most two, each only when it is
 * public.
 *
 * @param string[]|string $topics   Subjects of the question (or a single intent).
 * @param string          $question Visitor question.
 * @return array<int,array{title:string,url:string,excerpt:string}>
 */
function nt_assist_verified_sources( $topics, string $question = '' ): array {
	$ids = array();
	foreach ( (array) $topics as $topic ) {
		if ( in_array( $topic, array( 'contact', 'location', 'delivery', 'availability' ), true ) ) {
			$ids[] = 372;
		} elseif ( 'licence' === $topic ) {
			if ( 1 === preg_match( '/\b50cc\b/', nt_assist_normalize( $question ) ) ) {
				$ids[] = 669;
			}
		} else {
			$ids[] = 484;
		}
	}
	$sources = array();
	foreach ( array_slice( array_unique( $ids ), 0, 2 ) as $id ) {
		$item = nt_assist_public_post( (int) $id );
		if ( null !== $item ) {
			$sources[] = $item;
		}
	}
	return $sources;
}

/**
 * Re-rank only already-relevant, bounded WordPress candidates with mini BM25 (ported from PR #49).
 * Title tokens are weighted three times; no visitor-side crawl or persisted index.
 * The overlap gates in nt_assist_rank_sources() run before this function, avoiding poor matches.
 *
 * @param array<int,array<string,mixed>> $matches Gated public candidates.
 * @param string[]                       $terms   Normalized question terms.
 * @return array<int,array<string,mixed>> Highest-scoring first.
 */
function nt_assist_bm25_rerank( array $matches, array $terms ): array {
	if ( count( $matches ) < 2 || array() === $terms ) {
		return $matches;
	}

	$docs  = array();
	$df    = array_fill_keys( $terms, 0 );
	$total = 0;
	foreach ( $matches as $index => $item ) {
		$title = array_filter( explode( ' ', nt_assist_normalize( (string) ( $item['title'] ?? '' ) ) ), 'strlen' );
		$body  = array_filter( explode( ' ', nt_assist_normalize( (string) ( $item['rank_text'] ?? '' ) ) ), 'strlen' );
		$freq  = array();
		foreach ( $title as $token ) {
			$freq[ $token ] = ( $freq[ $token ] ?? 0 ) + 3;
		}
		foreach ( $body as $token ) {
			$freq[ $token ] = ( $freq[ $token ] ?? 0 ) + 1;
		}
		$length = max( 1, 3 * count( $title ) + count( $body ) );
		$total += $length;

		$docs[ $index ] = array(
			'length' => $length,
			'freq'   => $freq,
		);
		foreach ( $terms as $term ) {
			if ( isset( $freq[ $term ] ) ) {
				++$df[ $term ];
			}
		}
	}
	$count = count( $matches );
	$avg   = max( 1, $total / $count );
	$k1    = 1.5;
	$b     = 0.75;

	foreach ( $matches as $index => &$item ) {
		$doc   = $docs[ $index ];
		$score = 0.0;
		foreach ( $terms as $term ) {
			$freq = $doc['freq'][ $term ] ?? 0;
			if ( 0 === $freq ) {
				continue;
			}
			$idf    = log( 1 + ( $count - $df[ $term ] + 0.5 ) / ( $df[ $term ] + 0.5 ) );
			$score += $idf * ( $freq * ( $k1 + 1 ) ) / ( $freq + $k1 * ( 1 - $b + $b * $doc['length'] / $avg ) );
		}
		$item['bm25_score'] = $score;
	}
	unset( $item );

	usort(
		$matches,
		static function ( array $a, array $b ): int {
			return ( $b['bm25_score'] <=> $a['bm25_score'] )
				?: ( $b['score'] <=> $a['score'] )
				?: strcmp( (string) $a['title'], (string) $b['title'] );
		}
	);
	return $matches;
}

/**
 * Keep the candidates that are really about the question, best first, at most three. Titles and summaries
 * are scored locally; a match on only generic words ("điện", "giá") never counts, so an unrelated article
 * is not offered because it shares one everyday word.
 *
 * @param array<int,array{title:string,url:string,summary:string}> $candidates Public candidates from one bounded query.
 * @param string[]                                                 $terms      Normalized question terms.
 * @return array<int,array{title:string,url:string,excerpt:string}>
 */
function nt_assist_rank_sources( array $candidates, array $terms ): array {
	$generic  = array( 'dien', 'gia', 'bang', 'phi', 'tien', 'ngay', 'tuan', 'thang', 'nam' );
	$specific = array_diff( $terms, $generic );
	if ( array() === $specific ) {
		return array();
	}
	$matches = array();
	foreach ( $candidates as $candidate ) {
		$title   = (string) $candidate['title'];
		$summary = (string) $candidate['summary'];
		$hay     = ' ' . nt_assist_normalize( $title . ' ' . mb_substr( $summary, 0, 650, 'UTF-8' ) ) . ' ';
		$heading = ' ' . nt_assist_normalize( $title ) . ' ';
		$hits    = 0;
		$score   = 0;
		$key_hit = false;
		foreach ( $terms as $term ) {
			if ( false !== strpos( $hay, ' ' . $term . ' ' ) ) {
				++$hits;
				$score  += false !== strpos( $heading, ' ' . $term . ' ' ) ? 5 : 1;
				$key_hit = $key_hit || in_array( $term, $specific, true );
			}
		}
		$needed = count( $terms ) >= 3 ? 2 : count( $terms );
		if ( $hits < $needed || $score < 4 || ! $key_hit ) {
			continue;
		}
		$matches[] = array(
			'score'     => $score,
			'title'     => $title,
			'url'       => (string) $candidate['url'],
			'excerpt'   => mb_substr( $summary, 0, 160, 'UTF-8' ),
			'rank_text' => mb_substr( $summary, 0, 650, 'UTF-8' ),
		);
	}
	$matches = nt_assist_bm25_rerank( $matches, $terms );
	return array_map(
		static function ( array $item ): array {
			unset( $item['score'], $item['bm25_score'], $item['rank_text'] );
			return $item;
		},
		array_slice( $matches, 0, 3 )
	);
}

/**
 * Find public, topically relevant content with a bounded WordPress search (12 candidates at most).
 *
 * @param string $query Question.
 * @return array<int,array{title:string,url:string,excerpt:string}>
 */
function nt_assist_public_search( string $query ): array {
	$terms = nt_assist_terms( $query );
	if ( array() === $terms ) {
		return array();
	}
	$search     = new WP_Query(
		array(
			's'                   => implode( ' ', array_slice( $terms, 0, 3 ) ),
			'post_type'           => array( 'post', 'page', 'product' ),
			'post_status'         => 'publish',
			'has_password'        => false,
			'posts_per_page'      => 12,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'suppress_filters'    => true,
		)
	);
	$candidates = array();
	foreach ( $search->posts as $post ) {
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || '' !== $post->post_password ) {
			continue;
		}
		if ( '1' === (string) get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true ) ) {
			continue;
		}
		$url = get_permalink( $post );
		if ( ! is_string( $url ) || '' === $url ) {
			continue;
		}
		$body         = (string) ( $post->post_excerpt ?: $post->post_content );
		$candidates[] = array(
			'title'   => get_the_title( $post ),
			'url'     => $url,
			'summary' => trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( mb_substr( $body, 0, 4000, 'UTF-8' ) ) ) ) ),
		);
	}
	wp_reset_postdata();
	return nt_assist_rank_sources( $candidates, $terms );
}

/**
 * Whether a price-table cell holds an amount (not "Liên hệ" or "Báo giá riêng").
 *
 * @param string $cell Cell text.
 */
function nt_assist_has_amount( string $cell ): bool {
	return 1 === preg_match( '/\d/', $cell );
}

/**
 * The price of one vehicle, read from its row of the owner's price table.
 *
 * @param array  $row    Price-table row.
 * @param string $period day|week|month, or '' for all three.
 */
function nt_assist_price_sentence( array $row, string $period ): string {
	$names = array(
		'day'   => 'ngày',
		'week'  => 'tuần',
		'month' => 'tháng',
	);
	$name  = (string) $row['name'];
	if ( isset( $names[ $period ] ) ) {
		$cell = (string) $row[ $period ];
		return nt_assist_has_amount( $cell )
			? 'Giá tham khảo ' . $name . ' theo ' . $names[ $period ] . ': ' . $cell . '.'
			: 'Giá thuê ' . $name . ' theo ' . $names[ $period ] . ' cần báo giá riêng theo từng mẫu xe; vui lòng liên hệ cửa hàng.';
	}
	if ( ! nt_assist_has_amount( $row['day'] . $row['week'] . $row['month'] ) ) {
		return 'Giá thuê ' . $name . ' theo ngày, tuần và tháng cần liên hệ báo giá theo từng mẫu xe.';
	}
	return 'Giá tham khảo ' . $name . ': theo ngày ' . $row['day'] . ', theo tuần ' . $row['week'] . ', theo tháng ' . $row['month'] . '.';
}

/**
 * Whether the figure the visitor asked for is quote-only in the price table ("Liên hệ", "Báo giá riêng"),
 * so the answer sends them to the shop.
 *
 * @param array|null $row    Vehicle row, or null.
 * @param string     $period day|week|month, or ''.
 * @param string[]   $topics Subjects of the question.
 */
function nt_assist_needs_quote( ?array $row, string $period, array $topics ): bool {
	if ( null === $row ) {
		return false;
	}
	$all     = in_array( 'vehicles', $topics, true );
	$price   = $all || in_array( 'price', $topics, true );
	$deposit = $all || in_array( 'deposit', $topics, true );
	$cells   = isset( $row[ $period ] ) && in_array( $period, array( 'day', 'week', 'month' ), true ) ? (string) $row[ $period ] : $row['day'] . $row['week'] . $row['month'];
	return ( $price && ! nt_assist_has_amount( $cells ) ) || ( $deposit && ! nt_assist_has_amount( (string) $row['deposit'] ) );
}

/**
 * Price and/or deposit of one vehicle, or the general statement when no vehicle is known. Only the owner's
 * price table and policy are quoted; a quote-only cell is never turned into a number.
 *
 * @param string     $question Visitor question (to recognise a refund question).
 * @param array|null $row      Vehicle row, or null.
 * @param string     $period   day|week|month, or ''.
 * @param bool       $price    Whether the price is asked.
 * @param bool       $deposit  Whether the deposit is asked.
 */
function nt_assist_money_text( string $question, ?array $row, string $period, bool $price, bool $deposit ): string {
	$policy = nt_policy();
	$names  = array(
		'day'   => 'ngày',
		'week'  => 'tuần',
		'month' => 'tháng',
	);
	if ( $deposit && ! $price && 1 === preg_match( '/\b(?:hoan|tra lai|lay lai|mat coc)\b/', nt_assist_normalize( $question ) ) ) {
		return $policy['refund'] . ' ' . $policy['confirm'];
	}
	if ( null === $row ) {
		if ( $price && isset( $names[ $period ] ) ) {
			return 'Bạn muốn xem giá theo ' . $names[ $period ] . ' của mẫu xe nào? Bảng giá tham khảo có: ' . implode( ', ', array_column( nt_price_rows(), 'name' ) ) . '.';
		}
		$text = array();
		if ( $price ) {
			$text[] = 'Giá thuê tùy mẫu xe và thời hạn. Xem bảng giá tham khảo trong mục Hỏi đáp hoặc liên hệ cửa hàng để xác nhận giá và nhu cầu thuê theo giờ.';
		}
		if ( $deposit ) {
			$text[] = $policy['deposit_range'] . ' Giá trị và điều kiện cọc cần xác nhận theo xe và hợp đồng.';
		}
		return implode( ' ', $text );
	}
	$name  = (string) $row['name'];
	$parts = array();
	if ( $price ) {
		$parts[] = nt_assist_price_sentence( $row, $period );
	}
	if ( $deposit ) {
		if ( ! nt_assist_has_amount( (string) $row['deposit'] ) ) {
			$parts[] = 'Tiền cọc của ' . $name . ' được xác nhận theo từng mẫu xe; vui lòng liên hệ cửa hàng để biết mức cọc cụ thể.';
		} elseif ( $price ) {
			$parts[] = 'Tiền cọc: ' . $row['deposit'] . '.';
		} else {
			$parts[] = 'Tiền cọc tham khảo ' . $name . ': ' . $row['deposit'] . '. Giá trị và điều kiện cọc thực tế cần xác nhận theo hợp đồng.';
		}
	}
	if ( $price ) {
		$parts[] = $policy['price_note'];
	}
	return implode( ' ', $parts );
}

/**
 * Overview of the vehicle types, with the named vehicle's price and deposit when one is named.
 *
 * @param array|null $row Vehicle row named in the question, or null.
 */
function nt_assist_vehicle_text( ?array $row ): string {
	$lead = 'Website Nguyễn Tú có thông tin về xe điện, xe đạp điện, xe 50cc, xe số và xe ga.';
	if ( null !== $row ) {
		return $lead . ' ' . nt_assist_money_text( '', $row, '', true, true );
	}
	return $lead . ' Bảng giá tham khảo hiện có: ' . implode( ', ', array_column( nt_price_rows(), 'name' ) ) . '. Giá, điều kiện và mẫu xe còn cần cửa hàng xác nhận.';
}

/**
 * Licence and age statement. It carries no claim of its own beyond what the official consolidated text
 * (55/VBHN-VPQH, read on 22 Oct 2026, run 39854185181 of tools/qa/legal-source-check.mjs) says in the
 * articles named; it separates vehicle classes by design speed and displacement instead of by the name
 * "50cc", and hands the vehicle-specific answer to the shop.
 *
 * @param string $question Visitor question.
 */
function nt_assist_licence_text( string $question ): string {
	$text = 'Điều kiện về giấy phép lái xe và độ tuổi phụ thuộc loại xe và dung tích động cơ, không suy ra từ tên gọi như “50cc”. '
		. 'Theo Luật Trật tự, an toàn giao thông đường bộ (văn bản hợp nhất 55/VBHN-VPQH): xe gắn máy chạy động cơ nhiệt '
		. '(vận tốc thiết kế không quá 50 km/h, dung tích không quá 50 cm³) không bắt buộc có giấy phép lái xe, nhưng người lái '
		. 'phải từ đủ 16 tuổi, đủ sức khỏe, biết quy tắc giao thông và có kỹ năng lái (Điều 34, 56, 59); mô tô vượt mức đó cần '
		. 'giấy phép lái xe (hạng A1 đến 125 cm³ hoặc 11 kW, hạng A trên mức đó; từ đủ 18 tuổi) (Điều 57, 59).';
	if ( 1 === preg_match( '/\b(?:xe dien|xe may dien|xe dap dien|dap dien)\b/', nt_assist_normalize( $question ) ) ) {
		$text .= ' Với xe điện, việc là xe đạp điện hay xe máy điện phụ thuộc đặc tính động cơ theo Điều 34.';
	}
	return $text . ' Mẫu xe cụ thể và điều kiện thuê do cửa hàng xác nhận trước khi nhận xe.';
}

/**
 * Contact details from the owner's business data.
 */
function nt_assist_contact_text(): string {
	return 'Liên hệ Nguyễn Tú qua điện thoại hoặc Zalo ' . nt_business( 'phone_display' ) . '. Cửa hàng mở cửa ' . nt_hours_range() . ' hằng ngày. Giá, tiền cọc và việc còn xe hay không cần cửa hàng xác nhận trước khi đặt.';
}

/**
 * Generate a fact-only answer using centralized, owner-controlled business data. An empty string means no
 * fact applies and the question falls to the public search.
 *
 * @param string $question Visitor question.
 * @param array  $context  Validated model/duration context as the browser sent it (before this question).
 */
function nt_assist_fact_answer( string $question, array $context = array() ): string {
	$named  = nt_assist_model_row( $question );
	$topics = nt_assist_topics( $question, $named );
	if ( array() === $topics ) {
		return '';
	}
	// What the browser remembered, as it stands after this question (a new vehicle drops the old duration).
	$context = nt_assist_next_context(
		$context + array(
			'model'  => '',
			'period' => '',
		),
		$question,
		$named,
		$topics
	);
	$row     = $named;
	if ( null === $row && '' !== $context['model'] ) {
		$row = nt_assist_model_row( (string) $context['model'] );
	}
	$period = nt_assist_period( $question ) ?: (string) $context['period'];
	$policy = nt_policy();
	$parts  = array();
	if ( in_array( 'availability', $topics, true ) ) {
		$parts[] = 'Tôi không có dữ liệu tồn kho, lịch giữ xe hoặc khuyến mãi theo thời gian thực. Vui lòng gọi hoặc nhắn Zalo để cửa hàng kiểm tra trước khi đặt.';
	}
	if ( in_array( 'contact', $topics, true ) ) {
		$parts[] = nt_assist_contact_text();
	}
	if ( in_array( 'location', $topics, true ) ) {
		$parts[] = nt_address() . '. Giờ hoạt động niêm yết ' . nt_hours_range() . ' hằng ngày.';
	}
	$price   = in_array( 'price', $topics, true );
	$deposit = in_array( 'deposit', $topics, true );
	if ( $price || $deposit ) {
		$parts[] = nt_assist_money_text( $question, $row, $period, $price, $deposit );
	} elseif ( in_array( 'vehicles', $topics, true ) ) {
		$parts[] = nt_assist_vehicle_text( $named );
	}
	if ( in_array( 'licence', $topics, true ) ) {
		$parts[] = nt_assist_licence_text( $question );
	}
	if ( in_array( 'documents', $topics, true ) ) {
		$parts[] = 'Thủ tục và phương án giấy tờ bảo đảm được xác nhận theo từng hợp đồng. ' . $policy['documents'];
	}
	if ( in_array( 'delivery', $topics, true ) ) {
		$parts[] = 'Nguyễn Tú hỗ trợ giao nhận xe tùy khu vực Hà Nội. Điểm giao, phí và thời gian cần được cửa hàng xác nhận trước.';
	}
	return implode( ' ', $parts );
}

/**
 * Buttons that go with an answer: call and Zalo whenever the shop must confirm or be reached, directions for
 * an address question. URLs come only from the business data and the buttons the owner enabled for
 * QuickCall; the browser accepts nothing else (assets/js/assist.js).
 *
 * @param string[] $topics  Subjects of the question.
 * @param bool     $handoff Whether the answer sends the visitor to call or message the shop.
 * @return array<int,array{type:string,url:string}>
 */
function nt_assist_actions( array $topics, bool $handoff = false ): array {
	$want = array();
	if ( $handoff || array() !== array_intersect( $topics, array( 'contact', 'availability', 'delivery', 'licence' ) ) ) {
		$want = array( 'call', 'zalo' );
	}
	if ( in_array( 'location', $topics, true ) ) {
		$want[] = 'maps';
	}
	$urls    = array(
		'call' => nt_tel_url(),
		'zalo' => nt_zalo_url(),
		'maps' => nt_maps_url(),
	);
	$actions = array();
	foreach ( $want as $type ) {
		if ( nt_assist_button_enabled( $type ) && '' !== $urls[ $type ] ) {
			$actions[] = array(
				'type' => $type,
				'url'  => $urls[ $type ],
			);
		}
	}
	return $actions;
}

/**
 * What a question gets without touching the database: its subjects, the fact answer ('' when no fact applies),
 * the context the browser should remember, and whether a figure is quote-only so the shop has to be asked.
 *
 * @param string                            $question Visitor question.
 * @param array{model:string,period:string} $sent     Validated context the browser sent.
 * @return array{topics:string[],answer:string,context:array{model:string,period:string},quote:bool}
 */
function nt_assist_reply( string $question, array $sent ): array {
	$named   = nt_assist_model_row( $question );
	$topics  = nt_assist_topics( $question, $named );
	$context = nt_assist_next_context( $sent, $question, $named, $topics );
	$row     = $named ?? ( '' !== $context['model'] ? nt_assist_model_row( $context['model'] ) : null );
	return array(
		'topics'  => $topics,
		'answer'  => nt_assist_fact_answer( $question, $sent ),
		'context' => $context,
		'quote'   => nt_assist_needs_quote( $row, nt_assist_period( $question ) ?: $context['period'], $topics ),
	);
}

/**
 * Public read-only endpoint; returns no drafts, private orders or visitor data.
 * The server never stores chat history or browser context in WordPress.
 *
 * @param WP_REST_Request $request Sanitized question and bounded topic context.
 * @return WP_REST_Response|WP_Error Answer with verified links.
 */
function nt_assist_rest_answer( WP_REST_Request $request ) {
	if ( ! nt_native_assistant_enabled() ) {
		return new WP_Error( 'nt_assistant_off', 'Trợ lý hiện không được bật.', array( 'status' => 404 ) );
	}
	$question = trim( sanitize_text_field( (string) $request->get_param( 'question' ) ) );
	if ( mb_strlen( $question, 'UTF-8' ) < 2 || mb_strlen( $question, 'UTF-8' ) > 180 ) {
		return new WP_Error( 'nt_assistant_input', 'Câu hỏi cần dài từ 2 đến 180 ký tự.', array( 'status' => 400 ) );
	}
	$reply   = nt_assist_reply( $question, nt_assist_context( $request->get_param( 'context' ) ) );
	$topics  = $reply['topics'];
	$context = $reply['context'];
	$answer  = $reply['answer'];
	$intent  = $topics[0] ?? 'search';
	$sources = '' === $answer ? nt_assist_public_search( $question ) : nt_assist_verified_sources( $topics, $question );
	$handoff = $reply['quote'] || ( '' === $answer && array() === $sources );
	if ( '' === $answer ) {
		$answer = $sources
			? 'Tôi chưa có câu trả lời xác nhận cho câu hỏi này. Các bài công khai dưới đây có thể liên quan (gợi ý đọc, không phải câu trả lời chính thức); giá và điều kiện thuê cần cửa hàng xác nhận.'
			: 'Tôi chưa tìm được nguồn đủ phù hợp để trả lời. Vui lòng gọi Nguyễn Tú hoặc nhắn Zalo để được hỗ trợ cụ thể.';
	}
	return rest_ensure_response(
		array(
			'mode'    => 'tra-cuu-noi-dung',
			'intent'  => $intent,
			'answer'  => $answer,
			'sources' => $sources,
			'context' => $context,
			'contact' => array(
				'phone' => nt_tel_url(),
				'zalo'  => nt_zalo_url(),
			),
			'actions' => nt_assist_actions( $topics, $handoff ),
		)
	);
}
