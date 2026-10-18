<?php
/**
 * Shared opening-hours service, shortcodes and uncached public clock.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/** Current shop state from one configuration. */
function nt_hours_state(): array {
	return nt_hours_at( time(), nt_business( 'open' ), nt_business( 'close' ), (array) nt_option( 'hours.closed_days', array() ), (array) nt_option( 'hours.exceptions', array() ) );
}

/** Server-rendered fallback; refreshed from the uncached server clock after page load. */
function nt_hours_badge(): string {
	$state = nt_hours_state();
	return '<span class="nt-hours" data-nt-hours role="status">' . esc_html( $state['label'] ) . '</span>';
}
add_shortcode( 'nguyentu_hours', 'nt_hours_badge' );

/** REST routes never rely on a cached HTML page or device wall clock. */
function nt_hours_route(): void {
	register_rest_route(
		'nguyentu/v1',
		'/hours',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => static function () {
				$response = rest_ensure_response( nt_hours_state() );
				$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
				return $response;
			},
		)
	);
}
add_action( 'rest_api_init', 'nt_hours_route' );

/** Add state on contact pages without altering stored content.
 *
 * @param string $content Rendered contact content.
 */
function nt_contact_hours( string $content ): string {
	// This exact legacy schedule was verified in the homepage business section.
	// Render the shared component instead; preserve the stored page for rollback.
	if ( is_front_page() && in_the_loop() && is_main_query() ) {
		$content = str_replace( '08:00–21:30', nt_hours_badge(), $content );
	}
	if ( is_page( 'lien-he' ) && in_the_loop() && is_main_query() ) {
		$content .= '<div class="nt-container nt-contact-status"><p>' . nt_hours_badge() . '</p></div>';
	}
	return $content;
}
add_filter( 'the_content', 'nt_contact_hours', 30 );
