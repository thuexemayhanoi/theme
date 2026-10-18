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
	$props  = array(
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
	$closed = (array) nt_option( 'hours.closed_days', array() );
	$days   = array(
		1 => 'Monday',
		2 => 'Tuesday',
		3 => 'Wednesday',
		4 => 'Thursday',
		5 => 'Friday',
		6 => 'Saturday',
		7 => 'Sunday',
	);
	$props['openingHoursSpecification'][0]['dayOfWeek'] = array_values( array_diff_key( $days, array_flip( $closed ) ) );
	if ( 7 === count( $closed ) ) {
		$props['openingHoursSpecification'] = array(); }
	foreach ( (array) nt_option( 'hours.exceptions', array() ) as $date => $hours ) {
		$times = 'closed' === $hours ? array( '00:00', '00:00' ) : explode( '-', $hours );
		if ( 2 === count( $times ) ) {
			$props['specialOpeningHoursSpecification'][] = array(
				'@type'        => 'OpeningHoursSpecification',
				'validFrom'    => $date,
				'validThrough' => $date,
				'opens'        => $times[0],
				'closes'       => $times[1],
			);
		}
	}
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
 * Keep one verified business inside Yoast's graph when its site representation is a Person.
 * Existing Organization/LocalBusiness nodes retain ownership; no second graph is printed.
 *
 * @param array $graph Yoast graph nodes.
 * @return array
 */
function nt_yoast_business_graph( array $graph ): array {
	if ( ! nt_local_business_enabled() ) {
		return $graph;
	}
	foreach ( $graph as $node ) {
		if ( array_intersect( (array) ( $node['@type'] ?? array() ), array( 'Organization', 'LocalBusiness', 'MotorcycleDealer' ) ) ) {
			return $graph;
		}
	}
	$graph[] = array_merge(
		array(
			'@type' => 'LocalBusiness',
			'@id'   => home_url( '/#localbusiness' ),
			'name'  => nt_business( 'gbp_name' ),
			'url'   => home_url( '/' ),
		),
		nt_local_business_properties()
	);
	return $graph;
}
add_filter( 'wpseo_schema_graph', 'nt_yoast_business_graph', 30 );

/**
 * Suppress only the identified legacy WPCode graph during rendering, preserving its stored
 * value for rollback and every unrelated script. Yoast now owns the verified business node.
 *
 * @param mixed $header Stored WPCode header string.
 * @return mixed
 */
function nt_deduplicate_legacy_header( $header ) {
	if ( ! is_string( $header ) || 'yoast' !== nt_seo_plugin() || ! nt_local_business_enabled() ) {
		return $header;
	}
	return preg_replace_callback(
		'#<script\b[^>]*>(.*?)</script>#is',
		static function ( array $match ): string {
			$data = json_decode( $match[1], true );
			if ( ! is_array( $data ) ) {
				$data = json_decode( wp_unslash( $match[1] ), true );
			}
			foreach ( (array) ( $data['@graph'] ?? array() ) as $node ) {
				if ( home_url( '/#nguyen-tu-website' ) === ( $node['@id'] ?? '' ) ) {
					return '';
				}
			}
			return $match[0];
		},
		$header
	) ?? $header;
}
add_filter( 'option_ihaf_insert_header', 'nt_deduplicate_legacy_header', 30 );
