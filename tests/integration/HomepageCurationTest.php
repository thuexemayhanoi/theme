<?php
/** Homepage selection must survive new publishing and hide unpublished guides. */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HomepageCurationTest extends TestCase {

	private function links( string $section ): array {
		$original_query = $GLOBALS['wp_query'];
		$original_post  = $GLOBALS['post'] ?? null;
		$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => 562 ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_query']->the_post();
		ob_start();
		try {
			get_template_part( 'template-parts/home/editorial' );
			$html = (string) ob_get_contents();
		} finally {
			ob_end_clean();
			$GLOBALS['wp_query'] = $original_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['post']     = $original_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			wp_reset_postdata();
		}
		$document = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$document->loadHTML( '<?xml encoding="UTF-8">' . $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		$xpath = new DOMXPath( $document );
		$links = array();
		foreach ( $xpath->query( '//section[@aria-labelledby="' . $section . '"]//h3/a' ) as $link ) {
			$links[] = $link->getAttribute( 'href' );
		}
		return $links;
	}

	public function test_new_posts_update_latest_without_displacing_curated_guides(): void {
		$before = $this->links( 'nt-home-featured-title' );
		$this->assertSame( array_map( 'get_permalink', array( 476, 1060, 895, 829 ) ), $before );
		$new_id = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Homepage curation test: newest article',
			)
		);
		try {
			$this->assertGreaterThan( 0, $new_id );
			$this->assertSame( $before, $this->links( 'nt-home-featured-title' ) );
			$this->assertSame( get_permalink( $new_id ), $this->links( 'nt-home-latest-title' )[0] );
			$this->assertSame( array(), array_intersect( $before, $this->links( 'nt-home-latest-title' ) ) );
		} finally {
			wp_delete_post( $new_id, true );
		}
	}

	public function test_unpublished_selected_post_is_omitted_without_random_replacement(): void {
		$original_status = get_post_status( 841 );
		wp_update_post( array( 'ID' => 841, 'post_status' => 'draft' ) );
		try {
			$this->assertSame(
				array_map( 'get_permalink', array( 847, 862, 836, 857 ) ),
				$this->links( 'nt-home-topic-areas' )
			);
		} finally {
			wp_update_post( array( 'ID' => 841, 'post_status' => $original_status ) );
		}
	}
}
