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
		'maps'     => 'off',
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
			'url'   => nt_maps_url(),
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
					<button type="button" data-nt-chat-suggest="Giá thuê Honda Wave">Giá thuê</button>
					<button type="button" data-nt-chat-suggest="Tiền đặt cọc">Đặt cọc</button>
					<button type="button" data-nt-chat-suggest="Thủ tục thuê xe">Thủ tục</button>
				</div>
				<form class="nt-assist__form" data-nt-chat-form>
					<label class="screen-reader-text" for="nt-assist-question">Câu hỏi tư vấn</label>
					<input id="nt-assist-question" name="question" maxlength="180" minlength="2" required autocomplete="off" placeholder="Ví dụ: Thuê xe Vision giá bao nhiêu?">
					<button type="submit" aria-label="Gửi câu hỏi"><?php nt_the_icon( 'arrow-right', array( 'size' => 21 ) ); ?></button>
				</form>
				<p class="nt-assist__note">Không nhập số giấy tờ hay dữ liệu riêng tư. Giá và xe còn cần cửa hàng xác nhận. <a href="<?php echo esc_url( nt_zalo_url() ); ?>" target="_blank" rel="noopener noreferrer">Nhắn Zalo</a></p>
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
 * Read actual public posts/pages/products on each question: new posts need no reindex job.
 *
 * @param string $query Visitor's sanitized question.
 * @return array<int,array{title:string,url:string,excerpt:string}>
 */
function nt_assist_public_search( string $query ): array {
	$search  = new WP_Query(
		array(
			's'                   => $query,
			'post_type'           => array( 'post', 'page', 'product' ),
			'post_status'         => 'publish',
			'has_password'        => false,
			'posts_per_page'      => 4,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'suppress_filters'    => true,
		)
	);
	$sources = array();
	foreach ( $search->posts as $post ) {
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || '' !== $post->post_password ) {
			continue;
		}
		$url = get_permalink( $post );
		if ( ! is_string( $url ) || '' === $url ) {
			continue;
		}
		$text      = (string) ( $post->post_excerpt ?: $post->post_content );
		$summary   = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( $text ) ) ) );
		$sources[] = array(
			'title'   => get_the_title( $post ),
			'url'     => $url,
			'excerpt' => mb_substr( $summary, 0, 175 ),
		);
	}
	wp_reset_postdata();
	return $sources;
}

/**
 * Fact-based answer synthesis. Never infer a price for an unlisted model.
 *
 * @param string $question Sanitized query.
 */
function nt_assist_fact_answer( string $question ): string {
	$q      = mb_strtolower( $question, 'UTF-8' );
	$policy = nt_policy();
	if ( preg_match( '/(địa chỉ|ở đâu|giờ mở cửa|mấy giờ|đường đi)/iu', $q ) ) {
		return nt_address() . '. Mở cửa ' . nt_hours_range() . ' mỗi ngày.';
	}
	if ( preg_match( '/(đặt cọc|tiền cọc|thế chấp|hoàn cọc)/iu', $q ) ) {
		return $policy['deposit_range'] . ' Giá trị và điều kiện cọc cần được xác nhận theo xe và hợp đồng.';
	}
	if ( preg_match( '/(thủ tục|giấy tờ|hộ chiếu|passport|cmnd|cccd)/iu', $q ) ) {
		return 'Thủ tục và phương án giấy tờ bảo đảm được xác nhận theo từng hợp đồng. ' . $policy['documents'];
	}
	if ( preg_match( '/(giá|bao nhiêu|chi phí|tiền thuê|thuê theo)/iu', $q ) ) {
		foreach ( nt_price_rows() as $row ) {
			if ( false !== mb_stripos( $question, (string) $row['name'], 0, 'UTF-8' ) ) {
				return 'Giá tham khảo ' . $row['name'] . ': theo ngày ' . $row['day'] . ', theo tuần ' . $row['week'] . ', theo tháng ' . $row['month'] . '. ' . $policy['price_note'];
			}
		}
		return 'Giá thuê tùy loại xe, thời hạn và thời điểm. Vui lòng xem bảng giá tham khảo trên website; gọi điện hoặc nhắn Zalo để xác nhận giá thực tế, nhất là nhu cầu theo giờ.';
	}
	if ( preg_match( '/(giao xe|giao tận|nhận xe|ship xe)/iu', $q ) ) {
		return 'Cửa hàng có hỗ trợ giao nhận theo khu vực Hà Nội. Phí và điểm giao nhận cần được xác nhận trước khi đặt xe.';
	}
	if ( preg_match( '/(xe điện|xe đạp điện|50cc)/iu', $q ) ) {
		return 'Nguyễn Tú có thông tin về xe điện và xe 50cc trên website. Mẫu xe còn, giá thuê và điều kiện sử dụng cần liên hệ cửa hàng xác nhận.';
	}
	return '';
}

/**
 * Attach a directly relevant *published* website page for verified policy answers.
 * Avoid WP_Query's noisy generic matches when the answer comes from owner facts.
 * FAQ #484 covers rates, deposits and paperwork; Contact #372 covers directions.
 *
 * @param string $question Visitor question.
 * @return array<int,array{title:string,url:string,excerpt:string}>
 */
function nt_assist_verified_fact_sources( string $question ): array {
	$q  = mb_strtolower( $question, 'UTF-8' );
	$id = 484; // Existing published FAQ page, not an invented permalink.
	if ( preg_match( '/(địa chỉ|ở đâu|giờ mở cửa|mấy giờ|đường đi|giao xe|giao tận|nhận xe|ship xe)/iu', $q ) ) {
		$id = 372; // Published contact / pickup information.
	} elseif ( preg_match( '/xe đạp điện/iu', $q ) ) {
		$id = 647;
	} elseif ( preg_match( '/xe điện|xe máy điện/iu', $q ) ) {
		$id = 422;
	} elseif ( preg_match( '/50cc/iu', $q ) ) {
		$id = 669;
	}
	$post = get_post( $id );
	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || '' !== $post->post_password ) {
		return array();
	}
	$url = get_permalink( $post );
	if ( ! is_string( $url ) || '' === $url ) {
		return array();
	}
	$text    = (string) ( $post->post_excerpt ?: $post->post_content );
	$summary = trim( preg_replace( '/\\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( $text ) ) ) );
	return array(
		array(
			'title'   => get_the_title( $post ),
			'url'     => $url,
			'excerpt' => mb_substr( $summary, 0, 175 ),
		),
	);
}

/**
 * Secure REST callback: never returns drafts, account/order/customer information.
 *
 * @param WP_REST_Request $request Visitor query.
 * @return WP_REST_Response|WP_Error Reply with current-site source links.
 */
function nt_assist_rest_answer( WP_REST_Request $request ) {
	if ( ! nt_native_assistant_enabled() ) {
		return new WP_Error( 'nt_assistant_off', 'Trợ lý hiện không được bật.', array( 'status' => 404 ) );
	}
	$question = trim( sanitize_text_field( (string) $request->get_param( 'question' ) ) );
	if ( mb_strlen( $question, 'UTF-8' ) < 2 || mb_strlen( $question, 'UTF-8' ) > 180 ) {
		return new WP_Error( 'nt_assistant_input', 'Câu hỏi cần dài từ 2 đến 180 ký tự.', array( 'status' => 400 ) );
	}
	$answer  = nt_assist_fact_answer( $question );
	$sources = '' !== $answer ? nt_assist_verified_fact_sources( $question ) : nt_assist_public_search( $question );
	if ( '' === $answer ) {
		$answer = $sources
			? 'Tôi tìm thấy một số nội dung liên quan trên website. Bạn có thể mở các bài viết bên dưới để đọc thông tin nguồn.'
			: 'Tôi chưa tìm được thông tin đủ chắc chắn để trả lời. Vui lòng gọi Nguyễn Tú hoặc nhắn Zalo để được tư vấn cụ thể.';
	}
	return rest_ensure_response(
		array(
			'mode'    => 'tra-cuu-noi-dung',
			'answer'  => $answer,
			'sources' => $sources,
			'contact' => array(
				'phone' => nt_tel_url(),
				'zalo'  => nt_zalo_url(),
			),
		)
	);
}
