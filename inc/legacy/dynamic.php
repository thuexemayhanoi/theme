<?php
/**
 * Legacy data-driven shortcodes: products, product categories, blog posts, portfolio.
 *
 * They read live WordPress / WooCommerce data and render the theme's own cards. Counts are
 * capped so a stray attribute can never trigger an unbounded query.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve a list of term ids/slugs to slugs for a taxonomy.
 *
 * @param string $value    Attribute value.
 * @param string $taxonomy Taxonomy.
 * @return string[]
 */
function nt_lg_term_slugs( string $value, string $taxonomy ): array {
	$list  = nt_lg_id_slug_list( $value );
	$slugs = $list['slugs'];
	foreach ( $list['ids'] as $id ) {
		$term = get_term( $id, $taxonomy );
		if ( $term instanceof WP_Term ) {
			$slugs[] = $term->slug;
		}
	}
	return array_values( array_unique( $slugs ) );
}

/**
 * [ux_products]
 *
 * @param mixed $atts Attributes.
 */
function nt_lg_products( $atts ): string {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return '<!-- nt-legacy: [ux_products] needs WooCommerce -->';
	}
	$a = nt_lg_atts(
		array(
			'products'   => '8',
			'columns'    => '4',
			'columns__sm' => '2',
			'cat'        => '',
			'ids'        => '',
			'orderby'    => 'normal',
			'order'      => 'ASC',
			'show'       => '',
			'offset'     => '0',
			'class'      => '',
			'visibility' => '',
		),
		$atts,
		'ux_products'
	);
	$limit = nt_css_int( $a['products'], 1, 24, 8 );
	$args  = array(
		'status' => 'publish',
		'limit'  => $limit,
		'offset' => nt_css_int( $a['offset'], 0, 200, 0 ),
		'return' => 'objects',
	);
	$order_map = array(
		'normal' => 'menu_order',
		'sales'  => 'popularity',
		'rand'   => 'rand',
		'date'   => 'date',
		'title'  => 'title',
		'price'  => 'price',
	);
	$args['orderby'] = $order_map[ strtolower( $a['orderby'] ) ] ?? 'menu_order';
	$args['order']   = 'DESC' === strtoupper( $a['order'] ) ? 'DESC' : 'ASC';

	$ids = array_filter( array_map( 'intval', explode( ',', $a['ids'] ) ) );
	if ( array() !== $ids ) {
		$args['include'] = $ids;
		$args['orderby'] = 'post__in' === $args['orderby'] ? 'menu_order' : $args['orderby'];
	}
	$cats = nt_lg_term_slugs( $a['cat'], 'product_cat' );
	if ( array() !== $cats ) {
		$args['category'] = $cats;
	}
	if ( 'featured' === $a['show'] ) {
		$args['featured'] = true;
	}
	$products = wc_get_products( $args );
	if ( ! is_array( $products ) || array() === $products ) {
		return '';
	}
	$cols = nt_css_int( $a['columns'], 1, 6, 4 );
	$sm   = nt_css_int( $a['columns__sm'], 1, 3, 2 );
	ob_start();
	printf(
		'<ul class="%s" style="--lg-cols:%d;--lg-cols-sm:%d">',
		esc_attr( nt_lg_class( array_merge( array( 'nt-lg-products' ), nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) ) ),
		(int) $cols,
		(int) $sm
	);
	foreach ( $products as $product ) {
		echo '<li>';
		nt_product_card( $product, array( 'heading' => 'h3' ) );
		echo '</li>';
	}
	echo '</ul>';
	return (string) ob_get_clean();
}

/**
 * [ux_product_categories]
 *
 * @param mixed $atts Attributes.
 */
function nt_lg_product_categories( $atts ): string {
	if ( ! taxonomy_exists( 'product_cat' ) ) {
		return '<!-- nt-legacy: [ux_product_categories] needs WooCommerce -->';
	}
	$a = nt_lg_atts(
		array(
			'ids'        => '',
			'number'     => '8',
			'parent'     => '',
			'columns'    => '4',
			'hide_empty' => '1',
			'orderby'    => 'name',
			'class'      => '',
			'visibility' => '',
		),
		$atts,
		'ux_product_categories'
	);
	$query = array(
		'taxonomy'   => 'product_cat',
		'number'     => nt_css_int( $a['number'], 1, 24, 8 ),
		'hide_empty' => nt_lg_bool( $a['hide_empty'] ),
		'orderby'    => in_array( $a['orderby'], array( 'name', 'id', 'count', 'menu_order' ), true ) ? $a['orderby'] : 'name',
		'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
	);
	$ids = array_filter( array_map( 'intval', explode( ',', $a['ids'] ) ) );
	if ( array() !== $ids ) {
		$query['include'] = $ids;
		$query['exclude'] = array();
	} elseif ( '' !== $a['parent'] && ctype_digit( $a['parent'] ) ) {
		$query['parent'] = (int) $a['parent'];
	}
	$terms = get_terms( $query );
	if ( ! is_array( $terms ) || array() === $terms ) {
		return '';
	}
	$cols  = nt_css_int( $a['columns'], 1, 6, 4 );
	$items = '';
	foreach ( $terms as $term ) {
		$link = get_term_link( $term );
		if ( ! is_string( $link ) ) {
			continue;
		}
		$thumb = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
		$img   = $thumb > 0 ? nt_lg_image_html(
			(string) $thumb,
			array(
				'size'  => 'medium',
				'class' => 'nt-lg-cat__img',
				'sizes' => '(min-width: 850px) 22vw, 46vw',
			)
		) : '';
		$items .= sprintf(
			'<li><a class="nt-lg-cat" href="%s">%s<span class="nt-lg-cat__name">%s</span></a></li>',
			esc_url( $link ),
			'' !== $img ? '<span class="nt-lg-cat__media">' . $img . '</span>' : '<span class="nt-lg-cat__media">' . nt_icon( 'scooter', array( 'size' => 40 ) ) . '</span>',
			esc_html( $term->name )
		);
	}
	return sprintf(
		'<ul class="%s" style="--lg-cols:%d">%s</ul>',
		nt_lg_class( array_merge( array( 'nt-lg-cats' ), nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) ),
		(int) $cols,
		$items
	);
}

/**
 * [blog_posts]
 *
 * @param mixed $atts Attributes.
 */
function nt_lg_blog_posts( $atts ): string {
	$a = nt_lg_atts(
		array(
			'posts'      => '6',
			'columns'    => '3',
			'cat'        => '',
			'orderby'    => 'date',
			'order'      => 'DESC',
			'offset'     => '0',
			'class'      => '',
			'visibility' => '',
		),
		$atts,
		'blog_posts'
	);
	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => nt_css_int( $a['posts'], 1, 18, 6 ),
		'offset'              => nt_css_int( $a['offset'], 0, 200, 0 ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'order'               => 'ASC' === strtoupper( $a['order'] ) ? 'ASC' : 'DESC',
		'orderby'             => in_array( $a['orderby'], array( 'date', 'title', 'rand', 'modified' ), true ) ? $a['orderby'] : 'date',
	);
	$cats = nt_lg_id_slug_list( $a['cat'] );
	if ( array() !== $cats['ids'] ) {
		$args['category__in'] = $cats['ids'];
	} elseif ( array() !== $cats['slugs'] ) {
		$args['category_name'] = implode( ',', $cats['slugs'] );
	}
	$query = new WP_Query( $args );
	if ( ! $query->have_posts() ) {
		return '';
	}
	update_post_thumbnail_cache( $query );
	$cols = nt_css_int( $a['columns'], 1, 4, 3 );
	ob_start();
	printf(
		'<div class="%s" style="--lg-cols:%d">',
		esc_attr( nt_lg_class( array_merge( array( 'nt-lg-posts' ), nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) ) ),
		(int) $cols
	);
	while ( $query->have_posts() ) {
		$query->the_post();
		get_template_part( 'template-parts/content/card', 'post', array( 'heading' => 'h3' ) );
	}
	echo '</div>';
	wp_reset_postdata();
	return (string) ob_get_clean();
}

/**
 * [ux_portfolio]: featured items grid (needs the companion plugin or the old registration).
 *
 * @param mixed $atts Attributes.
 */
function nt_lg_portfolio( $atts ): string {
	if ( ! nt_featured_available() ) {
		return '<!-- nt-legacy: [ux_portfolio] needs the featured_item post type (plugin nguyentu-legacy-compat) -->';
	}
	$a = nt_lg_atts(
		array(
			'cat'        => '',
			'posts'      => '12',
			'columns'    => '3',
			'orderby'    => 'date',
			'order'      => 'DESC',
			'class'      => '',
			'visibility' => '',
		),
		$atts,
		'ux_portfolio'
	);
	$args = array(
		'post_type'           => 'featured_item',
		'post_status'         => 'publish',
		'posts_per_page'      => nt_css_int( $a['posts'], 1, 30, 12 ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'order'               => 'ASC' === strtoupper( $a['order'] ) ? 'ASC' : 'DESC',
		'orderby'             => in_array( $a['orderby'], array( 'date', 'title', 'rand', 'menu_order' ), true ) ? $a['orderby'] : 'date',
	);
	$cats = nt_lg_id_slug_list( $a['cat'] );
	if ( array() !== $cats['ids'] || array() !== $cats['slugs'] ) {
		$tax = array();
		if ( array() !== $cats['ids'] ) {
			$tax[] = array(
				'taxonomy'         => 'featured_item_category',
				'field'            => 'term_id',
				'terms'            => $cats['ids'],
				'include_children' => false,
			);
		}
		if ( array() !== $cats['slugs'] ) {
			$tax[] = array(
				'taxonomy'         => 'featured_item_category',
				'field'            => 'slug',
				'terms'            => $cats['slugs'],
				'include_children' => false,
			);
		}
		$args['tax_query'] = array_merge( array( 'relation' => 'OR' ), $tax );
	}
	$query = new WP_Query( $args );
	if ( ! $query->have_posts() ) {
		return '';
	}
	update_post_thumbnail_cache( $query );
	$cols = nt_css_int( $a['columns'], 1, 4, 3 );
	ob_start();
	printf(
		'<div class="%s" style="--lg-cols:%d">',
		esc_attr( nt_lg_class( array_merge( array( 'nt-lg-posts' ), nt_css_classes( $a['class'] ), nt_lg_visibility_classes( $a['visibility'] ) ) ) ),
		(int) $cols
	);
	while ( $query->have_posts() ) {
		$query->the_post();
		get_template_part( 'template-parts/featured/card', null, array( 'heading' => 'h3' ) );
	}
	echo '</div>';
	wp_reset_postdata();
	return (string) ob_get_clean();
}
