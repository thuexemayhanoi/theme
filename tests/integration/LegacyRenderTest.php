<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Legacy Flatsome-style shortcode content (pages 562, 310, 372, 424, 432, 494) must render as
 * clean HTML without the old theme: no raw shortcodes, SEO H1 preserved, links and images intact.
 */
final class LegacyRenderTest extends TestCase {

	private const RAW = '/\[\/?(?:ux_[a-z0-9_]+|row|col|section|row_inner|col_inner|gap|divider|title|text_box|button|accordion(?:-item)?|tabgroup|tab|message_box|scroll_to|blog_posts)(?=[\s\]\/])/i';

	public function test_bridge_is_registered_and_the_old_theme_is_not_loaded(): void {
		$this->assertTrue( nt_legacy_active(), 'no other component owns the shortcodes, so the bridge registers them' );
		foreach ( array( 'section', 'row', 'col', 'ux_banner', 'button', 'ux_products', 'ux_portfolio', 'accordion' ) as $tag ) {
			$this->assertTrue( shortcode_exists( $tag ), "[{$tag}] must be handled" );
		}
		$this->assertFalse( function_exists( 'flatsome_setup' ), 'Flatsome must not be loaded' );
	}

	/**
	 * @return array<string,array{0:int}>
	 */
	public static function pages(): array {
		return array( 'home 562' => array( 562 ), 'about 310' => array( 310 ), 'contact 372' => array( 372 ), 'who 424' => array( 424 ), 'mission 432' => array( 432 ), 'collection 494' => array( 494 ) );
	}

	#[DataProvider( 'pages' )]
	public function test_no_raw_shortcodes_reach_the_front_end( int $id ): void {
		$stored = (string) get_post_field( 'post_content', $id );
		$this->assertMatchesRegularExpression( self::RAW, $stored, "fixture {$id} must contain legacy shortcodes for this test to mean anything" );
		$html = nt_it_render( $id );
		$this->assertDoesNotMatchRegularExpression( self::RAW, wp_strip_all_tags( $html ), "raw shortcode text in page {$id}" );
		$this->assertStringNotContainsString( '[/', wp_strip_all_tags( $html ) );
		$this->assertStringContainsString( 'class="nt-legacy"', $html );
	}

	public function test_home_keeps_its_h1_and_ctas_and_renders_banner_boxes_and_products(): void {
		$html = nt_it_render( 562 );
		$this->assertSame( 1, nt_html_count_tag( $html, 'h1' ), 'exactly one H1 (the content one)' );
		$this->assertSame( 'Cho Thuê Xe Máy Hà Nội – Giao Tận Nơi, Cọc Nhẹ', nt_html_first_h1_text( $html ) );
		$this->assertFalse( nt_should_print_h1( $html ), 'the theme must not add a second H1' );

		$xp = nt_it_xpath( $html );
		$this->assertSame( 1, $xp->query( '//*[' . nt_it_has_class( 'nt-lg-banner' ) . ']' )->length );
		$this->assertSame( 3, $xp->query( '//*[' . nt_it_has_class( 'nt-lg-imagebox' ) . ']' )->length );
		$this->assertGreaterThanOrEqual( 8, $xp->query( '//ul[contains(@class,"nt-lg-products")]/li' )->length, '[ux_products products=8 cat=70] lists the catalogue' );
		$this->assertSame( 1, $xp->query( '//a[@href="tel:0942467674"]' )->length );
		$this->assertSame( 1, $xp->query( '//a[starts-with(@href,"https://zalo.me/0942467674")]' )->length );
		$this->assertSame( '_blank', $xp->query( '//a[starts-with(@href,"https://zalo.me/")]' )->item( 0 )->getAttribute( 'target' ) );
		$this->assertStringContainsString( 'noopener', $xp->query( '//a[starts-with(@href,"https://zalo.me/")]' )->item( 0 )->getAttribute( 'rel' ) );
		$this->assertStringContainsString( '112 Nguyễn Văn Cừ, Bồ Đề, Long Biên, Hà Nội', wp_strip_all_tags( $html ) );
	}

	public function test_first_banner_image_is_the_eager_high_priority_lcp_candidate(): void {
		$html = nt_it_render( 562 );
		$xp   = nt_it_xpath( $html );
		$img  = $xp->query( '//*[' . nt_it_has_class( 'nt-lg-banner' ) . ']//img' )->item( 0 );
		$this->assertNotNull( $img, 'banner background image rendered as a real <img>' );
		$this->assertSame( 'high', $img->getAttribute( 'fetchpriority' ) );
		$this->assertSame( 'eager', $img->getAttribute( 'loading' ) );
		$this->assertSame( '', $img->getAttribute( 'alt' ), 'decorative background keeps an empty alt' );
		$this->assertNotSame( '', $img->getAttribute( 'srcset' ), 'responsive srcset present' );
		$this->assertNotSame( '', $img->getAttribute( 'width' ) );
		$this->assertNotSame( '', $img->getAttribute( 'height' ) );
	}

	public function test_no_layout_breaking_autop_artifacts_inside_rows(): void {
		$html = nt_it_render( 562 );
		$this->assertDoesNotMatchRegularExpression( '#<div class="nt-lg-row[^"]*"[^>]*>\s*<br#', $html );
		$this->assertDoesNotMatchRegularExpression( '#<p>\s*</p>#', $html, 'no empty paragraphs' );
		$this->assertDoesNotMatchRegularExpression( '#<p>\s*(?:<!--.*?-->\s*)+</p>#s', $html, 'no paragraphs that only hold comments' );
		$this->assertDoesNotMatchRegularExpression( '#<p>\s*<(?:div|section)\b#', $html, 'block containers are not wrapped in <p>' );
	}

	public function test_content_without_h1_lets_the_theme_print_the_title(): void {
		$html = nt_it_render( 424 );
		$this->assertSame( 0, nt_html_count_tag( $html, 'h1' ) );
		$this->assertTrue( nt_should_print_h1( $html ) );
	}

	public function test_contact_page_renders_map_card_and_buttons(): void {
		$xp = nt_it_xpath( nt_it_render( 372 ) );
		$this->assertSame( 1, $xp->query( '//*[contains(@class,"nt-lg-map")]' )->length );
		$this->assertGreaterThanOrEqual( 2, $xp->query( '//a[contains(@class,"nt-lg-btn")]' )->length );
	}

	public function test_collection_page_lists_all_15_featured_items(): void {
		$xp = nt_it_xpath( nt_it_render( 494 ) );
		$this->assertSame( 15, $xp->query( '//article[contains(@class,"nt-card--featured")]' )->length );
	}

	public function test_unsupported_tags_are_removed_but_their_content_is_kept(): void {
		$out = apply_filters( 'the_content', '[ux_hotspot size="5"]Nội dung giữ lại[/ux_hotspot] [ux_unknown_widget foo="bar"] cuối.' );
		$this->assertStringContainsString( 'Nội dung giữ lại', $out );
		$this->assertStringContainsString( 'cuối.', $out );
		$this->assertStringNotContainsString( '[ux_', wp_strip_all_tags( $out ) );
		$this->assertStringContainsString( 'nt-legacy:', $out, 'an HTML comment marks the unsupported spot' );
		$seen = nt_legacy_unsupported_seen();
		$this->assertArrayHasKey( 'ux_hotspot', $seen );
		$this->assertArrayHasKey( 'ux_unknown_widget', $seen );
	}

	public function test_attribute_injection_cannot_reach_css_or_markup(): void {
		$out = do_shortcode( '[section bg_color="red;position:fixed" padding="10px;background:url(//evil.test/x)" class="ok \"><script>alert(1)</script>"][row][col span="6" padding="1px;x"]hi[/col][/row][/section]' );
		$this->assertStringNotContainsString( 'evil.test', $out );
		$this->assertStringNotContainsString( '<script', $out );
		$this->assertStringNotContainsString( 'position:fixed', $out );
		$btn = do_shortcode( '[button text="<img src=x onerror=alert(1)>" link="javascript:alert(1)"]' );
		$this->assertStringNotContainsString( '<img', $btn );
		$this->assertStringNotContainsString( 'javascript:', $btn );
	}

	public function test_title_keeps_the_authored_heading_level(): void {
		$this->assertStringContainsString( '<h2 class="nt-lg-title', do_shortcode( '[title text="Xe số" tag_name="h2"]' ) );
		$this->assertStringContainsString( '<h3 class="nt-lg-title', do_shortcode( '[title text="Xe ga"]' ), 'default level is h3, as in the original markup' );
	}

	public function test_links_and_alt_text_of_images_are_preserved(): void {
		$id   = (int) get_post_thumbnail_id( (int) nt_it_baseline()['products'][0]['ID'] );
		$alt  = 'Xe cho thuê alt kiểm thử';
		$was  = get_post_meta( $id, '_wp_attachment_image_alt', true );
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		$html = do_shortcode( '[ux_image id="' . $id . '" link="/lien-he/"]' );
		update_post_meta( $id, '_wp_attachment_image_alt', $was ); // leave the sandbox exactly as it was.
		$this->assertStringContainsString( 'alt="' . $alt . '"', $html );
		$this->assertStringContainsString( 'href="/lien-he/"', $html );
	}

	public function test_nested_the_content_runs_do_not_steal_the_outer_wrapper_or_formatting(): void {
		add_shortcode(
			'nt_test_nested',
			static function (): string {
				return wp_strip_all_tags( apply_filters( 'the_content', 'Bên trong' ) ); // what a card excerpt does.
			}
		);
		$out = apply_filters( 'the_content', "[section][row][col span=\"12\"]Đoạn một\n\nĐoạn hai [nt_test_nested][/col][/row][/section]" );
		remove_shortcode( 'nt_test_nested' );
		$this->assertStringStartsWith( '<div class="nt-legacy">', $out, 'the outer run keeps its wrapper' );
		$this->assertStringContainsString( '<p>Đoạn một</p>', $out, 'the outer run still gets its late wpautop' );
		$this->assertStringContainsString( 'Bên trong', $out );
		$this->assertSame( 10, has_filter( 'the_content', 'wpautop' ), 'wpautop is given back after the run' );
		$this->assertFalse( has_filter( 'the_content', 'nt_legacy_late_autop' ), 'no leftover late-autop filter' );
		$this->assertSame( 0, nt_legacy_frame()['depth'], 'frame stack is balanced' );
	}

	/**
	 * @return array<string,array{0:int}>
	 */
	public static function legacy_pages(): array {
		return array( 'home 562' => array( 562 ), 'about 310' => array( 310 ), 'contact 372' => array( 372 ), 'who 424' => array( 424 ), 'mission 432' => array( 432 ), 'collection 494' => array( 494 ) );
	}

	#[DataProvider( 'legacy_pages' )]
	public function test_every_link_has_an_accessible_name_and_markup_is_not_split_by_autop( int $id ): void {
		$html = nt_it_render( $id );
		$xp   = nt_it_xpath( $html );
		$bad  = array();
		foreach ( $xp->query( '//a[@href][not(@aria-hidden="true")]' ) as $a ) { // aria-hidden duplicates (card media links) are hidden from assistive tech on purpose.
			$named = '' !== trim( $a->textContent ) || '' !== trim( $a->getAttribute( 'aria-label' ) ) || $xp->query( './/img[string-length(@alt) > 0]', $a )->length > 0;
			if ( ! $named ) {
				$bad[] = $a->getAttribute( 'href' );
			}
		}
		$this->assertSame( array(), $bad, "links without an accessible name in page {$id}" );
		$this->assertDoesNotMatchRegularExpression( '#<a\b[^>]*>\s*</p>#', $html, 'wpautop must not close a paragraph right after an opening <a>' );
		$this->assertDoesNotMatchRegularExpression( '#<p>\s*</a>#', $html, 'wpautop must not open a paragraph right before a closing </a>' );
	}

	public function test_card_excerpts_keep_text_inside_layout_shortcodes_without_running_the_content_filter(): void {
		$runs    = 0;
		$counter = static function ( string $c ) use ( &$runs ): string {
			++$runs;
			return $c;
		};
		add_filter( 'the_content', $counter, 1 );
		$excerpt = nt_excerpt( 562, 40 );
		remove_filter( 'the_content', $counter, 1 );
		$this->assertSame( 0, $runs, 'building an excerpt must not run the_content' );
		$this->assertNotSame( '', $excerpt );
		$this->assertStringNotContainsString( '[', $excerpt, 'no shortcode token in the excerpt' );
		$this->assertStringContainsString( 'Cho Thuê Xe Máy Hà Nội', $excerpt, 'text inside [ux_banner]/[text_box] is kept' );
	}
}
