<?php
/**
 * Usage tracking unit test cases
 *
 * @package Usage Tracking
 **/

/**
 * Usage tracking unit test cases
 *
 * @package Usage Tracking
 **/
class WP_Test_WP_Job_Manager_Usage_Tracking_Data extends WPJM_BaseTest {
	/**
	 * Number of job listings with expired status
	 *
	 * @var int
	 */
	private $expired_count = 10;

	/**
	 * Number of job listings with preview status
	 *
	 * @var int
	 */
	private $preview_count = 1;

	/**
	 * Number of job listings with pending status
	 *
	 * @var int
	 */
	private $pending_count = 8;

	/**
	 * Number of job listings with pending payment status
	 *
	 * @var int
	 */
	private $pending_payment_count = 3;

	/**
	 * Number of job listings with publish status
	 *
	 * @var int
	 */
	private $publish_count = 15;

	/**
	 * Create a number of job listings with different statuses.
	 */
	private function create_job_listings() {
		$this->factory->job_listing->create_many(
			2, array( 'post_status' => 'draft' )
		);
		$this->factory->job_listing->create_many(
			$this->expired_count, array( 'post_status' => 'expired' )
		);
		$this->factory->job_listing->create_many(
			$this->preview_count, array( 'post_status' => 'preview' )
		);
		$this->factory->job_listing->create_many(
			$this->pending_count, array( 'post_status' => 'pending' )
		);
		$this->factory->job_listing->create_many(
			$this->pending_payment_count, array( 'post_status' => 'pending_payment' )
		);
		$this->factory->job_listing->create_many(
			$this->publish_count, array( 'post_status' => 'publish' )
		);
	}

	/**
	 * Tests that get_usage_data() returns the correct number of users with the
	 * "employer" role.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_employers() {
		$employer_count = 3;
		$subscriber_count = 2;

		$this->factory->user->create_many(
			$employer_count, array( 'role' => 'employer' )
		);
		$this->factory->user->create_many(
			$subscriber_count, array( 'role' => 'subscriber' )
		);

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();
		$this->assertEquals( $employer_count, $data['employers'] );
	}

	/**
	 * Expired jobs count.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_get_usage_data_expired_jobs() {
		$this->create_job_listings();

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();

		$this->assertEquals( $this->expired_count, $data['jobs_expired'] );
	}

	/**
	 * Pending jobs count.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_get_usage_data_pending_jobs() {
		$this->create_job_listings();

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();

		$this->assertEquals( $this->pending_count, $data['jobs_pending'] );
	}

	/**
	 * Pending payment jobs count.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_get_usage_data_pending_payment_jobs() {
		$this->create_job_listings();

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();

		$this->assertEquals( $this->pending_payment_count, $data['jobs_pending_payment'] );
	}

	/**
	 * Preview jobs count.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_get_usage_data_preview_jobs() {
		$this->create_job_listings();

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();

		$this->assertEquals( $this->preview_count, $data['jobs_preview'] );
	}

	/**
	 * Published jobs count.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_get_usage_data_publish_jobs() {
		$this->create_job_listings();

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();

		$this->assertEquals( $this->publish_count, $data['jobs_publish'] );
	}
}
