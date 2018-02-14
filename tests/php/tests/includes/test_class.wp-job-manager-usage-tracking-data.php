<?php

class WP_Test_WP_Job_Manager_Usage_Tracking_Data extends WPJM_BaseTest {
	private $expired_count         = 10;
	private $preview_count         = 1;
	private $pending_count         = 8;
	private $pending_payment_count = 3;
	private $publish_count         = 15;

	private function create_job_listings() {
		$this->factory->job_listing->create_many(
			2, array( 'post_status' => 'draft' )
		);
		$this->factory->job_listing->create_many(
			$expired_count, array( 'post_status' => 'expired' )
		);
		$this->factory->job_listing->create_many(
			$preview_count, array( 'post_status' => 'preview' )
		);
		$this->factory->job_listing->create_many(
			$pending_count, array( 'post_status' => 'pending' )
		);
		$this->factory->job_listing->create_many(
			$pending_payment_count, array( 'post_status' => 'pending_payment' )
		);
		$this->factory->job_listing->create_many(
			$publish_count, array( 'post_status' => 'publish' )
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

		$this->assertEquals( $expired_count, $data['jobs_expired'] );
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

		$this->assertEquals( $pending_count, $data['jobs_pending'] );
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

		$this->assertEquals( $pending_payment_count, $data['jobs_pending_payment'] );
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

		$this->assertEquals( $preview_count, $data['jobs_preview'] );
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

		$this->assertEquals( $publish_count, $data['jobs_publish'] );
	}
}
