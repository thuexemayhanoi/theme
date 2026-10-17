<?php
/**
 * Featured items ("Bộ sưu tập" / bảng giá landing pages).
 *
 * The post type `featured_item` and taxonomy `featured_item_category` used to be registered by
 * the previous theme. The independent theme only PRESENTS them (single, term archive); their
 * registration, with identical names and rewrite rules, lives in the companion plugin
 * plugins/nguyentu-legacy-compat so URLs survive the theme switch.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the featured-item post type is registered right now.
 */
function nt_featured_available(): bool {
	return post_type_exists( 'featured_item' ) && taxonomy_exists( 'featured_item_category' );
}

/**
 * Permalink of a featured-item term by slug, or an empty string when it does not exist.
 * The theme never builds these URLs by hand: a missing term simply yields no link.
 *
 * @param string $slug Term slug, e.g. "thue-ngay".
 */
function nt_featured_term_url( string $slug ): string {
	if ( ! taxonomy_exists( 'featured_item_category' ) ) {
		return '';
	}
	$term = get_term_by( 'slug', $slug, 'featured_item_category' );
	if ( ! $term instanceof WP_Term ) {
		return '';
	}
	$link = get_term_link( $term );
	return is_string( $link ) ? $link : '';
}

/**
 * Price column that matches a featured term slug.
 *
 * @param string $slug Term slug.
 * @return string day|week|month|'' when the term is not a rental period.
 */
function nt_featured_period_for_slug( string $slug ): string {
	$map = array(
		'thue-ngay'  => 'day',
		'thue-tuan'  => 'week',
		'thue-thang' => 'month',
	);
	return $map[ $slug ] ?? '';
}

/**
 * Sibling items in the same featured term (for "xem thêm" links), capped.
 *
 * @param int $post_id Current featured item id.
 * @param int $limit   Maximum items.
 * @return int[]
 */
function nt_featured_sibling_ids( int $post_id, int $limit = 4 ): array {
	$terms = get_the_terms( $post_id, 'featured_item_category' );
	if ( ! is_array( $terms ) || array() === $terms ) {
		return array();
	}
	// Prefer the most specific (deepest) term so siblings share the same rental period.
	usort(
		$terms,
		static fn( WP_Term $a, WP_Term $b ): int => count( get_ancestors( $b->term_id, 'featured_item_category', 'taxonomy' ) ) <=> count( get_ancestors( $a->term_id, 'featured_item_category', 'taxonomy' ) )
	);
	$query = new WP_Query(
		array(
			'post_type'              => 'featured_item',
			'post_status'            => 'publish',
			'posts_per_page'         => $limit,
			'post__not_in'           => array( $post_id ),
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'tax_query'              => array(
				array(
					'taxonomy'         => 'featured_item_category',
					'field'            => 'term_id',
					'terms'            => array( (int) $terms[0]->term_id ),
					'include_children' => false,
				),
			),
		)
	);
	return array_map( 'intval', $query->posts );
}

/**
 * Reference price box for a rental period. Rendered only when the page body does not already
 * list prices, and always with the owner's "giá tham khảo" disclaimer.
 *
 * @param string $period day|week|month.
 */
function nt_featured_price_box( string $period ): void {
	$labels = array(
		'day'   => 'theo ngày',
		'week'  => 'theo tuần',
		'month' => 'theo tháng',
	);
	if ( ! isset( $labels[ $period ] ) ) {
		return;
	}
	$rows = nt_price_rows();
	?>
	<section class="nt-pricebox" aria-labelledby="nt-pricebox-title">
		<h2 id="nt-pricebox-title" class="nt-pricebox__title">Bảng giá tham khảo <?php echo esc_html( $labels[ $period ] ); ?></h2>
		<table class="nt-table nt-table--prices">
			<thead>
				<tr><th scope="col">Loại xe</th><th scope="col">Giá <?php echo esc_html( $labels[ $period ] ); ?></th><th scope="col">Tiền cọc</th></tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $row['name'] ); ?></th>
						<td><?php echo esc_html( $row[ $period ] ); ?></td>
						<td><?php echo esc_html( $row['deposit'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="nt-note"><?php echo esc_html( nt_policy()['price_note'] ); ?></p>
	</section>
	<?php
}
