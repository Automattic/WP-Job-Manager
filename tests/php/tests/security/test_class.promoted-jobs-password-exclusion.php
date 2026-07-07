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
}
