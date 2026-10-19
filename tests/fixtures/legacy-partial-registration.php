<?php
// Disposable sandbox only. Missing one registration must not suppress recovery of the other.
if ( ! defined( 'ABSPATH' ) || 'local' !== wp_get_environment_type() ) {
	exit( 2 );
}
$nt_existing = get_post_type_object( 'featured_item' );
unregister_taxonomy( 'featured_item_category' );
NT_Compat_Registry::replay();
if ( ! taxonomy_exists( 'featured_item_category' ) || get_post_type_object( 'featured_item' ) !== $nt_existing ) {
	WP_CLI::error( 'Partial taxonomy recovery failed or overwrote the existing post type.' );
}
$nt_existing = get_taxonomy( 'featured_item_category' );
unregister_post_type( 'featured_item' );
NT_Compat_Registry::replay();
if ( ! post_type_exists( 'featured_item' ) || get_taxonomy( 'featured_item_category' ) !== $nt_existing ) {
	WP_CLI::error( 'Partial post-type recovery failed or overwrote the existing taxonomy.' );
}
WP_CLI::success( 'Both partial registration cases recover without replacing the other owner.' );
