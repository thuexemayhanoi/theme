<?php
/**
 * Router for `php -S` that gives WordPress full pretty-permalink behaviour.
 *
 *  - existing static files            -> returned as-is (built-in server serves them with MIME type)
 *  - existing PHP files (wp-login.php, wp-admin/*.php, wp-cron.php ...) -> executed normally
 *  - existing directories (/, /wp-admin/) -> built-in server picks index.php / index.html
 *  - everything else (/blog/xe-dien/, /robots.txt, /sitemap_index.xml, /wp-json/...) -> index.php
 *
 * Sandbox tooling only. Never expose this server beyond 127.0.0.1.
 */

$docroot = rtrim( (string) $_SERVER['DOCUMENT_ROOT'], '/' );
$request = (string) parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$path    = rawurldecode( $request === '' ? '/' : $request );

// Refuse path traversal outright.
if ( strpos( $path, "\0" ) !== false || preg_match( '#(^|/)\.\.(/|$)#', $path ) ) {
	http_response_code( 400 );
	echo 'Bad request';
	return true;
}

$candidate = $docroot . $path;

if ( $path !== '/' && is_file( $candidate ) ) {
	return false; // static asset or real PHP entry point
}

if ( is_dir( $candidate ) ) {
	foreach ( array( 'index.php', 'index.html', 'index.htm' ) as $index ) {
		if ( is_file( rtrim( $candidate, '/' ) . '/' . $index ) ) {
			return false; // let the built-in server resolve the directory index
		}
	}
}

// Pretty permalink: hand over to WordPress front controller.
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $docroot . '/index.php';
chdir( $docroot );
require $docroot . '/index.php';
