<?php
/**
 * Front-end assets: small, conditional, cache-busted, no jQuery, no CDN.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cache-busting version for a theme asset (theme version + file mtime).
 *
 * @param string $rel Path relative to the theme root.
 */
function nt_asset_ver( string $rel ): string {
	$path = NT_DIR . '/' . ltrim( $rel, '/' );
	return is_readable( $path ) ? NT_VERSION . '.' . (string) filemtime( $path ) : NT_VERSION;
}

/**
 * Public URL of a theme asset.
 *
 * @param string $rel Path relative to the theme root.
 */
function nt_asset_uri( string $rel ): string {
	return NT_URI . '/' . ltrim( $rel, '/' );
}

/**
 * WooCommerce page that needs shop styles.
 */
function nt_is_shop_context(): bool {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return false;
	}
	return is_woocommerce() || ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) || ( function_exists( 'is_account_page' ) && is_account_page() );
}

/**
 * Editorial context: single posts, archives, search, 404 and legacy featured items.
 */
function nt_is_editorial_context(): bool {
	if ( nt_is_shop_context() ) {
		return false;
	}
	return is_singular( 'post' ) || is_home() || is_archive() || is_search() || is_404() || is_singular( 'featured_item' );
}

/**
 * Enqueue styles and the single deferred script.
 */
function nt_enqueue_assets(): void {
	wp_enqueue_style( 'nt-main', nt_asset_uri( 'assets/css/main.css' ), array(), nt_asset_ver( 'assets/css/main.css' ) );

	if ( is_front_page() ) {
		wp_enqueue_style( 'nt-home', nt_asset_uri( 'assets/css/home.css' ), array( 'nt-main' ), nt_asset_ver( 'assets/css/home.css' ) );
	}
	if ( is_front_page() && 'editorial' === nt_home_layout() ) {
		wp_enqueue_style( 'nt-home-editorial', nt_asset_uri( 'assets/css/home-editorial.css' ), array( 'nt-home' ), nt_asset_ver( 'assets/css/home-editorial.css' ) );
	}
	if ( nt_is_editorial_context() || is_front_page() ) {
		wp_enqueue_style( 'nt-blog', nt_asset_uri( 'assets/css/blog.css' ), array( 'nt-main' ), nt_asset_ver( 'assets/css/blog.css' ) );
	}
	if ( nt_is_shop_context() ) {
		// Load after WooCommerce's own sheets so the resets in woo.css win at equal specificity.
		$nt_woo_deps = array( 'nt-main' );
		foreach ( array( 'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-general' ) as $nt_handle ) {
			if ( wp_style_is( $nt_handle, 'registered' ) ) {
				$nt_woo_deps[] = $nt_handle;
			}
		}
		wp_enqueue_style( 'nt-woo', nt_asset_uri( 'assets/css/woo.css' ), $nt_woo_deps, nt_asset_ver( 'assets/css/woo.css' ) );
	}
	if ( function_exists( 'nt_legacy_needs_css' ) && nt_legacy_needs_css() && ! ( is_front_page() && 'editorial' === nt_home_layout() ) ) {
		wp_enqueue_style( 'nt-legacy', nt_asset_uri( 'assets/css/legacy.css' ), array( 'nt-main' ), nt_asset_ver( 'assets/css/legacy.css' ) );
	}

	if ( nt_native_quickcall_enabled() || nt_native_assistant_enabled() ) {
		wp_enqueue_style( 'nt-assist', nt_asset_uri( 'assets/css/assist.css' ), array( 'nt-main' ), nt_asset_ver( 'assets/css/assist.css' ) );
		wp_enqueue_script(
			'nt-assist',
			nt_asset_uri( 'assets/js/assist.js' ),
			array(),
			nt_asset_ver( 'assets/js/assist.js' ),
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}

	wp_enqueue_style(
		'nt-color-mode',
		nt_asset_uri( 'assets/css/color-mode.css' ),
		array( 'nt-main' ),
		nt_asset_ver( 'assets/css/color-mode.css' )
	);

	wp_enqueue_script(
		'nt-theme',
		nt_asset_uri( 'assets/js/theme.js' ),
		array(),
		nt_asset_ver( 'assets/js/theme.js' ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);

	if ( is_front_page() ) {
		wp_enqueue_script( 'nt-rental', nt_asset_uri( 'assets/js/rental.js' ), array(), nt_asset_ver( 'assets/js/rental.js' ), array(
				'strategy'  => 'defer',
				'in_footer' => true,
			) );
	}

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'nt_enqueue_assets', 20 ); // after WooCommerce registers its styles (priority 10).

/**
 * Tell CSS that scripting is available (enables the off-canvas menu) before first paint.
 * Without JavaScript every menu stays fully visible and reachable.
 */
function nt_head_js_flag(): void {
	echo '<script>(function(){'
		. 'var root=document.documentElement;root.classList.add("js");'
		. 'var mode="light";try{var saved=window.localStorage.getItem("nt-color-mode");'
		. 'if(saved==="light"||saved==="dark"){mode=saved;}'
		. 'else if(window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches){mode="dark";}'
		. '}catch(e){}root.setAttribute("data-nt-color-mode",mode);'
		. '})();</script>' . "\n";
}
add_action( 'wp_head', 'nt_head_js_flag', 0 );

/**
 * Preload the two self-hosted font files used above the fold (latin + vietnamese subsets).
 */
function nt_preload_fonts(): void {
	foreach ( array( 'latin', 'vietnamese' ) as $subset ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( nt_asset_uri( "assets/fonts/plus-jakarta-sans-{$subset}-wght-normal.woff2" ) )
		);
	}
}
add_action( 'wp_head', 'nt_preload_fonts', 1 );

/**
 * Editorial home uses only the native calculator; preserve plugin assets on other pages.
 */
function nt_home_rental_assets(): void {
	if ( is_front_page() && 'editorial' === nt_home_layout() ) {
		wp_dequeue_script( 'nt-smart-rental' );
		wp_dequeue_style( 'nt-smart-rental' );
	}
}
add_action( 'wp_enqueue_scripts', 'nt_home_rental_assets', 100 );
