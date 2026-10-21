<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LayoutTest extends TestCase {

	public function test_wide_legacy_layouts_are_recognised_by_their_bridge_classes(): void {
		$this->assertTrue( nt_content_is_wide( '<div class="nt-legacy"><section class="nt-lg-section">x</section></div>' ) );
		$this->assertTrue( nt_content_is_wide( '<div class="nt-lg-banner">x</div>' ) );
		$this->assertTrue( nt_content_is_wide( '<div class="nt-lg-row">x</div>' ) );
		$this->assertTrue( nt_content_is_wide( '<div class="nt-service-page"><section>Native page</section></div>' ) );
		$this->assertFalse( nt_content_is_wide( '<h2>Giá thuê</h2><p>Văn bản thường.</p>' ) );
		$this->assertFalse( nt_content_is_wide( '<div class="nt-lg-title">only a title</div>' ) );
	}

	/**
	 * @return array<string,array{0:bool,1:bool,2:string}>
	 */
	public static function pagehead_cases(): array {
		return array(
			'plain body, template prints the H1'          => array( true, false, 'full' ),
			'plain body that already has its own H1'      => array( false, false, 'full' ),
			'wide body without an H1: template prints it' => array( true, true, 'full' ),
			'wide body that owns the H1: crumbs only'     => array( false, true, 'crumbs' ),
		);
	}

	#[DataProvider( 'pagehead_cases' )]
	public function test_pagehead_keeps_a_breadcrumb_without_adding_a_second_h1( bool $print_h1, bool $is_wide, string $expected ): void {
		$this->assertSame( $expected, nt_pagehead_mode( $print_h1, $is_wide ) );
	}

	/**
	 * @return array<string,array{0:bool,1:string,2:string}>
	 */
	public static function facts_cases(): array {
		return array(
			'native hero, auto'                 => array( false, 'auto', 'hero' ),
			'native hero, on'                   => array( false, 'on', 'hero' ),
			'native hero, off'                  => array( false, 'off', 'none' ),
			'authored hero, auto: nothing added' => array( true, 'auto', 'none' ),
			'authored hero, on: strip'          => array( true, 'on', 'strip' ),
			'authored hero, off'                => array( true, 'off', 'none' ),
		);
	}

	#[DataProvider( 'facts_cases' )]
	public function test_quick_facts_follow_the_setting_even_when_the_content_owns_the_hero( bool $content_has_h1, string $mode, string $expected ): void {
		$this->assertSame( $expected, nt_home_facts_placement( $content_has_h1, $mode ) );
	}
}
