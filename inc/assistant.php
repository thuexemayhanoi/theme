<?php
/**
 * Local retrieval assistant. Rule-based answers plus published WordPress search; no AI API.
 * No customer messages are persisted or sent off-site.
 *
 * @package NguyenTu
 */
defined( 'ABSPATH' ) || exit;

/** Normalized Vietnamese/English intent text. */
function nt_assistant_normalize( string $text ): string {
	return strtolower( remove_accents( $text ) );
}

/** Only published, public, password-free knowledge can be retrieved. */
function nt_knowledge_public( WP_Post $post ): bool {
	return 'publish' === $post->post_status && '' === $post->post_password && in_array( $post->post_type, array( 'post', 'page', 'product', 'featured_item' ), true );
}

/** Incremental index per saved object; never render shortcodes during indexing. */
function nt_knowledge_update( int $post_id ): void {
	$post = get_post( $post_id );
	if ( ! $post || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( ! nt_knowledge_public( $post ) ) {
		delete_post_meta( $post_id, '_nt_knowledge' );
		delete_post_meta( $post_id, '_nt_link_graph' );
		return;
	}
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( nt_content_to_text( $post->post_content ) ) ) ?? '' );
	update_post_meta( $post_id, '_nt_knowledge', array( 'excerpt' => wp_trim_words( $text, 65, '…' ), 'modified' => $post->post_modified_gmt, 'version' => 1 ) );
	$edges = array();
	$doc = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $post->post_content );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );
	foreach ( $doc->getElementsByTagName( 'a' ) as $anchor ) {
		$url = $anchor->getAttribute( 'href' );
		if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
			$url = home_url( $url );
		}
		if ( wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			continue;
		}
		$edges[] = array( 'url' => esc_url_raw( $url ), 'anchor' => mb_substr( trim( $anchor->textContent ), 0, 160 ) );
		if ( count( $edges ) >= 100 ) {
			break;
		}
	}
	update_post_meta( $post_id, '_nt_link_graph', $edges );
}
add_action( 'save_post', 'nt_knowledge_update', 30 );

/** Background backfill: 20 objects per cron tick, keyset pagination, resumable. */
function nt_knowledge_batch(): void {
	$cursor = (int) get_option( 'nt_knowledge_cursor', 0 );
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- bounded keyset scan for background backfill only.
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_status = 'publish' AND post_password = '' AND post_type IN ('post','page','product','featured_item') ORDER BY ID ASC LIMIT 20", $cursor ) );
	foreach ( $ids as $id ) {
		nt_knowledge_update( (int) $id );
		$cursor = (int) $id;
	}
	update_option( 'nt_knowledge_cursor', $cursor, false );
	if ( count( $ids ) === 20 && ! wp_next_scheduled( 'nt_knowledge_batch' ) ) {
		wp_schedule_single_event( time() + 60, 'nt_knowledge_batch' );
	}
	if ( count( $ids ) < 20 ) {
		update_option( 'nt_knowledge_index_version', 1, false );
	}
}
add_action( 'nt_knowledge_batch', 'nt_knowledge_batch' );

/** Bootstrap once after deployment, without scanning on a visitor request. */
function nt_knowledge_schedule(): void {
	if ( 1 !== (int) get_option( 'nt_knowledge_index_version', 0 ) && ! wp_next_scheduled( 'nt_knowledge_batch' ) ) {
		wp_schedule_single_event( time() + 10, 'nt_knowledge_batch' );
	}
}
add_action( 'init', 'nt_knowledge_schedule' );

/** Bounded local search with synonym expansion. Sources are revalidated on every response. */
function nt_assistant_sources( string $question ): array {
	$query = sanitize_text_field( mb_substr( $question, 0, 160 ) );
	$normalized = nt_assistant_normalize( $query );
	$synonyms = array( 'electric' => 'xe điện', 'scooter' => 'xe ga', 'helmet' => 'mũ bảo hiểm', 'license' => 'giấy phép lái xe', 'passport' => 'hộ chiếu', 'deposit' => 'đặt cọc', 'puncture' => 'thủng lốp' );
	foreach ( $synonyms as $word => $replacement ) {
		if ( str_contains( $normalized, $word ) ) {
			$query = $replacement;
			break;
		}
	}
	// WordPress search is lexical retrieval, not a claim of vector/LLM semantics.
	if ( preg_match( '/mu bao hiem|giay phep|pin|sac|bao duong|dong xuan|pho ha noi/', $normalized, $topic ) ) {
		$query = $topic[0];
	}
	$key = 'nt_search_' . md5( $query . wp_cache_get_last_changed( 'posts' ) );
	$ids = get_transient( $key );
	if ( false === $ids ) {
		$search = new WP_Query( array( 's' => $query, 'post_type' => array( 'post', 'page', 'product', 'featured_item' ), 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => 3, 'no_found_rows' => true, 'fields' => 'ids' ) );
		$ids = $search->posts;
		set_transient( $key, $ids, 5 * MINUTE_IN_SECONDS );
	}
	$sources = array();
	foreach ( (array) $ids as $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || ! nt_knowledge_public( $post ) ) {
			continue;
		}
		$index = get_post_meta( $post->ID, '_nt_knowledge', true );
		$sources[] = array( 'title' => wp_strip_all_tags( get_the_title( $post ) ), 'url' => get_permalink( $post ), 'excerpt' => is_array( $index ) ? $index['excerpt'] : '' );
	}
	if ( ! $sources ) {
		$terms = get_terms( array( 'taxonomy' => 'category', 'search' => $query, 'hide_empty' => true, 'number' => 3 ) );
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$url = get_term_link( $term );
			if ( ! is_wp_error( $url ) ) {
				$sources[] = array( 'title' => $term->name, 'url' => $url, 'excerpt' => wp_strip_all_tags( $term->description ) );
			}
		}
	}
	return $sources;
}

/** Verified commercial answers take precedence over editorial content. */
function nt_assistant_answer( string $question, string $context = '' ): array {
	$q = nt_assistant_normalize( $question );
    if ( preg_match( '/^prices:(vision|wave|air blade|click|mio|50cc|dien)$/', $context, $last ) && preg_match( '/^(con |the |and )?(theo )?(tuan|thang|ngay|week|month|day)( thi sao|ly|\?)?$/', trim( $q, " ?.!" ) ) ) {
        $q = 'gia ' . $last[1] . ' ' . $q;
    }
	$en = 1 === preg_match( '/\b(price|rent|rental|deposit|passport|open|hours|book|delivery|electric|scooter|hello|hi|puncture|breakdown|where|month|week|day)\b/', $q ) && ! preg_match( '/\b(thue|gia|xe|coc|gio|giao|chao|dien)\b/', $q );
	$answer = '';
	$intent = 'search';
	if ( preg_match( '/\b(hong|su co|thung|lop|tai nan|breakdown|puncture|accident)\b/', $q ) ) {
		$intent = 'support';
		$answer = $en ? 'Stop somewhere safe. Contact Nguyễn Tú before authorizing repairs; keep repair receipts. Call ' : 'Dừng xe ở vị trí an toàn. Gọi Nguyễn Tú để thống nhất cách xử lý trước khi sửa; giữ hóa đơn sửa chữa. Điện thoại: ';
		$answer .= nt_business( 'phone_display' ) . '.';
	} elseif ( preg_match( '/\b(gia|bao nhieu|how much|price|cost|theo ngay|theo tuan|theo thang|monthly|weekly|daily)\b/', $q ) ) {
		$intent = 'prices';
		$period = preg_match( '/thang|month/', $q ) ? 'month' : ( preg_match( '/tuan|week/', $q ) ? 'week' : 'day' );
		$matched = array();
		foreach ( nt_price_rows() as $row ) {
			$name = nt_assistant_normalize( $row['name'] );
			$wanted = preg_match( '/vision|wave|air blade|click|mio|50cc|dien|electric/', $q, $model );
			if ( ! $wanted || str_contains( $name, $model[0] ) || ( 'electric' === $model[0] && str_contains( $name, 'dien' ) ) ) {
				$matched[] = $row['name'] . ': ' . $row[ $period ];
			}
		}
		$unit = $en ? array( 'day' => 'day', 'week' => 'week', 'month' => 'month' ) : array( 'day' => 'ngày', 'week' => 'tuần', 'month' => 'tháng' );
		$answer = ( $en ? 'Reference prices per ' : 'Giá tham khảo theo ' ) . $unit[ $period ] . ': ' . implode( '; ', $matched ) . '. ' . ( $en ? 'Confirm the price, deposit and availability before booking.' : nt_policy()['price_note'] );
	} elseif ( preg_match( '/\b(coc|ho chieu|thu tuc|giay to|deposit|passport|documents)\b/', $q ) ) {
		$intent = 'deposit';
		$answer = $en ? 'The motorcycle deposit is 2,000,000–5,000,000 VND depending on the model. Any document guarantee requires a separate lawful agreement. Contact the shop to confirm requirements.' : nt_policy()['deposit_range'] . ' ' . nt_policy()['documents'] . ' Vui lòng liên hệ để xác nhận thủ tục cụ thể.';
	} elseif ( preg_match( '/\b(mo cua|dong cua|may gio|gio|open|hours|closed)\b/', $q ) ) {
		$intent = 'hours';
		$state = nt_hours_state();
		$answer = $en ? ( $state['open'] ? 'Open now.' : 'Closed now.' ) . ' Regular hours: ' . nt_hours_range() . ' (Asia/Ho_Chi_Minh). Contact us to confirm holiday hours.' : $state['label'] . '. Giờ thông thường: ' . nt_hours_range() . ' (giờ Hà Nội).';
	} elseif ( preg_match( '/\b(giao|delivery|deliver|long bien|hoan kiem|pho co|tay ho|ba dinh|o dau|where|dia chi)\b/', $q ) ) {
		$intent = 'location';
		$answer = nt_address() . '. ' . ( $en ? 'Delivery in Hanoi can be arranged for a fee; the location, time and charge must be confirmed with the shop.' : 'Có thể thỏa thuận giao xe tại Hà Nội, có phí. Cần xác nhận địa điểm, thời gian và phí giao trước khi đặt xe.' );
	} elseif ( preg_match( '/\b(dat|lien he|book|contact|available|con xe)\b/', $q ) ) {
		$intent = 'booking';
		$answer = $en ? 'Please tell the shop your dates, preferred model and pickup location by phone or Zalo. This assistant cannot confirm availability or reservations.' : 'Tổng thời gian thuê, dòng xe và địa điểm nhận xe là gì? Vui lòng gửi các thông tin này qua gọi điện hoặc Zalo. Trợ lý chưa thể xác nhận xe còn sẵn hoặc đặt xe thành công.';
	} elseif ( preg_match( '/\b(50cc|xe so|xe ga|xe dien|xe dap dien|electric|scooter|vision|wave)\b/', $q ) ) {
		$intent = 'vehicles';
		$answer = $en ? 'Nguyễn Tú rents motorcycles, scooters, 50cc scooters, electric motorcycles and e-bikes. Which model and rental period do you need? Availability must be confirmed by the shop.' : 'Nguyễn Tú cho thuê xe số, xe ga, xe tay ga 50cc, xe máy điện và xe đạp điện. Khách cần loại xe nào, thuê bao lâu? Tình trạng xe phải được cửa hàng xác nhận.';
	}
	$sources = 'search' === $intent ? nt_assistant_sources( $question ) : array();
	if ( '' === $answer ) {
		$answer = $sources ? ( $en ? 'These published pages may help. Please open the source for details:' : 'Các trang đã xuất bản sau có thể phù hợp. Khách vui lòng mở nguồn để xem chi tiết:' ) : ( $en ? 'I do not have verified information for this question. Please contact Nguyễn Tú: ' : 'Trợ lý chưa có dữ liệu đã xác minh cho câu hỏi này. Vui lòng liên hệ Nguyễn Tú: ' ) . nt_business( 'phone_display' );
	}
	if ( 'prices' === $intent && preg_match( '/vision|wave|air blade|click|mio|50cc|dien/', $q, $selected ) ) {
		$context = 'prices:' . $selected[0];
	} else {
		$context = $intent;
	}
	return array( 'answer' => $answer, 'intent' => $intent, 'sources' => $sources, 'context' => $context, 'hours' => nt_hours_state(), 'mode' => 'local-rules-and-search' );
}

/** Public endpoint: bounded input, per-IP rate limit, no forwarded-IP trust or message logging. */
function nt_assistant_routes(): void {
	register_rest_route( 'nguyentu/v1', '/assistant', array(
		'methods' => 'POST',
		'permission_callback' => '__return_true',
		'callback' => static function ( WP_REST_Request $request ) {
			if ( nt_chat_widget_active() || 'off' === nt_option( 'assistant', 'on' ) ) {
				return new WP_Error( 'nt_disabled', 'Trợ lý đang tạm tắt.', array( 'status' => 503 ) );
			}
			$q = $request->get_param( 'question' );
			if ( ! is_string( $q ) || '' === trim( $q ) || mb_strlen( $q ) > 300 ) {
				return new WP_Error( 'nt_input', 'Vui lòng nhập câu hỏi từ 1 đến 300 ký tự.', array( 'status' => 400 ) );
			}
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
			$key = 'nt_rate_' . hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
			$hits = (int) get_transient( $key );
			if ( $hits >= 20 ) {
				return new WP_Error( 'nt_rate', 'Vui lòng chờ một phút hoặc gọi trực tiếp.', array( 'status' => 429 ) );
			}
			set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );
						$context = $request->get_param( 'context' );
			$context = is_string( $context ) ? sanitize_text_field( mb_substr( $context, 0, 40 ) ) : '';
			$response = rest_ensure_response( nt_assistant_answer( sanitize_text_field( $q ), $context ) );
			$response->header( 'Cache-Control', 'no-store' );
			return $response;
		},
	) );
}
add_action( 'rest_api_init', 'nt_assistant_routes' );
