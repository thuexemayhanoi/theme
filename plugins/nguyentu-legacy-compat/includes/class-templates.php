<?php
/**
 * Minimal front-end templates for featured items, used ONLY when the active theme does not
 * provide its own (the Nguyễn Tú theme does, so this is a safety net).
 *
 * @package NguyenTuLegacyCompat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Template fallback.
 */
final class NT_Compat_Templates {

	/**
	 * Register hooks.
	 */
	public static function hooks(): void {
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 99 );
	}

	/**
	 * Swap in the plugin template when the theme has none for these views.
	 *
	 * @param string $template Template chosen by WordPress.
	 */
	public static function template_include( string $template ): string {
		if ( is_singular( NT_Compat_Registry::CPT ) && ! locate_template( 'single-featured_item.php' ) ) {
			return self::pick( $template, 'single-featured_item.php' );
		}
		if ( is_tax( NT_Compat_Registry::TAX ) && ! locate_template( 'taxonomy-featured_item_category.php' ) ) {
			return self::pick( $template, 'taxonomy-featured_item_category.php' );
		}
		return $template;
	}

	/**
	 * Path of a bundled template, or the original when it is missing.
	 *
	 * @param string $template Original template.
	 * @param string $file     Bundled file name.
	 */
	private static function pick( string $template, string $file ): string {
		$path = NT_COMPAT_DIR . 'templates/' . $file;
		return is_readable( $path ) ? $path : $template;
	}
}
