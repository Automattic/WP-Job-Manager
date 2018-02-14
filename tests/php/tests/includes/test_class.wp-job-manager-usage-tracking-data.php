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
	 * Job listing IDs
	 *
	 * @var array
	 */
	private $listings;

	public function setUp() {
		parent::setUp();

		$this->create_job_listings();
	}

	/**
	 * Create a number of job listings with different statuses.
	 */
	private function create_job_listings() {
		$draft           = $this->factory->job_listing->create_many(
			2, array( 'post_status' => 'draft' )
		);
		$expired         = $this->factory->job_listing->create_many(
			$this->expired_count, array( 'post_status' => 'expired' )
		);
		$preview         = $this->factory->job_listing->create_many(
			$this->preview_count, array( 'post_status' => 'preview' )
		);
		$pending         = $this->factory->job_listing->create_many(
			$this->pending_count, array( 'post_status' => 'pending' )
		);
		$pending_payment = $this->factory->job_listing->create_many(
			$this->pending_payment_count, array( 'post_status' => 'pending_payment' )
		);
		$publish         = $this->factory->job_listing->create_many(
			$this->publish_count, array( 'post_status' => 'publish' )
		);

		$this->listings = array_merge( $draft, $expired, $preview, $pending, $pending_payment, $publish );
	}

	/**
	 * Tests that get_usage_data() returns the correct number of users with the
	 * "employer" role.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_employers() {
		$employer_count   = 3;
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
		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();

		$this->assertEquals( $this->publish_count, $data['jobs_publish'] );
	}

	/**
	 * Jobs with a company logo count.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_company_logo_count
	 */
	public function test_get_company_logo_count() {
		// Create some media attachments.
		$media = $this->factory->attachment->create_many(
			6, array(
				'post_type'   => 'job_listing',
				'post_status' => 'publish',
			)
		);

		// Add logos to some listings with varying statuses.
		add_post_meta( $this->listings[0], '_thumbnail_id', $media[0] );
		add_post_meta( $this->listings[5], '_thumbnail_id', $media[1] );
		add_post_meta( $this->listings[6], '_thumbnail_id', $media[2] );
		add_post_meta( $this->listings[12], '_thumbnail_id', $media[3] );
		add_post_meta( $this->listings[20], '_thumbnail_id', $media[4] );
		add_post_meta( $this->listings[24], '_thumbnail_id', $media[5] );

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();

		// 2 expired + 1 publish
		$this->assertEquals( 3, $data['jobs_logo'] );
	}

	/**
	 * Jobs with a company logo count.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_job_type_count
	 */
	public function test_get_job_type_count() {
		$terms = $this->factory->term->create_many( 6, array( 'taxonomy' => 'job_listing_type' ) );

		// Assign job types to some jobs.
		wp_set_object_terms( $this->listings[0], $terms[0], 'job_listing_type', false );
		wp_set_object_terms( $this->listings[5], $terms[1], 'job_listing_type', false );
		wp_set_object_terms( $this->listings[6], $terms[2], 'job_listing_type', false );
		wp_set_object_terms( $this->listings[12], $terms[3], 'job_listing_type', false );
		wp_set_object_terms( $this->listings[20], $terms[4], 'job_listing_type', false );
		wp_set_object_terms( $this->listings[24], $terms[5], 'job_listing_type', false );

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();

		// 2 expired + 1 publish
		$this->assertEquals( 3, $data['jobs_type'] );
	}
}
