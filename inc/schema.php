<?php
/**
 * Structured data: Yoast owns the @graph. The theme only fills in verified LocalBusiness
 * properties that are MISSING from Yoast's own Organization node, through Yoast's documented
 * filter, so there is never a second competing entity.
 *
 * It never invents ratings, review counts, price ranges, geo coordinates, offers or FAQ markup.
 *
 * @package NguyenTu
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the LocalBusiness enrichment is enabled (default on, owner can switch it off).
 */
function nt_local_business_enabled(): bool {
	$stored = nt_option( 'schema.local_business', '1' );
	return (bool) apply_filters( 'nguyentu_local_business_schema', '0' !== (string) $stored );
}

/**
 * Verified LocalBusiness properties built from the business data.
 *
 * @return array<string,mixed>
 */
function nt_local_business_properties(): array {
	$props = array(
		'telephone'                 => nt_business( 'phone_intl' ),
		'address'                   => array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => nt_business( 'street' ) . ', ' . nt_business( 'ward' ),
			'addressLocality' => nt_business( 'district' ),
			'addressRegion'   => nt_business( 'city' ),
			'addressCountry'  => nt_business( 'country_code' ),
		),
		'hasMap'                    => nt_maps_url(),
		'openingHoursSpecification' => array(
			array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ),
				'opens'     => nt_business( 'open' ),
				'closes'    => nt_business( 'close' ),
			),
		),
	);
	return (array) apply_filters( 'nguyentu_local_business_properties', $props );
}

/**
 * Merge verified LocalBusiness data into Yoast's Organization node.
 *
 * Existing values are never overwritten; the node keeps its @id and gains the LocalBusiness
 * type, so entity count stays exactly one.
 *
 * @param mixed $data Yoast Organization schema piece.
 * @return mixed
 */
function nt_yoast_enrich_organization( $data ) {
	if ( ! is_array( $data ) || ! nt_local_business_enabled() ) {
		return $data;
	}
	foreach ( nt_local_business_properties() as $key => $value ) {
		if ( ! isset( $data[ $key ] ) || '' === $data[ $key ] || array() === $data[ $key ] ) {
			$data[ $key ] = $value;
		}
	}
	$types = array_values( array_unique( array_merge( (array) ( $data['@type'] ?? 'Organization' ), array( 'LocalBusiness' ) ) ) );
	if ( ! in_array( 'Organization', $types, true ) ) {
		array_unshift( $types, 'Organization' );
	}
	$data['@type'] = $types;
	return $data;
}
add_filter( 'wpseo_schema_organization', 'nt_yoast_enrich_organization', 20 );

/**
 * The static service homepage is a WebPage, not the legacy editor's Article.
 *
 * Keep Yoast's other graph pieces and every other route unchanged. This filter
 * deliberately matches the audited homepage ID rather than all WordPress pages.
 *
 * @param bool $needed Whether Yoast needs the Article piece.
 * @return bool
 */
function nt_yoast_home_article_needed( bool $needed ): bool {
	if ( is_front_page() && is_page( 562 ) ) {
		return false;
	}
	return $needed;
}
add_filter( 'wpseo_schema_needs_article', 'nt_yoast_home_article_needed', 20 );
