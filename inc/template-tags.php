<?php
/**
 * Template tags: small output helpers shared by templates.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Call / Zalo / Maps call-to-action buttons built from the verified contact data.
 *
 * @param array<string,mixed> $args size (sm|md|lg), class, zalo (bool), maps (bool), call_label.
 */
function nt_cta_buttons( array $args = array() ): void {
	$args  = wp_parse_args(
		$args,
		array(
			'size'       => 'md',
			'class'      => '',
			'zalo'       => true,
			'maps'       => true,
			'call_label' => '',
		)
	);
	$size  = in_array( $args['size'], array( 'sm', 'md', 'lg' ), true ) ? $args['size'] : 'md';
	$label = '' !== $args['call_label'] ? $args['call_label'] : 'Gọi ' . nt_business( 'phone_display' );
	?>
	<div class="nt-cta nt-cta--<?php echo esc_attr( $size ); ?> <?php echo esc_attr( $args['class'] ); ?>">
		<a class="nt-btn nt-btn--call nt-btn--<?php echo esc_attr( $size ); ?>" href="<?php echo esc_url( nt_tel_url() ); ?>">
			<?php nt_the_icon( 'phone', array( 'size' => 20 ) ); ?>
			<span><?php echo esc_html( $label ); ?></span>
		</a>
		<?php if ( $args['zalo'] ) : ?>
			<a class="nt-btn nt-btn--zalo nt-btn--<?php echo esc_attr( $size ); ?>" href="<?php echo esc_url( nt_zalo_url() ); ?>" target="_blank" rel="noopener">
				<?php nt_the_icon( 'chat', array( 'size' => 20 ) ); ?>
				<span>Nhắn Zalo</span>
			</a>
		<?php endif; ?>
		<?php if ( $args['maps'] ) : ?>
			<a class="nt-btn nt-btn--ghost nt-btn--<?php echo esc_attr( $size ); ?>" href="<?php echo esc_url( nt_maps_url() ); ?>" target="_blank" rel="noopener">
				<?php nt_the_icon( 'pin', array( 'size' => 20 ) ); ?>
				<span>Chỉ đường</span>
			</a>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Excerpt for cards and search results, built without running the_content: a manual excerpt as
 * written, else the start of the stored content. Core's get_the_excerpt() applies the_content to
 * the whole post for every card, which is slow, re-enters the legacy bridge while a page is being
 * rendered and lets unrelated plugins' content filters run for text nobody sees.
 *
 * @param WP_Post|int|null $post  Post (default: current).
 * @param int              $words Word limit.
 */
function nt_excerpt( $post = null, int $words = 24 ): string {
	$post = get_post( $post );
	if ( ! $post instanceof WP_Post || post_password_required( $post ) ) {
		return '';
	}
	$text = '' !== trim( (string) $post->post_excerpt ) ? nt_plain_text( (string) $post->post_excerpt ) : nt_content_to_text( (string) $post->post_content );
	return nt_trim_words( $text, $words );
}

/**
 * Thumbnail markup for a card, or a decorative placeholder when the post has no image.
 *
 * Uses only the image sizes WordPress already generated for existing media (no custom sizes,
 * so no regeneration is required), a responsive sizes attribute, and lazy loading unless the
 * caller marks it as above the fold.
 *
 * @param int                 $post_id Post id.
 * @param array<string,mixed> $args    size, sizes, eager (bool).
 */
function nt_card_image( int $post_id, array $args = array() ): string {
	$args  = wp_parse_args(
		$args,
		array(
			'size'  => 'medium_large',
			'sizes' => '(min-width: 1024px) 380px, (min-width: 640px) 46vw, 100vw',
			'eager' => false,
		)
	);
	$thumb = get_post_thumbnail_id( $post_id );
	if ( $thumb ) {
		$attr = array(
			'class'    => 'nt-card__img',
			'sizes'    => $args['sizes'],
			'decoding' => 'async',
			'loading'  => $args['eager'] ? 'eager' : 'lazy',
		);
		if ( $args['eager'] ) {
			$attr['fetchpriority'] = 'high';
		}
		$html = wp_get_attachment_image( (int) $thumb, (string) $args['size'], false, $attr );
		if ( '' !== $html ) {
			return $html;
		}
	}
	return '<span class="nt-card__placeholder" aria-hidden="true">' . nt_icon( 'scooter', array( 'size' => 56 ) ) . '</span>';
}

/**
 * Category chips for a post (topic categories only, deepest first, capped).
 *
 * @param int $post_id Post id.
 * @param int $limit   Maximum chips.
 */
function nt_category_chips( int $post_id, int $limit = 2 ): string {
	$ids   = nt_topic_terms( $post_id )['topic'];
	$chips = array();
	foreach ( $ids as $term_id ) {
		$term = get_term( $term_id, 'category' );
		$link = ( $term instanceof WP_Term ) ? get_term_link( $term ) : '';
		if ( $term instanceof WP_Term && is_string( $link ) ) {
			$chips[ $term_id ] = sprintf( '<a class="nt-chip" href="%s">%s</a>', esc_url( $link ), esc_html( nt_decode( $term->name ) ) );
		}
	}
	return implode( '', array_slice( $chips, 0, $limit ) );
}

/**
 * "17 tháng 10, 2026" style date with a machine-readable time element.
 *
 * @param int    $post_id Post id.
 * @param string $which   published|modified.
 */
function nt_date_html( int $post_id, string $which = 'published' ): string {
	$modified = 'modified' === $which;
	$iso      = $modified ? get_the_modified_date( 'c', $post_id ) : get_the_date( 'c', $post_id );
	$text     = $modified ? get_the_modified_date( '', $post_id ) : get_the_date( '', $post_id );
	return sprintf( '<time datetime="%s">%s</time>', esc_attr( (string) $iso ), esc_html( (string) $text ) );
}

/**
 * Reading time label for rendered content.
 *
 * @param string $html Rendered content.
 */
function nt_reading_time_label( string $html ): string {
	return nt_reading_minutes( $html ) . ' phút đọc';
}

/**
 * Numbered pagination (real, crawlable links).
 */
function nt_pagination(): void {
	the_posts_pagination(
		array(
			'mid_size'           => 1,
			'prev_text'          => '<span aria-hidden="true">‹</span><span class="screen-reader-text">Trang trước</span>',
			'next_text'          => '<span aria-hidden="true">›</span><span class="screen-reader-text">Trang sau</span>',
			'screen_reader_text' => 'Phân trang',
			'aria_label'         => 'Phân trang',
		)
	);
}

/**
 * Pagination markup without core's extra visually-hidden <h2> (an empty-ish heading in every
 * archive adds noise to the heading outline). The nav keeps its aria-label.
 *
 * @param string $template Core template.
 */
function nt_navigation_markup_template( string $template ): string {
	unset( $template );
	return '<nav class="navigation %1$s" aria-label="%4$s"><div class="nav-links">%3$s</div></nav>';
}
add_filter( 'navigation_markup_template', 'nt_navigation_markup_template' );

/**
 * Share links. Plain links plus a progressive-enhancement button (Web Share API / copy link).
 * No third-party scripts and no tracking pixels.
 *
 * @param string $url   Absolute URL to share.
 * @param string $title Page title.
 */
function nt_share_links( string $url, string $title ): void {
	$enc_url   = rawurlencode( $url );
	$enc_title = rawurlencode( $title );
	?>
	<div class="nt-share" data-nt-share data-url="<?php echo esc_url( $url ); ?>" data-title="<?php echo esc_attr( $title ); ?>">
		<span class="nt-share__label">Chia sẻ</span>
		<a class="nt-share__btn" href="<?php echo esc_url( 'https://www.facebook.com/sharer/sharer.php?u=' . $enc_url ); ?>" target="_blank" rel="noopener">Facebook</a>
		<a class="nt-share__btn" href="<?php echo esc_url( 'https://zalo.me/share?url=' . $enc_url . '&title=' . $enc_title ); ?>" target="_blank" rel="noopener">Zalo</a>
		<button type="button" class="nt-share__btn" data-nt-copy hidden>
			<?php nt_the_icon( 'link', array( 'size' => 16 ) ); ?>
			<span>Sao chép liên kết</span>
		</button>
		<span class="screen-reader-text" role="status" aria-live="polite" data-nt-share-status></span>
	</div>
	<?php
}

/**
 * Archive / term heading data for the editorial header.
 *
 * @return array{title:string,description:string}
 */
function nt_archive_heading(): array {
	$title = wp_strip_all_tags( get_the_archive_title() );
	if ( is_category() || is_tag() || is_tax() ) {
		$title = single_term_title( '', false );
	} elseif ( is_author() ) {
		$title = (string) get_the_author_meta( 'display_name', (int) get_queried_object_id() );
	}
	// Only a genuine, editor-written description is shown. Nothing is invented for empty terms.
	$description = '';
	if ( is_category() || is_tag() || is_tax() ) {
		$description = term_description();
	} elseif ( is_author() ) {
		$description = (string) get_the_author_meta( 'description', (int) get_queried_object_id() );
	}
	return array(
		'title'       => (string) $title,
		'description' => (string) $description,
	);
}

/**
 * Published posts in a category including its descendants. Core's term count is direct only, so a
 * hub whose posts live in child terms would read "0" next to a page that lists them.
 *
 * @param WP_Term $term Term.
 */
function nt_term_total( WP_Term $term ): int {
	if ( 'category' !== $term->taxonomy || array() === get_term_children( $term->term_id, $term->taxonomy ) ) {
		return (int) $term->count;
	}
	$key   = 'total_' . $term->term_id . '_' . wp_cache_get_last_changed( 'posts' );
	$found = wp_cache_get( $key, 'nguyentu' );
	if ( false === $found ) {
		$query = new WP_Query(
			array(
				'cat'                    => $term->term_id,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$found = (int) $query->found_posts;
		wp_cache_set( $key, $found, 'nguyentu', HOUR_IN_SECONDS );
	}
	return (int) $found;
}

/**
 * Child terms of the queried term (or the given one), for the "chuyên mục con" chips.
 *
 * @param WP_Term|null $term Term; defaults to the queried object.
 * @return WP_Term[]
 */
function nt_child_terms( ?WP_Term $term = null ): array {
	$term = $term ?? get_queried_object();
	if ( ! $term instanceof WP_Term || ! is_taxonomy_hierarchical( $term->taxonomy ) ) {
		return array();
	}
	$children = get_terms(
		array(
			'taxonomy'   => $term->taxonomy,
			'parent'     => $term->term_id,
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);
	return is_array( $children ) ? $children : array();
}
