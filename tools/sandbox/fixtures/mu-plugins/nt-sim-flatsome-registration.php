<?php
/**
 * Plugin Name: NT SIM - Flatsome featured_item registration (SIMULATION)
 * Description: SIMULATION ONLY. Re-creates what the Flatsome theme registers on production for the legacy "featured_item" post type and "featured_item_category" taxonomy, so a companion plugin's capture-then-replay behaviour can be tested. Installed by tools/sandbox/sync.sh only when SIM_FLATSOME=1 (default); removed when SIM_FLATSOME=0. Never deploy.
 * Version:     1.0.0
 *
 * Behaviour reproduced (init priority 10, like a theme file loaded on init):
 *   post type  featured_item           public, show_in_rest, supports title/editor/thumbnail/excerpt/page-attributes,
 *                                      rewrite slug "featured_item"  => /featured_item/<slug>/
 *   taxonomy   featured_item_category  hierarchical, rewrite slug "bo-suu-tap", rewrite hierarchical=false
 *                                      => /bo-suu-tap/<term-slug>/  (also for child terms: /bo-suu-tap/thue-ngay/)
 *
 * NOTE: the exact registration arguments of the real Flatsome theme were not read from production
 * (no access). Only the facts listed above come from the audit brief; everything else here is a
 * plausible default. Treat this file as a stand-in, not as evidence about Flatsome.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	static function () {
		register_post_type(
			'featured_item',
			array(
				'labels'             => array(
					'name'               => 'Featured Items',
					'singular_name'      => 'Featured Item',
					'add_new_item'       => 'Add New Featured Item',
					'edit_item'          => 'Edit Featured Item',
					'all_items'          => 'All Featured Items',
					'menu_name'          => 'Featured Items',
					'search_items'       => 'Search Featured Items',
					'not_found'          => 'No featured items found.',
				),
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_nav_menus'  => true,
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-portfolio',
				'capability_type'    => 'post',
				'hierarchical'       => false,
				'has_archive'        => true,
				'query_var'          => true,
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
				'rewrite'            => array(
					'slug'       => 'featured_item',
					'with_front' => true,
				),
			)
		);

		register_taxonomy(
			'featured_item_category',
			'featured_item',
			array(
				'labels'            => array(
					'name'          => 'Featured Item Categories',
					'singular_name' => 'Featured Item Category',
					'menu_name'     => 'Categories',
					'all_items'     => 'All Categories',
					'edit_item'     => 'Edit Category',
					'add_new_item'  => 'Add New Category',
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_nav_menus' => true,
				'show_in_rest'      => true,
				'query_var'         => true,
				'rewrite'           => array(
					'slug'         => 'bo-suu-tap',
					'hierarchical' => false,
					'with_front'   => true,
				),
			)
		);
	},
	10
);

// Reproduce the effective permastructs and link filter verified on 2026-10-19. Their values
// differ from the registration objects: this discrepancy is exactly what v1.0 failed to replay.
add_action(
	'init',
	static function () {
		global $wp_rewrite;
		$wp_rewrite->add_permastruct( 'featured_item', 'bo-suu-tap/%featured_item_category%/%featured_item%', array( 'with_front' => true ) );
		$wp_rewrite->add_permastruct( 'featured_item_category', 'bo-suu-tap/%featured_item_category%', array( 'with_front' => true ) );
	},
	20
);
add_filter(
	'post_type_link',
	static function ( $url, $post, $leavename ) {
		if ( 'featured_item' !== $post->post_type ) {
			return $url;
		}
		$terms = get_the_terms( $post->ID, 'featured_item_category' );
		if ( ! is_array( $terms ) ) {
			return $url;
		}
		usort( $terms, static fn( $a, $b ) => $a->term_id <=> $b->term_id );
		return home_url( '/bo-suu-tap/' . implode( ',', wp_list_pluck( $terms, 'slug' ) ) . '/' . ( $leavename ? '%featured_item%' : $post->post_name ) . '/' );
	},
	10,
	3
);
