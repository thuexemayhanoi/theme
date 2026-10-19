<?php
/**
 * Preserve the effective legacy URL contract, which registration arguments alone did not capture.
 * Verified against all 19 pre-switch Yoast indexables and GSC on 2026-10-19.
 *
 * @package NguyenTuLegacyCompat
 */

defined( 'ABSPATH' ) || exit;

/** URL generation, routing and object-specific alias redirects; independent of the active theme. */
final class NT_Compat_Urls {

	/** Register once. No rewrite flush is performed by frontend requests. */
	public static function hooks(): void {
		add_filter( 'post_type_link', array( __CLASS__, 'post_link' ), 20, 3 );
		add_filter( 'term_link', array( __CLASS__, 'term_link' ), 20, 3 );
		add_filter( 'rewrite_rules_array', array( __CLASS__, 'rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'request', array( __CLASS__, 'validate_group' ) );
		add_action( 'template_redirect', array( __CLASS__, 'redirect' ), 1 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'protect_404' ) );
		add_filter( 'do_redirect_guess_404_permalink', array( __CLASS__, 'allow_guess' ) );
	}

	/**
	 * Public routing variable, distinct from the actual taxonomy query.
	 *
	 * @param string[] $vars Variables.
	 * @return string[]
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = 'nt_compat_group';
		return $vars;
	}

	/** Whether this plugin owns at least one of the legacy registrations. */
	private static function active(): bool {
		return NT_Compat_Registry::replay_active();
	}

	/**
	 * Stable ordering matches every pre-switch Yoast URL.
	 *
	 * @param int $id Post ID.
	 * @return string[]
	 */
	private static function groups( int $id ): array {
		$terms = get_the_terms( $id, NT_Compat_Registry::TAX );
		if ( ! is_array( $terms ) ) {
			return array();
		}
		usort( $terms, static fn( WP_Term $a, WP_Term $b ): int => $a->term_id <=> $b->term_id );
		return array_values( wp_list_pluck( $terms, 'slug' ) );
	}

	/**
	 * Restore the legacy comma-separated category segment, not a newly invented shorter URL.
	 *
	 * @param string  $url      Generated URL.
	 * @param WP_Post $post     Post object.
	 * @param bool    $leavename Preserve the sample-permalink placeholder.
	 */
	public static function post_link( string $url, WP_Post $post, bool $leavename = false ): string {
		if ( ! self::active() || NT_Compat_Registry::CPT !== $post->post_type ) {
			return $url;
		}
		$groups = self::groups( $post->ID );
		if ( array() === $groups ) {
			return $url;
		}
		$slug = $leavename ? '%featured_item%' : $post->post_name;
		return home_url( user_trailingslashit( '/bo-suu-tap/' . implode( ',', $groups ) . '/' . $slug, 'single' ) );
	}

	/**
	 * Restore the verified taxonomy base without altering other taxonomies.
	 *
	 * @param string  $url      URL.
	 * @param WP_Term $term     Term.
	 * @param string  $taxonomy Taxonomy.
	 */
	public static function term_link( string $url, WP_Term $term, string $taxonomy ): string {
		if ( ! self::active() || NT_Compat_Registry::TAX !== $taxonomy ) {
			return $url;
		}
		return home_url( user_trailingslashit( '/bo-suu-tap/' . $term->slug, 'category' ) );
	}

	/**
	 * Prepend specific taxonomy endpoints before the two-segment single route, avoiding collisions
	 * with /feed/, /embed/ and /page/N/. Keep existing rules (including WooCommerce and tags) intact.
	 *
	 * @param array<string,string> $rules Existing WordPress rules.
	 * @return array<string,string>
	 */
	public static function rules( array $rules ): array {
		if ( ! self::active() ) {
			return $rules;
		}
		$extra = array();
		foreach ( array( 'bo-suu-tap', 'featured_item_category' ) as $base ) {
			$query = 'index.php?featured_item_category=$matches[1]';
			$extra[ $base . '/([^/]+)/feed/(feed|rdf|rss|rss2|atom)/?$' ] = $query . '&feed=$matches[2]';
			$extra[ $base . '/([^/]+)/(feed|rdf|rss|rss2|atom)/?$' ]      = $query . '&feed=$matches[2]';
			$extra[ $base . '/([^/]+)/page/([0-9]+)/?$' ]               = $query . '&paged=$matches[2]';
			$extra[ $base . '/([^/]+)/embed/?$' ]                       = $query . '&embed=true';
			$extra[ $base . '/([^/]+)/?$' ]                            = $query;
		}
		$single = 'bo-suu-tap/([^/]+)/([^/]+)';
		$query  = 'index.php?featured_item=$matches[2]&nt_compat_group=$matches[1]';
		$extra[ $single . '/feed/(feed|rdf|rss|rss2|atom)/?$' ] = $query . '&feed=$matches[3]';
		$extra[ $single . '/(feed|rdf|rss|rss2|atom)/?$' ]      = $query . '&feed=$matches[3]';
		$extra[ $single . '/embed/?$' ]                       = $query . '&embed=true';
		$extra[ $single . '/trackback/?$' ]                   = $query . '&tb=1';
		$extra[ $single . '(?:/([0-9]+))?/?$' ]               = $query . '&page=$matches[3]';
		return $extra + $rules;
	}

	/**
	 * Reject invented category segments instead of redirecting an unrelated 404 by slug guessing.
	 *
	 * @param array<string,mixed> $vars Query variables.
	 * @return array<string,mixed>
	 */
	public static function validate_group( array $vars ): array {
		if ( ! self::active() || empty( $vars['nt_compat_group'] ) || empty( $vars['featured_item'] ) ) {
			return $vars;
		}
		if ( ! is_string( $vars['nt_compat_group'] ) || ! is_string( $vars['featured_item'] ) ) {
			return array( 'error' => '404' );
		}
		$post   = get_page_by_path( (string) $vars['featured_item'], OBJECT, NT_Compat_Registry::CPT );
		$groups = $post instanceof WP_Post ? self::groups( $post->ID ) : array();
		$asked  = explode( ',', (string) $vars['nt_compat_group'] );
		if ( array() === $groups || array_diff( $asked, $groups ) ) {
			return array( 'error' => '404' );
		}
		return $vars;
	}

	/** Whether a request belongs to the narrowly scoped compatibility routes. */
	private static function managed_request(): bool {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed as a URL path, never output.
		$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		return (bool) preg_match( '#^' . preg_quote( $home, '#' ) . '(bo-suu-tap|featured_item|featured_item_category)/#', $path );
	}

	/** Target URL for a resolved public object; empty for 404s, archives, previews or unrelated data. */
	public static function redirect_target(): string {
		if ( ! self::active() || ! self::managed_request() || is_404() || is_preview() ) {
			return '';
		}
		$url = '';
		if ( is_singular( NT_Compat_Registry::CPT ) ) {
			$post = get_queried_object();
			if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || '' !== $post->post_password ) {
				return '';
			}
			$url = (string) get_permalink( $post );
			if ( (int) get_query_var( 'page' ) > 1 ) {
				$url = trailingslashit( $url ) . user_trailingslashit( (string) get_query_var( 'page' ), 'single_paged' );
			}
		} elseif ( is_tax( NT_Compat_Registry::TAX ) ) {
			$url = get_term_link( get_queried_object() );
			if ( ! is_string( $url ) ) {
				return '';
			}
			if ( (int) get_query_var( 'paged' ) > 1 ) {
				$url = trailingslashit( $url ) . user_trailingslashit( 'page/' . (int) get_query_var( 'paged' ), 'paged' );
			}
		}
		if ( '' === $url ) {
			return '';
		}
		if ( is_feed() ) {
			$feed = (string) get_query_var( 'feed' );
			$url  = trailingslashit( $url ) . user_trailingslashit( 'feed/' . ( 'feed' === $feed ? '' : $feed ), 'feed' );
		} elseif ( is_embed() ) {
			$url = trailingslashit( $url ) . 'embed/';
		} elseif ( is_trackback() ) {
			$url = trailingslashit( $url ) . 'trackback/';
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared as a path, not rendered.
		$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
		return $path === wp_parse_url( $url, PHP_URL_PATH ) ? '' : $url;
	}

	/** 301 only a resolved public legacy object. Never POST, preview, missing content or homepage. */
	public static function redirect(): void {
		if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) {
			return;
		}
		$target = self::redirect_target();
		if ( '' === $target ) {
			return;
		}
		// Preserve tracking parameters while dropping WordPress routing/preview parameters.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed and encoded by add_query_arg, not emitted directly.
		$query = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_QUERY );
		wp_parse_str( $query, $params );
		foreach ( array( 'p', 'page_id', 'name', 'post_type', 'featured_item', 'featured_item_category', 'nt_compat_group', 'preview', 'preview_id', 'preview_nonce', 'page', 'paged', 'feed', 'embed', 'tb' ) as $key ) {
			unset( $params[ $key ] );
		}
		wp_safe_redirect( add_query_arg( $params, $target ), 301, 'NguyenTu Legacy Compat' );
		exit;
	}

	/**
	 * Leave missing legacy objects as actual 404s.
	 *
	 * @param string|false $redirect Proposed core canonical redirect.
	 * @return string|false
	 */
	public static function protect_404( $redirect ) {
		return self::active() && self::managed_request() && is_404() ? false : $redirect;
	}

	/**
	 * Disable core slug guessing only within the managed legacy routes.
	 *
	 * @param bool $allow Whether core may guess a permalink for a 404.
	 */
	public static function allow_guess( bool $allow ): bool {
		return self::active() && self::managed_request() ? false : $allow;
	}
}
