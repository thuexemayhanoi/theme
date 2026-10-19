<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Real pre-switch Yoast URLs, including the comma-separated term segment. */
final class LegacyUrlRecoveryTest extends TestCase {

	public function test_all_19_audited_objects_generate_the_pre_switch_urls(): void {
		$evidence = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/tests/fixtures/legacy-public-urls.json' ), true );
		$seen = 0;
		foreach ( $evidence['yoast'] as $row ) {
			if ( ! in_array( $row['object_type'], array( 'post', 'term' ), true ) ) {
				continue;
			}
			$url = 'post' === $row['object_type'] ? get_permalink( (int) $row['object_id'] ) : get_term_link( (int) $row['object_id'], 'featured_item_category' );
			$this->assertSame( wp_parse_url( $row['permalink'], PHP_URL_PATH ), wp_parse_url( (string) $url, PHP_URL_PATH ), 'object ' . $row['object_id'] );
			++$seen;
		}
		$this->assertSame( 19, $seen );
	}
}
