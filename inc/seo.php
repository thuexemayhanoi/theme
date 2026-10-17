<?php
/**
 * SEO integration: Yoast SEO is the single source of truth for titles, meta descriptions,
 * canonical, robots, Open Graph, Twitter cards, XML sitemaps and schema.
 *
 * This file therefore:
 *  - emits NOTHING for those tags while an SEO plugin is active;
 *  - provides the breadcrumb markup (Yoast's own when enabled, a schema-free copy otherwise);
 *  - keeps exactly one <h1> per template;
 *  - offers a tiny defensive <head> fallback ONLY when no SEO plugin is active.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Slug of the active SEO plugin, or an empty string.
 */
function nt_seo_plugin(): string {
	if ( defined( 'WPSEO_VERSION' ) ) {
		return 'yoast';
	}
	if ( defined( 'RANK_MATH_VERSION' ) ) {
		return 'rankmath';
	}
	if ( defined( 'AIOSEO_VERSION' ) ) {
		return 'aioseo';
	}
	if ( defined( 'SEOPRESS_VERSION' ) ) {
		return 'seopress';
	}
	return '';
}

/**
 * Whether any SEO plugin owns the head.
 */
function nt_seo_plugin_active(): bool {
	return '' !== nt_seo_plugin();
}

/**
 * Whether Yoast's breadcrumb function exists and the breadcrumbs option is on.
 */
function nt_yoast_breadcrumbs_enabled(): bool {
	if ( ! function_exists( 'yoast_breadcrumb' ) || ! class_exists( 'WPSEO_Options' ) ) {
		return false;
	}
	return (bool) WPSEO_Options::get( 'breadcrumbs-enable', false );
}

/**
 * Primary category of a post (Yoast's primary term when set, otherwise the deepest category).
 *
 * @param int $post_id Post id.
 */
function nt_primary_category( int $post_id ): ?WP_Term {
	if ( class_exists( 'WPSEO_Primary_Term' ) ) {
		$primary = new WPSEO_Primary_Term( 'category', $post_id );
		$term_id = (int) $primary->get_primary_term();
		if ( $term_id > 0 ) {
			$term = get_term( $term_id, 'category' );
			if ( $term instanceof WP_Term ) {
				return $term;
			}
		}
	}
	$terms = get_the_category( $post_id );
	if ( ! is_array( $terms ) || array() === $terms ) {
		return null;
	}
	$default = (int) get_option( 'default_category' );
	$best    = null;
	$best_d  = -1;
	foreach ( $terms as $term ) {
		$depth = count( get_ancestors( $term->term_id, 'category', 'taxonomy' ) );
		// Prefer a real topic over the default "Uncategorized" bucket.
		if ( $term->term_id === $default && count( $terms ) > 1 ) {
			continue;
		}
		if ( $depth > $best_d ) {
			$best   = $term;
			$best_d = $depth;
		}
	}
	return $best ?? $terms[0];
}

/**
 * Ancestor terms (root first) followed by the term itself.
 *
 * @param WP_Term $term Term.
 * @return array<int,array{label:string,url:string}>
 */
function nt_term_trail( WP_Term $term ): array {
	$trail = array();
	foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
		$ancestor = get_term( $ancestor_id, $term->taxonomy );
		$link     = ( $ancestor instanceof WP_Term ) ? get_term_link( $ancestor ) : '';
		if ( $ancestor instanceof WP_Term && is_string( $link ) ) {
			$trail[] = array(
				'label' => nt_decode( $ancestor->name ),
				'url'   => $link,
			);
		}
	}
	$self = get_term_link( $term );
	if ( is_string( $self ) ) {
		$trail[] = array(
			'label' => nt_decode( $term->name ),
			'url'   => $self,
		);
	}
	return $trail;
}

/**
 * Breadcrumb trail for the current request. The last item has an empty URL (current page).
 *
 * @return array<int,array{label:string,url:string}>
 */
function nt_breadcrumb_trail(): array {
	$trail = array(
		array(
			'label' => 'Trang chủ',
			'url'   => home_url( '/' ),
		),
	);

	if ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			if ( 'post' === $post->post_type ) {
				$cat = nt_primary_category( $post->ID );
				if ( $cat instanceof WP_Term ) {
					$trail = array_merge( $trail, nt_term_trail( $cat ) );
				}
			} elseif ( 'product' === $post->post_type ) {
				$shop_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
				if ( $shop_id > 0 ) {
					$trail[] = array(
						'label' => nt_decode( get_the_title( $shop_id ) ),
						'url'   => (string) get_permalink( $shop_id ),
					);
				}
				$terms = get_the_terms( $post->ID, 'product_cat' );
				if ( is_array( $terms ) && array() !== $terms ) {
					$deepest = null;
					$best    = -1;
					foreach ( $terms as $t ) {
						$d = count( get_ancestors( $t->term_id, 'product_cat', 'taxonomy' ) );
						if ( $d > $best ) {
							$best    = $d;
							$deepest = $t;
						}
					}
					if ( $deepest instanceof WP_Term ) {
						$trail = array_merge( $trail, nt_term_trail( $deepest ) );
					}
				}
			} elseif ( 'featured_item' === $post->post_type ) {
				$terms = get_the_terms( $post->ID, 'featured_item_category' );
				if ( is_array( $terms ) && array() !== $terms ) {
					$deepest = null;
					$best    = -1;
					foreach ( $terms as $t ) {
						$d = count( get_ancestors( $t->term_id, 'featured_item_category', 'taxonomy' ) );
						if ( $d > $best ) {
							$best    = $d;
							$deepest = $t;
						}
					}
					if ( $deepest instanceof WP_Term ) {
						$trail = array_merge( $trail, nt_term_trail( $deepest ) );
					}
				}
			} elseif ( is_post_type_hierarchical( $post->post_type ) ) {
				foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
					$trail[] = array(
						'label' => nt_decode( get_the_title( $ancestor_id ) ),
						'url'   => (string) get_permalink( $ancestor_id ),
					);
				}
			}
			$trail[] = array(
				'label' => nt_decode( get_the_title( $post ) ),
				'url'   => '',
			);
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$terms = nt_term_trail( $term );
			array_pop( $terms );
			$trail   = array_merge( $trail, $terms );
			$trail[] = array(
				'label' => nt_decode( $term->name ),
				'url'   => '',
			);
		}
	} elseif ( is_search() ) {
		$trail[] = array(
			'label' => 'Tìm kiếm',
			'url'   => '',
		);
	} elseif ( is_author() ) {
		$trail[] = array(
			'label' => (string) get_the_author_meta( 'display_name', (int) get_queried_object_id() ),
			'url'   => '',
		);
	} elseif ( is_404() ) {
		$trail[] = array(
			'label' => 'Không tìm thấy trang',
			'url'   => '',
		);
	} elseif ( is_archive() ) {
		$trail[] = array(
			'label' => wp_strip_all_tags( get_the_archive_title() ),
			'url'   => '',
		);
	}

	/**
	 * Filter the breadcrumb trail.
	 *
	 * @param array<int,array{label:string,url:string}> $trail Trail.
	 */
	return (array) apply_filters( 'nguyentu_breadcrumb_trail', $trail );
}

/**
 * Print the breadcrumb. Yoast's own markup is used when its breadcrumbs are enabled so the
 * visible trail and the BreadcrumbList schema are the same data; otherwise a schema-free list.
 */
function nt_breadcrumbs(): void {
	if ( is_front_page() ) {
		return;
	}
	if ( nt_yoast_breadcrumbs_enabled() ) {
		yoast_breadcrumb( '<nav class="nt-breadcrumb nt-breadcrumb--yoast" aria-label="Breadcrumb">', '</nav>' );
		return;
	}
	$trail = nt_breadcrumb_trail();
	if ( count( $trail ) < 2 ) {
		return;
	}
	echo '<nav class="nt-breadcrumb" aria-label="Breadcrumb"><ol>';
	$last = count( $trail ) - 1;
	foreach ( $trail as $i => $crumb ) {
		if ( $i === $last || '' === $crumb['url'] ) {
			printf( '<li><span aria-current="page">%s</span></li>', esc_html( $crumb['label'] ) );
		} else {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $crumb['url'] ), esc_html( $crumb['label'] ) );
		}
	}
	echo '</ol></nav>';
}

/**
 * Whether the theme should print its own page-title <h1> for a rendered body.
 * If the body already holds an <h1> (e.g. a legacy hero) the existing SEO heading wins and
 * the template must not add a second one.
 *
 * @param string $rendered_html Rendered main content.
 */
function nt_should_print_h1( string $rendered_html ): bool {
	return ! nt_html_has_h1( $rendered_html );
}

/**
 * Defensive <head> fallback. Runs only when NO SEO plugin is active (staging accident,
 * plugin switched off); with Yoast active it prints nothing at all.
 */
function nt_seo_fallback_head(): void {
	if ( nt_seo_plugin_active() ) {
		return;
	}
	$desc = '';
	if ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			$desc = has_excerpt( $post ) ? get_the_excerpt( $post ) : nt_trim_words( nt_plain_text( strip_shortcodes( $post->post_content ) ), 28, '' );
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$desc = nt_plain_text( term_description() );
	} elseif ( is_front_page() || is_home() ) {
		$desc = (string) get_bloginfo( 'description' );
	}
	$desc = trim( wp_strip_all_tags( $desc ) );
	if ( '' !== $desc ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
	}
	// Core already prints rel=canonical for singular views; cover term archives only.
	if ( ( is_category() || is_tag() || is_tax() ) && ! is_paged() ) {
		$link = get_term_link( get_queried_object() );
		if ( is_string( $link ) ) {
			printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $link ) );
		}
	}
}
add_action( 'wp_head', 'nt_seo_fallback_head', 1 );

/**
 * Keep 404 pages out of the index when no SEO plugin does it (search results are already
 * handled by WordPress core's wp_robots filter).
 *
 * @param array<string,mixed> $robots Robots directives.
 * @return array<string,mixed>
 */
function nt_fallback_robots( array $robots ): array {
	if ( ! nt_seo_plugin_active() && is_404() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'nt_fallback_robots' );

/**
 * Compact diagnostics for the settings screen and WP-CLI.
 *
 * @return array<string,mixed>
 */
function nt_seo_status(): array {
	return array(
		'seo_plugin'          => nt_seo_plugin(),
		'yoast_version'       => defined( 'WPSEO_VERSION' ) ? WPSEO_VERSION : '',
		'yoast_breadcrumbs'   => nt_yoast_breadcrumbs_enabled(),
		'blog_public'         => (int) get_option( 'blog_public' ),
		'permalink_structure' => (string) get_option( 'permalink_structure' ),
		'local_business'      => function_exists( 'nt_local_business_enabled' ) ? nt_local_business_enabled() : false,
	);
}
