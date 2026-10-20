<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase {

	public function test_navigation_labels_handle_case_entities_and_preserve_unknown_items(): void {
		$this->assertSame( 'So sánh xe', nt_navigation_label( 'Đánh Giá &amp; So Sánh Xe' ) );
		$this->assertSame( 'Hướng dẫn', nt_navigation_label( 'Hướng dẫn thuê xe' ) );
		$this->assertSame( 'Xe máy điện', nt_navigation_label( 'Thuê xe máy điện' ) );
		$this->assertSame( 'Xe đạp điện', nt_navigation_label( 'Thuê xe đạp điện' ) );
		$this->assertSame( 'Thuê theo phường', nt_navigation_label( 'Thuê xe máy theo phường Hà Nội' ) );
		$this->assertSame( 'Mục mới chưa cấu hình', nt_navigation_label( 'Mục mới chưa cấu hình' ) );
		$this->assertSame( '', nt_navigation_label( '' ) );
	}

	public function test_slugify_removes_vietnamese_diacritics(): void {
		$this->assertSame( 'cho-thue-xe-may-ha-noi', nt_slugify( 'Cho Thuê Xe Máy Hà Nội' ) );
		$this->assertSame( 'duong-nguyen-van-cu', nt_slugify( 'Đường Nguyễn Văn Cừ' ) );
		$this->assertSame( 'ky-thuat-bao-duong', nt_slugify( 'Kỹ Thuật & Bảo Dưỡng' ) );
		$this->assertSame( '', nt_slugify( '—' ) );
	}

	public function test_plain_text_strips_tags_scripts_and_decodes_entities(): void {
		$html = '<p>Giá &amp; cọc</p><script>alert(1)</script><style>p{}</style><b> 150.000đ </b>';
		$this->assertSame( 'Giá & cọc 150.000đ', nt_plain_text( $html ) );
	}

	public function test_word_count_counts_vietnamese_syllables(): void {
		$this->assertSame( 6, nt_word_count( '<p>Thuê xe máy ở Hà Nội</p>' ) );
		$this->assertSame( 0, nt_word_count( '<p> </p>' ) );
	}

	public function test_reading_minutes_never_below_one(): void {
		$this->assertSame( 1, nt_reading_minutes( '<p>ngắn</p>' ) );
		$this->assertSame( 3, nt_reading_minutes( str_repeat( 'từ ', 600 ), 220 ) );
	}

	public function test_trim_words_keeps_whole_words_and_adds_suffix_only_when_cut(): void {
		$this->assertSame( 'một hai ba…', nt_trim_words( 'một hai ba bốn năm', 3 ) );
		$this->assertSame( 'một hai', nt_trim_words( 'một hai', 3 ) );
	}

	public function test_phone_digits_keeps_leading_plus_only(): void {
		$this->assertSame( '0942467674', nt_phone_digits( '0942 467 674' ) );
		$this->assertSame( '+84942467674', nt_phone_digits( '+84 942-467-674' ) );
	}
}
