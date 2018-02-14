<?php

class WP_Test_WP_Job_Manager_Usage_Tracking_Data extends WPJM_BaseTest {
	/**
	 * Tests that get_usage_data() returns the correct number of published job
	 * listings.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_job_listings() {
		$published_listing_count = 3;
		$draft_listing_count = 2;

		$this->factory->job_listing->create_many(
			$published_listing_count, array( 'post_status' => 'publish' )
		);
		$this->factory->job_listing->create_many(
			$draft_listing_count, array( 'post_status' => 'draft' )
		);

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();
		$this->assertEquals( $published_listing_count, $data['jobs_published'] );
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
}
