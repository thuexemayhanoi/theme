<?php
/**
 * Runs the theme's own nt_html_toc_placement() on bodies read from a JSON file, for tools/qa/toc-live.mjs.
 * Only the pure libraries are loaded; no WordPress, database or network.
 *
 *   php tools/qa/toc-placement-cli.php bodies.json > placements.json
 *
 * bodies.json maps a key (a URL) to the inner HTML of a post's text. For each key the output holds the label and byte
 * offset of the placement, and the body with a probe element inserted at that offset (nothing inserted for
 * "before-content"), so the caller can check the result with an independent HTML parser.
 *
 * @package NguyenTu
 */

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require ABSPATH . 'inc/lib/text.php';
require ABSPATH . 'inc/lib/html.php';

if ( ! isset( $argv[1] ) || ! is_file( $argv[1] ) ) {
	fwrite( STDERR, "usage: php tools/qa/toc-placement-cli.php bodies.json\n" );
	exit( 2 );
}
$bodies = json_decode( (string) file_get_contents( $argv[1] ), true, 512, JSON_THROW_ON_ERROR );
$probe  = '<details id="nt-toc-probe"></details>';
$out    = array();
foreach ( $bodies as $key => $html ) {
	$place       = nt_html_toc_placement( (string) $html );
	$out[ $key ] = array(
		'placement' => $place['placement'],
		'offset'    => $place['offset'],
		'html'      => 'before-content' === $place['placement'] ? (string) $html : nt_html_insert_at( (string) $html, $place['offset'], $probe ),
	);
}
echo json_encode( $out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ), "\n";
