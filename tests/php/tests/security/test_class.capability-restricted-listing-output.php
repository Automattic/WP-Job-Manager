<?php
/**
 * Regression tests covering AJAX, REST, RSS, and JSON-LD output paths when
 * `job_manager_browse_job_listings_capability` / `job_manager_view_job_listing_capability`
 * are configured: capability denial must apply to every public output path, not only the
 * visible templates.
 *
 * @package wp-job-manager/tests
 */

class Tests_Capability_Restricted_Listing_Output extends WPJM_REST_TestCase {

	public function setUp(): void {
		parent::setUp();
		// Restrict browse + view to the `read` capability — anonymous users have no caps,
		// so they must be denied across every public output path.
		update_option( 'job_manager_browse_job_listings_capability', [ 'read' ] );
		update_option( 'job_manager_view_job_listing_capability', [ 'read' ] );
	}

	public function tearDown(): void {
		delete_option( 'job_manager_browse_job_listings_capability' );
		delete_option( 'job_manager_view_job_listing_capability' );
		parent::tearDown();
	}

	/**
	 * @covers WP_Job_Manager_Ajax::get_listings
	 */
	public function test_ajax_get_listings_denies_anonymous_when_browse_cap_set() {
		$this->factory->job_listing->create_many( 2 );
		$this->logout();

		add_filter( 'wp_die_ajax_handler', [ $this, 'return_do_not_die' ] );
		ob_start();
		WP_Job_Manager_Ajax::instance()->get_listings();
		$payload = json_decode( (string) ob_get_clean(), true );
		remove_filter( 'wp_die_ajax_handler', [ $this, 'return_do_not_die' ] );

		$this->assertIsArray( $payload );
		$this->assertFalse( $payload['found_jobs'] );
		$this->assertSame( '', $payload['html'] );
	}

	/**
	 * @covers WP_Job_Manager_REST_API::exclude_filled_from_query
	 */
	public function test_rest_collection_denies_anonymous_when_browse_cap_set() {
		$this->factory->job_listing->create_many( 2 );
		$this->logout();

		$response = $this->get( '/wp/v2/job-listings' );
		$this->assertResponseStatus( $response, 200 );
		$this->assertSame( [], $response->get_data() );
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::job_feed
	 */
	public function test_rss_feed_denies_anonymous_when_browse_cap_set() {
		$this->factory->job_listing->create_many( 2 );
		$this->logout();

		$feed = $this->capture_job_feed();
		$this->assertStringNotContainsString( '<item>', $feed );
	}

	/**
	 * @covers ::wpjm_output_job_listing_structured_data
	 *
	 * The JSON-LD emitter runs from `wp_footer` and must honor the view-capability check
	 * the visible template applies.
	 */
	public function test_jsonld_suppressed_when_view_cap_denies() {
		$post_id = $this->factory->job_listing->create();
		$this->logout();

		$this->assertFalse( wpjm_output_job_listing_structured_data( $post_id ) );
	}

	/**
	 * @covers ::wpjm_output_job_listing_structured_data
	 *
	 * Sanity: when capabilities allow the viewer (admin), structured data still emits.
	 */
	public function test_jsonld_still_emits_for_capable_viewer() {
		$post_id = $this->factory->job_listing->create();
		$this->login_as_admin();

		$this->assertTrue( wpjm_output_job_listing_structured_data( $post_id ) );
	}

	/**
	 * Captures the RSS feed output for the job_feed handler.
	 *
	 * Mirrors the helper in test_class.wp-job-manager-post-types.php.
	 */
	private function capture_job_feed() {
		ob_start();
		try {
			@WP_Job_Manager_Post_Types::instance()->job_feed();
			$out = ob_get_clean();
		} catch ( Exception $e ) {
			$out = ob_get_clean();
			throw $e;
		}
		return (string) $out;
	}
}
