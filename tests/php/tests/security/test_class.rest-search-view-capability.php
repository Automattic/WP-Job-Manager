<?php
/**
 * Regression tests for the View Job Capability gate on the generic WordPress REST
 * Search endpoint (`/wp/v2/search`).
 *
 * Covers the configuration where anonymous users may browse listing indexes
 * (`job_manager_browse_job_listings_capability` empty) but must satisfy a
 * capability to view individual listing details
 * (`job_manager_view_job_listing_capability` set). The generic search controller
 * must not surface `job_listing` results — id, title, url — that the single-item
 * route and feeds already withhold from a denied viewer.
 *
 * @package wp-job-manager/tests
 */
class Tests_REST_Search_View_Capability extends WPJM_REST_TestCase {

	public function setUp(): void {
		parent::setUp();
		// Browsing is open to everyone; viewing details requires the `read` capability,
		// which anonymous users do not have.
		delete_option( 'job_manager_browse_job_listings_capability' );
		update_option( 'job_manager_view_job_listing_capability', [ 'read' ] );
	}

	public function tearDown(): void {
		delete_option( 'job_manager_view_job_listing_capability' );
		parent::tearDown();
	}

	/**
	 * Returns the IDs the REST search endpoint surfaced for the given args.
	 */
	private function search_ids( $args ) {
		$response = $this->get( '/wp/v2/search', $args );

		return wp_list_pluck( (array) $response->get_data(), 'id' );
	}

	/**
	 * A view-restricted (non-password) listing must not appear in search for an
	 * anonymous viewer.
	 *
	 * @covers WP_Job_Manager_REST_API::gate_search_query_for_listings
	 */
	public function test_search_excludes_restricted_listing_for_anonymous() {
		$post_id = $this->factory->job_listing->create(
			[ 'post_title' => 'SEARCHTOKEN restricted listing' ]
		);
		$this->logout();

		$this->assertNotContains(
			$post_id,
			$this->search_ids( [ 'subtype' => 'job_listing', 'search' => 'SEARCHTOKEN' ] ),
			'A view-restricted listing must not appear in REST search for a denied viewer.'
		);
	}

	/**
	 * Positive control: a viewer who satisfies the view capability still finds the
	 * listing, so the gate does not over-block.
	 *
	 * @covers WP_Job_Manager_REST_API::gate_search_query_for_listings
	 */
	public function test_search_includes_listing_for_capable_viewer() {
		$post_id = $this->factory->job_listing->create(
			[ 'post_title' => 'SEARCHTOKEN visible listing' ]
		);
		$this->login_as_admin();

		$this->assertContains(
			$post_id,
			$this->search_ids( [ 'subtype' => 'job_listing', 'search' => 'SEARCHTOKEN' ] ),
			'A capable viewer must still find the listing in REST search.'
		);
	}

	/**
	 * A mixed-subtype search by a denied viewer must still return other post types;
	 * only the listing is withheld.
	 *
	 * @covers WP_Job_Manager_REST_API::gate_search_query_for_listings
	 */
	public function test_search_preserves_other_post_types_for_denied_viewer() {
		$listing_id = $this->factory->job_listing->create(
			[ 'post_title' => 'SEARCHTOKEN restricted listing' ]
		);
		$post_id = $this->factory->post->create(
			[ 'post_title' => 'SEARCHTOKEN ordinary post', 'post_status' => 'publish' ]
		);
		$this->logout();

		$ids = $this->search_ids( [ 'subtype' => [ 'post', 'job_listing' ], 'search' => 'SEARCHTOKEN' ] );

		$this->assertContains( $post_id, $ids, 'An ordinary post must still be searchable for a denied viewer.' );
		$this->assertNotContains( $listing_id, $ids, 'The restricted listing must be withheld from the mixed search.' );
	}

	/**
	 * When browsing itself is gated, a denied viewer gets no listings from search.
	 *
	 * @covers WP_Job_Manager_REST_API::gate_search_query_for_listings
	 */
	public function test_search_excludes_listing_when_browse_denied() {
		update_option( 'job_manager_browse_job_listings_capability', [ 'read' ] );
		$post_id = $this->factory->job_listing->create(
			[ 'post_title' => 'SEARCHTOKEN restricted listing' ]
		);
		$this->logout();

		$this->assertNotContains(
			$post_id,
			$this->search_ids( [ 'subtype' => 'job_listing', 'search' => 'SEARCHTOKEN' ] ),
			'A browse-denied viewer must not enumerate listings via REST search.'
		);

		delete_option( 'job_manager_browse_job_listings_capability' );
	}
}
