<?php
/**
 * SANDBOX SEED - reproduces the 2026-10-17 audit baseline of thuexemaynguyentu.com with EXACT IDs.
 *
 *   ./seed.sh                      (restores the "pristine" snapshot first, then runs this file)
 *   ./wp.sh eval-file seed.php     (only on a pristine DB; refuses to run twice)
 *
 * Reads:  docs/wordpress-baseline-2026-10-17.json   (NT_BASELINE_JSON)
 *         tests/fixtures/pages/<ID>.html            (NT_PAGES_FIXTURE_DIR) -> page content verbatim, if present
 * Writes: DB (+ wp-content/uploads for generated images) and $NT_SANDBOX_DIR/seed-manifest.json
 *
 * All text is SYNTHETIC filler (see php/seed-data.php). Nothing here touches production.
 * Not theme code. Do not ship.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI: wp eval-file seed.php\n" ); // phpcs:ignore
	exit( 1 );
}

require_once __DIR__ . '/php/wc-classic-pages.php';
require_once __DIR__ . '/php/seed-images.php';

// ---------------------------------------------------------------------------------------------
// helpers
// ---------------------------------------------------------------------------------------------

if ( ! function_exists( 'nt_seed_log' ) ) {

	function nt_seed_log( $msg ) {
		WP_CLI::log( $msg );
	}

	function nt_seed_fail( $msg ) {
		WP_CLI::error( $msg );
	}

	/** Date arguments for wp_insert_post from a UTC timestamp. */
	function nt_seed_dates( $ts ) {
		$gmt = gmdate( 'Y-m-d H:i:s', $ts );
		return array(
			'post_date_gmt' => $gmt,
			'post_date'     => get_date_from_gmt( $gmt ),
		);
	}

	/** wp_insert_post with exact ID (import_id), slashing, and strict result checks. */
	function nt_seed_insert_post( array $args ) {
		$want_id = isset( $args['import_id'] ) ? (int) $args['import_id'] : 0;
		$result  = wp_insert_post( wp_slash( $args ), true );
		if ( is_wp_error( $result ) ) {
			nt_seed_fail( sprintf( 'wp_insert_post failed for %s (ID %d): %s', $args['post_title'] ?? '?', $want_id, $result->get_error_message() ) );
		}
		if ( $want_id && (int) $result !== $want_id ) {
			nt_seed_fail( sprintf( 'Expected post ID %d but got %d for "%s" (ID already in use?)', $want_id, $result, $args['post_title'] ?? '?' ) );
		}
		if ( isset( $args['post_name'] ) ) {
			$got = get_post_field( 'post_name', $result );
			if ( $got !== $args['post_name'] ) {
				nt_seed_fail( sprintf( 'Slug mismatch for ID %d: wanted "%s" got "%s" (slug collision?)', $result, $args['post_name'], $got ) );
			}
		}
		return (int) $result;
	}

	/** Make sure post_content in the DB is byte-identical to $content (kses/filters can alter it). */
	function nt_seed_force_content( $post_id, $content ) {
		global $wpdb;
		if ( get_post_field( 'post_content', $post_id, 'raw' ) !== $content ) {
			$wpdb->update( $wpdb->posts, array( 'post_content' => $content ), array( 'ID' => $post_id ) );
			clean_post_cache( $post_id );
		}
	}

	/** Insert a term with an explicit term_id (term_taxonomy_id = term_id). Updates the row when it exists. */
	function nt_seed_term( $term_id, $name, $slug, $taxonomy, $parent = 0 ) {
		global $wpdb;
		$term_id = (int) $term_id;
		$exists  = $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->terms} WHERE term_id = %d", $term_id ) );
		if ( $exists ) {
			$wpdb->update( $wpdb->terms, array( 'name' => $name, 'slug' => $slug ), array( 'term_id' => $term_id ) );
			$tt = $wpdb->get_row( $wpdb->prepare( "SELECT term_taxonomy_id, taxonomy FROM {$wpdb->term_taxonomy} WHERE term_id = %d", $term_id ) );
			if ( ! $tt ) {
				$wpdb->insert( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $term_id, 'term_id' => $term_id, 'taxonomy' => $taxonomy, 'description' => '', 'parent' => (int) $parent, 'count' => 0 ) );
			} elseif ( $tt->taxonomy !== $taxonomy ) {
				nt_seed_fail( "Term $term_id already exists in taxonomy {$tt->taxonomy}, wanted $taxonomy" );
			} else {
				$wpdb->update( $wpdb->term_taxonomy, array( 'parent' => (int) $parent ), array( 'term_id' => $term_id ) );
			}
			return;
		}
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d", $term_id ) ) ) {
			nt_seed_fail( "term_taxonomy_id $term_id is already used by another term" );
		}
		$ok1 = $wpdb->insert( $wpdb->terms, array( 'term_id' => $term_id, 'name' => $name, 'slug' => $slug, 'term_group' => 0 ) );
		$ok2 = $wpdb->insert( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $term_id, 'term_id' => $term_id, 'taxonomy' => $taxonomy, 'description' => '', 'parent' => (int) $parent, 'count' => 0 ) );
		if ( false === $ok1 || false === $ok2 ) {
			nt_seed_fail( "Could not insert term $term_id ($name): " . $wpdb->last_error );
		}
	}

	/** Attach terms (by term ID) to an object; works even when the taxonomy is not registered. */
	function nt_seed_assign_terms( $object_id, $taxonomy, array $term_ids ) {
		global $wpdb;
		$term_ids = array_values( array_unique( array_map( 'intval', $term_ids ) ) );
		if ( taxonomy_exists( $taxonomy ) ) {
			$r = wp_set_object_terms( $object_id, $term_ids, $taxonomy, false );
			if ( is_wp_error( $r ) ) {
				nt_seed_fail( "wp_set_object_terms($object_id, $taxonomy): " . $r->get_error_message() );
			}
			return;
		}
		foreach ( $term_ids as $tid ) { // term_taxonomy_id == term_id by construction
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id, term_order) VALUES (%d, %d, 0)", $object_id, $tid ) );
		}
	}

	// ---- deterministic randomness (own PRNG wrappers around mt_rand) --------------------------

	function nt_seed_rand( $min, $max ) {
		return mt_rand( $min, $max );
	}

	function nt_seed_shuffle( array $a ) {
		for ( $i = count( $a ) - 1; $i > 0; $i-- ) {
			$j       = mt_rand( 0, $i );
			$tmp     = $a[ $i ];
			$a[ $i ] = $a[ $j ];
			$a[ $j ] = $tmp;
		}
		return $a;
	}

	function nt_seed_ucfirst( $s ) {
		return mb_strtoupper( mb_substr( $s, 0, 1 ) ) . mb_substr( $s, 1 );
	}

	function nt_seed_words( $html ) {
		$text = trim( wp_strip_all_tags( $html ) );
		return '' === $text ? 0 : count( preg_split( '/\s+/u', $text ) );
	}

	/** Creates image + real attachment (with generated sizes). Returns attachment ID. */
	function nt_seed_attachment( $att_id, $slug, $title, $sub, $alt, $parent_id, $ts, $author_id, $kind = 'card' ) {
		$dir_info = wp_upload_dir( gmdate( 'Y/m', $ts ) );
		if ( ! empty( $dir_info['error'] ) ) {
			nt_seed_fail( 'uploads dir: ' . $dir_info['error'] );
		}
		$is_logo  = ( 'logo' === $kind );
		$filename = wp_unique_filename( $dir_info['path'], $slug . ( $is_logo ? '.png' : '.jpg' ) );
		$file     = $dir_info['path'] . '/' . $filename;
		if ( 'hero' === $kind ) {
			nt_seed_make_hero( $file ); // no text: dusk sky, sun, road
		} elseif ( $is_logo ) {
			nt_seed_make_logo( $file );
		} else {
			nt_seed_make_card( $file, $title, $sub, $slug );
		}
		$args = array_merge(
			array(
				'import_id'      => $att_id,
				'post_mime_type' => $is_logo ? 'image/png' : 'image/jpeg',
				'post_title'     => $title,
				'post_name'      => $slug,
				'post_content'   => '',
				'post_excerpt'   => '',
				'post_status'    => 'inherit',
				'post_author'    => $author_id,
				'guid'           => $dir_info['url'] . '/' . $filename,
			),
			nt_seed_dates( $ts )
		);
		$id = wp_insert_attachment( wp_slash( $args ), $file, $parent_id, true );
		if ( is_wp_error( $id ) ) {
			nt_seed_fail( 'wp_insert_attachment: ' . $id->get_error_message() );
		}
		if ( (int) $id !== (int) $att_id ) {
			nt_seed_fail( "Attachment got ID $id instead of $att_id" );
		}
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		update_post_meta( $id, '_nt_sandbox_generated', '1' );
		$meta = wp_generate_attachment_metadata( $id, $file );
		wp_update_attachment_metadata( $id, $meta );
		return (int) $id;
	}

	// ---- blog post body (block markup or classic HTML) ----------------------------------------

	function nt_seed_build_post( array $data, $index, $title, array $cat_ids, $classic, $with_table, $with_quote ) {
		mt_srand( 20261017 + $index * 7919 );
		$main_cat = $cat_ids[0];
		$topics   = $data['topics'][ $main_cat ] ?? $data['topics']['default'];
		$used     = array();

		$topic = static function () use ( $topics ) {
			return $topics[ mt_rand( 0, count( $topics ) - 1 ) ];
		};
		$sentence = static function () use ( $data, $topic, &$used ) {
			for ( $try = 0; $try < 12; $try++ ) {
				if ( mt_rand( 0, 99 ) < 45 ) {
					$t = $topic();
					$s = str_replace( array( '{t}', '{T}' ), array( $t, nt_seed_ucfirst( $t ) ), $data['templates'][ mt_rand( 0, count( $data['templates'] ) - 1 ) ] );
				} else {
					$s = $data['sentences'][ mt_rand( 0, count( $data['sentences'] ) - 1 ) ];
				}
				if ( empty( $used[ $s ] ) ) {
					$used[ $s ] = 1;
					return $s;
				}
			}
			return $s;
		};
		$para = static function ( $n_min = 3, $n_max = 5 ) use ( $sentence ) {
			$n = mt_rand( $n_min, $n_max );
			$p = array();
			for ( $k = 0; $k < $n; $k++ ) {
				$p[] = $sentence();
			}
			return implode( ' ', $p );
		};

		$P = static function ( $text ) use ( $classic ) {
			return $classic ? "<p>$text</p>" : "<!-- wp:paragraph -->\n<p>$text</p>\n<!-- /wp:paragraph -->";
		};
		$H2 = static function ( $text ) use ( $classic ) {
			return $classic ? "<h2>$text</h2>" : "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">$text</h2>\n<!-- /wp:heading -->";
		};
		$H3 = static function ( $text ) use ( $classic ) {
			return $classic ? "<h3>$text</h3>" : "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">$text</h3>\n<!-- /wp:heading -->";
		};
		$UL = static function ( array $items ) use ( $classic ) {
			if ( $classic ) {
				return "<ul>\n<li>" . implode( "</li>\n<li>", $items ) . "</li>\n</ul>";
			}
			$li = '';
			foreach ( $items as $it ) {
				$li .= "<!-- wp:list-item -->\n<li>$it</li>\n<!-- /wp:list-item -->";
			}
			return "<!-- wp:list -->\n<ul class=\"wp-block-list\">$li</ul>\n<!-- /wp:list -->";
		};
		$QUOTE = static function ( $text, $cite ) use ( $classic ) {
			return $classic
				? "<blockquote><p>$text</p><cite>$cite</cite></blockquote>"
				: "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>$text</p>\n<!-- /wp:paragraph --><cite>$cite</cite></blockquote>\n<!-- /wp:quote -->";
		};
		$TABLE = static function ( array $head, array $rows ) use ( $classic ) {
			$th = '<th>' . implode( '</th><th>', $head ) . '</th>';
			$tr = '';
			foreach ( $rows as $r ) {
				$tr .= '<tr><td>' . implode( '</td><td>', $r ) . '</td></tr>';
			}
			$table = "<table><thead><tr>$th</tr></thead><tbody>$tr</tbody></table>";
			return $classic ? $table : "<!-- wp:table -->\n<figure class=\"wp-block-table\">" . str_replace( '<table>', '<table class="has-fixed-layout">', $table ) . "</figure>\n<!-- /wp:table -->";
		};

		// Target 600..1500 whitespace-separated words, steered by a budget and hard-clamped at the end.
		$target = 600 + mt_rand( 0, 900 );
		$n_h2   = 3 + mt_rand( 0, 2 );
		$lead   = 'Trong bài viết này, chúng ta cùng tìm hiểu chủ đề “' . $title . '” theo cách đơn giản và dễ áp dụng. ';
		$intro  = $lead . $para( 2, 3 );
		$items  = array(); // each: html, drop (may be removed by the clamp), w (words)
		$words  = 0;
		$add    = static function ( $html, $drop = false ) use ( &$items, &$words ) {
			$w       = nt_seed_words( $html );
			$items[] = array( 'html' => $html, 'drop' => $drop, 'w' => $w );
			$words  += $w;
		};
		$add( $P( $intro ) );

		$h2_pool    = nt_seed_shuffle( $data['h2'] );
		$h3_pool    = nt_seed_shuffle( $data['h3'] );
		$list_done  = false;
		$table_done = ! $with_table;
		$quote_done = ! $with_quote;
		for ( $s = 0; $s < $n_h2; $s++ ) {
			$add( $H2( str_replace( '{t}', $topic(), $h2_pool[ $s ] ) ) );
			$reserve   = 90 + ( $list_done ? 0 : 45 ) + ( $table_done ? 0 : 60 ) + ( $quote_done ? 0 : 25 );
			$k         = max( 1, (int) round( ( $target - $words - $reserve ) / ( $n_h2 - $s ) / 85 ) );
			for ( $j = 0; $j < $k; $j++ ) {
				$add( $P( $para() ), $j > 0 ); // the first paragraph under a heading is never dropped
				if ( 0 === $j ) {
					if ( ! $list_done && $s >= min( 1, $n_h2 - 1 ) ) {
						$add( $UL( array_slice( nt_seed_shuffle( $data['list_items'] ), 0, mt_rand( 4, 6 ) ) ) );
						$list_done = true;
					}
					if ( ! $table_done && $s >= min( 2, $n_h2 - 1 ) ) {
						$add( $TABLE( $data['table']['head'], array_slice( nt_seed_shuffle( $data['table']['rows'] ), 0, 4 ) ) );
						$table_done = true;
					}
					if ( ! $quote_done && $s >= min( 3, $n_h2 - 1 ) ) {
						$q = $data['quotes'][ mt_rand( 0, count( $data['quotes'] ) - 1 ) ];
						$add( $QUOTE( $q[0], $q[1] ) );
						$quote_done = true;
					}
				}
			}
			if ( mt_rand( 0, 99 ) < 45 && ( $words + 190 ) < $target + 120 ) {
				$add( $H3( $h3_pool[ $s % count( $h3_pool ) ] ) . "\n\n" . $P( $para( 2, 3 ) ), true );
			}
		}
		$conclusion_at = count( $items ); // closing paragraph + notice stay under the last H2
		$add( $P( $para( 2, 3 ) ) );
		$add( $P( '<em>' . $data['synthetic_notice'] . '</em>' ) );

		// hard clamp: 600..1500 words
		while ( $words > 1500 ) {
			$drop_at = -1;
			foreach ( $items as $idx => $it ) {
				if ( $it['drop'] ) {
					$drop_at = $idx; // last droppable wins
				}
			}
			if ( $drop_at < 0 ) {
				break;
			}
			$words -= $items[ $drop_at ]['w'];
			array_splice( $items, $drop_at, 1 );
			if ( $drop_at < $conclusion_at ) {
				--$conclusion_at;
			}
		}
		while ( $words < 600 ) {
			$html = $P( $para() );
			$w    = nt_seed_words( $html );
			array_splice( $items, $conclusion_at, 0, array( array( 'html' => $html, 'drop' => false, 'w' => $w ) ) );
			++$conclusion_at;
			$words += $w;
		}

		$content = implode( "\n\n", array_column( $items, 'html' ) );
		return array(
			'content' => $content,
			'intro'   => $intro,
			'words'   => nt_seed_words( $content ),
		);
	}

	/**
	 * Replaces the fixture placeholder tokens in page-fixture text BEFORE it is inserted:
	 *   {{HERO_ID}}              -> attachment ID of the generated hero image
	 *   {{PRODUCT_IMG:<slug>}}   -> attachment ID of that product's featured image
	 * Any token-shaped leftover (e.g. {{SOMETHING_ELSE}}, unknown product slug) aborts the seed.
	 *
	 * @param string              $text      Fixture file text.
	 * @param string              $label     File name used in error messages.
	 * @param int                 $hero_id   Hero attachment ID.
	 * @param array<string,int>   $by_slug   product slug => attachment ID.
	 * @param array<string,int>   $used      (by reference) token => number of replacements, for the manifest.
	 */
	function nt_seed_resolve_tokens( $text, $label, $hero_id, array $by_slug, array &$used ) {
		if ( ! get_post( $hero_id ) || 'attachment' !== get_post_type( $hero_id ) ) {
			nt_seed_fail( "Hero attachment $hero_id does not exist (needed by $label)" );
		}
		$count = 0;
		$text  = str_replace( '{{HERO_ID}}', (string) $hero_id, $text, $count );
		if ( $count ) {
			$used['{{HERO_ID}}'] = ( $used['{{HERO_ID}}'] ?? 0 ) + $count;
		}
		$text = preg_replace_callback(
			'/\{\{PRODUCT_IMG:([^}\s]*)\}\}/',
			static function ( $m ) use ( $by_slug, $label, &$used ) {
				$slug = $m[1];
				if ( ! isset( $by_slug[ $slug ] ) ) {
					nt_seed_fail( "Unresolvable token {{PRODUCT_IMG:$slug}} in $label: no product with slug '$slug' (known: " . implode( ', ', array_keys( $by_slug ) ) . ')' );
				}
				$att = (int) $by_slug[ $slug ];
				if ( 'attachment' !== get_post_type( $att ) ) {
					nt_seed_fail( "Token {{PRODUCT_IMG:$slug}} in $label points at attachment $att which does not exist" );
				}
				$key          = '{{PRODUCT_IMG:' . $slug . '}}';
				$used[ $key ] = ( $used[ $key ] ?? 0 ) + 1;
				return (string) $att;
			},
			$text
		);
		if ( null === $text ) {
			nt_seed_fail( "Token replacement failed for $label (PCRE error)" );
		}
		if ( preg_match( '/\{\{[A-Z][A-Z0-9_]*(?::[^}\n]*)?\}\}/', $text, $left ) ) {
			nt_seed_fail( "Unresolved placeholder token {$left[0]} in $label (supported: {{HERO_ID}}, {{PRODUCT_IMG:<product-slug>}})" );
		}
		return $text;
	}

	function nt_seed_trim( $text, $max = 155 ) {
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}
		$cut = mb_substr( $text, 0, $max );
		$sp  = mb_strrpos( $cut, ' ' );
		return rtrim( mb_substr( $cut, 0, false === $sp ? $max : $sp ), ' ,;:-' ) . '…';
	}
}

// ---------------------------------------------------------------------------------------------
// 0. preconditions
// ---------------------------------------------------------------------------------------------
global $wpdb, $wp_rewrite;
$t_start = microtime( true );
$phase   = static function ( $name ) use ( $t_start ) {
	nt_seed_log( sprintf( '[%6.1fs] %s', microtime( true ) - $t_start, $name ) );
};

if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WPSEO_Options' ) ) {
	nt_seed_fail( 'WooCommerce and Yoast SEO must be active (run setup.sh first).' );
}
if ( get_option( 'nt_sandbox_seeded' ) && '1' !== getenv( 'NT_SEED_FORCE' ) ) {
	nt_seed_fail( 'This database is already seeded. Use ./seed.sh (restores the pristine snapshot first).' );
}

$repo          = getenv( 'NT_REPO_ROOT' ) ? getenv( 'NT_REPO_ROOT' ) : dirname( __DIR__, 2 );
$baseline_file = getenv( 'NT_BASELINE_JSON' ) ? getenv( 'NT_BASELINE_JSON' ) : $repo . '/docs/wordpress-baseline-2026-10-17.json';
$fixture_dir   = getenv( 'NT_PAGES_FIXTURE_DIR' ) ? getenv( 'NT_PAGES_FIXTURE_DIR' ) : $repo . '/tests/fixtures/pages';
$manifest_file = getenv( 'NT_SEED_MANIFEST' ) ? getenv( 'NT_SEED_MANIFEST' ) : ( getenv( 'NT_SANDBOX_DIR' ) ? getenv( 'NT_SANDBOX_DIR' ) : sys_get_temp_dir() ) . '/seed-manifest.json';
if ( ! is_readable( $baseline_file ) ) {
	nt_seed_fail( "Baseline JSON not readable: $baseline_file" );
}
$base = json_decode( (string) file_get_contents( $baseline_file ), true );
if ( ! is_array( $base ) ) {
	nt_seed_fail( 'Baseline JSON is not valid JSON.' );
}
$data = require __DIR__ . '/php/seed-data.php';

$anchor_ts = strtotime( ( getenv( 'NT_SEED_ANCHOR' ) ? getenv( 'NT_SEED_ANCHOR' ) : '2026-10-17' ) . ' 12:00:00 UTC' );
if ( $anchor_ts > time() - 3600 ) {
	$anchor_ts = time() - 3600; // never create future (scheduled) posts
}
$day = 86400;

// Keep the seed fast and deterministic: no revisions, no kses rewriting, no Yoast indexable writes
// (seed.sh rebuilds all Yoast indexables in a fresh process once everything is in place).
kses_remove_filters();
add_filter( 'wp_revisions_to_keep', '__return_zero' );
add_filter( 'Yoast\WP\SEO\should_index_indexables', '__return_false', 100 );
wp_defer_term_counting( true );
wp_set_current_user( 1 );
update_option( 'default_comment_status', 'open' );
$wpdb->query( "ALTER TABLE {$wpdb->posts} AUTO_INCREMENT = 20001" ); // incidental auto IDs stay clear of every fixed ID

// ---------------------------------------------------------------------------------------------
// 1. permalinks + category base (must be active BEFORE content is created)
// ---------------------------------------------------------------------------------------------
$wp_rewrite->set_permalink_structure( '/%postname%/' );
update_option( 'category_base', '' );
update_option( 'tag_base', '' );
update_option(
	'woocommerce_permalinks',
	array(
		'product_base'           => '/san-pham',
		'category_base'          => 'danh-muc-san-pham',
		'tag_base'               => '',
		'attribute_base'         => '',
		'use_verbose_page_rules' => false,
	)
);
// Re-register WooCommerce types so the new slugs apply inside this very process.
WC_Post_Types::register_taxonomies();
WC_Post_Types::register_post_types();
// Production removes the "/category/" prefix: blog category 69 lives at /blog/ ... Yoast SEO's own feature.
WPSEO_Options::set( 'stripcategorybase', true );
$phase( 'permalinks (/%postname%/, /san-pham, danh-muc-san-pham) + Yoast stripcategorybase' );

// ---------------------------------------------------------------------------------------------
// 2. purge WordPress / WooCommerce default content
// ---------------------------------------------------------------------------------------------
$default_ids = get_posts(
	array(
		'post_type'      => array( 'post', 'page' ),
		'post_status'    => 'any',
		'numberposts'    => -1,
		'fields'         => 'ids',
		'exclude'        => array( 9001, 9002, 9003 ),
		'suppress_filters' => true,
	)
);
foreach ( $default_ids as $dead ) {
	wp_delete_post( $dead, true );
}
foreach ( array( 'woocommerce_refund_returns_page_id', 'woocommerce_terms_page_id' ) as $opt ) {
	$pid = (int) get_option( $opt );
	if ( $pid && ! get_post( $pid ) ) {
		delete_option( $opt );
	}
}
$phase( sprintf( 'purged %d default posts/pages', count( $default_ids ) ) );

// ---------------------------------------------------------------------------------------------
// 3. taxonomies: exact term IDs
// ---------------------------------------------------------------------------------------------
$cat_rows = array();
foreach ( $base['taxonomies']['category'] as $c ) {
	$name = html_entity_decode( $c['name'], ENT_QUOTES, 'UTF-8' );
	nt_seed_term( $c['id'], $name, $c['slug'], 'category', $c['parent'] );
	$cat_rows[ (int) $c['id'] ] = array( 'name' => $name, 'slug' => $c['slug'], 'parent' => (int) $c['parent'], 'count' => (int) $c['count'] );
}
foreach ( $base['taxonomies']['product_cat'] as $c ) {
	nt_seed_term( $c['term_id'], html_entity_decode( $c['name'], ENT_QUOTES, 'UTF-8' ), $c['slug'], 'product_cat', $c['parent'] );
}
foreach ( $base['taxonomies']['featured_item_category'] as $c ) {
	nt_seed_term( $c['term_id'], html_entity_decode( $c['name'], ENT_QUOTES, 'UTF-8' ), $c['slug'], 'featured_item_category', $c['parent'] );
}
nt_seed_term( (int) $base['menus']['main_menu_id'], 'Main chính', 'main-chinh', 'nav_menu', 0 );
nt_seed_term( (int) $base['menus']['secondary_menu_id'], 'Secondary', 'secondary', 'nav_menu', 0 );
update_option( 'default_category', 1 );
update_option( 'default_product_cat', 15 ); // WooCommerce's own default product_cat is already term 15 (see README)
$twice = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'product_cat' AND t.slug = 'uncategorized'" );
if ( 1 !== $twice ) {
	nt_seed_fail( "Expected exactly one product_cat 'uncategorized', found $twice" );
}
if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE term_id <> term_taxonomy_id" ) > 0 ) {
	nt_seed_fail( 'term_taxonomy_id differs from term_id for some term (unexpected).' );
}
foreach ( array( 'category', 'product_cat', 'featured_item_category', 'nav_menu' ) as $tax ) {
	delete_option( $tax . '_children' );
}
wp_cache_flush();
$phase( 'terms: 26 categories, product_cat 70-73 (+15), featured_item_category 77-80, nav_menu 66/67' );

// ---------------------------------------------------------------------------------------------
// 4. users
// ---------------------------------------------------------------------------------------------
$editor = get_user_by( 'login', 'nguyentu-editor' );
if ( $editor ) {
	$editor_id = (int) $editor->ID;
} else {
	$editor_id = wp_insert_user(
		array(
			'user_login'    => 'nguyentu-editor',
			'user_nicename' => 'nguyentu-editor',
			'user_pass'     => wp_generate_password( 32, true, true ),
			'user_email'    => 'editor@example.invalid',
			'display_name'  => 'Nguyễn Tú',
			'nickname'      => 'Nguyễn Tú',
			'first_name'    => 'Nguyễn',
			'last_name'     => 'Tú',
			'role'          => 'editor',
		)
	);
	if ( is_wp_error( $editor_id ) ) {
		nt_seed_fail( 'wp_insert_user: ' . $editor_id->get_error_message() );
	}
}
$phase( "user nguyentu-editor (ID $editor_id)" );

$manifest = array(
	'generated_at'  => gmdate( 'c' ),
	'anchor_date'   => gmdate( 'Y-m-d', $anchor_ts ),
	'editor_user'   => $editor_id,
	'pages'         => array(),
	'sandbox_pages' => array( 9001 => 'cart', 9002 => 'checkout', 9003 => 'my-account' ),
	'products'      => array(),
	'featured_items' => array(),
	'menu_items'    => array(),
	'posts'         => array(),
	'attachments'   => array(),
	'notes'         => array(),
);

// ---------------------------------------------------------------------------------------------
// 5. attachments: hero + product images (exact IDs 9100-9113).
//    Created BEFORE the pages because the page fixtures reference them through the tokens
//    {{HERO_ID}} and {{PRODUCT_IMG:<product-slug>}}.
// ---------------------------------------------------------------------------------------------
$hero_id = 9100;
nt_seed_attachment( $hero_id, 'sandbox-hero', 'Hero: con đường hoàng hôn (sandbox)', '', 'Ảnh hero mô phỏng: con đường dẫn về phía mặt trời lúc hoàng hôn (sandbox)', 0, $anchor_ts - 400 * $day, $editor_id, 'hero' );
$manifest['attachments']['hero'] = $hero_id;
$product_att         = array(); // product ID => attachment ID
$product_att_by_slug = array(); // product slug => attachment ID
$prod_list           = $base['products'];
usort(
	$prod_list,
	static function ( $a, $b ) {
		return (int) $a['ID'] <=> (int) $b['ID'];
	}
);
foreach ( $prod_list as $i => $p ) {
	$att_id = 9101 + $i;
	$ts     = $anchor_ts - ( 540 - $i * 30 ) * $day;
	nt_seed_attachment( $att_id, 'sandbox-product-' . $p['post_name'], $p['post_title'], 'Sản phẩm (sandbox)', 'Ảnh sản phẩm mô phỏng: ' . $p['post_title'], (int) $p['ID'], $ts, $editor_id );
	$product_att[ (int) $p['ID'] ]          = $att_id;
	$product_att_by_slug[ $p['post_name'] ] = $att_id;
}
// Site logo (sandbox-only ID 9120): Yoast only emits an Organization piece for a named company WITH a logo.
$logo_id = 9120;
nt_seed_attachment( $logo_id, 'sandbox-logo', 'Logo Nguyễn Tú (sandbox)', '', 'Logo mô phỏng: NT', 0, $anchor_ts - 400 * $day, $editor_id, 'logo' );
$manifest['attachments']['logo'] = $logo_id;
WPSEO_Options::set( 'company_or_person', 'company' );
WPSEO_Options::set( 'company_name', 'Cho Thuê Xe Máy Nguyễn Tú' );
WPSEO_Options::set( 'website_name', 'Cho Thuê Xe Máy Nguyễn Tú' );
WPSEO_Options::set( 'company_logo', (string) wp_get_attachment_url( $logo_id ) );
WPSEO_Options::set( 'company_logo_id', $logo_id );
$phase( 'attachments: hero 9100 (generated, no text) + 13 product images 9101-9113 + logo 9120' );

// ---------------------------------------------------------------------------------------------
// 6. pages (exact IDs). A fixture file tests/fixtures/pages/<ID>.html is used verbatim, after
//    the placeholder tokens above are replaced (an unresolvable token aborts the seed).
// ---------------------------------------------------------------------------------------------
$page_parent = array( 424 => 310, 432 => 310 );
$page_order  = array( 310, 424, 432, 372, 484, 494, 3040, 326, 449, 562 );
$page_by_id  = array();
foreach ( $base['pages'] as $p ) {
	$page_by_id[ (int) $p['ID'] ] = $p;
}
if ( count( $page_by_id ) !== 10 ) {
	nt_seed_fail( 'Baseline must list 10 pages.' );
}
$token_use = array();
$page_idx  = 0;
foreach ( $page_order as $pid ) {
	$p       = $page_by_id[ $pid ];
	$fixture = rtrim( $fixture_dir, '/' ) . '/' . $pid . '.html';
	if ( is_readable( $fixture ) ) {
		$content = nt_seed_resolve_tokens( (string) file_get_contents( $fixture ), 'tests/fixtures/pages/' . $pid . '.html', $hero_id, $product_att_by_slug, $token_use );
		$source  = 'fixture:' . $pid . '.html';
	} else {
		$content = '<p>Nội dung mô phỏng (sandbox) cho trang “' . $p['post_title'] . '”. Đặt file <code>tests/fixtures/pages/' . $pid . '.html</code> để kiểm thử nội dung legacy.</p>';
		$source  = 'placeholder';
	}
	$meta = array(
		'_nt_sandbox_page_source' => $source,
		'_yoast_wpseo_metadesc'   => nt_seed_trim( '[Sandbox] Mô tả meta mô phỏng cho trang “' . $p['post_title'] . '” của Cho Thuê Xe Máy Nguyễn Tú.' ),
	);
	if ( 562 === $pid ) {
		$meta['_yoast_wpseo_title'] = $p['post_title'];
	}
	$id = nt_seed_insert_post(
		array_merge(
			array(
				'import_id'      => $pid,
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => $p['post_title'],
				'post_name'      => $p['post_name'],
				'post_content'   => $content,
				'post_parent'    => $page_parent[ $pid ] ?? 0,
				'post_author'    => $editor_id,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'meta_input'     => $meta,
			),
			nt_seed_dates( $anchor_ts - ( 700 - $page_idx * 20 ) * $day )
		)
	);
	nt_seed_force_content( $id, $content ); // byte-identical: no kses / content filter may alter shortcodes or comments
	$manifest['pages'][ $id ] = array( 'slug' => $p['post_name'], 'parent' => $page_parent[ $pid ] ?? 0, 'content' => $source, 'bytes' => strlen( $content ) );
	++$page_idx;
}
$manifest['fixture_tokens'] = $token_use;
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', 562 );
update_option( 'page_for_posts', 0 );
update_option( 'woocommerce_shop_page_id', 3040 );
foreach ( nt_sb_ensure_classic_wc_pages( $editor_id ) as $cid => $cslug ) {
	$manifest['sandbox_pages'][ $cid ] = $cslug;
}
$phase( 'pages: 10 baseline (+ sandbox 9001-9003), front page 562, shop page 3040; tokens replaced: ' . wp_json_encode( $token_use ) );

// ---------------------------------------------------------------------------------------------
// 7. WooCommerce products (exact IDs, simple, VND)
// ---------------------------------------------------------------------------------------------
$product_cat_for = array( 'xe-so' => 71, 'xe-ga' => 72, 'xe-dien' => 73 );
foreach ( $prod_list as $i => $p ) {
	$pid   = (int) $p['ID'];
	$slug  = $p['post_name'];
	$kind  = $data['product_kinds'][ $slug ] ?? null;
	if ( ! $kind ) {
		nt_seed_fail( "No kind configured for product $slug" );
	}
	$tx    = $data['product_texts'][ $kind ];
	$ts    = $anchor_ts - ( 540 - $i * 30 ) * $day;
	nt_seed_insert_post(
		array_merge(
			array(
				'import_id'      => $pid,
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'post_title'     => $p['post_title'],
				'post_name'      => $slug,
				'post_content'   => '',
				'post_author'    => $editor_id,
				'comment_status' => 'open',
				'ping_status'    => 'closed',
			),
			nt_seed_dates( $ts )
		)
	);
	$product = wc_get_product( $pid );
	if ( ! $product instanceof WC_Product_Simple ) {
		nt_seed_fail( "Product $pid is not a simple product object" );
	}
	$long = '';
	foreach ( $tx['long'] as $para ) {
		$long .= '<p>' . $para . '</p>' . "\n";
	}
	$long .= '<ul><li>' . implode( '</li><li>', $tx['list'] ) . '</li></ul>';
	$price = $data['product_prices'][ $slug ] ?? '';
	$product->set_name( $p['post_title'] );
	$product->set_slug( $slug );
	$product->set_description( $long );
	$product->set_short_description( $tx['short'] );
	$product->set_regular_price( $price );   // '' => no price on purpose (non-purchasable path)
	$product->set_manage_stock( false );
	$product->set_stock_status( 'instock' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_category_ids( array( $product_cat_for[ $kind ] ) );
	$product->set_image_id( $product_att[ $pid ] );
	$product->set_reviews_allowed( true );
	$product->save();
	update_post_meta( $pid, '_yoast_wpseo_metadesc', nt_seed_trim( '[Sandbox] ' . $tx['short'] ) );
	$manifest['products'][ $pid ] = array( 'slug' => $slug, 'cat' => $product_cat_for[ $kind ], 'price' => $price, 'image' => $product_att[ $pid ] );
}
$phase( 'products: 13 simple products (6 priced, 7 without price)' );

// ---------------------------------------------------------------------------------------------
// 8. featured_item posts (exact IDs) -> terms 77 + child
// ---------------------------------------------------------------------------------------------
$fi_child = array(
	811 => 80, 809 => 80, 802 => 80, 795 => 80, 793 => 80,
	710 => 79, 708 => 79, 705 => 79, 703 => 79, 701 => 79,
	696 => 78, 693 => 78, 686 => 78, 682 => 78, 587 => 78,
);
foreach ( array_values( $base['featured_items'] ) as $i => $fi ) {
	$fid   = (int) $fi['ID'];
	$child = $fi_child[ $fid ] ?? 0;
	if ( ! $child ) {
		nt_seed_fail( "No featured_item_category child configured for $fid" );
	}
	$tx      = $data['featured_texts'][ $child ];
	$content = "<h2>{$tx[0]}</h2>\n<p>{$tx[1]}</p>\n<p>{$tx[2]}</p>";
	nt_seed_insert_post(
		array_merge(
			array(
				'import_id'      => $fid,
				'post_type'      => 'featured_item',
				'post_status'    => 'publish',
				'post_title'     => $fi['post_title'],
				'post_name'      => $fi['post_name'],
				'post_content'   => $content,
				'post_author'    => $editor_id,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'meta_input'     => array(
					'_yoast_wpseo_metadesc' => nt_seed_trim( '[Sandbox] Mô tả mô phỏng: ' . $fi['post_title'] ),
				),
			),
			nt_seed_dates( $anchor_ts - ( 300 - $i * 6 ) * $day )
		)
	);
	nt_seed_assign_terms( $fid, 'featured_item_category', array( 77, $child ) );
	$manifest['featured_items'][ $fid ] = array( 'slug' => $fi['post_name'], 'terms' => array( 77, $child ) );
}
$phase( 'featured_item: 15 posts (each in 77 + one child term)' );

// ---------------------------------------------------------------------------------------------
// 9. nav menu 67 (36 items, exact IDs, parents, order)
// ---------------------------------------------------------------------------------------------
$menu_id = (int) $base['menus']['main_menu_id'];
foreach ( $base['menus']['main_items'] as $it ) {
	$mid = (int) $it['db_id'];
	nt_seed_insert_post(
		array_merge(
			array(
				'import_id'      => $mid,
				'post_type'      => 'nav_menu_item',
				'post_status'    => 'publish',
				'post_title'     => $it['title'],
				'post_name'      => (string) $mid,
				'post_content'   => '',
				'post_excerpt'   => '',
				'menu_order'     => (int) $it['position'],
				'post_author'    => 1,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			nt_seed_dates( $anchor_ts - 200 * $day )
		)
	);
	update_post_meta( $mid, '_menu_item_type', $it['type'] );
	update_post_meta( $mid, '_menu_item_menu_item_parent', (string) (int) $it['parent'] );
	update_post_meta( $mid, '_menu_item_object_id', (string) (int) $it['object_id'] );
	update_post_meta( $mid, '_menu_item_object', $it['object'] );
	update_post_meta( $mid, '_menu_item_target', '' );
	update_post_meta( $mid, '_menu_item_classes', array( '' ) );
	update_post_meta( $mid, '_menu_item_xfn', '' );
	update_post_meta( $mid, '_menu_item_url', 'custom' === $it['type'] ? $it['link'] : '' );
	nt_seed_assign_terms( $mid, 'nav_menu', array( $menu_id ) );
	$manifest['menu_items'][ $mid ] = array( 'parent' => (int) $it['parent'], 'position' => (int) $it['position'], 'type' => $it['type'], 'object' => $it['object'], 'object_id' => (int) $it['object_id'] );
}
$phase( 'nav_menu 67: 36 items (menu 66 stays empty)' );

// ---------------------------------------------------------------------------------------------
// 10. 160 blog posts (IDs 10001-10160) + ~40 featured images (9200-9239)
// ---------------------------------------------------------------------------------------------
$slots = array();   // title => cats[]
foreach ( $data['post_titles'] as $cat => $titles ) {
	foreach ( $titles as $title ) {
		$slots[ $title ] = array( $cat );
	}
}
$slots[ $data['shared_post']['title'] ] = $data['shared_post']['cats'];
$entries = array();
foreach ( $slots as $title => $cats ) {
	$entries[] = array( 'title' => $title, 'cats' => $cats );
}
if ( 160 !== count( $entries ) ) {
	nt_seed_fail( 'Expected 160 blog posts, built ' . count( $entries ) );
}
mt_srand( 1017 );
$entries = nt_seed_shuffle( $entries );   // categories are not clustered by date

$n_posts     = count( $entries );
$span        = 365 * $day;
$step        = (int) floor( $span / $n_posts );
$img_counter = 0;
$word_total  = 0;
$post_cat_counts = array();
foreach ( $entries as $i => $e ) {
	$pid     = 10001 + $i;
	$ts      = $anchor_ts - ( $n_posts - 1 - $i ) * $step - ( 3600 * ( 1 + ( $i * 7 ) % 10 ) ) - ( ( $i * 13 ) % 60 ) * 60;
	$classic = ( 2 === $i % 3 );
	$built   = nt_seed_build_post( $data, $i, $e['title'], $e['cats'], $classic, ( 0 === $i % 5 ), ( 1 === $i % 4 ) );
	$meta    = array( '_nt_sandbox_synthetic' => '1', '_nt_sandbox_markup' => $classic ? 'classic' : 'blocks' );
	if ( 3 === $i % 4 ) {
		$meta['_yoast_wpseo_metadesc'] = nt_seed_trim( '[Sandbox] ' . $built['intro'] );
	}
	if ( 5 === $i % 10 ) {
		$meta['_yoast_wpseo_title'] = $e['title'] . ' | Nguyễn Tú';
	}
	if ( 7 === $i % 10 ) {
		$meta['_yoast_wpseo_focuskw'] = mb_strtolower( mb_substr( $e['title'], 0, 28 ) );
	}
	$args = array_merge(
		array(
			'import_id'      => $pid,
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'post_title'     => $e['title'],
			'post_name'      => sanitize_title( $e['title'] ),
			'post_content'   => $built['content'],
			'post_excerpt'   => ( 1 === $i % 3 ) ? nt_seed_trim( $built['intro'], 200 ) : '',
			'post_author'    => $editor_id,
			'post_category'  => $e['cats'],
			'comment_status' => 'open',
			'ping_status'    => 'closed',
			'meta_input'     => $meta,
		),
		nt_seed_dates( $ts )
	);
	nt_seed_insert_post( $args );
	nt_seed_force_content( $pid, $built['content'] );
	$thumb = 0;
	if ( 0 === $i % 4 ) { // 40 posts get a featured image
		$thumb = 9200 + $img_counter++;
		$label = nt_seed_trim( $e['title'], 60 );
		nt_seed_attachment( $thumb, 'sandbox-post-' . $pid, $label, $cat_rows[ $e['cats'][0] ]['name'] . ' · #' . $pid, 'Ảnh minh họa mô phỏng: ' . $e['title'], $pid, $ts, $editor_id );
		set_post_thumbnail( $pid, $thumb );
	}
	foreach ( $e['cats'] as $cid ) {
		$post_cat_counts[ $cid ] = ( $post_cat_counts[ $cid ] ?? 0 ) + 1;
	}
	$word_total += $built['words'];
	$manifest['posts'][ $pid ] = array( 'slug' => get_post_field( 'post_name', $pid ), 'cats' => $e['cats'], 'thumb' => $thumb, 'words' => $built['words'], 'markup' => $classic ? 'classic' : 'blocks', 'date' => gmdate( 'Y-m-d', $ts ) );
	if ( 0 === ( $i + 1 ) % 40 ) {
		$phase( sprintf( 'posts: %d/160', $i + 1 ) );
	}
}
mt_srand();
$manifest['attachments']['post_images'] = array( 'first' => 9200, 'count' => $img_counter );
$manifest['attachments']['products']    = $product_att;
// category counts must equal the baseline exactly
foreach ( $cat_rows as $cid => $row ) {
	$have = $post_cat_counts[ $cid ] ?? 0;
	if ( $have !== $row['count'] ) {
		nt_seed_fail( sprintf( 'Category %d planned count %d != baseline %d', $cid, $have, $row['count'] ) );
	}
}
$phase( sprintf( 'posts done: 160 posts, %d featured images, avg %d words', $img_counter, (int) ( $word_total / $n_posts ) ) );

// ---------------------------------------------------------------------------------------------
// 11. counts, caches, auto-increment, manifest
// ---------------------------------------------------------------------------------------------
wp_defer_term_counting( false ); // runs the (WooCommerce aware) recount for every touched term
$wpdb->query(
	"UPDATE {$wpdb->term_taxonomy} tt SET tt.count = (
		SELECT COUNT(*) FROM {$wpdb->term_relationships} tr JOIN {$wpdb->posts} p ON p.ID = tr.object_id
		WHERE tr.term_taxonomy_id = tt.term_taxonomy_id AND p.post_status = 'publish' AND p.post_type = 'featured_item'
	) WHERE tt.taxonomy = 'featured_item_category'"
);
$wpdb->query(
	"UPDATE {$wpdb->term_taxonomy} tt SET tt.count = (
		SELECT COUNT(*) FROM {$wpdb->term_relationships} tr WHERE tr.term_taxonomy_id = tt.term_taxonomy_id
	) WHERE tt.taxonomy = 'nav_menu'"
);
// WooCommerce: refresh the "including children" counters shown on the front end.
$pc_ids = $wpdb->get_col( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'product_cat'" );
_wc_term_recount( array_map( 'intval', $pc_ids ), get_taxonomy( 'product_cat' ), true, true );
delete_transient( 'wc_term_counts' );
foreach ( array( 'category', 'product_cat', 'featured_item_category', 'nav_menu' ) as $tax ) {
	delete_option( $tax . '_children' );
	clean_taxonomy_cache( $tax );
}
$max_id = (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts}" );
$wpdb->query( 'ALTER TABLE ' . $wpdb->posts . ' AUTO_INCREMENT = ' . ( $max_id + 1 ) );
$max_term = (int) $wpdb->get_var( "SELECT MAX(term_id) FROM {$wpdb->terms}" );
$wpdb->query( 'ALTER TABLE ' . $wpdb->terms . ' AUTO_INCREMENT = ' . ( $max_term + 1 ) );
$max_tt = (int) $wpdb->get_var( "SELECT MAX(term_taxonomy_id) FROM {$wpdb->term_taxonomy}" );
$wpdb->query( 'ALTER TABLE ' . $wpdb->term_taxonomy . ' AUTO_INCREMENT = ' . ( $max_tt + 1 ) );
wp_cache_flush();

$manifest['notes'] = array(
	'sandbox_only_ids' => 'pages 9001-9003 (classic WooCommerce pages), attachments 9100-9113 + 9120 (logo) + 9200-9239, blog posts 10001-10160, user nguyentu-editor',
	'attachment_count' => 'Baseline had 123 attachments; the sandbox only has the generated ones (+ the WooCommerce placeholder).',
	'category_names'   => 'Names with &amp; in the baseline are stored decoded (plain &).',
	'shared_post'      => 'Exactly one post carries two categories: ' . $data['shared_post']['title'] . ' (157 + 156).',
);
$manifest['counts'] = array(
	'posts'          => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'" ),
	'pages'          => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='page' AND post_status='publish'" ),
	'products'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'" ),
	'featured_items' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='featured_item' AND post_status='publish'" ),
	'menu_items'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='nav_menu_item'" ),
	'attachments'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment'" ),
);
file_put_contents( $manifest_file, wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );

update_option(
	'nt_sandbox_seeded',
	array(
		'at'       => gmdate( 'c' ),
		'baseline' => basename( $baseline_file ),
		'counts'   => $manifest['counts'],
	)
);
$phase( 'counts ' . wp_json_encode( $manifest['counts'] ) );
WP_CLI::success( "Seed finished in " . round( microtime( true ) - $t_start, 1 ) . "s; manifest: $manifest_file" );
