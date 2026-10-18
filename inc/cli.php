<?php
/**
 * WP-CLI helpers. Every command is read-only: none of them writes posts, terms, menus,
 * options or media. They exist to produce evidence for the migration reports.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Read-only diagnostics for the Nguyễn Tú theme.
 */
class NT_CLI {

	/**
	 * Print compatibility checks (SEO plugin, WooCommerce, featured items, menus, QuickCall).
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json or yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * @param array<int,string>    $args       Positional args.
	 * @param array<string,string> $assoc_args Associative args.
	 */
	public function status( $args, $assoc_args ): void {
		$rows = array();
		foreach ( nt_status_checks() as $check ) {
			$rows[] = array(
				'check'  => $check['label'],
				'result' => $check['ok'] ? 'OK' : 'ATTENTION',
				'detail' => $check['detail'],
			);
		}
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'check', 'result', 'detail' ) );
	}

	/**
	 * Print a menu as JSON (id, parent, object, object_id, position, title, url).
	 *
	 * ## OPTIONS
	 *
	 * [--menu=<id>]
	 * : Menu term id.
	 * ---
	 * default: 67
	 * ---
	 *
	 * @subcommand menu-snapshot
	 *
	 * @param array<int,string>    $args       Positional args.
	 * @param array<string,string> $assoc_args Associative args.
	 */
	public function menu_snapshot( $args, $assoc_args ): void {
		WP_CLI::print_value( nt_menu_snapshot( (int) ( $assoc_args['menu'] ?? 67 ) ), array( 'format' => 'json' ) );
	}

	/**
	 * Scan stored post_content for shortcodes and report how the bridge treats each tag.
	 *
	 * Read-only. Run it on staging against the real content to produce the compatibility
	 * matrix (docs/LEGACY-COMPAT.md).
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<types>]
	 * : Comma separated post types.
	 * ---
	 * default: page,featured_item,product
	 * ---
	 *
	 * [--format=<format>]
	 * : table, json or markdown.
	 * ---
	 * default: table
	 * ---
	 *
	 * @subcommand legacy-scan
	 *
	 * @param array<int,string>    $args       Positional args.
	 * @param array<string,string> $assoc_args Associative args.
	 */
	public function legacy_scan( $args, $assoc_args ): void {
		$types = array_filter( array_map( 'trim', explode( ',', $assoc_args['post-type'] ?? 'page,featured_item,product' ) ) );
		$ids   = get_posts(
			array(
				'post_type'        => $types,
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);

		$handlers = nt_legacy_handlers();
		$stripped = nt_legacy_stripped_tags();
		$tags     = array();
		$pages    = array();
		foreach ( $ids as $post_id ) {
			$raw = (string) get_post_field( 'post_content', $post_id );
			if ( false === strpos( $raw, '[' ) ) {
				continue;
			}
			if ( ! preg_match_all( '/\[([a-zA-Z0-9_\-]+)((?:\s[^\]]*)?)\]/', $raw, $m, PREG_SET_ORDER ) ) {
				continue;
			}
			foreach ( $m as $hit ) {
				$tag = strtolower( $hit[1] );
				if ( ! isset( $handlers[ $tag ] ) && ! in_array( $tag, $stripped, true ) && 0 !== strpos( $tag, 'ux_' ) && ! shortcode_exists( $tag ) ) {
					continue; // Plain bracketed text, not a shortcode.
				}
				$tags[ $tag ]['count']             = ( $tags[ $tag ]['count'] ?? 0 ) + 1;
				$tags[ $tag ]['posts'][ $post_id ] = true;
				$pages[ $post_id ][ $tag ]         = ( $pages[ $post_id ][ $tag ] ?? 0 ) + 1;
				$parsed                            = shortcode_parse_atts( trim( $hit[2] ) );
				foreach ( is_array( $parsed ) ? array_keys( $parsed ) : array() as $attr ) {
					if ( is_string( $attr ) ) {
						$tags[ $tag ]['attrs'][ $attr ] = ( $tags[ $tag ]['attrs'][ $attr ] ?? 0 ) + 1;
					}
				}
			}
		}

		$rows = array();
		ksort( $tags );
		foreach ( $tags as $tag => $info ) {
			if ( isset( $handlers[ $tag ] ) ) {
				$status = 'RENDERED';
			} elseif ( in_array( $tag, $stripped, true ) ) {
				$status = 'STRIPPED (content kept)';
			} elseif ( 0 === strpos( $tag, 'ux_' ) ) {
				$status = 'STRIPPED (unknown ux_*)';
			} else {
				$status = 'OTHER PLUGIN/CORE';
			}
			$attrs = $info['attrs'] ?? array();
			arsort( $attrs );
			$rows[] = array(
				'tag'        => '[' . $tag . ']',
				'status'     => $status,
				'uses'       => $info['count'],
				'posts'      => implode( ',', array_keys( $info['posts'] ) ),
				'attributes' => implode( ', ', array_keys( $attrs ) ),
			);
		}

		$format = $assoc_args['format'] ?? 'table';
		if ( 'markdown' === $format ) {
			WP_CLI::line( '| Tag | Status | Uses | Post IDs | Attributes seen |' );
			WP_CLI::line( '| --- | --- | ---: | --- | --- |' );
			foreach ( $rows as $row ) {
				WP_CLI::line( sprintf( '| `%s` | %s | %d | %s | %s |', $row['tag'], $row['status'], $row['uses'], $row['posts'], $row['attributes'] ) );
			}
			return;
		}
		WP_CLI\Utils\format_items( $format, $rows, array( 'tag', 'status', 'uses', 'posts', 'attributes' ) );
	}
}

WP_CLI::add_command( 'nguyentu', 'NT_CLI' );
