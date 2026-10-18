<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ThemeTest extends TestCase {

	public function test_theme_supports_and_assets(): void {
		foreach ( array( 'title-tag', 'post-thumbnails', 'html5', 'custom-logo', 'responsive-embeds', 'editor-styles', 'automatic-feed-links', 'woocommerce', 'wc-product-gallery-zoom', 'wc-product-gallery-lightbox', 'wc-product-gallery-slider' ) as $feature ) {
			$this->assertTrue( current_theme_supports( $feature ), $feature );
		}
		$this->assertFalse( current_theme_supports( 'yoast-seo-breadcrumbs' ), 'must not flip Yoast breadcrumb/schema settings' );
		foreach ( array( 'assets/css/main.css', 'assets/css/home.css', 'assets/css/blog.css', 'assets/css/woo.css', 'assets/css/legacy.css', 'assets/js/theme.js', 'assets/fonts/plus-jakarta-sans-latin-wght-normal.woff2' ) as $rel ) {
			$this->assertFileExists( get_template_directory() . '/' . $rel );
		}
	}

	public function test_widget_areas_and_template_hierarchy_files(): void {
		global $wp_registered_sidebars;
		$this->assertArrayHasKey( 'sidebar-blog', $wp_registered_sidebars );
		$this->assertArrayHasKey( 'footer-extra', $wp_registered_sidebars );
		foreach ( array( 'front-page.php', 'single.php', 'page.php', 'home.php', 'archive.php', 'category.php', 'search.php', '404.php', 'header.php', 'footer.php', 'single-featured_item.php', 'taxonomy-featured_item_category.php' ) as $tpl ) {
			$this->assertNotSame( '', locate_template( $tpl ), $tpl );
		}
	}

	public function test_head_cleanup_keeps_feeds_canonical_and_rest_discovery(): void {
		$this->assertFalse( has_action( 'wp_head', 'wp_generator' ) );
		$this->assertFalse( has_action( 'wp_head', 'print_emoji_detection_script' ) );
		$this->assertNotFalse( has_action( 'wp_head', 'rel_canonical' ) || defined( 'WPSEO_VERSION' ), 'canonical stays with core/Yoast' );
		$this->assertNotFalse( has_action( 'wp_head', 'feed_links' ), 'RSS feed links stay' );
		$this->assertNotFalse( has_action( 'wp_head', 'rest_output_link_wp_head' ), 'REST discovery stays' );
	}

	public function test_quickcall_and_chat_detection_follow_active_plugins(): void {
		$this->assertIsBool( nt_quickcall_active() );
		$this->assertIsBool( nt_chat_widget_active() );
		$bar = nt_show_cta_bar();
		$this->assertSame( ! nt_quickcall_active(), $bar, 'auto mode: the theme bar shows only when QuickCall is absent' );
	}

	public function test_status_checks_report_the_environment(): void {
		$checks = nt_status_checks();
		$labels = array_column( $checks, 'label' );
		$this->assertContains( 'WooCommerce', $labels );
		$this->assertContains( 'Vị trí menu: primary', $labels );
		$this->assertTrue( current( array_filter( $checks, static fn( $c ) => 'WooCommerce' === $c['label'] ) )['ok'] );
	}

	public function test_related_posts_are_same_topic_published_only_and_capped(): void {
		$ids = get_posts( array( 'numberposts' => 1, 'fields' => 'ids', 'category' => 157 ) );
		$this->assertNotEmpty( $ids );
		$related = nt_related_post_ids( (int) $ids[0], 4 );
		$this->assertLessThanOrEqual( 4, count( $related ) );
		$this->assertNotContains( (int) $ids[0], $related );
		foreach ( $related as $rid ) {
			$this->assertSame( 'publish', get_post_status( $rid ) );
			$this->assertSame( 'post', get_post_type( $rid ) );
		}
		$topic = array_merge( array( 157 ), get_term_children( 157, 'category' ) );
		foreach ( $related as $rid ) {
			$cats = wp_get_post_categories( $rid );
			$this->assertNotEmpty( array_intersect( $cats, array_merge( $topic, array( 69 ) ) ), "post {$rid} shares the topic" );
		}
	}

	public function test_related_posts_fall_back_to_the_whole_parent_topic_including_its_sub_categories(): void {
		// Category 177 is a child of 127. Its only post has no other category, so the exact-topic tier is empty and every
		// related post must come from the parent topic: its own posts AND the posts that sit only in sibling sub-categories.
		$ids = get_posts( array( 'category__in' => array( 177 ), 'numberposts' => 1, 'fields' => 'ids' ) );
		$this->assertNotEmpty( $ids, 'category 177 has a post in the sandbox' );
		$post_id = (int) $ids[0];
		$terms   = nt_topic_terms( $post_id );
		$this->assertSame( array( 177 ), $terms['topic'] );
		$this->assertSame( array( 127 ), $terms['parents'] );

		$pool = static function ( array $category_ids ) use ( $post_id ): array {
			return array_map( 'intval', get_posts( array( 'category__in' => $category_ids, 'post__not_in' => array( $post_id ), 'numberposts' => -1, 'fields' => 'ids', 'post_status' => 'publish' ) ) );
		};
		$with_children = $pool( array_merge( array( 127 ), get_term_children( 127, 'category' ) ) );
		$direct_only   = $pool( array( 127 ) );
		$limit         = 12;
		$this->assertGreaterThan( min( $limit, count( $direct_only ) ), min( $limit, count( $with_children ) ), 'fixture check: posts that sit only in sibling sub-categories exist, so the two behaviours differ' );

		$related = nt_related_post_ids( $post_id, $limit );
		$this->assertCount( min( $limit, count( $with_children ) ), $related, 'the parent tier reaches the sibling sub-categories' );
		$this->assertSame( array(), array_diff( $related, $with_children ), 'only posts of the parent topic' );
		$this->assertNotContains( $post_id, $related );
		$this->assertSame( count( $related ), count( array_unique( $related ) ), 'no post twice' );
	}

	public function test_url_localization_points_menu_links_at_the_current_host(): void {
		$items = wp_get_nav_menu_items( 67 );
		$this->assertNotEmpty( $items );
		foreach ( nt_localize_menu_items( $items ) as $item ) {
			$this->assertStringNotContainsString( 'thuexemaynguyentu.com', (string) $item->url, 'staging links must not leak to production' );
		}
	}
}
