<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * A homepage schema correction must never remove Article from ordinary posts.
 */
final class HomepageArticleSchemaTest extends TestCase {

	public function test_article_filter_is_scoped_to_page_562_as_the_front_page(): void {
		$original_front = get_option( 'show_on_front' );
		$original_page  = get_option( 'page_on_front' );
		global $wp_query;
		$original_query = $wp_query;

		try {
			$this->assertInstanceOf( WP_Post::class, get_post( 562 ) );
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', 562 );
			$wp_query = new WP_Query( array( 'page_id' => 562 ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$this->assertTrue( is_front_page() );
			$this->assertFalse( apply_filters( 'wpseo_schema_needs_article', true ) );

			$wp_query = new WP_Query( array( 'p' => 823 ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$this->assertTrue( is_single() );
			$this->assertTrue( apply_filters( 'wpseo_schema_needs_article', true ) );
			$this->assertFalse( apply_filters( 'wpseo_schema_needs_article', false ) );

			$wp_query = new WP_Query( array( 'page_id' => 310 ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$this->assertTrue( is_page( 310 ) );
			$this->assertTrue( apply_filters( 'wpseo_schema_needs_article', true ) );

			update_option( 'page_on_front', 310 );
			$this->assertTrue( is_front_page() );
			$this->assertTrue( apply_filters( 'wpseo_schema_needs_article', true ), 'Do not change a replacement homepage without a fresh audit.' );

			$wp_query = new WP_Query( array( 'page_id' => 562 ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$this->assertFalse( is_front_page() );
			$this->assertTrue( apply_filters( 'wpseo_schema_needs_article', true ), 'Page 562 is exempt only while it is the static front page.' );
		} finally {
			update_option( 'show_on_front', $original_front );
			update_option( 'page_on_front', $original_page );
			$wp_query = $original_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}
}
