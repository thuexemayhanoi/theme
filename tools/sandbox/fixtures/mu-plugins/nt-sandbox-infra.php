<?php
/**
 * Plugin Name: NT Sandbox Infrastructure (SANDBOX ONLY)
 * Description: Test-harness glue for the disposable Nguyen Tu WordPress sandbox. NOT part of the theme and NOT for production. Always installed by tools/sandbox/sync.sh.
 * Version:     1.0.0
 *
 * What it does:
 *  1. Yoast SEO: keeps persisting indexables although WP_ENVIRONMENT_TYPE=local
 *     (Yoast skips this on non-production environments). Production persists them.
 *  2. Yoast SEO: no "installation successful" redirect on the first wp-admin visit.
 *  3. Per-request theme override: send header `X-Sandbox-Theme: <stylesheet>` to render a
 *     request with another installed theme WITHOUT changing the active theme in the DB
 *     (used by verify-seed.sh to check URLs under a core default theme). Local env only.
 *  4. Outgoing mail is never sent: wp_mail() is short-circuited and appended to logs/mail.log.
 *  5. WordPress.org update checks are answered locally ("nothing to update"): outbound HTTP is
 *     blocked in the sandbox anyway, this just removes the warnings/log noise they would cause.
 *  6. Silences the WordPress 6.7+ "translation loading triggered too early" notice for third-party
 *     text domains (WooCommerce etc. under WPLANG=vi). It stays ENABLED for the `nguyentu` domain so
 *     the theme/plugin under test is still checked.
 *  7. Removes WordPress's emoji detection script/styles. Headless Chromium has no colour-emoji font,
 *     so the polyfill would request SVGs from s.w.org (blocked here) and every page containing an
 *     emoji would log a failed request / console error. Set NT_SANDBOX_KEEP_EMOJI=1 (when starting
 *     the server) to keep WordPress's default behaviour.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1 + 2: Yoast behaviour.
add_filter( 'Yoast\WP\SEO\should_index_indexables', '__return_true' );
add_filter( 'wpseo_should_redirect_after_install', '__return_false' );

// 3: per-request theme override (header X-Sandbox-Theme).
if ( 'local' === wp_get_environment_type() && ! empty( $_SERVER['HTTP_X_SANDBOX_THEME'] ) ) {
	$nt_sb_theme = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) wp_unslash( $_SERVER['HTTP_X_SANDBOX_THEME'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( '' !== $nt_sb_theme && is_dir( WP_CONTENT_DIR . '/themes/' . $nt_sb_theme ) ) {
		add_filter(
			'stylesheet',
			static function () use ( $nt_sb_theme ) {
				return $nt_sb_theme;
			}
		);
		add_filter(
			'template',
			static function () use ( $nt_sb_theme ) {
				static $template = null;
				if ( null === $template ) {
					$theme    = wp_get_theme( $nt_sb_theme );
					$template = $theme->exists() ? $theme->get_template() : $nt_sb_theme;
				}
				return $template;
			}
		);
		if ( ! headers_sent() ) {
			header( 'X-Sandbox-Theme-Override: ' . $nt_sb_theme );
		}
	}
}

// 4: never send mail from the sandbox.
add_filter(
	'pre_wp_mail',
	static function ( $short_circuit, $atts ) {
		$dir  = getenv( 'NT_SANDBOX_DIR' );
		$file = ( $dir ? rtrim( $dir, '/' ) . '/logs' : WP_CONTENT_DIR ) . '/mail.log';
		$to   = isset( $atts['to'] ) ? ( is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : $atts['to'] ) : '';
		$subj = isset( $atts['subject'] ) ? $atts['subject'] : '';
		error_log( sprintf( "[%s] to=%s subject=%s\n", gmdate( 'c' ), $to, $subj ), 3, $file ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		return true;
	},
	10,
	2
);

// 5: no update checks against api.wordpress.org (offline sandbox).
add_filter(
	'pre_site_transient_update_core',
	static function () {
		global $wp_version;
		$state                  = new stdClass();
		$state->last_checked    = time();
		$state->version_checked = $wp_version;
		$state->updates         = array();
		return $state;
	}
);
add_filter(
	'pre_site_transient_update_plugins',
	static function () {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$state               = new stdClass();
		$state->last_checked = time();
		$state->checked      = array();
		$state->response     = array();
		$state->translations = array();
		$state->no_update    = array();
		foreach ( get_plugins() as $file => $data ) {
			$state->checked[ $file ] = $data['Version'];
		}
		return $state;
	}
);
add_filter(
	'pre_site_transient_update_themes',
	static function () {
		$state               = new stdClass();
		$state->last_checked = time();
		$state->checked      = array();
		$state->response     = array();
		$state->translations = array();
		foreach ( wp_get_themes() as $slug => $theme ) {
			$state->checked[ $slug ] = $theme->get( 'Version' );
		}
		return $state;
	}
);

// 6: keep debug.log readable - only report early textdomain loading for our own domain.
add_filter(
	'doing_it_wrong_trigger_error',
	static function ( $trigger, $function_name, $message ) {
		if ( '_load_textdomain_just_in_time' === $function_name && false === strpos( (string) $message, '<code>nguyentu' ) ) {
			return false;
		}
		return $trigger;
	},
	10,
	3
);

// 7: hermetic front end (no s.w.org emoji SVG requests).
if ( ! getenv( 'NT_SANDBOX_KEEP_EMOJI' ) ) {
	add_action(
		'init',
		static function () {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'embed_head', 'print_emoji_detection_script' );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
			remove_action( 'enqueue_embed_scripts', 'wp_enqueue_emoji_styles' );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
			add_filter( 'emoji_svg_url', '__return_false' );
		},
		1
	);
}
