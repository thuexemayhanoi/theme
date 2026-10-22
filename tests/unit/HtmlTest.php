<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HtmlTest extends TestCase {

	public function test_h1_detection_ignores_comments_scripts_and_lookalike_tags(): void {
		$this->assertTrue( nt_html_has_h1( '<div><h1 class="x">Tiêu đề</h1></div>' ) );
		$this->assertTrue( nt_html_has_h1( '<H1>Hoa</H1>' ) );
		$this->assertFalse( nt_html_has_h1( '<!-- <h1>hidden</h1> --><p>text</p>' ) );
		$this->assertFalse( nt_html_has_h1( '<script>var s="<h1>x</h1>";</script>' ) );
		$this->assertFalse( nt_html_has_h1( '<h10>no</h10><header>x</header>' ) );
	}

	public function test_h1_count_and_text(): void {
		$html = '<h1>Cho Thuê <em>Xe</em> Máy</h1><p>x</p><h1>Hai</h1>';
		$this->assertSame( 2, nt_html_count_tag( $html, 'h1' ) );
		$this->assertSame( 'Cho Thuê Xe Máy', nt_html_first_h1_text( $html ) );
		$this->assertSame( '', nt_html_first_h1_text( '<h2>x</h2>' ) );
	}

	public function test_heading_ids_are_added_and_existing_ids_preserved(): void {
		$html   = '<h2>Giá thuê xe</h2><p>a</p><h3 id="keep-me">Tiền cọc</h3><h2>Giá thuê xe</h2><h4>Bỏ qua</h4>';
		$result = nt_html_headings_with_ids( $html, 2, 3 );
		$this->assertStringContainsString( '<h2 id="gia-thue-xe">Giá thuê xe</h2>', $result['html'] );
		$this->assertStringContainsString( '<h3 id="keep-me">Tiền cọc</h3>', $result['html'] );
		$this->assertStringContainsString( '<h2 id="gia-thue-xe-2">Giá thuê xe</h2>', $result['html'] );
		$this->assertStringContainsString( '<h4>Bỏ qua</h4>', $result['html'] );
		$this->assertCount( 3, $result['items'] );
		$this->assertSame( array( 2, 3, 2 ), array_column( $result['items'], 'level' ) );
	}

	public function test_a_generated_id_never_duplicates_an_authored_id_that_comes_later(): void {
		$result = nt_html_headings_with_ids( '<h2>Foo</h2><p>a</p><h3 id="foo">Khác</h3><h2>Foo</h2>', 2, 3 );
		$this->assertStringContainsString( '<h2 id="foo-2">Foo</h2>', $result['html'] );
		$this->assertStringContainsString( '<h3 id="foo">Khác</h3>', $result['html'] );
		$this->assertStringContainsString( '<h2 id="foo-3">Foo</h2>', $result['html'] );
		$this->assertSame( array( 'foo-2', 'foo', 'foo-3' ), array_column( $result['items'], 'id' ) );
		$this->assertSame( 3, count( array_unique( array_column( $result['items'], 'id' ) ) ), 'every TOC target is unique' );
	}

	public function test_generated_ids_also_avoid_ids_on_other_elements(): void {
		$result = nt_html_headings_with_ids( '<h2>Giá thuê</h2><div id="gia-thue">x</div><a id=\'gia-thue-2\'></a>' );
		$this->assertSame( array( 'gia-thue-3' ), array_column( $result['items'], 'id' ) );
		$this->assertStringContainsString( '<div id="gia-thue">x</div>', $result['html'], 'authored markup is untouched' );
	}

	public function test_heading_ids_never_change_text_or_levels(): void {
		$html   = '<h2 class="c">Bảng <strong>giá</strong></h2>';
		$result = nt_html_headings_with_ids( $html );
		$this->assertSame( '<h2 class="c" id="bang-gia">Bảng <strong>giá</strong></h2>', $result['html'] );
	}

	public function test_empty_headings_are_skipped(): void {
		$result = nt_html_headings_with_ids( '<h2> </h2><h2>Có chữ</h2>' );
		$this->assertCount( 1, $result['items'] );
	}

	public function test_tables_are_wrapped_once_in_a_focusable_scroll_region(): void {
		$html = '<table><tr><td>1</td></tr></table><p>x</p><table><tr><td>2</td></tr></table>';
		$out  = nt_html_wrap_tables( $html );
		$this->assertSame( 2, substr_count( $out, 'class="nt-table-wrap"' ) );
		$this->assertSame( 2, substr_count( $out, 'tabindex="0"' ) );
		$this->assertSame( $html, nt_html_wrap_tables( $html ) === $out ? $html : '' );
		$this->assertSame( '<p>no table</p>', nt_html_wrap_tables( '<p>no table</p>' ) );
	}

	public function test_links_are_collected_uniquely(): void {
		$links = nt_html_links( '<a href="/a/">1</a><a href=\'/b/\'>2</a><a href="/a/">3</a><!-- <a href="/hidden/">x</a> -->' );
		$this->assertSame( array( '/a/', '/b/' ), $links );
	}

	/**
	 * Place the contents list in a body and parse the result, so structure can be asserted on a DOM.
	 *
	 * @return array{placement:string,html:string,xpath:DOMXPath}
	 */
	private function with_toc( string $html ): array {
		$toc    = '<details class="nt-toc nt-toc--inline" id="toc-under-test"><summary>Mục lục</summary></details>';
		$place  = nt_html_toc_placement( $html );
		$result = 'before-content' === $place['placement'] ? $html : nt_html_insert_at( $html, $place['offset'], $toc );
		$dom    = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?><div id="root">' . $result . '</div>' );
		libxml_clear_errors();
		return array(
			'placement' => $place['placement'],
			'html'      => $result,
			'xpath'     => new DOMXPath( $dom ),
		);
	}

	public function test_the_contents_list_goes_straight_after_the_first_h2(): void {
		$html = '<p>Mở đầu.</p><h2>Một</h2><p>a</p><h2>Hai</h2><h3>Ba</h3><p>b</p>';
		$r    = $this->with_toc( $html );
		$this->assertSame( 'after-first-h2', $r['placement'] );
		$this->assertStringContainsString( '<h2>Một</h2><details class="nt-toc nt-toc--inline" id="toc-under-test">', $r['html'] );
		$prev = $r['xpath']->query( '//*[@id="toc-under-test"]/preceding-sibling::*[1]' )->item( 0 );
		$this->assertSame( 'h2', $prev->nodeName );
		$this->assertSame( 'Một', trim( $prev->textContent ) );
		$next = $r['xpath']->query( '//*[@id="toc-under-test"]/following-sibling::*[1]' )->item( 0 );
		$this->assertSame( 'p', $next->nodeName );
		$this->assertSame( 0, $r['xpath']->query( '//h2//*[@id="toc-under-test"]' )->length, 'never inside a heading' );
		$this->assertSame( 1, $r['xpath']->query( '//*[@id="toc-under-test"]' )->length, 'exactly one list' );
	}

	public function test_inserting_the_list_changes_nothing_else_in_the_body(): void {
		$html   = "<!-- wp:paragraph -->\n<p>Mở đầu có <a href=\"/x/\">liên kết</a>.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2 class=\"wp-block-heading\" id=\"mot\">Một &amp; hai</h2>\n<!-- /wp:heading -->\n\n<p>a</p><h3>Ba</h3><h2>Bốn</h2>";
		$place  = nt_html_toc_placement( $html );
		$toc    = '<details>X</details>';
		$result = nt_html_insert_at( $html, $place['offset'], $toc );
		$this->assertSame( $html, str_replace( $toc, '', $result ), 'removing the list gives back the original bytes' );
		$headings = static function ( string $s ): array {
			preg_match_all( '#<h([1-6])\b[^>]*>(.*?)</h\1>#is', $s, $m, PREG_SET_ORDER );
			return array_map( static fn( array $x ): string => $x[0], $m );
		};
		$this->assertSame( $headings( $html ), $headings( $result ), 'heading order, levels, ids and text are untouched' );
	}

	public function test_the_first_h2_may_start_the_body_and_carry_attributes_with_a_greater_than_sign(): void {
		$r = $this->with_toc( '<H2 data-x="a>b" class=\'c\'>Tiêu <em>đề</em></H2><p>x</p>' );
		$this->assertSame( 'after-first-h2', $r['placement'] );
		$this->assertSame( 'h2', $r['xpath']->query( '//*[@id="toc-under-test"]/preceding-sibling::*[1]' )->item( 0 )->nodeName );
	}

	public function test_an_empty_h2_is_not_the_first_h2(): void {
		$r = $this->with_toc( '<p>i</p><h2> </h2><p>x</p><h2>Có chữ</h2><p>y</p>' );
		$this->assertSame( 'after-first-h2', $r['placement'] );
		$prev = $r['xpath']->query( '//*[@id="toc-under-test"]/preceding-sibling::*[1]' )->item( 0 );
		$this->assertSame( 'Có chữ', trim( $prev->textContent ) );
	}

	public function test_headings_in_comments_scripts_and_templates_are_ignored(): void {
		$html = '<!-- <h2>giả</h2> --><script>var s="<h2>x</h2>";</script><template><h2>mẫu</h2></template><noscript><h2>n</h2></noscript><p>i</p><h2>Thật</h2><p>x</p>';
		$r    = $this->with_toc( $html );
		$this->assertSame( 'after-first-h2', $r['placement'] );
		$this->assertSame( 'Thật', trim( $r['xpath']->query( '//*[@id="toc-under-test"]/preceding-sibling::*[1]' )->item( 0 )->textContent ) );
	}

	public function test_a_first_h2_inside_another_block_is_never_used(): void {
		foreach ( array(
			'group'      => '<p>i</p><div class="wp-block-group"><h2>Một</h2><p>a</p></div><h2>Hai</h2><h3>Ba</h3>',
			'columns'    => '<p>i</p><div class="wp-block-columns"><div class="wp-block-column"><h2>Một</h2></div></div><h2>Hai</h2>',
			'quote'      => '<p>i</p><blockquote><h2>Một</h2></blockquote><h2>Hai</h2>',
			'list item'  => '<p>i</p><ul><li><h2>Một</h2></li></ul><h2>Hai</h2>',
			'details'    => '<p>i</p><details><summary>s</summary><h2>Một</h2></details><h2>Hai</h2>',
			'link'       => '<p>i</p><a href="/x/"><h2>Một</h2></a><h2>Hai</h2>',
			'figure'     => '<p>i</p><figure><h2>Một</h2></figure><h2>Hai</h2>',
		) as $name => $html ) {
			$r = $this->with_toc( $html );
			$this->assertSame( 'after-intro', $r['placement'], $name );
			$this->assertSame( 1, $r['xpath']->query( '//*[@id="root"]/*[@id="toc-under-test"]' )->length, "$name: the list is a direct child of the body, not inside a block" );
			$prev = $r['xpath']->query( '//*[@id="toc-under-test"]/preceding-sibling::*[1]' )->item( 0 );
			$this->assertSame( 'p', $prev->nodeName, "$name: right after the introduction" );
		}
	}

	public function test_without_an_h2_the_list_goes_before_the_first_section(): void {
		$r = $this->with_toc( '<p>Mở đầu.</p><p>Tiếp.</p><ul><li>a</li></ul><h3>A</h3><p>x</p><h3>B</h3><h3>C</h3>' );
		$this->assertSame( 'after-intro', $r['placement'] );
		$prev = $r['xpath']->query( '//*[@id="toc-under-test"]/preceding-sibling::*[1]' )->item( 0 );
		$next = $r['xpath']->query( '//*[@id="toc-under-test"]/following-sibling::*[1]' )->item( 0 );
		$this->assertSame( 'ul', $prev->nodeName );
		$this->assertSame( 'h3', $next->nodeName );
	}

	public function test_without_any_safe_place_the_list_stays_before_the_text(): void {
		foreach ( array(
			'nested h2 first, no intro' => '<div><h2>A</h2><p>x</p></div><h3>B</h3>',
			'h3 only, no intro'         => '<h3>A</h3><p>x</p><h3>B</h3>',
			'no headings'               => '<p>Chỉ có đoạn văn.</p>',
			'empty body'                => '',
			'unterminated comment'      => '<p>i</p><h2>A</h2><!-- không đóng <p>x</p>',
		) as $name => $html ) {
			$place = nt_html_toc_placement( $html );
			$this->assertSame( array( 'placement' => 'before-content', 'offset' => 0 ), $place, $name );
		}
	}

	public function test_malformed_markup_that_a_browser_repairs_is_followed(): void {
		$cases = array(
			'unclosed paragraph'    => '<p>Mở đầu<h2>Một</h2><p>a</p>',
			'unclosed list items'   => '<ul><li>a<li>b</ul><h2>Một</h2><p>a</p>',
			'stray closing tags'    => '</div></p><h2>Một</h2><p>a</p>',
			'void and self closing' => '<p>a<br>b</p><hr><img src="x.webp" alt=""><svg><path d="M0 0"/></svg><h2>Một</h2><p>a</p>',
			'unclosed inline'       => '<p><strong>đậm</p><h2>Một</h2>',
		);
		foreach ( $cases as $name => $html ) {
			$place = nt_html_toc_placement( $html );
			$this->assertSame( 'after-first-h2', $place['placement'], $name );
			$this->assertSame( '</h2>', substr( $html, $place['offset'] - 5, 5 ), "$name: the offset is right after the closing tag" );
		}
	}

	public function test_the_offset_never_falls_inside_a_tag_or_a_raw_text_element(): void {
		$html  = '<p>i</p><script>if (a < b) { x = "</p><h2>no</h2>"; }</script><style>h2 > p { color: red }</style><h2>Một</h2>';
		$place = nt_html_toc_placement( $html );
		$this->assertSame( 'after-first-h2', $place['placement'] );
		$tag = '<h2>Một</h2>';
		$this->assertSame( $tag, substr( $html, $place['offset'] - strlen( $tag ), strlen( $tag ) ), 'byte offsets, right after the heading' );
		$nested = nt_html_toc_placement( '<p>i</p><div><script>var a = 1;</script><h2>Một</h2></div><h3>x</h3>' );
		$this->assertSame( 'after-intro', $nested['placement'] );
		$this->assertSame( strlen( '<p>i</p>' ), $nested['offset'], 'right after the introduction paragraph' );
	}

	public function test_the_offset_helper_is_safe_at_the_edges(): void {
		$this->assertSame( 'X<p>a</p>', nt_html_insert_at( '<p>a</p>', -5, 'X' ) );
		$this->assertSame( '<p>a</p>X', nt_html_insert_at( '<p>a</p>', 999, 'X' ) );
		$this->assertSame( '<p>a</p>', nt_html_insert_at( '<p>a</p>', 3, '' ) );
	}

	public function test_a_long_body_is_scanned_quickly_and_completely(): void {
		$body  = str_repeat( '<p>Đoạn văn có <a href="/x/">liên kết</a>, <strong>đậm</strong> và <em>nghiêng</em>.</p>' . "\n", 4000 );
		$html  = '<p>Mở đầu.</p>' . $body . '<h2>Muộn</h2>' . $body;
		$start = microtime( true );
		$place = nt_html_toc_placement( $html );
		$this->assertLessThan( 1.5, microtime( true ) - $start, 'linear scan' );
		$this->assertSame( 'after-first-h2', $place['placement'] );
		$this->assertSame( '</h2>', substr( $html, $place['offset'] - 5, 5 ) );
	}
}
