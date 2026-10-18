<?php
/**
 * Integration bootstrap: loads the disposable WordPress sandbox (never production).
 *
 *   NT_WP_ROOT   path of the sandbox WordPress (default: $SANDBOX_DIR/wp)
 *   NT_SITE_URL  site URL (default http://127.0.0.1:8088)
 *
 * It refuses to run unless WP_ENVIRONMENT_TYPE is "local" or the host is a loopback address, so
 * the suite can never be pointed at a real site by accident.
 *
 * @package NguyenTu
 */

declare(strict_types=1);

$nt_root = getenv( 'NT_WP_ROOT' );
if ( ! $nt_root ) {
	$sandbox = getenv( 'SANDBOX_DIR' ) ?: ( rtrim( (string) ( getenv( 'TMPDIR' ) ?: '/tmp' ), '/' ) . '/nguyentu-sandbox' );
	$nt_root = $sandbox . '/wp';
}
if ( ! is_file( $nt_root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Sandbox WordPress not found at {$nt_root}. Run tools/sandbox/setup.sh and seed.sh first.\n" );
	exit( 2 );
}

$nt_url  = getenv( 'NT_SITE_URL' ) ?: 'http://127.0.0.1:8088';
$nt_host = (string) parse_url( $nt_url, PHP_URL_HOST );
if ( ! in_array( $nt_host, array( '127.0.0.1', 'localhost' ), true ) ) {
	fwrite( STDERR, "Refusing to run integration tests against non-loopback host {$nt_host}.\n" );
	exit( 2 );
}

$_SERVER['HTTP_HOST']       = (string) parse_url( $nt_url, PHP_URL_HOST ) . ( parse_url( $nt_url, PHP_URL_PORT ) ? ':' . parse_url( $nt_url, PHP_URL_PORT ) : '' );
$_SERVER['SERVER_NAME']     = $nt_host;
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';

define( 'WP_USE_THEMES', false );
require_once $nt_root . '/wp-load.php';

if ( 'local' !== wp_get_environment_type() ) {
	fwrite( STDERR, "Refusing to run: WP_ENVIRONMENT_TYPE is not 'local'.\n" );
	exit( 2 );
}

require_once __DIR__ . '/support.php';
