<?php
/**
 * Regression test: the public promoted-jobs feed must not expose password-protected listings.
 *
 * GET /wpjm-internal/v1/promoted-jobs is an unauthenticated feed consumed by the Promoted
 * Jobs marketplace. Its collection query returned every published promoted listing,
 * including password-protected ones, disclosing the raw post_content the operator gated
 * behind a password. Password-protected listings must be excluded from the feed as
 * defense in depth (CWE-284).
 *
 * The same controller's unauthenticated single-item route,
 * GET /wpjm-internal/v1/promoted-jobs/{job_id}, gated reads only on core's
 * check_read_permission(), which does not consider passwords. The password gate is
 * per-render-path in this codebase; the single-item route must re-assert it.
 *
 * @package wp-job-manager
 */
class Tests_Promoted_Jobs_Password_Exclusion extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/promoted-jobs/class-wp-job-manager-promoted-jobs.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/promoted-jobs/class-wp-job-manager-promoted-jobs-status-handler.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/promoted-jobs/class-wp-job-manager-promoted-jobs-api.php';
	}

	/**
	 * Flags a listing as promoted.
	 *
	 * @param int $job_id Job listing ID.
	 */
	private function promote( $job_id ) {
		update_post_meta( $job_id, WP_Job_Manager_Promoted_Jobs::PROMOTED_META_KEY, '1' );
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
	 * The unauthenticated collection must not include a password-protected listing.
	 *
	 * @covers WP_Job_Manager_Promoted_Jobs_API::get_items
	 */
	public function test_password_protected_promoted_listing_is_excluded() {
		$public_job = $this->factory->job_listing->create( [ 'post_status' => 'publish' ] );
		$secret_job = $this->factory->job_listing->create(
			[
				'post_status'   => 'publish',
				'post_password' => 'secret',
			]
		);
		$this->promote( $public_job );
		$this->promote( $secret_job );

		$ids = $this->get_feed_ids();

		$this->assertContains(
			(string) $public_job,
			$ids,
			'A public promoted listing must appear in the feed.'
		);
		$this->assertNotContains(
			(string) $secret_job,
			$ids,
			'A password-protected promoted listing must not appear in the feed.'
		);
	}

	/**
	 * The unauthenticated single-item route must re-assert the password gate and not
	 * return a password-protected listing's content.
	 *
	 * @covers WP_Job_Manager_Promoted_Jobs_API::get_job_data
	 */
	public function test_password_protected_listing_is_not_readable_by_id() {
		$secret_job = $this->factory->job_listing->create(
			[
				'post_status'   => 'publish',
				'post_password' => 'secret',
				'post_content'  => 'Content behind a password.',
			]
		);
		$this->promote( $secret_job );

		$response = $this->get_job_data_response( $secret_job );

		$this->assertWPError( $response, 'Requesting a password-protected listing by ID must fail.' );
		$this->assertSame( 'rest_forbidden', $response->get_error_code() );
	}

	/**
	 * The single-item route must still serve a public promoted listing.
	 *
	 * @covers WP_Job_Manager_Promoted_Jobs_API::get_job_data
	 */
	public function test_public_listing_is_readable_by_id() {
		$public_job = $this->factory->job_listing->create( [ 'post_status' => 'publish' ] );
		$this->promote( $public_job );

		$response = $this->get_job_data_response( $public_job );

		$this->assertNotWPError( $response, 'A public promoted listing must remain readable by ID.' );
		$this->assertSame( (string) $public_job, $response->get_data()['job_data']['id'] );
	}
}
