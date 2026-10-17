<?php
/**
 * SANDBOX HELPER (not theme code): database / option / permalink assertions for verify-seed.sh.
 * Run through WP-CLI:  ./wp.sh eval-file php/verify-seed.php
 *
 * Output (tab separated, parsed by verify-seed.sh):
 *   ROW <PASS|FAIL|INFO> <section> <check> <expected> <actual>
 *   URL <label> <path>            (dynamic URLs for the curl part)
 * Nothing is modified.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run through WP-CLI: wp eval-file\n" ); // phpcs:ignore
	exit( 1 );
}

global $wpdb;

$repo     = getenv( 'NT_REPO_ROOT' ) ? getenv( 'NT_REPO_ROOT' ) : dirname( __DIR__, 3 );
$baseline = getenv( 'NT_BASELINE_JSON' ) ? getenv( 'NT_BASELINE_JSON' ) : $repo . '/docs/wordpress-baseline-2026-10-17.json';
$fixtures = getenv( 'NT_PAGES_FIXTURE_DIR' ) ? getenv( 'NT_PAGES_FIXTURE_DIR' ) : $repo . '/tests/fixtures/pages';
$base     = json_decode( (string) file_get_contents( $baseline ), true );
if ( ! is_array( $base ) ) {
	fwrite( STDERR, "baseline JSON unreadable: $baseline\n" ); // phpcs:ignore
	exit( 1 );
}
$home = untrailingslashit( home_url() );

function nt_v_clean( $v ) {
	if ( is_bool( $v ) ) {
		$v = $v ? 'true' : 'false';
	}
	return trim( str_replace( array( "\t", "\r", "\n" ), ' ', (string) $v ) );
}
function nt_v_row( $status, $section, $check, $expected, $actual ) {
	echo "ROW\t", $status, "\t", $section, "\t", nt_v_clean( $check ), "\t", nt_v_clean( $expected ), "\t", nt_v_clean( $actual ), "\n"; // phpcs:ignore
}
function nt_v_eq( $section, $check, $expected, $actual ) {
	nt_v_row( (string) $expected === (string) $actual ? 'PASS' : 'FAIL', $section, $check, $expected, $actual );
}
/** PASS when $bad is empty, else FAIL with the (shortened) list of problems. */
function nt_v_none( $section, $check, array $bad, $what = '0 mismatches' ) {
	$shown = implode( ', ', array_slice( $bad, 0, 6 ) ) . ( count( $bad ) > 6 ? sprintf( ' (+%d more)', count( $bad ) - 6 ) : '' );
	nt_v_row( $bad ? 'FAIL' : 'PASS', $section, $check, $what, $bad ? $shown : $what );
}
function nt_v_count( $sql ) {
	global $wpdb;
	return (int) $wpdb->get_var( $sql ); // phpcs:ignore
}
function nt_v_path( $url ) {
	$p = (string) wp_parse_url( $url, PHP_URL_PATH );
	return '' === $p ? '/' : $p;
}

// ============================================================================================
// counts
// ============================================================================================
$page_ids = array_map( 'intval', array_column( $base['pages'], 'ID' ) );
$in_pages = implode( ',', $page_ids );
nt_v_eq( 'counts', 'published posts', $base['baseline_counts']['published_posts'], nt_v_count( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='post' AND post_status='publish'" ) );
nt_v_eq( 'counts', 'published pages (10 baseline IDs)', $base['baseline_counts']['published_pages'], nt_v_count( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='page' AND post_status='publish' AND ID IN ($in_pages)" ) );
nt_v_eq( 'counts', 'published pages total (10 + sandbox 9001-9003)', 13, nt_v_count( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='page' AND post_status='publish'" ) );
nt_v_eq( 'counts', 'published products', $base['baseline_counts']['published_products'], nt_v_count( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'" ) );
nt_v_eq( 'counts', 'published featured_item', $base['baseline_counts']['published_featured_items'], nt_v_count( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='featured_item' AND post_status='publish'" ) );
nt_v_eq( 'counts', 'post categories (taxonomy category)', $base['baseline_counts']['post_categories'], nt_v_count( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy='category'" ) );
nt_v_eq( 'counts', 'menu 67 items', $base['baseline_counts']['main_menu_items'], nt_v_count( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id=67" ) );
nt_v_eq( 'counts', 'menu 66 items (empty)', 0, nt_v_count( "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id=66" ) );
nt_v_eq( 'counts', 'nav_menu_item posts in total', 36, nt_v_count( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='nav_menu_item'" ) );
nt_v_eq( 'counts', 'product_cat terms (70-73 + 15)', $base['baseline_counts']['product_categories'], nt_v_count( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy='product_cat'" ) );
nt_v_eq( 'counts', 'featured_item_category terms', $base['baseline_counts']['featured_terms'], nt_v_count( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy='featured_item_category'" ) );
$att = nt_v_count( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment'" );
nt_v_row( $att >= 54 ? 'PASS' : 'FAIL', 'counts', 'attachments (generated 54 + WooCommerce placeholder)', '>= 54', $att );
nt_v_eq( 'counts', 'drafts / private / pending posts, pages, products', 0, nt_v_count( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('post','page','product','featured_item') AND post_status NOT IN ('publish')" ) );

// ============================================================================================
// exact IDs, slugs, titles, parents
// ============================================================================================
$bad = array();
foreach ( $base['pages'] as $p ) {
	$post = get_post( (int) $p['ID'] );
	if ( ! $post || 'page' !== $post->post_type || 'publish' !== $post->post_status ) {
		$bad[] = $p['ID'] . ':missing';
		continue;
	}
	if ( $post->post_name !== $p['post_name'] ) {
		$bad[] = $p['ID'] . ':slug=' . $post->post_name;
	}
	if ( $post->post_title !== $p['post_title'] ) {
		$bad[] = $p['ID'] . ':title';
	}
	$want_parent = in_array( (int) $p['ID'], array( 424, 432 ), true ) ? 310 : 0;
	if ( (int) $post->post_parent !== $want_parent ) {
		$bad[] = $p['ID'] . ':parent=' . $post->post_parent;
	}
}
nt_v_none( 'ids', '10 pages: ID/slug/title/parent (424,432 under 310)', $bad );

$bad = array();
foreach ( $base['products'] as $p ) {
	$post = get_post( (int) $p['ID'] );
	if ( ! $post || 'product' !== $post->post_type || 'publish' !== $post->post_status ) {
		$bad[] = $p['ID'] . ':missing';
		continue;
	}
	if ( $post->post_name !== $p['post_name'] || $post->post_title !== $p['post_title'] ) {
		$bad[] = $p['ID'] . ':slug/title';
	}
}
nt_v_none( 'ids', '13 products: ID/slug/title', $bad );

$fi_expect = array( 811 => 80, 809 => 80, 802 => 80, 795 => 80, 793 => 80, 710 => 79, 708 => 79, 705 => 79, 703 => 79, 701 => 79, 696 => 78, 693 => 78, 686 => 78, 682 => 78, 587 => 78 );
$bad       = array();
foreach ( $base['featured_items'] as $p ) {
	$post = get_post( (int) $p['ID'] );
	if ( ! $post || 'featured_item' !== $post->post_type || 'publish' !== $post->post_status ) {
		$bad[] = $p['ID'] . ':missing';
		continue;
	}
	if ( $post->post_name !== $p['post_name'] || $post->post_title !== $p['post_title'] ) {
		$bad[] = $p['ID'] . ':slug/title';
	}
	$tt = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_relationships} WHERE object_id=%d", $p['ID'] ) ) );
	sort( $tt );
	$want = array( 77, $fi_expect[ (int) $p['ID'] ] );
	sort( $want );
	if ( $tt !== $want ) {
		$bad[] = $p['ID'] . ':terms=' . implode( '+', $tt );
	}
}
nt_v_none( 'ids', '15 featured_item: ID/slug/title + terms 77 and one of 78/79/80', $bad );

// ---- terms -------------------------------------------------------------------------------
$bad = array();
foreach ( $base['taxonomies']['category'] as $c ) {
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT t.name, t.slug, tt.parent, tt.count, tt.term_taxonomy_id, tt.taxonomy FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id WHERE t.term_id=%d", $c['id'] ) );
	if ( ! $row || 'category' !== $row->taxonomy ) {
		$bad[] = $c['id'] . ':missing';
		continue;
	}
	if ( $row->name !== html_entity_decode( $c['name'], ENT_QUOTES, 'UTF-8' ) || $row->slug !== $c['slug'] ) {
		$bad[] = $c['id'] . ':name/slug';
	}
	if ( (int) $row->parent !== (int) $c['parent'] ) {
		$bad[] = $c['id'] . ':parent=' . $row->parent . '!=' . $c['parent'];
	}
	if ( (int) $row->term_taxonomy_id !== (int) $c['id'] ) {
		$bad[] = $c['id'] . ':tt_id';
	}
}
nt_v_none( 'terms', '26 categories: term_id/name/slug/parent', $bad );

$bad = array();
foreach ( $base['taxonomies']['category'] as $c ) {
	$have = (int) get_term( (int) $c['id'], 'category' )->count;
	if ( $have !== (int) $c['count'] ) {
		$bad[] = $c['id'] . ':' . $have . '!=' . $c['count'];
	}
}
nt_v_none( 'terms', '26 categories: post counts equal baseline (sum 161)', $bad, '0 mismatches' );
nt_v_eq( 'terms', 'sum of category counts', 161, (int) $wpdb->get_var( "SELECT SUM(count) FROM {$wpdb->term_taxonomy} WHERE taxonomy='category'" ) );

$bad = array();
foreach ( $base['taxonomies']['product_cat'] as $c ) {
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT t.name, t.slug, tt.parent, tt.taxonomy FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id WHERE t.term_id=%d", $c['term_id'] ) );
	if ( ! $row || 'product_cat' !== $row->taxonomy || $row->name !== $c['name'] || $row->slug !== $c['slug'] || (int) $row->parent !== (int) $c['parent'] ) {
		$bad[] = $c['term_id'];
	}
}
nt_v_none( 'terms', 'product_cat 70 > 71,72,73 and Uncategorized 15: name/slug/parent', $bad );
nt_v_eq( 'terms', 'exactly one product_cat slug "uncategorized"', 1, nt_v_count( "SELECT COUNT(*) FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id WHERE tt.taxonomy='product_cat' AND t.slug='uncategorized'" ) );
nt_v_eq( 'terms', 'option default_product_cat', 15, get_option( 'default_product_cat' ) );

$shown = array();
foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ) as $t ) { // WooCommerce adds the children to the count (non-admin context)
	$shown[ $t->term_id ] = (int) $t->count;
}
$want = array( 70 => 13, 71 => 4, 72 => 6, 73 => 3, 15 => 0 );
$bad  = array();
foreach ( $want as $tid => $n ) {
	if ( ( $shown[ $tid ] ?? -1 ) !== $n ) {
		$bad[] = $tid . ':' . ( $shown[ $tid ] ?? 'none' ) . '!=' . $n;
	}
}
nt_v_none( 'terms', 'product_cat displayed counts 70:13 71:4 72:6 73:3 15:0 (WooCommerce, children included)', $bad );

$bad = array();
foreach ( $base['taxonomies']['featured_item_category'] as $c ) {
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT t.name, t.slug, tt.parent, tt.count, tt.taxonomy FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id WHERE t.term_id=%d", $c['term_id'] ) );
	if ( ! $row || 'featured_item_category' !== $row->taxonomy || $row->name !== $c['name'] || $row->slug !== $c['slug'] || (int) $row->parent !== (int) $c['parent'] || (int) $row->count !== (int) $c['count'] ) {
		$bad[] = $c['term_id'];
	}
}
nt_v_none( 'terms', 'featured_item_category 77 > 78,79,80: name/slug/parent/count (15,5,5,5)', $bad );
$nav = $wpdb->get_results( "SELECT t.term_id, t.name, t.slug FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id WHERE tt.taxonomy='nav_menu' ORDER BY t.term_id", ARRAY_A );
nt_v_eq( 'terms', 'nav_menu terms', '66:Secondary:secondary|67:Main chính:main-chinh', implode( '|', array_map( static function ( $r ) { return $r['term_id'] . ':' . $r['name'] . ':' . $r['slug']; }, $nav ) ) );
nt_v_eq( 'terms', 'term_taxonomy_id == term_id for every term', 0, nt_v_count( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE term_id <> term_taxonomy_id" ) );

// ============================================================================================
// menu 67: 36 items, exact IDs / parents / order / object references
// ============================================================================================
$bad_parent = array();
$bad_pos    = array();
$bad_ref    = array();
$bad_url    = array();
$bad_term   = array();
$custom     = 0;
foreach ( $base['menus']['main_items'] as $it ) {
	$id   = (int) $it['db_id'];
	$post = get_post( $id );
	if ( ! $post || 'nav_menu_item' !== $post->post_type ) {
		$bad_ref[] = $id . ':missing';
		continue;
	}
	if ( (int) get_post_meta( $id, '_menu_item_menu_item_parent', true ) !== (int) $it['parent'] ) {
		$bad_parent[] = $id . ':' . get_post_meta( $id, '_menu_item_menu_item_parent', true ) . '!=' . $it['parent'];
	}
	if ( (int) $post->menu_order !== (int) $it['position'] ) {
		$bad_pos[] = $id . ':' . $post->menu_order . '!=' . $it['position'];
	}
	if ( get_post_meta( $id, '_menu_item_type', true ) !== $it['type'] || get_post_meta( $id, '_menu_item_object', true ) !== $it['object'] || (int) get_post_meta( $id, '_menu_item_object_id', true ) !== (int) $it['object_id'] ) {
		$bad_ref[] = $id . ':type/object/object_id';
	}
	if ( 'custom' === $it['type'] ) {
		++$custom;
		if ( get_post_meta( $id, '_menu_item_url', true ) !== $it['link'] ) {
			$bad_url[] = $id;
		}
	}
	if ( $post->post_title !== $it['title'] ) {
		$bad_ref[] = $id . ':title';
	}
	if ( ! has_term( 67, 'nav_menu', $id ) ) {
		$bad_term[] = $id;
	}
}
nt_v_none( 'menu', '36 menu items: _menu_item_menu_item_parent equals baseline', $bad_parent );
nt_v_none( 'menu', '36 menu items: menu_order equals baseline position', $bad_pos );
nt_v_none( 'menu', '36 menu items: type/object/object_id/title', $bad_ref );
nt_v_none( 'menu', sprintf( '%d custom links keep their production absolute URL', $custom ), $bad_url );
nt_v_none( 'menu', '36 menu items belong to nav_menu term 67', $bad_term );
$items = wp_get_nav_menu_items( 67 );
nt_v_eq( 'menu', 'wp_get_nav_menu_items(67) returns', 36, is_array( $items ) ? count( $items ) : 0 );
$locs   = get_option( 'theme_mods_flatsome-child' );
$locs   = is_array( $locs ) && isset( $locs['nav_menu_locations'] ) ? $locs['nav_menu_locations'] : array();
ksort( $locs );
nt_v_eq( 'menu', 'flatsome-child locations (legacy stand-in)', 'footer=66,primary=67,primary_mobile=67,top_bar_nav=66', implode( ',', array_map( static function ( $k, $v ) { return "$k=$v"; }, array_keys( $locs ), $locs ) ) );
$active = (string) get_option( 'stylesheet' );
$mods   = get_option( 'theme_mods_' . $active );
nt_v_eq( 'menu', "no locations assigned by the seed for active theme ($active)", 'none', is_array( $mods ) && ! empty( $mods['nav_menu_locations'] ) ? wp_json_encode( $mods['nav_menu_locations'] ) : 'none' );

// ============================================================================================
// permalinks as production resolves them
// ============================================================================================
$bad = array();
foreach ( $base['taxonomies']['category'] as $c ) {
	$want = nt_v_path( $c['link'] );
	$got  = nt_v_path( (string) get_term_link( (int) $c['id'], 'category' ) );
	if ( $want !== $got ) {
		$bad[] = $c['id'] . ':' . $got . '!=' . $want;
	}
}
nt_v_none( 'permalinks', '26 category links equal production paths (no /category/ prefix)', $bad );

$bad = array();
foreach ( $base['menus']['main_items'] as $it ) {
	if ( 'taxonomy' !== $it['type'] ) {
		continue;
	}
	$want = nt_v_path( $it['link'] );
	$got  = nt_v_path( (string) get_term_link( (int) $it['object_id'], $it['object'] ) );
	if ( $want !== $got ) {
		$bad[] = $it['object'] . '#' . $it['object_id'] . ':' . $got . '!=' . $want;
	}
}
nt_v_none( 'permalinks', 'menu taxonomy items (category, product_cat, featured_item_category) resolve to production paths', $bad );

$bad = array();
foreach ( $base['menus']['main_items'] as $it ) {
	if ( 'post_type' !== $it['type'] ) {
		continue;
	}
	$want = nt_v_path( $it['link'] );
	$got  = nt_v_path( (string) get_permalink( (int) $it['object_id'] ) );
	if ( 562 === (int) $it['object_id'] ) {
		$got = nt_v_path( home_url( '/' ) ); // the static front page is served at /
		if ( (int) get_option( 'page_on_front' ) !== 562 ) {
			$bad[] = 'page_on_front';
		}
	}
	if ( $want !== $got ) {
		$bad[] = '#' . $it['object_id'] . ':' . $got . '!=' . $want;
	}
}
nt_v_none( 'permalinks', 'menu page items resolve to production paths (incl. /gioi-thieu/ve-chung-toi/)', $bad );

$cat_paths = array();
foreach ( $base['taxonomies']['category'] as $c ) {
	$cat_paths[ nt_v_path( (string) get_term_link( (int) $c['id'], 'category' ) ) ] = (int) $c['id'];
}
$bad = array();
foreach ( $base['menus']['main_items'] as $it ) {
	if ( 'custom' === $it['type'] && ! isset( $cat_paths[ nt_v_path( $it['link'] ) ] ) ) {
		$bad[] = $it['db_id'] . ':' . nt_v_path( $it['link'] );
	}
}
nt_v_none( 'permalinks', 'every custom /blog/... menu URL maps to an existing category archive', $bad );

$bad = array();
foreach ( $base['products'] as $p ) {
	$want = '/san-pham/' . $p['post_name'] . '/';
	$got  = nt_v_path( (string) get_permalink( (int) $p['ID'] ) );
	if ( $want !== $got ) {
		$bad[] = $p['ID'] . ':' . $got;
	}
}
nt_v_none( 'permalinks', '13 product URLs are /san-pham/<slug>/', $bad );
$wcp = get_option( 'woocommerce_permalinks' );
nt_v_eq( 'permalinks', 'woocommerce_permalinks product_base / category_base', '/san-pham | danh-muc-san-pham', ( $wcp['product_base'] ?? '?' ) . ' | ' . ( $wcp['category_base'] ?? '?' ) );
nt_v_eq( 'permalinks', 'permalink_structure', '/%postname%/', get_option( 'permalink_structure' ) );
nt_v_eq( 'permalinks', 'Yoast stripcategorybase', 'true', WPSEO_Options::get( 'stripcategorybase' ) ? 'true' : 'false' );
$fi = get_posts( array( 'post_type' => 'featured_item', 'numberposts' => 1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
if ( $fi ) {
	nt_v_row( 'INFO', 'permalinks', 'featured_item single permalink (from the simulator)', '-', nt_v_path( (string) get_permalink( $fi[0] ) ) );
	echo "URL\tfeatured_item single\t", nt_v_path( (string) get_permalink( $fi[0] ) ), "\n"; // phpcs:ignore
}
$post1 = get_posts( array( 'post_type' => 'post', 'numberposts' => 1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
if ( $post1 ) {
	echo "URL\tblog post\t", nt_v_path( (string) get_permalink( $post1[0] ) ), "\n"; // phpcs:ignore
}

// ---- URL groups for the curl part (one WP boot instead of many) -----------------------------
foreach ( get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false ) ) as $t ) {
	echo "URL\tg:category archives (all 26, incl. empty ones)\t", nt_v_path( (string) get_term_link( $t ) ), "\n"; // phpcs:ignore
}
foreach ( $page_ids as $pid ) {
	echo "URL\tg:baseline pages (10)\t", nt_v_path( (string) get_permalink( $pid ) ), "\n"; // phpcs:ignore
}
foreach ( $base['products'] as $p ) {
	echo "URL\tg:product pages (13)\t", nt_v_path( (string) get_permalink( (int) $p['ID'] ) ), "\n"; // phpcs:ignore
}
foreach ( $base['featured_items'] as $p ) {
	echo "URL\tg:featured_item singles (15)\t", nt_v_path( (string) get_permalink( (int) $p['ID'] ) ), "\n"; // phpcs:ignore
}
foreach ( (array) wp_get_nav_menu_items( 67 ) as $mi ) {
	echo "URL\tg:menu 67 targets (36; custom links mapped to local paths)\t", nt_v_path( (string) $mi->url ), "\n"; // phpcs:ignore
}

// ============================================================================================
// WooCommerce products
// ============================================================================================
$prices = array( 'honda-wave' => '150000', 'honda-vision' => '200000', 'honda-air-blade' => '200000', 'honda-click' => '150000', 'yamaha-mio-classico' => '150000', 'xe-ga-50cc' => '200000' );
$cats   = array( 71 => array( 'honda-wave', 'xe-so-honda-blade', 'yamaha-sirius', 'honda-cub' ), 72 => array( 'honda-vision', 'honda-air-blade', 'honda-click', 'yamaha-mio-classico', 'yamaha-mio-ultimo', 'xe-ga-50cc' ), 73 => array( 'xe-may-dien', 'xe-dap-dien', 'bang-gia-thue-xe-dien' ) );
$bad_p  = array();
$bad_c  = array();
$bad_m  = array();
foreach ( $base['products'] as $p ) {
	$prod = wc_get_product( (int) $p['ID'] );
	if ( ! $prod || 'simple' !== $prod->get_type() ) {
		$bad_m[] = $p['ID'] . ':type';
		continue;
	}
	$want_price = $prices[ $p['post_name'] ] ?? '';
	if ( (string) $prod->get_regular_price() !== $want_price || (string) $prod->get_price() !== $want_price ) {
		$bad_p[] = $p['post_name'] . ':' . $prod->get_regular_price() . '/' . $prod->get_price();
	}
	if ( ! $prod->is_in_stock() || ! $prod->get_image_id() || '' === $prod->get_short_description() || '' === $prod->get_description() ) {
		$bad_m[] = $p['post_name'] . ':stock/image/desc';
	}
	if ( '' === $want_price && $prod->is_purchasable() ) {
		$bad_m[] = $p['post_name'] . ':purchasable-without-price';
	}
	$ids  = array_map( 'intval', wp_get_post_terms( (int) $p['ID'], 'product_cat', array( 'fields' => 'ids' ) ) );
	$leaf = 0;
	foreach ( $cats as $tid => $slugs ) {
		if ( in_array( $p['post_name'], $slugs, true ) ) {
			$leaf = $tid;
		}
	}
	if ( array( $leaf ) !== $ids ) {
		$bad_c[] = $p['post_name'] . ':' . implode( '+', $ids ) . '!=' . $leaf;
	}
}
nt_v_none( 'products', 'prices: 6 priced (VND, regular price), 7 with NO price', $bad_p );
nt_v_none( 'products', 'categories: only the leaf term 71/72/73 (xe-so 4, xe-ga 6, xe-dien 3)', $bad_c );
nt_v_none( 'products', 'simple, in stock, image + descriptions; unpriced ones not purchasable', $bad_m );
nt_v_eq( 'products', 'currency / decimals', 'VND / 0', get_option( 'woocommerce_currency' ) . ' / ' . get_option( 'woocommerce_price_num_decimals' ) );
nt_v_row( 'INFO', 'products', 'wc_price(150000)', '-', html_entity_decode( wp_strip_all_tags( wc_price( 150000 ) ), ENT_QUOTES, 'UTF-8' ) );
$classic = array( 'cart' => array( 9001, '[woocommerce_cart]' ), 'checkout' => array( 9002, '[woocommerce_checkout]' ), 'myaccount' => array( 9003, '[woocommerce_my_account]' ) );
$bad     = array();
foreach ( $classic as $k => $v ) {
	if ( (int) wc_get_page_id( $k ) !== $v[0] || trim( (string) get_post_field( 'post_content', $v[0] ) ) !== $v[1] ) {
		$bad[] = $k;
	}
}
nt_v_none( 'products', 'classic shortcode cart/checkout/my-account pages 9001-9003 wired into WooCommerce', $bad );
nt_v_eq( 'products', 'shop page id', 3040, wc_get_page_id( 'shop' ) );

// ============================================================================================
// options
// ============================================================================================
nt_v_eq( 'options', 'show_on_front / page_on_front / page_for_posts', 'page / 562 / 0', get_option( 'show_on_front' ) . ' / ' . get_option( 'page_on_front' ) . ' / ' . get_option( 'page_for_posts' ) );
nt_v_eq( 'options', 'WPLANG / get_locale()', 'vi / vi', get_option( 'WPLANG' ) . ' / ' . get_locale() );
nt_v_eq( 'options', 'timezone_string', 'Asia/Ho_Chi_Minh', get_option( 'timezone_string' ) );
nt_v_eq( 'options', 'blog_public', 1, get_option( 'blog_public' ) );
nt_v_eq( 'options', 'WP_ENVIRONMENT_TYPE', 'local', wp_get_environment_type() );
nt_v_eq( 'options', 'WP_DEBUG / WP_DEBUG_LOG set', 'true / true', ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'true' : 'false' ) . ' / ' . ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ? 'true' : 'false' ) );
nt_v_row( 'INFO', 'options', 'WP_DEBUG_LOG path', '-', defined( 'WP_DEBUG_LOG' ) ? WP_DEBUG_LOG : '-' );
nt_v_eq( 'options', 'default_category', 1, get_option( 'default_category' ) );
nt_v_row( 'INFO', 'options', 'active theme (stylesheet)', '-', $active );
$sims = array();
foreach ( array( 'moto-quickcall-flatsome-safe-v4/moto-quickcall-sim.php', 'motoai-chat-widget/motoai-chat-widget-sim.php' ) as $pl ) {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$sims[] = is_plugin_active( $pl ) ? 'active' : 'INACTIVE';
}
nt_v_eq( 'options', 'simulator plugins (quickcall, motoai)', 'active, active', implode( ', ', $sims ) );
nt_v_row( 'INFO', 'options', 'simulator mu-plugin featured_item registration', '-', file_exists( WPMU_PLUGIN_DIR . '/nt-sim-flatsome-registration.php' ) ? 'installed (SIM_FLATSOME=1)' : 'absent (SIM_FLATSOME=0)' );

// ============================================================================================
// blog posts
// ============================================================================================
$editor = get_user_by( 'login', 'nguyentu-editor' );
nt_v_eq( 'posts', 'author user nguyentu-editor display name', 'Nguyễn Tú', $editor ? $editor->display_name : 'missing' );
$posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) );
$bad_author = $bad_slug = $bad_words = $bad_h2 = $bad_marker = $bad_thumb = array();
$multi      = 0;
$member     = 0;
$h3         = $tab = $quo = $lst = $exc = $thumbs = 0;
$wmin       = PHP_INT_MAX;
$wmax       = 0;
$wsum       = 0;
$months     = array();
$dmin       = PHP_INT_MAX;
$dmax       = 0;
$classic_n  = 0;
foreach ( $posts as $post ) {
	if ( ! $editor || (int) $post->post_author !== (int) $editor->ID ) {
		$bad_author[] = $post->ID;
	}
	if ( ! preg_match( '/^[a-z0-9-]+$/', $post->post_name ) ) {
		$bad_slug[] = $post->ID;
	}
	$words = count( preg_split( '/\s+/u', trim( wp_strip_all_tags( $post->post_content ) ) ) );
	$wmin  = min( $wmin, $words );
	$wmax  = max( $wmax, $words );
	$wsum += $words;
	if ( $words < 600 || $words > 1500 ) {
		$bad_words[] = $post->ID . ':' . $words;
	}
	$n_h2 = preg_match_all( '/<h2[\s>]/', $post->post_content );
	if ( $n_h2 < 3 || $n_h2 > 5 ) {
		$bad_h2[] = $post->ID . ':' . $n_h2;
	}
	$h3  += preg_match( '/<h3[\s>]/', $post->post_content ) ? 1 : 0;
	$tab += false !== strpos( $post->post_content, '<table' ) ? 1 : 0;
	$quo += false !== strpos( $post->post_content, '<blockquote' ) ? 1 : 0;
	$lst += false !== strpos( $post->post_content, '<ul' ) ? 1 : 0;
	$exc += '' !== trim( $post->post_excerpt ) ? 1 : 0;
	$classic_n += false === strpos( $post->post_content, '<!-- wp:' ) ? 1 : 0;
	if ( '1' !== get_post_meta( $post->ID, '_nt_sandbox_synthetic', true ) || false === strpos( $post->post_content, 'Nội dung mô phỏng' ) ) {
		$bad_marker[] = $post->ID;
	}
	$cn      = count( wp_get_post_categories( $post->ID ) );
	$member += $cn;
	$multi  += $cn > 1 ? 1 : 0;
	$tid     = (int) get_post_thumbnail_id( $post->ID );
	if ( $tid ) {
		++$thumbs;
		$meta = wp_get_attachment_metadata( $tid );
		$file = get_attached_file( $tid );
		if ( ! $file || ! file_exists( $file ) || empty( $meta['sizes'] ) || 1600 !== (int) ( $meta['width'] ?? 0 ) || 900 !== (int) ( $meta['height'] ?? 0 ) ) {
			$bad_thumb[] = $post->ID . '->' . $tid;
		}
	}
	$ts       = strtotime( $post->post_date_gmt . ' UTC' );
	$months[ gmdate( 'Y-m', $ts ) ] = 1;
	$dmin     = min( $dmin, $ts );
	$dmax     = max( $dmax, $ts );
}
nt_v_none( 'posts', 'all 160 authored by nguyentu-editor', $bad_author );
nt_v_none( 'posts', 'slugs are ASCII (no diacritics)', $bad_slug );
nt_v_row( $bad_words ? 'FAIL' : 'PASS', 'posts', 'word count 600-1500 per post', '600..1500', sprintf( 'min %d / avg %d / max %d%s', $wmin, $wsum / max( 1, count( $posts ) ), $wmax, $bad_words ? ' bad: ' . implode( ',', array_slice( $bad_words, 0, 5 ) ) : '' ) );
nt_v_none( 'posts', '3-5 H2 sections per post', $bad_h2 );
nt_v_row( $h3 >= 30 ? 'PASS' : 'FAIL', 'posts', 'posts with H3 / table / quote / list (some, some, some, all)', 'h3>=30, table>=20, quote>=20, list=160', sprintf( 'h3 %d, table %d, quote %d, list %d', $h3, $tab, $quo, $lst ) );
nt_v_row( ( $tab >= 20 && $quo >= 20 && $lst === count( $posts ) ) ? 'PASS' : 'FAIL', 'posts', 'structure variety present', 'table>=20 quote>=20 list=all', sprintf( 'table %d, quote %d, list %d', $tab, $quo, $lst ) );
nt_v_row( $exc >= 30 ? 'PASS' : 'FAIL', 'posts', 'posts with a manual excerpt', '>= 30', $exc );
nt_v_row( ( $classic_n >= 30 && $classic_n <= 130 ) ? 'PASS' : 'FAIL', 'posts', 'markup mix: classic HTML vs block markup', 'both present', sprintf( 'classic %d / blocks %d', $classic_n, count( $posts ) - $classic_n ) );
nt_v_none( 'posts', 'synthetic marker (meta _nt_sandbox_synthetic + visible notice)', $bad_marker );
nt_v_eq( 'posts', 'category memberships over 160 posts', 161, $member );
nt_v_eq( 'posts', 'posts that carry two categories', 1, $multi );
nt_v_eq( 'posts', 'posts with a featured image', 40, $thumbs );
nt_v_none( 'posts', 'post images: file on disk, 1600x900, sizes generated', $bad_thumb );
$now = time();
nt_v_row( ( $dmin >= $now - 366 * 86400 && $dmax <= $now && count( $months ) >= 12 ) ? 'PASS' : 'FAIL', 'posts', 'dates spread over the last 12 months', '>=12 months, none in the future', sprintf( '%d months, %s .. %s', count( $months ), gmdate( 'Y-m-d', $dmin ), gmdate( 'Y-m-d', $dmax ) ) );
$prod_thumbs = nt_v_count( "SELECT COUNT(*) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE p.post_type='product' AND pm.meta_key='_thumbnail_id' AND pm.meta_value<>''" );
nt_v_eq( 'posts', 'products with a featured image', 13, $prod_thumbs );
$hero = get_post( 9100 );
nt_v_row( ( $hero && 'attachment' === $hero->post_type && file_exists( (string) get_attached_file( 9100 ) ) ) ? 'PASS' : 'FAIL', 'posts', 'hero attachment 9100 (1600x900)', 'exists', $hero ? 'exists' : 'missing' );

// ============================================================================================
// page fixtures (tests/fixtures/pages/<ID>.html) inserted verbatim, tokens replaced
// ============================================================================================
$hero_id = 9100;
$by_slug = array();
foreach ( $base['products'] as $p ) {
	$by_slug[ $p['post_name'] ] = (int) get_post_meta( (int) $p['ID'], '_thumbnail_id', true );
}
$checked = array();
$bad     = array();
foreach ( $page_ids as $pid ) {
	$file = rtrim( $fixtures, '/' ) . '/' . $pid . '.html';
	if ( ! is_readable( $file ) ) {
		continue;
	}
	$want = (string) file_get_contents( $file );
	$want = str_replace( '{{HERO_ID}}', (string) $hero_id, $want );
	$want = preg_replace_callback(
		'/\{\{PRODUCT_IMG:([^}\s]*)\}\}/',
		static function ( $m ) use ( $by_slug ) {
			return isset( $by_slug[ $m[1] ] ) ? (string) $by_slug[ $m[1] ] : $m[0];
		},
		$want
	);
	$have = (string) get_post_field( 'post_content', $pid, 'raw' );
	$checked[] = $pid;
	if ( $have !== $want ) {
		$bad[] = $pid . ':content differs';
	} elseif ( preg_match( '/\{\{[A-Z][A-Z0-9_]*(?::[^}\n]*)?\}\}/', $have ) ) {
		$bad[] = $pid . ':token left';
	}
}
nt_v_none( 'fixtures', sprintf( 'page fixtures byte-identical after token replacement (%s)', $checked ? implode( ',', $checked ) : 'none present' ), $bad );
if ( $checked ) {
	$raw = 0;
	foreach ( $checked as $pid ) {
		$raw += preg_match_all( '/\[(ux_|row|col|section|button|title|text_box)[\s\]]/', (string) get_post_field( 'post_content', $pid, 'raw' ) );
	}
	nt_v_row( $raw > 0 ? 'PASS' : 'INFO', 'fixtures', 'legacy shortcodes preserved in post_content (not rendered by a default theme)', '> 0', $raw );
}

// ============================================================================================
// Yoast SEO
// ============================================================================================
$ind = $wpdb->prefix . 'yoast_indexable';
$cnt = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $ind" ); // phpcs:ignore
nt_v_row( $cnt >= 200 ? 'PASS' : 'FAIL', 'yoast', 'indexables built (posts, pages, products, terms ...)', '>= 200', $cnt );
$stale = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $ind WHERE object_type='post' AND object_id=%d AND permalink NOT LIKE %s", 410, '%/san-pham/honda-wave/' ) ); // phpcs:ignore
nt_v_eq( 'yoast', 'indexable permalink for product 410 is /san-pham/honda-wave/', 0, $stale );
$stale_term = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $ind WHERE object_type='term' AND object_id=%d AND permalink NOT LIKE %s", 166, '%/blog/xe-dien/xe-dap-dien/' ) ); // phpcs:ignore
nt_v_eq( 'yoast', 'indexable permalink for category 166 is /blog/xe-dien/xe-dap-dien/', 0, $stale_term );
nt_v_eq( 'yoast', 'migrations status', 'free ' . WPSEO_VERSION, 'free ' . ( get_option( 'wpseo' )['version'] ?? '?' ) );
