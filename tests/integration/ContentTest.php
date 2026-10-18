<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ContentTest extends TestCase {

	public function test_map_embeds_gain_missing_hints_without_changing_destinations(): void {
		$map = '<iframe src="https://www.google.com/maps/embed?pb=verified" width="600" height="450" allowfullscreen></iframe>';
		$out = nt_prepare_map_embeds( '<h1>Liên hệ</h1><p>0942 467 674</p>' . $map );
		$tags = new WP_HTML_Tag_Processor( $out );
		$this->assertTrue( $tags->next_tag( 'iframe' ) );
		$this->assertSame( 'lazy', $tags->get_attribute( 'loading' ) );
		$this->assertSame( 'Bản đồ Google Maps', $tags->get_attribute( 'title' ) );
		$this->assertSame( 'https://www.google.com/maps/embed?pb=verified', $tags->get_attribute( 'src' ) );
		$this->assertSame( '600', $tags->get_attribute( 'width' ) );
		$this->assertSame( '450', $tags->get_attribute( 'height' ) );
		$this->assertTrue( $tags->get_attribute( 'allowfullscreen' ) );
		$this->assertStringContainsString( '<h1>Liên hệ</h1><p>0942 467 674</p>', $out );
		$this->assertSame( $out, nt_prepare_map_embeds( $out ), 'rendering twice is idempotent' );
	}

	public function test_map_embed_hints_respect_authored_settings_and_other_providers(): void {
		$html = '<iframe src="https://www.google.com/maps/embed?pb=verified" title="Điểm nhận xe" loading="eager"></iframe>'
			. '<iframe src="https://www.youtube.com/embed/example"></iframe>'
			. '<iframe src="https://google.com.example.org/maps/embed"></iframe>';
		$this->assertSame( $html, nt_prepare_map_embeds( $html ) );
	}

	public function test_counts_match_the_baseline(): void {
		$base  = nt_it_baseline()['baseline_counts'];
		$posts = wp_count_posts( 'post' );
		$this->assertSame( $base['published_posts'], (int) $posts->publish, '160 published posts' );
		$this->assertSame( $base['published_products'], (int) wp_count_posts( 'product' )->publish, '13 products' );
		$this->assertTrue( post_type_exists( 'featured_item' ) );
		$this->assertSame( $base['published_featured_items'], (int) wp_count_posts( 'featured_item' )->publish, '15 featured items' );
	}

	public function test_every_baseline_page_exists_with_its_id_slug_and_parent(): void {
		foreach ( nt_it_baseline()['pages'] as $expected ) {
			$post = get_post( (int) $expected['ID'] );
			$this->assertInstanceOf( WP_Post::class, $post, "page {$expected['ID']}" );
			$this->assertSame( 'publish', $post->post_status );
			$this->assertSame( $expected['post_name'], $post->post_name );
		}
		$this->assertSame( 562, (int) get_option( 'page_on_front' ) );
		$this->assertSame( 'page', get_option( 'show_on_front' ) );
		$this->assertSame( '/%postname%/', get_option( 'permalink_structure' ) );
		$this->assertSame( '/gioi-thieu/ve-chung-toi/', nt_it_path( (string) get_permalink( 424 ) ) );
		$this->assertSame( '/', nt_it_path( (string) get_permalink( 562 ) ), 'the front page keeps the / URL' );
	}

	public function test_products_and_featured_items_keep_ids_slugs_and_urls(): void {
		foreach ( nt_it_baseline()['products'] as $expected ) {
			$post = get_post( (int) $expected['ID'] );
			$this->assertSame( $expected['post_name'], $post->post_name );
			$this->assertSame( '/san-pham/' . $expected['post_name'] . '/', nt_it_path( (string) get_permalink( $post ) ) );
		}
		foreach ( nt_it_baseline()['featured_items'] as $expected ) {
			$post = get_post( (int) $expected['ID'] );
			$this->assertInstanceOf( WP_Post::class, $post );
			$this->assertSame( 'featured_item', $post->post_type );
			$this->assertSame( $expected['post_name'], $post->post_name );
		}
	}

	public function test_featured_items_are_in_term_77_and_one_period_term(): void {
		$counts = array( 78 => 0, 79 => 0, 80 => 0 );
		foreach ( nt_it_baseline()['featured_items'] as $expected ) {
			$terms = wp_list_pluck( (array) get_the_terms( (int) $expected['ID'], 'featured_item_category' ), 'term_id' );
			$this->assertContains( 77, array_map( 'intval', $terms ), "item {$expected['ID']} is in Bảng giá" );
			foreach ( array( 78, 79, 80 ) as $period ) {
				if ( in_array( $period, array_map( 'intval', $terms ), true ) ) {
					++$counts[ $period ];
				}
			}
		}
		$this->assertSame( array( 78 => 5, 79 => 5, 80 => 5 ), $counts );
	}

	public function test_theme_does_not_mutate_content(): void {
		global $wpdb;
		$before = (string) $wpdb->get_var( "SELECT MD5(GROUP_CONCAT(CONCAT(ID, post_modified_gmt) ORDER BY ID)) FROM {$wpdb->posts} WHERE post_type IN ('post','page','product','featured_item')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		nt_it_render( 562 );
		nt_home_posts_query( 6 );
		nt_related_post_ids( (int) get_posts( array( 'numberposts' => 1, 'fields' => 'ids' ) )[0], 4 );
		$after = (string) $wpdb->get_var( "SELECT MD5(GROUP_CONCAT(CONCAT(ID, post_modified_gmt) ORDER BY ID)) FROM {$wpdb->posts} WHERE post_type IN ('post','page','product','featured_item')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertSame( $before, $after, 'rendering must never write posts' );
	}
}
