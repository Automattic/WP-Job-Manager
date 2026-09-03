<?php
/**
 * Regression test: the promoted-jobs feed must honour the View Job Capability.
 *
 * GET /wpjm-internal/v1/promoted-jobs is an unauthenticated feed consumed by the Promoted
 * Jobs marketplace. Its collection query returned every published promoted listing even
 * when the `job_manager_view_job_listing_capability` option restricts who may view
 * listing details, disclosing title, description, permalink, location, company and
 * salary to requesters the operator denied. Listings the requester cannot view must be
 * excluded from the feed, like password-protected ones are.
 *
 * The same controller's unauthenticated single-item route,
 * GET /wpjm-internal/v1/promoted-jobs/{job_id}, gated reads only on core's
 * check_read_permission() and the password gate. The View Job Capability gate is
 * per-render-path in this codebase; the single-item route must re-assert it and answer
 * a denied requester with the same shape it uses for password-protected listings.
 *
 * @package wp-job-manager
 */
class Tests_Promoted_Jobs_View_Capability extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/promoted-jobs/class-wp-job-manager-promoted-jobs.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/promoted-jobs/class-wp-job-manager-promoted-jobs-status-handler.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/promoted-jobs/class-wp-job-manager-promoted-jobs-api.php';
	}

	public function tearDown(): void {
		delete_option( 'job_manager_view_job_listing_capability' );
		parent::tearDown();
	}

	/**
	 * Restricts viewing listing details to administrators.
	 */
	private function restrict_viewing_to_admins() {
		update_option( 'job_manager_view_job_listing_capability', [ 'manage_options' ] );
	}

	/**
	 * Creates a published promoted listing.
	 *
	 * @return int Job listing ID.
	 */
	private function create_promoted_listing() {
		$job_id = $this->factory->job_listing->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $job_id, WP_Job_Manager_Promoted_Jobs::PROMOTED_META_KEY, '1' );

		return $job_id;
	}

	/**
	 * Returns the listing IDs the promoted-jobs collection endpoint exposes.
	 *
	 * @return string[]
	 */
	private function get_feed_ids() {
		$api      = new WP_Job_Manager_Promoted_Jobs_API( new WP_Job_Manager_Promoted_Jobs_Status_Handler() );
		$response = $api->get_items();
		$this->assertInstanceOf( 'WP_REST_Response', $response );

		$data = $response->get_data();
		return wp_list_pluck( $data['jobs'], 'id' );
	}

	/**
	 * Returns the single-item route's response for a listing ID.
	 *
	 * @param int $job_id Job listing ID.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	private function get_job_data_response( $job_id ) {
		$api     = new WP_Job_Manager_Promoted_Jobs_API( new WP_Job_Manager_Promoted_Jobs_Status_Handler() );
		$request = new WP_REST_Request( 'GET', '/wpjm-internal/v1/promoted-jobs/' . $job_id );
		$request->set_param( 'job_id', $job_id );

		return $api->get_job_data( $request );
	}

	/**
	 * The collection must not include a listing an anonymous requester cannot view.
	 *
	 * @covers WP_Job_Manager_Promoted_Jobs_API::get_items
	 */
	public function test_restricted_listing_is_excluded_from_collection_for_anonymous() {
		$job_id = $this->create_promoted_listing();
		$this->restrict_viewing_to_admins();
		$this->logout();

		$this->assertNotContains(
			(string) $job_id,
			$this->get_feed_ids(),
			'A view-capability-restricted promoted listing must not appear in the feed for a denied requester.'
		);
	}

	/**
	 * The single-item route must re-assert the View Job Capability gate and answer a
	 * denied requester with the same shape it uses for password-protected listings.
	 *
	 * @covers WP_Job_Manager_Promoted_Jobs_API::get_job_data
	 */
	public function test_restricted_listing_is_not_readable_by_id_for_anonymous() {
		$job_id = $this->create_promoted_listing();
		$this->restrict_viewing_to_admins();
		$this->logout();

		$response = $this->get_job_data_response( $job_id );

		$this->assertWPError( $response, 'Requesting a view-capability-restricted listing by ID must fail for a denied requester.' );
		$this->assertSame( 'rest_forbidden', $response->get_error_code() );
		$this->assertSame( rest_authorization_required_code(), $response->get_error_data()['status'] );
	}

	/**
	 * Control: with no view capability configured, an anonymous requester still receives
	 * the listing from both routes.
	 *
	 * @covers WP_Job_Manager_Promoted_Jobs_API::get_items
	 * @covers WP_Job_Manager_Promoted_Jobs_API::get_job_data
	 */
	public function test_listing_is_returned_when_no_view_capability_is_configured() {
		$job_id = $this->create_promoted_listing();
		delete_option( 'job_manager_view_job_listing_capability' );
		$this->logout();

		$this->assertContains(
			(string) $job_id,
			$this->get_feed_ids(),
			'With no view capability configured, the listing must appear in the feed.'
		);

		$response = $this->get_job_data_response( $job_id );
		$this->assertNotWPError( $response, 'With no view capability configured, the listing must remain readable by ID.' );
		$this->assertSame( (string) $job_id, $response->get_data()['job_data']['id'] );
	}

	/**
	 * Positive control: a requester who satisfies the view capability still receives the
	 * listing from both routes, so the gate does not over-block.
	 *
	 * @covers WP_Job_Manager_Promoted_Jobs_API::get_items
	 * @covers WP_Job_Manager_Promoted_Jobs_API::get_job_data
	 */
	public function test_restricted_listing_is_returned_for_capable_requester() {
		$job_id = $this->create_promoted_listing();
		$this->restrict_viewing_to_admins();
		$this->login_as_admin();

		$this->assertContains(
			(string) $job_id,
			$this->get_feed_ids(),
			'A capable requester must still receive the listing in the feed.'
		);

		$response = $this->get_job_data_response( $job_id );
		$this->assertNotWPError( $response, 'A capable requester must still be able to read the listing by ID.' );
		$this->assertSame( (string) $job_id, $response->get_data()['job_data']['id'] );
	}
}
