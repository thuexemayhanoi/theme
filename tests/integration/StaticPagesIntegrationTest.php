<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StaticPagesIntegrationTest extends TestCase {
	private function render( int $id, ?callable $before_body = null ): string {
		global $post, $wp_query;
		$prior_query = $wp_query;
		$prior_post  = $post;
		try {
			$wp_query = new WP_Query( array( 'page_id' => $id ) );
			$wp_query->the_post();
			if ( null !== $before_body ) {
				$before_body();
			}
			return nt_get_page_body();
		} finally {
			$wp_query = $prior_query;
			$post     = $prior_post;
			wp_reset_postdata();
		}
	}

	public function test_legacy_contact_is_native_but_its_source_and_seo_are_unchanged(): void {
		$source = get_post_field( 'post_content', 372 );
		$meta   = get_post_meta( 372 );
		$html   = $this->render( 372 );
		$this->assertStringContainsString( 'nt-contact-page', $html );
		$this->assertStringNotContainsString( 'nt-lg-', $html );
		$this->assertSame( 1, nt_html_count_tag( $html, 'h1' ) );
		$this->assertStringContainsString( 'tel:0942467674', $html );
		$this->assertStringContainsString( nt_business( 'street' ), $html );
		$this->assertSame( $source, get_post_field( 'post_content', 372 ) );
		$this->assertSame( $meta, get_post_meta( 372 ) );
	}

	public function test_collection_uses_three_real_term_links_without_images(): void {
		$html = $this->render( 494 );
		$this->assertStringContainsString( 'nt-collection-page', $html );
		$this->assertSame( 0, nt_html_count_tag( $html, 'img' ) );
		$this->assertSame( 1, nt_html_count_tag( $html, 'h1' ) );
		foreach ( array( 'thue-ngay', 'thue-tuan', 'thue-thang' ) as $slug ) {
			$this->assertNotSame( '', nt_featured_term_url( $slug ) );
			$this->assertStringContainsString( esc_url( nt_featured_term_url( $slug ) ), $html );
		}
	}

	public function test_the_saved_cf7_shortcode_runs_once_and_keeps_its_fields(): void {
		$source   = get_post_field( 'post_content', 372 );
		$previous = $GLOBALS['shortcode_tags']['contact-form-7'] ?? null;
		$runs     = 0;
		$traces   = array();
		try {
			add_shortcode( 'contact-form-7', static function () use ( &$runs, &$traces ): string {
				++$runs;
				$traces[] = wp_debug_backtrace_summary();
				return '<form><input name="your-email"><input type="hidden" name="token" value="kept"></form>';
			} );
			wp_update_post( array( 'ID' => 372, 'post_content' => $source . '[contact-form-7 id="8"]' ) );
			// Save/index hooks and query setup may inspect shortcode content too.
			// Count only callbacks caused by the visitor's page-body render.
			$html = $this->render( 372, static function () use ( &$runs, &$traces ): void {
				$runs   = 0;
				$traces = array();
			} );
			$this->assertSame( 1, $runs, implode( ' | ', $traces ) );
			$this->assertSame( 1, nt_html_count_tag( $html, 'form' ) );
			$this->assertStringContainsString( 'name="your-email"', $html );
			$this->assertStringContainsString( 'value="kept"', $html );
		} finally {
			wp_update_post( array( 'ID' => 372, 'post_content' => $source ) );
			if ( null === $previous ) {
				remove_shortcode( 'contact-form-7' );
			} else {
				add_shortcode( 'contact-form-7', $previous );
			}
		}
	}

	public function test_home_retains_its_banner_images(): void {
		$this->assertGreaterThan( 0, nt_html_count_tag( $this->render( 562 ), 'img' ) );
	}
}
