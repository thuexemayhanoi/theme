<?php
/**
 * Related posts: same topic only, published posts only, capped, and computed with a fixed
 * number of queries (no N+1). Falls back to nothing rather than to unrelated links.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Category ids that describe a post's topic: its categories without the default bucket and the
 * root "Blog" category (which every post shares and therefore says nothing about topic).
 *
 * @param int $post_id Post id.
 * @return array{topic:int[],parents:int[]}
 */
function nt_topic_terms( int $post_id ): array {
	$default = (int) get_option( 'default_category' );
	$root    = nt_blog_root_id();
	$topic   = array();
	$parents = array();
	foreach ( (array) get_the_category( $post_id ) as $term ) {
		if ( $term->term_id === $default || $term->term_id === $root ) {
			continue;
		}
		$topic[] = (int) $term->term_id;
		if ( $term->parent > 0 && (int) $term->parent !== $root ) {
			$parents[] = (int) $term->parent;
		}
	}
	return array(
		'topic'   => array_values( array_unique( $topic ) ),
		'parents' => array_values( array_unique( $parents ) ),
	);
}

/**
 * Term id of the root blog category (slug "blog"), 0 when absent.
 */
function nt_blog_root_id(): int {
	static $id = null;
	if ( null === $id ) {
		$term = get_term_by( 'slug', 'blog', 'category' );
		$id   = ( $term instanceof WP_Term ) ? (int) $term->term_id : 0;
	}
	return $id;
}

/**
 * IDs of related posts for a post.
 *
 * @param int $post_id Post id.
 * @param int $limit   Maximum number of posts.
 * @return int[]
 */
function nt_related_post_ids( int $post_id, int $limit = 4 ): array {
	$limit = max( 1, min( 12, $limit ) );
	$key   = sprintf( 'rel_%d_%d_%s', $post_id, $limit, wp_cache_get_last_changed( 'posts' ) );
	$hit   = wp_cache_get( $key, 'nt_related' );
	if ( is_array( $hit ) ) {
		return $hit;
	}

	$terms = nt_topic_terms( $post_id );
	$ids   = array();
	// Tier 1 is the post's own topic categories only. Tier 2 is the broader parent topic WITH its
	// sub-categories: category__in matches direct assignments only, so a post that sits just in a
	// sibling sub-category would never be found.
	$tiers = array(
		array(
			'terms'    => $terms['topic'],
			'children' => false,
		),
		array(
			'terms'    => $terms['parents'],
			'children' => true,
		),
	);
	foreach ( $tiers as $tier ) {
		if ( array() === $tier['terms'] || count( $ids ) >= $limit ) {
			continue;
		}
		$query = new WP_Query(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- one bounded query per tier (posts_per_page), result cached.
				'tax_query'              => array(
					array(
						'taxonomy'         => 'category',
						'field'            => 'term_id',
						'terms'            => $tier['terms'],
						'include_children' => $tier['children'],
					),
				),
				'post__not_in'           => array_merge( array( $post_id ), $ids ),
				'posts_per_page'         => $limit - count( $ids ),
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'ignore_sticky_posts'    => true,
			)
		);
		$ids   = array_merge( $ids, array_map( 'intval', $query->posts ) );
	}

	wp_cache_set( $key, $ids, 'nt_related', 6 * HOUR_IN_SECONDS );
	return $ids;
}

/**
 * WP_Query of related posts with term and meta caches primed in bulk.
 *
 * @param int $post_id Post id.
 * @param int $limit   Maximum number of posts.
 */
function nt_related_query( int $post_id, int $limit = 4 ): ?WP_Query {
	$ids = nt_related_post_ids( $post_id, $limit );
	if ( array() === $ids ) {
		return null;
	}
	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'post__in'            => $ids,
			'orderby'             => 'post__in',
			'posts_per_page'      => count( $ids ),
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		)
	);
	if ( $query->have_posts() ) {
		update_post_thumbnail_cache( $query );
	}
	return $query;
}
