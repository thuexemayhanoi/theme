<?php
/**
 * WP-CLI: status, capture, export-urls, compare, verify. Only `capture` writes (its own option).
 *
 * @package NguyenTuLegacyCompat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Featured item compatibility commands.
 */
final class NT_Compat_CLI {

	/**
	 * Show whether the previous theme registers featured_item, whether replay is active, the stored
	 * rewrite slugs and the item/term counts.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nguyentu-compat status
	 *
	 * @param array<int,string>    $args       Positional args.
	 * @param array<string,string> $assoc_args Associative args.
	 */
	public function status( $args, $assoc_args ): void {
		$snap = NT_Compat_Registry::snapshot();
		$def  = $snap['definition'] ?? array();
		$rows = array(
			array(
				'key'   => 'registered_by_other_component',
				'value' => NT_Compat_Registry::registered_by_other() ? 'yes' : 'no',
			),
			array(
				'key'   => 'replay_active',
				'value' => NT_Compat_Registry::replay_active() ? 'yes' : 'no',
			),
			array(
				'key'   => 'snapshot_source',
				'value' => (string) ( $snap['source'] ?? 'none' ),
			),
			array(
				'key'   => 'snapshot_captured_at',
				'value' => (string) ( $snap['captured_at'] ?? '' ),
			),
			array(
				'key'   => 'snapshot_order',
				'value' => implode( ' > ', (array) ( $snap['order'] ?? array() ) ),
			),
			array(
				'key'   => 'cpt_rewrite',
				'value' => wp_json_encode( $def['post_type']['args']['rewrite'] ?? null ),
			),
			array(
				'key'   => 'tax_rewrite',
				'value' => wp_json_encode( $def['taxonomy']['args']['rewrite'] ?? null ),
			),
			array(
				'key'   => 'post_type_exists',
				'value' => post_type_exists( NT_Compat_Registry::CPT ) ? 'yes' : 'no',
			),
			array(
				'key'   => 'taxonomy_exists',
				'value' => taxonomy_exists( NT_Compat_Registry::TAX ) ? 'yes' : 'no',
			),
			array(
				'key'   => 'published_items',
				'value' => (string) ( post_type_exists( NT_Compat_Registry::CPT ) ? (int) wp_count_posts( NT_Compat_Registry::CPT )->publish : 0 ),
			),
			array(
				'key'   => 'stored_rewrite_rules',
				'value' => (string) count( NT_Compat_Registry::stored_rules() ),
			),
		);
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'key', 'value' ) );
	}

	/**
	 * Store the registration made by the previous theme (run while it is still active).
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Overwrite an existing runtime snapshot.
	 *
	 * @param array<int,string>    $args       Positional args.
	 * @param array<string,string> $assoc_args Associative args.
	 */
	public function capture( $args, $assoc_args ): void {
		$result = NT_Compat_Registry::capture( isset( $assoc_args['force'] ) );
		if ( $result['ok'] ) {
			WP_CLI::success( $result['message'] );
		} else {
			WP_CLI::warning( $result['message'] );
		}
	}

	/**
	 * Print every featured item and term URL as CSV (type,id,slug,status,path). Read-only.
	 * Run it BEFORE the theme switch and again AFTER, then use `compare`.
	 *
	 * @subcommand export-urls
	 *
	 * @param array<int,string>    $args       Positional args.
	 * @param array<string,string> $assoc_args Associative args.
	 */
	public function export_urls( $args, $assoc_args ): void {
		unset( $args, $assoc_args ); // WP-CLI signature; no options.
		WP_CLI::line( 'type,id,slug,status,path' );
		foreach ( self::collect() as $row ) {
			WP_CLI::line( implode( ',', array( $row['type'], $row['id'], $row['slug'], $row['status'], $row['path'] ) ) );
		}
	}

	/**
	 * Compare current URLs with a CSV produced earlier by `export-urls`. Exits with 1 on any
	 * missing or changed URL.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : CSV from `export-urls` taken before the theme switch.
	 *
	 * @param array<int,string> $args Positional args.
	 */
	public function compare( $args ): void {
		$file = (string) ( $args[0] ?? '' );
		if ( '' === $file || ! is_readable( $file ) ) {
			WP_CLI::error( 'Không đọc được tệp: ' . $file );
		}
		$before = array();
		foreach ( file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) ?: array() as $i => $line ) {
			if ( 0 === $i ) {
				continue;
			}
			$cols = str_getcsv( $line );
			if ( count( $cols ) >= 5 ) {
				$before[ $cols[0] . ':' . $cols[1] ] = $cols[4];
			}
		}
		$after = array();
		foreach ( self::collect() as $row ) {
			$after[ $row['type'] . ':' . $row['id'] ] = $row['path'];
		}
		$problems = 0;
		foreach ( $before as $key => $path ) {
			if ( ! isset( $after[ $key ] ) ) {
				WP_CLI::warning( "MISSING  {$key}  {$path}" );
				++$problems;
			} elseif ( $after[ $key ] !== $path ) {
				WP_CLI::warning( "CHANGED  {$key}  {$path}  ->  {$after[$key]}" );
				++$problems;
			}
		}
		WP_CLI::log( sprintf( 'Trước: %d URL · Sau: %d URL', count( $before ), count( $after ) ) );
		if ( $problems > 0 ) {
			WP_CLI::halt( 1 );
		}
		WP_CLI::success( 'Tất cả URL featured_item giữ nguyên (path giống hệt).' );
	}

	/**
	 * Check that every featured item / term URL resolves through the stored rewrite rules to
	 * itself. Optionally check an expected-URL list (one URL or CSV with a "path" column per
	 * line) and/or request each URL over HTTP.
	 *
	 * ## OPTIONS
	 *
	 * [--urls=<file>]
	 * : Expected URLs, one per line (or CSV containing a URL column), taken from the live
	 * featured_item and featured_item_category sitemaps. Every one must be served by the current
	 * registration; this is the check against the real site.
	 *
	 * [--probe]
	 * : Also request every URL over HTTP and require 200. (Not named --http: that is a WP-CLI global
	 *   flag meant for the wp-cli/restful package.)
	 *
	 * @param array<int,string>    $args       Positional args.
	 * @param array<string,string> $assoc_args Associative args.
	 */
	public function verify( $args, $assoc_args ): void {
		$rows = self::collect();
		if ( array() === $rows ) {
			WP_CLI::error( 'Không có featured_item hay term nào: post type/taxonomy chưa được đăng ký.' );
		}
		$fail = 0;
		foreach ( $rows as $row ) {
			$vars = self::resolve( $row['path'] );
			$want = 'item' === $row['type'] ? array( NT_Compat_Registry::CPT, $row['slug'] ) : array( NT_Compat_Registry::TAX, $row['slug'] );
			$ok   = isset( $vars[ $want[0] ] ) && (string) $vars[ $want[0] ] === (string) $want[1];
			if ( ! $ok ) {
				++$fail;
				WP_CLI::warning( sprintf( 'FAIL  %s %s  %s  ->  %s', $row['type'], $row['id'], $row['path'], wp_json_encode( $vars ) ) );
			}
			if ( $ok && isset( $assoc_args['probe'] ) ) {
				$code = (int) wp_remote_retrieve_response_code(
					wp_remote_get(
						home_url( $row['path'] ),
						array(
							'timeout'     => 20,
							'redirection' => 0,
						)
					)
				);
				if ( 200 !== $code ) {
					++$fail;
					WP_CLI::warning( sprintf( 'HTTP %d  %s', $code, $row['path'] ) );
				}
			}
		}
		if ( ! empty( $assoc_args['urls'] ) ) {
			if ( ! is_readable( $assoc_args['urls'] ) ) {
				WP_CLI::error( 'Không đọc được tệp --urls: ' . $assoc_args['urls'] );
			}
			$known = array_map( 'trailingslashit', array_column( $rows, 'path' ) );
			foreach ( file( $assoc_args['urls'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) ?: array() as $line ) {
				$url = '';
				foreach ( str_getcsv( $line ) as $col ) {
					if ( 0 === strpos( $col, 'http' ) || 0 === strpos( $col, '/' ) ) {
						$url = $col;
						break;
					}
				}
				$path = '' !== $url ? (string) wp_parse_url( $url, PHP_URL_PATH ) : '';
				if ( '' === $path ) {
					continue;
				}
				if ( ! in_array( trailingslashit( $path ), $known, true ) ) {
					++$fail;
					WP_CLI::warning( 'EXPECTED URL NOT SERVED (slug rewrite khác site thật?): ' . $path );
				}
			}
		}
		WP_CLI::log( sprintf( 'Đã kiểm tra %d URL (featured_item + term).', count( $rows ) ) );
		if ( $fail > 0 ) {
			WP_CLI::error( "{$fail} URL không resolve đúng. Nếu vừa đổi theme hãy chạy: wp rewrite flush" );
		}
		WP_CLI::success( 'Mọi URL featured_item và term resolve đúng về chính nó.' );
	}

	/**
	 * Items and terms with their paths.
	 *
	 * @return array<int,array{type:string,id:int,slug:string,status:string,path:string}>
	 */
	private static function collect(): array {
		$rows = array();
		if ( taxonomy_exists( NT_Compat_Registry::TAX ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => NT_Compat_Registry::TAX,
					'hide_empty' => false,
					'orderby'    => 'term_id',
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				$link   = get_term_link( $term );
				$rows[] = array(
					'type'   => 'term',
					'id'     => (int) $term->term_id,
					'slug'   => $term->slug,
					'status' => 'term',
					'path'   => is_string( $link ) ? (string) wp_parse_url( $link, PHP_URL_PATH ) : '',
				);
			}
		}
		if ( post_type_exists( NT_Compat_Registry::CPT ) ) {
			$ids = get_posts(
				array(
					'post_type'      => NT_Compat_Registry::CPT,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);
			foreach ( $ids as $id ) {
				$rows[] = array(
					'type'   => 'item',
					'id'     => (int) $id,
					'slug'   => (string) get_post_field( 'post_name', $id ),
					'status' => 'publish',
					'path'   => (string) wp_parse_url( (string) get_permalink( $id ), PHP_URL_PATH ),
				);
			}
		}
		return $rows;
	}

	/**
	 * Resolve a path through the stored rewrite rules the way WP::parse_request does.
	 *
	 * @param string $path URL path.
	 * @return array<string,string> Query vars, empty when nothing matches.
	 */
	private static function resolve( string $path ): array {
		global $wp_rewrite;
		$home = (string) wp_parse_url( home_url(), PHP_URL_PATH );
		$req  = trim( preg_replace( '#^' . preg_quote( untrailingslashit( $home ), '#' ) . '#', '', $path ) ?? $path, '/' );
		foreach ( (array) $wp_rewrite->wp_rewrite_rules() as $match => $query ) {
			if ( preg_match( "#^$match#", $req, $m ) || preg_match( "#^$match#", urldecode( $req ), $m ) ) {
				$query = preg_replace( '!^.+\?!', '', $query );
				$query = addslashes( WP_MatchesMapRegex::apply( (string) $query, $m ) );
				parse_str( $query, $vars );
				return array_map( 'strval', $vars );
			}
		}
		return array();
	}
}

WP_CLI::add_command( 'nguyentu-compat', 'NT_Compat_CLI' );
