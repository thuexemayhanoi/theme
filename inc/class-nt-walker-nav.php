<?php
/**
 * Accessible navigation walker (WAI-ARIA disclosure navigation pattern).
 *
 * - Every parent gets a real <button aria-expanded aria-controls> next to its link, so the link
 *   stays a link (crawlable, clickable) and the toggle is separately operable by keyboard.
 * - Top-level items whose subtree is 3+ levels deep are flagged "nt-has-mega" so CSS can lay
 *   them out as a multi-column panel instead of a nested fly-out that runs off screen.
 * - It reads the menu exactly as stored: no item is added, removed or re-ordered.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walker for the primary navigation.
 */
class NT_Walker_Nav extends Walker_Nav_Menu {

	/**
	 * Parent item id => child item ids.
	 *
	 * @var array<int,int[]>
	 */
	protected array $children_map = array();

	/**
	 * Item id => number of levels below the item (0 = leaf).
	 *
	 * @var array<int,int>
	 */
	protected array $subtree_depth = array();

	/**
	 * Last item opened (the parent of the next <ul>).
	 *
	 * @var int
	 */
	protected int $last_item_id = 0;

	/**
	 * Count of rendered items (read by tests/diagnostics).
	 *
	 * @var int
	 */
	public int $rendered = 0;

	/**
	 * Index the tree before walking so each item knows how deep its subtree goes.
	 *
	 * @param array<int,object> $elements  Menu items.
	 * @param int               $max_depth Maximum depth.
	 * @param mixed             ...$args   Walker args.
	 * @return string
	 */
	public function walk( $elements, $max_depth, ...$args ) {
		$this->children_map  = array();
		$this->subtree_depth = array();
		foreach ( $elements as $el ) {
			$this->children_map[ (int) $el->menu_item_parent ][] = (int) $el->ID;
		}
		foreach ( $elements as $el ) {
			$this->depth_of( (int) $el->ID );
		}
		return parent::walk( $elements, $max_depth, ...$args );
	}

	/**
	 * Memoised subtree depth.
	 *
	 * @param int $id Item id.
	 */
	protected function depth_of( int $id ): int {
		if ( isset( $this->subtree_depth[ $id ] ) ) {
			return $this->subtree_depth[ $id ];
		}
		$max = 0;
		foreach ( $this->children_map[ $id ] ?? array() as $child ) {
			$max = max( $max, 1 + $this->depth_of( $child ) );
		}
		$this->subtree_depth[ $id ] = $max;
		return $max;
	}

	/**
	 * Open a sub-menu list.
	 *
	 * @param string   $output Output buffer.
	 * @param int      $depth  Depth of the parent.
	 * @param stdClass $args   Menu args.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		$level   = $depth + 1;
		$classes = array( 'nt-sub', 'nt-sub--d' . $level );
		$output .= sprintf(
			'<ul id="nt-sub-%1$d" class="%2$s">',
			$this->last_item_id,
			esc_attr( implode( ' ', $classes ) )
		);
	}

	/**
	 * Close a sub-menu list.
	 *
	 * @param string   $output Output buffer.
	 * @param int      $depth  Depth of the parent.
	 * @param stdClass $args   Menu args.
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ) {
		$output .= '</ul>';
	}

	/**
	 * Open a list item and render its link and optional disclosure button.
	 *
	 * @param string   $output Output buffer.
	 * @param WP_Post  $item   Menu item.
	 * @param int      $depth  Depth.
	 * @param stdClass $args   Menu args.
	 * @param int      $id     Unused.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$item_id            = (int) $item->ID;
		$this->last_item_id = $item_id;
		++$this->rendered;

		$sub_levels  = $this->subtree_depth[ $item_id ] ?? 0;
		$has_sub     = $sub_levels > 0;
		$classes     = array_filter( array_map( 'sanitize_html_class', (array) $item->classes ) );
		$classes[]   = 'nt-menu__item';
		$classes[]   = 'nt-menu__item--d' . $depth;
		if ( $has_sub ) {
			$classes[] = 'nt-has-sub';
		}
		if ( 0 === $depth && $sub_levels >= 2 ) {
			$classes[] = 'nt-has-mega';
		}
		$classes = apply_filters( 'nav_menu_css_class', $classes, $item, $args, $depth );

		$output .= sprintf( '<li class="%s">', esc_attr( implode( ' ', array_unique( (array) $classes ) ) ) );

		$atts = array(
			'href'  => ! empty( $item->url ) ? $item->url : '',
			'class' => 'nt-menu__link',
		);
		if ( ! empty( $item->target ) ) {
			$atts['target'] = $item->target;
		}
		$rel = trim( (string) $item->xfn );
		if ( '_blank' === ( $item->target ?? '' ) ) {
			$rel = trim( $rel . ' noopener' );
		}
		if ( '' !== $rel ) {
			$atts['rel'] = $rel;
		}
		if ( $item->current ) {
			$atts['aria-current'] = 'page';
		}
		$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );

		$attr_html = '';
		foreach ( $atts as $name => $value ) {
			if ( '' === (string) $value ) {
				continue;
			}
			$attr_html .= ' ' . $name . '="' . ( 'href' === $name ? esc_url( $value ) : esc_attr( $value ) ) . '"';
		}

		$title   = apply_filters( 'the_title', $item->title, $item->ID );
		$title   = apply_filters( 'nav_menu_item_title', $title, $item, $args, $depth );
		$output .= '<a' . $attr_html . '>' . $title . '</a>';

		if ( $has_sub ) {
			$output .= sprintf(
				'<button type="button" class="nt-sub-toggle" aria-expanded="false" aria-controls="nt-sub-%1$d" data-nt-sub-toggle><span class="screen-reader-text">%2$s</span>%3$s</button>',
				$item_id,
				esc_html( 'Menu con: ' . wp_strip_all_tags( $title ) ),
				nt_icon( 'chevron-down', array( 'size' => 16 ) )
			);
		}
	}

	/**
	 * Close a list item.
	 *
	 * @param string   $output Output buffer.
	 * @param WP_Post  $item   Menu item.
	 * @param int      $depth  Depth.
	 * @param stdClass $args   Menu args.
	 */
	public function end_el( &$output, $item, $depth = 0, $args = null ) {
		$output .= '</li>';
	}
}
