<?php
/**
 * Static-page presentation. Editor content, media and SEO data remain recoverable.
 * The two remaining UX Builder service layouts use native templates until an editor
 * replaces their legacy content. Homepage and every WooCommerce context are excluded.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remove presentation images from informational page bodies, retaining functional
 * form images (for example a CAPTCHA) and SVG interface icons. Runs only in page.php.
 *
 * @param string $html Rendered page body.
 */
function nt_page_without_decorative_images( string $html ): string {
	$tag  = '(?:[^>"\x27]|"[^"]*"|\x27[^\x27]*\x27)*';
	$html = preg_replace_callback(
		'#<form\b' . $tag . '>.*?</form\s*>|<picture\b' . $tag . '>.*?</picture\s*>|<img\b' . $tag . '>#is',
		static function ( array $match ): string {
			return 0 === stripos( $match[0], '<form' ) ? $match[0] : '';
		},
		$html
	) ?? $html;
	// Do not leave image-only links or empty figure boxes taking up vertical space.
	$html = preg_replace( '#<a\b' . $tag . '>\s*</a\s*>#is', '', $html ) ?? $html;
	return preg_replace( '#<figure\b' . $tag . '>\s*</figure\s*>#is', '', $html ) ?? $html;
}

/**
 * Render the current page once, using native presentation for the two legacy pages.
 * No content/option write, route change, SEO override or global content filter.
 */
function nt_get_page_body(): string {
	if ( ! is_page() || is_front_page() || nt_is_shop_context() ) {
		return nt_get_rendered_content();
	}

	$source   = (string) get_the_content( null, false );
	$slug     = (string) get_post_field( 'post_name', get_the_ID() );
	$template = array(
		'lien-he'    => 'contact',
		'bo-suu-tap' => 'collection',
	)[ $slug ] ?? '';
	if ( '' !== $template && nt_legacy_has_shortcodes( $source ) ) {
		$heading = nt_html_first_h1_text( $source );
		$form    = '';
		if ( 'contact' === $template && preg_match( '/\[contact-form-7\b[^\]]*\]/i', $source, $match ) ) {
			$form = $match[0];
		}
		ob_start();
		get_template_part(
			'template-parts/pages/' . $template,
			null,
			array(
				'heading' => '' !== $heading ? $heading : get_the_title(),
				'form'    => $form,
			)
		);
		$html = (string) ob_get_clean();
		// Keep standard WordPress / CF7 filters and heading IDs, exactly once.
		$html = apply_filters( 'the_content', $html );
	} else {
		$html = nt_get_rendered_content();
	}

	return nt_page_without_decorative_images( $html );
}
