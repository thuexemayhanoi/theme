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

	public function test_toc_ignores_hidden_headings_and_preserves_their_source(): void {
		$hidden = '<!-- <h2>Giá cũ</h2> -->'
			. '<script>var sample="<h2>Script</h2>";</script>'
			. '<template><h2>Mẫu</h2></template>';
		$result = nt_html_headings_with_ids( '<h2>Giá thuê</h2>' . $hidden . '<h3 id="coc">Tiền cọc</h3>' );
		$this->assertSame( array( 'gia-thue', 'coc' ), array_column( $result['items'], 'id' ) );
		$this->assertStringContainsString( $hidden, $result['html'] );
	}

	public function test_toc_does_not_link_into_an_unclosed_legacy_comment(): void {
		$hidden = '<!-- Bảng giá cũ <table></table><h2>Thủ tục</h2><h3>Giấy tờ</h3>';
		$result = nt_html_headings_with_ids( '<h2>Giá thuê</h2>' . $hidden );
		$this->assertCount( 1, $result['items'] );
		$this->assertStringEndsWith( $hidden, $result['html'] );
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
}
