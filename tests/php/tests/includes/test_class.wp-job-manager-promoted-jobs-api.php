<?php
/**
 * Tests for WP_Job_Manager_Promoted_Jobs_API.
 *
 * @package wp-job-manager
 */
class Tests_WP_Job_Manager_Promoted_Jobs_API extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/promoted-jobs/class-wp-job-manager-promoted-jobs.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/promoted-jobs/class-wp-job-manager-promoted-jobs-status-handler.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/promoted-jobs/class-wp-job-manager-promoted-jobs-api.php';
	}

	/**
	 * Returns the `job_data` payload for a listing via the single-item route.
	 *
	 * @param int $job_id Job listing ID.
	 * @return array
	 */
	private function get_job_data( $job_id ) {
		$api     = new WP_Job_Manager_Promoted_Jobs_API( new WP_Job_Manager_Promoted_Jobs_Status_Handler() );
		$request = new WP_REST_Request( 'GET', '/wpjm-internal/v1/promoted-jobs/' . $job_id );
		$request->set_param( 'job_id', $job_id );

		$response = $api->get_job_data( $request );
		$this->assertInstanceOf( 'WP_REST_Response', $response );

		return $response->get_data()['job_data'];
	}

	/**
	 * `is_remote` must stay boolean-shaped (true for Remote and Hybrid, false for On-Site),
	 * now sourced from the workplace type taxonomy instead of the legacy checkbox meta.
	 *
	 * @covers WP_Job_Manager_Promoted_Jobs_API::get_job_data
	 */
	public function test_is_remote_reflects_workplace_type() {
		$expectations = [
			'on-site' => false,
			'remote'  => true,
			'hybrid'  => true,
		];

		foreach ( $expectations as $workplace_type => $expected_is_remote ) {
			$job_id = $this->factory->job_listing->create( [ 'post_status' => 'publish' ] );
			wp_set_object_terms( $job_id, $workplace_type, \WP_Job_Manager_Post_Types::TAX_WORKPLACE_TYPE );

			$job_data = $this->get_job_data( $job_id );

			$this->assertSame( $expected_is_remote, $job_data['is_remote'], "workplace type: {$workplace_type}" );
		}
	}

	/**
	 * A listing with no workplace type assigned must not be treated as remote.
	 *
	 * @covers WP_Job_Manager_Promoted_Jobs_API::get_job_data
	 */
	public function test_is_remote_false_when_no_workplace_type_assigned() {
		$job_id = $this->factory->job_listing->create( [ 'post_status' => 'publish' ] );

		$job_data = $this->get_job_data( $job_id );

		$this->assertFalse( $job_data['is_remote'] );
	}
}
