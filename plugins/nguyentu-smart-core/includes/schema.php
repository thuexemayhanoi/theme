<?php
/**
 * Structured data ownership: never create a second entity graph beside Yoast.
 *
 * On the native Nguyễn Tú theme, inc/schema.php is the existing Yoast integration.
 * This plugin adds ONLY a standalone-theme fallback after a theme swap, or a
 * conservative full graph when no known SEO plugin outputs structured data.
 *
 * @package NguyenTuSmartCore
 */

defined( 'ABSPATH' ) || exit;

final class NTSC_Schema {

	/**
	 * Register hooks after plugins have loaded. WordPress loads themes later;
	 * the callbacks check active theme functions when they actually run.
	 */
	public static function register(): void {
		add_filter( 'wpseo_schema_organization', array( __CLASS__, 'enrich_yoast_organization' ), 30 );
		add_action( 'wp_head', array( __CLASS__, 'print_fallback' ), 30 );
	}

	/**
	 * Whether a known SEO plugin owns document metadata and schema.
	 */
	public static function seo_owner(): string {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'Yoast SEO';
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return 'Rank Math';
		}
		if ( defined( 'AIOSEO_VERSION' ) ) {
			return 'AIOSEO';
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			return 'SEOPress';
		}
		if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
			return 'The SEO Framework';
		}
		if ( function_exists( 'nt_seo_plugin_active' ) && nt_seo_plugin_active() ) {
			return 'Theme-detected SEO plugin';
		}
		return '';
	}

	/**
	 * Build only verified, relevant LocalBusiness facts.
	 *
	 * @return array<string,mixed>
	 */
	public static function local_properties(): array {
		$b = ntsc_business();

		$address = array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => trim( (string) ( $b['street'] ?? '' ) . ', ' . (string) ( $b['ward'] ?? '' ), ', ' ),
			'addressLocality' => (string) ( $b['district'] ?? '' ),
			'addressRegion'   => (string) ( $b['city'] ?? '' ),
			'addressCountry'  => (string) ( $b['country_code'] ?? '' ),
		);

		return array(
			'telephone'                 => (string) ( $b['phone_intl'] ?? '' ),
			'address'                   => $address,
			'hasMap'                    => esc_url_raw( (string) ( $b['maps_url'] ?? '' ) ),
			'openingHoursSpecification' => array(
				array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ),
					'opens'     => (string) ( $b['open'] ?? '' ),
					'closes'    => (string) ( $b['close'] ?? '' ),
				),
			),
		);
	}

	/**
	 * Yoast fallback only when the custom theme does not already enrich its graph.
	 *
	 * @param mixed $data Yoast's existing Organization data.
	 * @return mixed
	 */
	public static function enrich_yoast_organization( $data ) {
		if ( ! is_array( $data ) || ! ntsc_site_allowed() ) {
			return $data;
		}
		if ( function_exists( 'nt_yoast_enrich_organization' ) || defined( 'WPSEO_LOCAL_VERSION' ) ) {
			return $data;
		}
		$b = ntsc_business();
		if ( '' === trim( (string) ( $b['brand'] ?? '' ) ) || '' === trim( (string) ( $b['phone_intl'] ?? '' ) ) ) {
			return $data;
		}

		foreach ( self::local_properties() as $key => $value ) {
			if ( ! isset( $data[ $key ] ) || '' === $data[ $key ] || array() === $data[ $key ] ) {
				$data[ $key ] = $value;
			}
		}

		$types = (array) ( $data['@type'] ?? 'Organization' );
		$types = array_values( array_unique( array_merge( $types, array( 'Organization', 'LocalBusiness' ) ) ) );
		$data['@type'] = $types;
		return $data;
	}

	/**
	 * Do not print competing schema on pages already managed by Yoast/other SEO.
	 * Only public, indexable-style frontend page types can receive this fallback.
	 */
	public static function print_fallback(): void {
		if ( ! ntsc_site_allowed() || '' !== self::seo_owner() || is_admin() || is_feed() || is_404() || is_search() ) {
			return;
		}
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || '' !== $post->post_password ) {
				return;
			}
		}
		if ( ! apply_filters( 'ntsc_schema_fallback_enabled', true ) ) {
			return;
		}

		$b = ntsc_business();
		if ( '' === trim( (string) ( $b['brand'] ?? '' ) ) || '' === trim( (string) ( $b['phone_intl'] ?? '' ) ) || '' === trim( (string) ( $b['street'] ?? '' ) ) ) {
			return;
		}

		$site_url = 'https://thuexemaynguyentu.com/';
		$org      = array(
			'@type' => array( 'Organization', 'LocalBusiness' ),
			'@id'   => $site_url . '#organization',
			'name'  => sanitize_text_field( (string) $b['brand'] ),
			'url'   => $site_url,
		);
		$org = array_merge( $org, self::local_properties() );
		if ( ! empty( $b['facebook_url'] ) ) {
			$org['sameAs'] = array( esc_url_raw( (string) $b['facebook_url'] ) );
		}

		$graph = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				$org,
				array(
					'@type'     => 'WebSite',
					'@id'       => $site_url . '#website',
					'url'       => $site_url,
					'name'      => sanitize_text_field( (string) get_bloginfo( 'name' ) ),
					'publisher' => array( '@id' => $site_url . '#organization' ),
					'inLanguage' => get_bloginfo( 'language' ),
				),
			),
		);

		$json = wp_json_encode( $graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		if ( false !== $json ) {
			echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON is hex-escaped.
		}
	}
}
