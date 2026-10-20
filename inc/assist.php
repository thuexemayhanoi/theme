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
	$text = mb_strtolower( remove_accents( $text ), 'UTF-8' );
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
 * Intent controls the evidence source and never offers unverified inventory.
 *
 * @param string $question User question.
 */
function nt_assist_intent( string $question ): string {
	$q = nt_assist_normalize( $question );
	if ( preg_match( '/\b(con hang|con xe|san xe|ton kho|dat truoc|khuyen mai|giam gia)\b/', $q ) ) {
		return 'availability';
	}
	if ( preg_match( '/\b(coc|the chap|ky quy|hoan coc)\b/', $q ) ) {
		return 'deposit';
	}
	if ( preg_match( '/\b(giay to|thu tuc|ho chieu|passport|cmnd|cccd|can gi)\b/', $q ) ) {
		return 'documents';
	}
	if ( preg_match( '/\b(dia chi|o dau|chi duong|duong di|gio mo cua|may gio|mo cua)\b/', $q ) ) {
		return 'location';
	}
	if ( preg_match( '/\b(giao xe|giao tan|ship xe|giao nhan|nhan xe)\b/', $q ) ) {
		return 'delivery';
	}
	if ( preg_match( '/\b(gia|bao nhieu|chi phi|tien thue|tinh tien|bang gia|thue theo|theo gio)\b/', $q ) ) {
		return 'price';
	}
	if ( preg_match( '/\b(xe dien|xe dap dien|50cc|xe so|xe ga)\b/', $q ) ) {
		// Technical and legal questions need matching public articles, not a sales template.
		if ( preg_match( '/\b(pin|chai|sac|ngap|nuoc|phanh|loi|hong|ip67|bao duong|bang lai|phap luat|quy dinh)\b/', $q ) ) {
			return 'search';
		}
		return 'vehicles';
	}
	return 'search';
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
	$aliases = array(
		'honda wave'              => array( 'wave', 'rsx' ),
		'honda vision'            => array( 'vision' ),
		'honda air blade'         => array( 'air blade', 'airblade' ),
		'honda click yamaha mio'  => array( 'click', 'mio' ),
		'xe tay ga 50cc'          => array( '50cc' ),
		'xe may dien xe dap dien' => array( 'xe may dien', 'xe dap dien', 'xe dien' ),
	);
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
 * Find the duration explicitly named in this question.
 *
 * @param string $question Visitor query.
 */
function nt_assist_period( string $question ): string {
	$q = nt_assist_normalize( $question );
	if ( preg_match( '/\b(thang|month)\b/', $q ) ) {
		return 'month';
	}
	if ( preg_match( '/\b(tuan|week)\b/', $q ) ) {
		return 'week';
	}
	if ( preg_match( '/\b(ngay|day)\b/', $q ) ) {
		return 'day';
	}
	return '';
}

/**
 * Read only published, non-passworded pages with public slugs verified on WP.
 * The admin may change the page URL and get_permalink() follows it.
 *
 * @param string $intent Trusted intent.
 * @return array<int,array{title:string,url:string,excerpt:string}>
 */
function nt_assist_verified_sources( string $intent ): array {
	$id   = 'location' === $intent || 'delivery' === $intent || 'availability' === $intent ? 372 : 484;
	$post = get_post( $id );
	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || '' !== $post->post_password ) {
		return array();
	}
	if ( '1' === (string) get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true ) ) {
		return array();
	}
	$url = get_permalink( $post );
	if ( ! is_string( $url ) || '' === $url ) {
		return array();
	}
	return array(
		array(
			'title'   => get_the_title( $post ),
			'url'     => $url,
			'excerpt' => '',
		),
	);
}

/**
 * Find public, topically relevant content with a bounded WordPress search.
 * Titles and summaries are scored locally; generic word matches must not
 * accidentally return unrelated electric-bike or touring articles.
 *
 * @param string $query Question.
 * @return array<int,array{title:string,url:string,excerpt:string}>
 */
function nt_assist_public_search( string $query ): array {
	$terms = nt_assist_terms( $query );
	if ( array() === $terms ) {
		return array();
	}
	$search  = new WP_Query(
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
	$matches = array();
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
		$title   = get_the_title( $post );
		$summary = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( (string) ( $post->post_excerpt ?: $post->post_content ) ) ) ) );
		$hay     = nt_assist_normalize( $title . ' ' . mb_substr( $summary, 0, 650, 'UTF-8' ) );
		$heading = ' ' . nt_assist_normalize( $title ) . ' ';
		$hits    = 0;
		$score   = 0;
		foreach ( $terms as $term ) {
			if ( false !== strpos( ' ' . $hay . ' ', ' ' . $term . ' ' ) ) {
				++$hits;
				$score += false !== strpos( $heading, ' ' . $term . ' ' ) ? 5 : 1;
			}
		}
		$needed = count( $terms ) >= 3 ? 2 : count( $terms );
		if ( $hits < $needed || $score < 4 ) {
			continue;
		}
		$matches[] = array(
			'score'   => $score,
			'title'   => $title,
			'url'     => $url,
			'excerpt' => mb_substr( $summary, 0, 160, 'UTF-8' ),
		);
	}
	wp_reset_postdata();
	usort(
		$matches,
		static function ( array $a, array $b ): int {
			return $b['score'] <=> $a['score'];
		}
	);
	return array_map(
		static function ( array $item ): array {
			unset( $item['score'] );
			return $item;
		},
		array_slice( $matches, 0, 3 )
	);
}

/**
 * Generate a fact-only answer using centralized, owner-controlled business data.
 *
 * @param string $question Visitor question.
 * @param array  $context  Validated model/duration context.
 */
function nt_assist_fact_answer( string $question, array $context = array() ): string {
	$intent = nt_assist_intent( $question );
	$policy = nt_policy();
	$row    = nt_assist_model_row( $question );
	if ( null === $row && ! empty( $context['model'] ) ) {
		$row = nt_assist_model_row( (string) $context['model'] );
	}
	$period = nt_assist_period( $question );
	if ( '' === $period ) {
		$period = $context['period'] ?? '';
	}
	if ( 'availability' === $intent ) {
		return 'Tôi không có dữ liệu tồn kho, lịch giữ xe hoặc khuyến mãi theo thời gian thực. Vui lòng gọi hoặc nhắn Zalo để cửa hàng kiểm tra trước khi đặt.';
	}
	if ( 'location' === $intent ) {
		return nt_address() . '. Mở cửa ' . nt_hours_range() . ' mỗi ngày.';
	}
	if ( 'deposit' === $intent ) {
		return $row
			? 'Tiền cọc tham khảo ' . $row['name'] . ': ' . $row['deposit'] . '. Giá trị và điều kiện cọc thực tế cần xác nhận theo hợp đồng.'
			: $policy['deposit_range'] . ' Giá trị và điều kiện cọc cần xác nhận theo xe và hợp đồng.';
	}
	if ( 'documents' === $intent ) {
		return 'Thủ tục và phương án giấy tờ bảo đảm được xác nhận theo từng hợp đồng. ' . $policy['documents'];
	}
	if ( 'delivery' === $intent ) {
		return 'Nguyễn Tú hỗ trợ giao nhận xe tùy khu vực Hà Nội. Điểm giao, phí và thời gian cần được cửa hàng xác nhận trước.';
	}
	if ( 'price' === $intent || ( $row && '' !== $period && preg_match( '/\b(thang|tuan|ngay|month|week|day)\b/', nt_assist_normalize( $question ) ) ) ) {
		if ( $row ) {
			if ( in_array( $period, array( 'day', 'week', 'month' ), true ) ) {
				$names = array(
					'day'   => 'ngày',
					'week'  => 'tuần',
					'month' => 'tháng',
				);
				return 'Giá tham khảo ' . $row['name'] . ' theo ' . $names[ $period ] . ': ' . $row[ $period ] . '. ' . $policy['price_note'];
			}
			return 'Giá tham khảo ' . $row['name'] . ': theo ngày ' . $row['day'] . ', theo tuần ' . $row['week'] . ', theo tháng ' . $row['month'] . '. ' . $policy['price_note'];
		}
		return 'Giá thuê tùy mẫu xe và thời hạn. Xem bảng giá tham khảo trong mục Hỏi đáp hoặc liên hệ cửa hàng để xác nhận giá và nhu cầu thuê theo giờ.';
	}
	if ( 'vehicles' === $intent ) {
		return 'Website Nguyễn Tú có thông tin về xe điện, xe đạp điện, xe 50cc, xe số và xe ga. Giá, điều kiện và mẫu xe còn cần cửa hàng xác nhận.';
	}
	return '';
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
	$context = nt_assist_context( $request->get_param( 'context' ) );
	$row     = nt_assist_model_row( $question );
	if ( $row ) {
		$context['model'] = (string) $row['name'];
	}
	$term = nt_assist_period( $question );
	if ( '' !== $term ) {
		$context['period'] = $term;
	}
	$intent  = nt_assist_intent( $question );
	$answer  = nt_assist_fact_answer( $question, $context );
	$sources = '' === $answer ? nt_assist_public_search( $question ) : nt_assist_verified_sources( $intent );
	if ( '' === $answer ) {
		$answer = $sources
			? 'Tôi tìm được một số bài liên quan trên website Nguyễn Tú. Mở tài liệu bên dưới để xem chi tiết; giá và điều kiện thuê cần cửa hàng xác nhận.'
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
		)
	);
}
