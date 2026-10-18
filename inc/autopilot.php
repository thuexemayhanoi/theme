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
				'hours'     => nt_autopilot_endpoint( 'hours' ),
				'assistant' => nt_autopilot_endpoint( 'assistant' ),
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
