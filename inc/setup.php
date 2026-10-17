<?php
/**
 * Theme supports, menu locations, widget areas and head hygiene.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Menu locations. The slugs deliberately equal the ones used by the previous theme so that
 * WordPress can carry existing menu assignments over when the theme is switched.
 *
 * @return array<string,string>
 */
function nt_menu_locations(): array {
	return array(
		'primary'        => 'Menu chính',
		'primary_mobile' => 'Menu chính - Di động',
		'secondary'      => 'Menu phụ',
		'footer'         => 'Menu chân trang',
		'top_bar_nav'    => 'Menu thanh trên cùng',
		'my_account'     => 'Menu tài khoản của tôi',
		'vertical'       => 'Menu dọc',
	);
}

/**
 * Theme supports.
 */
function nt_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 80,
			'width'       => 320,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_editor_style( 'assets/css/editor.css' );

	register_nav_menus( nt_menu_locations() );

	// Reading column width, used by embeds and the block editor.
	$GLOBALS['content_width'] = 760;
}
add_action( 'after_setup_theme', 'nt_setup' );

/**
 * Optional widget areas. Both render nothing when empty.
 */
function nt_widgets_init(): void {
	register_sidebar(
		array(
			'id'            => 'sidebar-blog',
			'name'          => 'Cột bên bài viết',
			'description'   => 'Hiển thị cạnh mục lục trên bài viết (tùy chọn).',
			'before_widget' => '<section id="%1$s" class="nt-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="nt-widget__title">',
			'after_title'   => '</h2>',
		)
	);
	register_sidebar(
		array(
			'id'            => 'footer-extra',
			'name'          => 'Chân trang mở rộng',
			'description'   => 'Hiển thị phía trên dòng bản quyền (tùy chọn).',
			'before_widget' => '<section id="%1$s" class="nt-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="nt-widget__title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'nt_widgets_init' );

/**
 * Remove emoji scripts, generator and legacy discovery links from <head>.
 * Feed links, REST discovery, canonical and everything Yoast prints are left untouched.
 */
function nt_clean_head(): void {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
}
add_action( 'init', 'nt_clean_head' );

/**
 * Drop the emoji CDN from resource hints (no external critical path).
 *
 * @param array<int,mixed> $urls          URLs.
 * @param string           $relation_type Relation.
 * @return array<int,mixed>
 */
function nt_resource_hints( array $urls, string $relation_type ): array {
	if ( 'dns-prefetch' !== $relation_type ) {
		return $urls;
	}
	return array_values(
		array_filter(
			$urls,
			static function ( $url ): bool {
				$href = is_array( $url ) ? ( $url['href'] ?? '' ) : (string) $url;
				return false === strpos( $href, 's.w.org' );
			}
		)
	);
}
add_filter( 'wp_resource_hints', 'nt_resource_hints', 10, 2 );

/**
 * The oEmbed host script is only useful for embedding this site elsewhere.
 */
function nt_dequeue_embed(): void {
	wp_deregister_script( 'wp-embed' );
}
add_action( 'wp_footer', 'nt_dequeue_embed', 1 );

/**
 * Body classes that drive layout decisions in CSS.
 *
 * @param string[] $classes Existing classes.
 * @return string[]
 */
function nt_body_classes( array $classes ): array {
	$classes[] = 'nt';
	if ( nt_quickcall_active() ) {
		$classes[] = 'nt-has-quickcall';
	}
	if ( nt_chat_widget_active() ) {
		$classes[] = 'nt-has-chat';
	}
	if ( nt_show_cta_bar() ) {
		$classes[] = 'nt-has-cta-bar';
	}
	return $classes;
}
add_filter( 'body_class', 'nt_body_classes' );

/**
 * Whether an active plugin directory starts with a given prefix.
 *
 * @param string $prefix Plugin directory prefix, e.g. "moto-quickcall".
 */
function nt_plugin_prefix_active( string $prefix ): bool {
	$active = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) {
		$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	}
	foreach ( $active as $plugin ) {
		if ( 0 === strpos( (string) $plugin, $prefix ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Moto QuickCall (floating contact bar plugin) detection. The theme then never draws its own
 * fixed contact bar, so two bars can never stack.
 */
function nt_quickcall_active(): bool {
	return (bool) apply_filters( 'nguyentu_quickcall_active', nt_plugin_prefix_active( 'moto-quickcall' ) );
}

/**
 * MotoAI chat widget detection (used only to keep clear of its bottom-right bubble).
 */
function nt_chat_widget_active(): bool {
	return (bool) apply_filters( 'nguyentu_chat_widget_active', nt_plugin_prefix_active( 'motoai' ) );
}

/**
 * Whether the theme's own mobile contact bar should render.
 * "auto" shows it only when QuickCall is not active.
 */
function nt_show_cta_bar(): bool {
	$mode = nt_toggle( 'cta_bar' );
	if ( 'on' === $mode ) {
		return true;
	}
	if ( 'off' === $mode ) {
		return false;
	}
	return ! nt_quickcall_active();
}
