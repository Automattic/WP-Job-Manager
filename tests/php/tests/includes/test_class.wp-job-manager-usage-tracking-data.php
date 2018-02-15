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
	 * IDs for job listings that are in draft status
	 *
	 * @var array
	 */
	private $draft;

	/**
	 * IDs for job listings that have expired
	 *
	 * @var array
	 */
	private $expired;

	/**
	 * IDs for job listings that are in preview status
	 *
	 * @var array
	 */
	private $preview;

	/**
	 * IDs for job listings that are pending approval
	 *
	 * @var array
	 */
	private $pending;

	/**
	 * IDs for job listings that are pending payment
	 *
	 * @var array
	 */
	private $pending_payment;

	/**
	 * IDs for job listings that are published
	 *
	 * @var array
	 */
	private $publish;

	public function setUp() {
		parent::setUp();

		$this->create_job_listings();
	}

	/**
	 * Create a number of job listings with different statuses.
	 */
	private function create_job_listings() {
		$this->draft           = $this->factory->job_listing->create_many(
			2, array( 'post_status' => 'draft' )
		);
		$this->expired         = $this->factory->job_listing->create_many(
			10, array( 'post_status' => 'expired' )
		);
		$this->preview         = $this->factory->job_listing->create_many(
			1, array( 'post_status' => 'preview' )
		);
		$this->pending         = $this->factory->job_listing->create_many(
			8, array( 'post_status' => 'pending' )
		);
		$this->pending_payment = $this->factory->job_listing->create_many(
			3, array( 'post_status' => 'pending_payment' )
		);
		$this->publish         = $this->factory->job_listing->create_many(
			15, array( 'post_status' => 'publish' )
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

		$this->assertEquals( count( $this->expired ), $data['jobs_status_expired'] );
	}

	/**
	 * Pending jobs count.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_get_usage_data_pending_jobs() {
		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();

		$this->assertEquals( count( $this->pending ), $data['jobs_status_pending'] );
	}

	/**
	 * Pending payment jobs count.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_get_usage_data_pending_payment_jobs() {
		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();

		$this->assertEquals( count( $this->pending_payment ), $data['jobs_status_pending_payment'] );
	}

	/**
	 * Preview jobs count.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_get_usage_data_preview_jobs() {
		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();

		$this->assertEquals( count( $this->preview ), $data['jobs_status_preview'] );
	}

	/**
	 * Published jobs count.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_get_usage_data_publish_jobs() {
		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();

		$this->assertEquals( count( $this->publish ), $data['jobs_status_publish'] );
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
		add_post_meta( $this->draft[0], '_thumbnail_id', $media[0] );
		add_post_meta( $this->expired[5], '_thumbnail_id', $media[1] );
		add_post_meta( $this->expired[6], '_thumbnail_id', $media[2] );
		add_post_meta( $this->preview[0], '_thumbnail_id', $media[3] );
		add_post_meta( $this->pending[3], '_thumbnail_id', $media[4] );
		add_post_meta( $this->publish[9], '_thumbnail_id', $media[5] );

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
		wp_set_object_terms( $this->draft[0], $terms[0], 'job_listing_type', false );
		wp_set_object_terms( $this->expired[5], $terms[1], 'job_listing_type', false );
		wp_set_object_terms( $this->expired[6], $terms[2], 'job_listing_type', false );
		wp_set_object_terms( $this->preview[0], $terms[3], 'job_listing_type', false );
		wp_set_object_terms( $this->pending[3], $terms[4], 'job_listing_type', false );
		wp_set_object_terms( $this->publish[9], $terms[5], 'job_listing_type', false );

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();

		// 2 expired + 1 publish
		$this->assertEquals( 3, $data['jobs_type'] );
	}
}
