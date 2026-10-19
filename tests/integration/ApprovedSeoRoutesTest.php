<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ApprovedSeoRoutesTest extends TestCase {

	public function test_only_the_18_audited_aliases_have_explicit_rewrite_rules(): void {
		$this->assertCount( 15, NT_Compat_Legacy_Routes::items() );
		$this->assertCount( 3, NT_Compat_Legacy_Routes::terms() );
		$rules = get_option( 'rewrite_rules' );
		$this->assertIsArray( $rules );
		foreach ( NT_Compat_Legacy_Routes::items() as $path => $id ) {
			$this->assertSame( 'index.php?post_type=featured_item&p=' . $id, $rules[ '^' . preg_quote( $path, '#' ) . '/?$' ] ?? null, $path );
		}
		foreach ( NT_Compat_Legacy_Routes::terms() as $path => $id ) {
			$this->assertSame( 'index.php?featured_item_category=' . basename( $path ), $rules[ '^' . preg_quote( $path, '#' ) . '/?$' ] ?? null, $path );
			$this->assertSame( basename( $path ), get_term( $id, 'featured_item_category' )->slug );
		}
		$this->assertArrayNotHasKey( '^bo-suu-tap/bang-gia/?$', $rules );
	}

	public function test_old_item_urls_serve_the_existing_posts_and_new_routes_survive(): void {
		foreach ( NT_Compat_Legacy_Routes::items() as $path => $id ) {
			$this->assertSame( basename( $path ), get_post( $id )->post_name );
			foreach ( array( home_url( '/' . $path . '/' ), get_permalink( $id ) ) as $url ) {
				$response = wp_remote_get( $url, array( 'redirection' => 0, 'timeout' => 15 ) );
				$this->assertFalse( is_wp_error( $response ), $url );
				$this->assertSame( 200, wp_remote_retrieve_response_code( $response ), $url );
				$this->assertStringContainsString( get_the_title( $id ), wp_remote_retrieve_body( $response ), $url );
			}
		}
	}

	public function test_old_term_urls_serve_the_existing_archives(): void {
		foreach ( NT_Compat_Legacy_Routes::terms() as $path => $id ) {
			$response = wp_remote_get( home_url( '/' . $path . '/' ), array( 'redirection' => 0, 'timeout' => 15 ) );
			$this->assertFalse( is_wp_error( $response ), $path );
			$this->assertSame( 200, wp_remote_retrieve_response_code( $response ), $path );
			$this->assertStringContainsString( get_term( $id, 'featured_item_category' )->name, wp_remote_retrieve_body( $response ), $path );
		}
	}
}
