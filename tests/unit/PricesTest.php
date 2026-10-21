<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PricesTest extends TestCase {

	public function test_parse_lines_pads_columns_and_skips_comments(): void {
		$raw  = "# comment\n\nHonda Wave | 150.000đ | 700.000đ | 900.000–1.200.000đ | 2–3 triệu\nXe điện | Liên hệ\n | rỗng";
		$rows = nt_parse_price_lines( $raw );
		$this->assertCount( 2, $rows );
		$this->assertSame( 'Honda Wave', $rows[0]['name'] );
		$this->assertSame( '2–3 triệu', $rows[0]['deposit'] );
		$this->assertSame( 'Liên hệ', $rows[1]['day'] );
		$this->assertSame( '', $rows[1]['deposit'] );
	}

	public function test_parse_strips_markup_from_cells(): void {
		$rows = nt_parse_price_lines( 'Wave <script>x</script> | <b>150.000đ</b>' );
		$this->assertSame( 'Wave', $rows[0]['name'] );
		$this->assertSame( '150.000đ', $rows[0]['day'] );
	}

	public function test_default_rows_round_trip_through_the_admin_textarea_format(): void {
		$defaults = nt_price_defaults();
		$this->assertSame( $defaults, nt_parse_price_lines( nt_format_price_lines( $defaults ) ) );
	}

	public function test_default_price_table_matches_the_owner_data(): void {
		$rows = array_column( nt_price_defaults(), null, 'name' );
		$this->assertSame( '150.000đ', $rows['Honda Wave']['day'] );
		$this->assertSame( '700.000đ', $rows['Honda Wave']['week'] );
		$this->assertSame( '900.000–1.200.000đ', $rows['Honda Wave']['month'] );
		$this->assertSame( '2–3 triệu', $rows['Honda Wave']['deposit'] );
		$this->assertSame( '1.800.000–2.000.000đ', $rows['Honda Vision']['month'] );
		$this->assertSame( '4–5 triệu', $rows['Honda Vision']['deposit'] );
		$this->assertSame( '1.500.000đ', $rows['Honda Air Blade']['month'] );
		// Owner-approved authoritative rates; unspecified 50cc/electric packages are quote-on-request.
		$this->assertSame( '200.000đ', $rows['Xe tay ga 50cc']['day'] );
		$this->assertSame( 'Báo giá riêng', $rows['Xe tay ga 50cc']['week'] );
		$this->assertSame( 'Báo giá riêng', $rows['Xe tay ga 50cc']['month'] );
		$this->assertSame( '4 triệu', $rows['Xe tay ga 50cc']['deposit'] );
		$this->assertSame( 'Liên hệ', $rows['Xe máy điện / Xe đạp điện']['day'] );
		$this->assertSame( 'Liên hệ', $rows['Xe máy điện / Xe đạp điện']['week'] );
		$this->assertSame( 'Liên hệ', $rows['Xe máy điện / Xe đạp điện']['month'] );
		$this->assertSame( 'Xác nhận theo mẫu xe', $rows['Xe máy điện / Xe đạp điện']['deposit'] );
		$this->assertCount( 6, $rows );
	}


	public function test_unpriced_packages_do_not_create_fabricated_price_minima(): void {
		$rows = array_column( nt_price_defaults(), null, 'name' );
		$this->assertNull( nt_price_cell_min( $rows['Xe tay ga 50cc']['week'] ) );
		$this->assertNull( nt_price_cell_min( $rows['Xe tay ga 50cc']['month'] ) );
		foreach ( array( 'day', 'week', 'month' ) as $period ) {
			$this->assertNull( nt_price_cell_min( $rows['Xe máy điện / Xe đạp điện'][ $period ] ) );
		}
	}


	public function test_cell_minimum_and_column_minimum(): void {
		$this->assertSame( 900000, nt_price_cell_min( '900.000–1.200.000đ' ) );
		$this->assertSame( 150000, nt_price_cell_min( '150.000đ' ) );
		$this->assertNull( nt_price_cell_min( 'Liên hệ' ) );
		$this->assertNull( nt_price_cell_min( '2–3 triệu' ) );
		$rows = nt_price_defaults();
		$this->assertSame( 150000, nt_price_column_min( $rows, 'day' ) );
		$this->assertSame( 700000, nt_price_column_min( $rows, 'week' ) );
		$this->assertSame( 900000, nt_price_column_min( $rows, 'month' ) );
	}

	public function test_format_vnd(): void {
		$this->assertSame( '150.000đ', nt_format_vnd( 150000 ) );
		$this->assertSame( '1.200.000đ', nt_format_vnd( 1200000 ) );
	}

	public function test_price_mentions_detect_existing_price_lists(): void {
		$list = '<p>Wave 150.000đ/ngày, Vision 200.000 VNĐ, cọc 2 triệu, Click 150k</p>';
		$this->assertGreaterThanOrEqual( 3, nt_count_price_mentions( $list ) );
		$this->assertTrue( nt_content_has_prices( $list ) );
		$this->assertFalse( nt_content_has_prices( '<p>Gọi 0942 467 674 để biết giá.</p>' ) );
		$this->assertFalse( nt_content_has_prices( '<p>Chỉ có một mức 150.000đ.</p>' ) );
	}
}
