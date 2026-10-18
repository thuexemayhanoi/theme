<?php
/**
 * Shared contact UI, display preference and maintenance hooks.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/** Optional WhatsApp: empty until explicitly configured and verified by the owner. */
function nt_whatsapp_url(): string {
	$url = (string) nt_option( 'business.whatsapp_url', '' );
	return 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) && 'wa.me' === wp_parse_url( $url, PHP_URL_HOST ) ? $url : '';
}

/** Whether the new contact component owns the floating position. */
function nt_native_contact_enabled(): bool {
	return ! nt_quickcall_active() && 'off' !== nt_option( 'cta_bar', 'auto' );
}

/** Assets + same-origin endpoints, no third-party runtime. */
function nt_autopilot_assets(): void {
	wp_enqueue_style( 'nt-autopilot', nt_asset_uri( 'assets/css/autopilot.css' ), array( 'nt-main' ), nt_asset_ver( 'assets/css/autopilot.css' ) );
	wp_enqueue_script(
		'nt-autopilot',
		nt_asset_uri( 'assets/js/autopilot.js' ),
		array(),
		nt_asset_ver( 'assets/js/autopilot.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
	wp_add_inline_script(
		'nt-autopilot',
		'window.ntAutopilot=' . wp_json_encode(
			array(
				'hours'       => nt_autopilot_endpoint( 'hours' ),
				'hours_label' => 'Giờ thường lệ ' . nt_hours_range() . ' — Liên hệ để xác nhận',
				'assistant'   => nt_autopilot_endpoint( 'assistant' ),
			)
		) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'nt_autopilot_assets', 30 );

/** Set preference before CSS paints. Storage may be blocked; auto still works. */
function nt_color_mode_head(): void {
	?>
	<script id="nt-color-init">(function(){var m='auto';try{m=localStorage.getItem('nt-color-mode')||'auto';}catch(e){}if(!/^(auto|light|dark)$/.test(m))m='auto';document.documentElement.dataset.ntMode=m;document.documentElement.dataset.ntTheme=m==='auto'?(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):m;})();</script>
	<?php
}
add_action( 'wp_head', 'nt_color_mode_head', 0 );

/** Shared UI: only one native chat, and one contact owner. */
function nt_autopilot_widgets(): void {
	get_template_part( 'template-parts/autopilot/widgets' );
}
add_action( 'wp_footer', 'nt_autopilot_widgets', 5 );

/** Remove scheduled jobs on theme switch; retain rebuildable knowledge and business settings. */
function nt_autopilot_unschedule(): void {
	wp_clear_scheduled_hook( 'nt_knowledge_batch' );
}
add_action( 'switch_theme', 'nt_autopilot_unschedule' );

/** Map editorial topic to the category hierarchy, with no content rewriting. */
function nt_cluster_navigation(): void {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	$terms = get_the_category();
	if ( ! $terms ) {
		return;
	}
	echo '<nav class="nt-cluster" aria-label="Chủ đề bài viết"><strong>Đọc theo chủ đề:</strong> ';
	$seen = array();
	foreach ( $terms as $term ) {
		foreach ( array_merge( array_reverse( get_ancestors( $term->term_id, 'category' ) ), array( $term->term_id ) ) as $id ) {
			if ( isset( $seen[ $id ] ) || (int) get_option( 'default_category' ) === (int) $id ) {
				continue;
			}
			$seen[ $id ] = true;
			$url         = get_category_link( $id );
			if ( ! is_wp_error( $url ) ) {
				printf( '<a href="%s">%s</a> ', esc_url( $url ), esc_html( get_cat_name( $id ) ) );
			}
		}
	}
	$commercial = nt_cluster_commercial_link( get_the_ID() );
	if ( $commercial ) {
		printf( '<a class="nt-cluster__service" href="%s">%s</a>', esc_url( $commercial['url'] ), esc_html( $commercial['label'] ) );
	}
	echo '</nav>';
}


/**
 * Same-origin REST endpoint; draft previews carry their existing sandbox token.
 *
 * @param string $route Public route name.
 * @return string
 */
function nt_autopilot_endpoint( string $route ): string {
	$url = rest_url( 'nguyentu/v1/' . $route );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- token is validated by WPVibe, no mutation here.
	$preview = isset( $_GET['wpvibe_preview'] ) ? sanitize_text_field( wp_unslash( $_GET['wpvibe_preview'] ) ) : '';
	return '' !== $preview ? add_query_arg( 'wpvibe_preview', $preview, $url ) : $url;
}

/**
 * Contextual commercial target for verified rental/electric clusters only.
 * Targets must still exist, be published and have no password; travel clusters are not forced.
 *
 * @param int $post_id Supporting article ID.
 * @return array
 */
function nt_cluster_commercial_link( int $post_id ): array {
	$map = (array) apply_filters(
		'nguyentu_cluster_commercial_targets',
		array(
			'su-dung-xe-thue-50cc' => array(
				'slug'  => 'thue-xe-may-50cc-ha-noi',
				'label' => 'Dịch vụ thuê xe 50cc',
			),
			'huong-dan-thue-xe'    => array(
				'slug'  => 'thue-xe-may-ha-noi-gia-re',
				'label' => 'Bảng giá và dịch vụ thuê xe',
			),
			'xe-dien'              => array(
				'slug'  => 'cho-thue-xe-dien-ha-noi',
				'label' => 'Dịch vụ thuê xe điện',
			),
		)
	);
	$ids = array();
	foreach ( (array) get_the_category( $post_id ) as $term ) {
		$ids = array_merge( $ids, array( $term->term_id ), get_ancestors( $term->term_id, 'category' ) );
	}
	foreach ( array_unique( $ids ) as $id ) {
		$term = get_term( $id, 'category' );
		if ( ! $term instanceof WP_Term || ! isset( $map[ $term->slug ] ) ) {
			continue; }
		$entry  = $map[ $term->slug ];
		$target = get_page_by_path( $entry['slug'], OBJECT, array( 'post', 'page' ) );
		if ( $target instanceof WP_Post && $target->ID !== $post_id && nt_knowledge_public( $target ) ) {
			return array(
				'url'   => get_permalink( $target ),
				'label' => $entry['label'],
			);
		}
	}
	return array();
}
