<?php
/**
 * Regression tests for the View Job Capability gate on the oEmbed response data
 * (`/wp-json/oembed/1.0/embed?url=...`).
 *
 * The oEmbed endpoint returns a published listing's title and author identity as
 * machine-readable data without reaching the browse gate, single-listing view, or
 * single-item REST route. For a viewer denied by browse or View Job Capability, it
 * must fail closed, matching the single-item REST route, which returns 404.
 *
 * Unlike core search, oEmbed is a single-item endpoint, so the per-listing check
 * (which lets an author reach their own listing) remains part of the boundary.
 *
 * @package wp-job-manager/tests
 */
class Tests_oEmbed_View_Capability extends WPJM_REST_TestCase {

	public function setUp(): void {
		parent::setUp();
		delete_option( 'job_manager_browse_job_listings_capability' );
		update_option( 'job_manager_view_job_listing_capability', [ 'read' ] );
	}

	public function tearDown(): void {
		delete_option( 'job_manager_view_job_listing_capability' );
		delete_option( 'job_manager_browse_job_listings_capability' );
		parent::tearDown();
	}

	private function get_oembed_endpoint_response( $post_id ) {
		return $this->get(
			'/oembed/1.0/embed',
			[ 'url' => home_url( '/?post_type=job_listing&p=' . $post_id ) ]
		);
	}

	/**
	 * A view-restricted listing must produce no oEmbed data for an anonymous viewer.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_oembed_response_for_listings
	 */
	public function test_oembed_excludes_restricted_listing_for_anonymous() {
		$post_id = $this->factory->job_listing->create();
		$this->logout();

		$this->assertEmpty(
			get_oembed_response_data( get_post( $post_id ), 600 ),
			'A view-restricted listing must not expose oEmbed data to a denied viewer.'
		);
	}

	/**
	 * A browse-restricted listing must produce no oEmbed data even when viewing is
	 * otherwise unrestricted.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_oembed_response_for_listings
	 */
	public function test_oembed_excludes_listing_when_browse_denied() {
		delete_option( 'job_manager_view_job_listing_capability' );
		update_option( 'job_manager_browse_job_listings_capability', [ 'read' ] );
		$post_id = $this->factory->job_listing->create();
		$this->logout();

		$this->assertEmpty(
			get_oembed_response_data( get_post( $post_id ), 600 ),
			'A browse-restricted listing must not expose oEmbed data to a denied viewer.'
		);
	}

	/**
	 * The public oEmbed REST endpoint must fail closed for a restricted listing.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_oembed_response_for_listings
	 */
	public function test_oembed_endpoint_returns_not_found_for_restricted_listing() {
		$post_id = $this->factory->job_listing->create(
			[ 'post_title' => 'OEMBED_RESTRICTED_LISTING_TITLE' ]
		);
		$this->logout();

		$response = $this->get_oembed_endpoint_response( $post_id );
		$this->assertResponseStatus( $response, 404 );
		$this->assertStringNotContainsString(
			'OEMBED_RESTRICTED_LISTING_TITLE',
			wp_json_encode( $response->get_data() ),
			'The oEmbed REST endpoint must not serialize restricted listing metadata.'
		);
	}

	/**
	 * Positive control: a capable viewer still gets oEmbed data, so the gate does not over-block.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_oembed_response_for_listings
	 */
	public function test_oembed_includes_listing_for_capable_viewer() {
		$post_id = $this->factory->job_listing->create();
		$this->login_as_admin();

		$data = get_oembed_response_data( get_post( $post_id ), 600 );
		$this->assertNotEmpty( $data, 'A capable viewer must still receive oEmbed data.' );
	}

	/**
	 * Positive control for the public oEmbed REST endpoint.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_oembed_response_for_listings
	 */
	public function test_oembed_endpoint_includes_listing_for_capable_viewer() {
		$post_id = $this->factory->job_listing->create(
			[ 'post_title' => 'OEMBED_VISIBLE_LISTING_TITLE' ]
		);
		$this->login_as_admin();

		$response = $this->get_oembed_endpoint_response( $post_id );
		$this->assertResponseStatus( $response, 200 );
		$this->assertStringContainsString(
			'OEMBED_VISIBLE_LISTING_TITLE',
			wp_json_encode( $response->get_data() ),
			'A capable viewer must still receive listing metadata from the oEmbed REST endpoint.'
		);
	}

	/**
	 * The per-listing check lets a denied author reach their own listing's oEmbed data,
	 * matching the single-listing view and single-item REST route.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_oembed_response_for_listings
	 */
	public function test_oembed_includes_own_listing_for_denied_author() {
		update_option( 'job_manager_view_job_listing_capability', [ 'manage_options' ] );
		$author_id = $this->factory->user->create( [ 'role' => 'employer' ] );
		$post_id   = $this->factory->job_listing->create( [ 'post_author' => $author_id ] );
		wp_set_current_user( $author_id );

		$this->assertNotEmpty(
			get_oembed_response_data( get_post( $post_id ), 600 ),
			'An author must still reach their own listing oEmbed data.'
		);
	}

	/**
	 * When listings are unrestricted (the default), oEmbed is left untouched.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_oembed_response_for_listings
	 */
	public function test_oembed_includes_listing_when_unrestricted() {
		delete_option( 'job_manager_view_job_listing_capability' );
		$post_id = $this->factory->job_listing->create();
		$this->logout();

		$this->assertNotEmpty(
			get_oembed_response_data( get_post( $post_id ), 600 ),
			'With no view capability configured, listing oEmbed remains available.'
		);
	}

	/**
	 * The gate must not affect oEmbed for ordinary post types.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_oembed_response_for_listings
	 */
	public function test_oembed_unaffected_for_ordinary_post() {
		$post_id = $this->factory->post->create( [ 'post_status' => 'publish' ] );
		$this->logout();

		$this->assertNotEmpty(
			get_oembed_response_data( get_post( $post_id ), 600 ),
			'An ordinary post must still expose oEmbed data regardless of the listing view capability.'
		);
	}
}
