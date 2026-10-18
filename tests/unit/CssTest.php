<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CssTest extends TestCase {

	/**
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function lengths(): array {
		return array(
			'bare number is px'  => array( '60', '60px' ),
			'px'                 => array( '300px', '300px' ),
			'percent'            => array( '50%', '50%' ),
			'rem uppercase unit' => array( '4REM', '4rem' ),
			'auto'               => array( 'auto', 'auto' ),
			'injection'          => array( '10px;background:url(x)', '' ),
			'expression'         => array( 'calc(1px+1px)', '' ),
			'url'                => array( 'url(javascript:1)', '' ),
			'empty'              => array( '', '' ),
		);
	}

	#[DataProvider( 'lengths' )]
	public function test_css_len( string $in, string $out ): void {
		$this->assertSame( $out, nt_css_len( $in ) );
	}

	public function test_css_box_accepts_up_to_four_safe_lengths(): void {
		$this->assertSame( '10px 20px', nt_css_box( '10 20' ) );
		$this->assertSame( '', nt_css_box( '1 2 3 4 5' ) );
		$this->assertSame( '', nt_css_box( '10px red' ) );
	}

	/**
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function colors(): array {
		return array(
			'hex'          => array( '#D2381F', '#d2381f' ),
			'short hex'    => array( '#fff', '#fff' ),
			'rgba'         => array( 'rgba(12, 26, 51, 0.62)', 'rgba(12, 26, 51, 0.62)' ),
			'rgb'          => array( 'rgb(250, 247, 240)', 'rgb(250, 247, 240)' ),
			'keyword'      => array( 'Transparent', 'transparent' ),
			'named color'  => array( 'red', '' ),
			'injection'    => array( 'red;position:fixed', '' ),
			'url'          => array( 'url(x)', '' ),
			'bad hex'      => array( '#12', '' ),
		);
	}

	#[DataProvider( 'colors' )]
	public function test_css_color( string $in, string $out ): void {
		$this->assertSame( $out, nt_css_color( $in ) );
	}

	public function test_css_position_and_int_and_style(): void {
		$this->assertSame( '50% 40%', nt_css_position( '50% 40%' ) );
		$this->assertSame( 'left top', nt_css_position( 'left top' ) );
		$this->assertSame( '', nt_css_position( '50% 40%; x' ) );
		$this->assertSame( 12, nt_css_int( '99', 1, 12, 12 ) );
		$this->assertSame( 1, nt_css_int( '-5', 1, 12, 12 ) );
		$this->assertSame( 12, nt_css_int( 'abc', 1, 12, 12 ) );
		$this->assertSame( '--lg-pad:10px;color:#fff;', nt_css_style( array( '--lg-pad' => '10px', 'color' => '#fff', 'x' => '' ) ) );
		$this->assertSame( '', nt_css_style( array( 'bad prop;' => '1' ) ) );
	}

	public function test_css_classes_drop_unsafe_tokens(): void {
		$this->assertSame( array( 'text-center', 'a_b' ), nt_css_classes( 'text-center "><script> a_b 9bad' ) );
	}
}
