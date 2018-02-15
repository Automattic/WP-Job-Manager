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

	/**
	 * Create a number of job listings with different statuses.
	 */
	private function create_default_job_listings() {
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
		$this->create_default_job_listings();
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
		$this->create_default_job_listings();
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
		$this->create_default_job_listings();
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
		$this->create_default_job_listings();
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
		$this->create_default_job_listings();
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
		$this->create_default_job_listings();

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
		$this->create_default_job_listings();
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

	/**
	 * Tests that get_usage_data() returns the correct number of job listings
	 * with a location.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_jobs_location() {
		$published = 3;
		$expired   = 2;

		$this->create_job_listings_with_meta( '_job_location', 'Toronto', $published, $expired );

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();
		$this->assertEquals( $published + $expired, $data['jobs_location'] );
	}

	/**
	 * Tests that get_usage_data() returns the correct number of job listings
	 * with an application email or URL.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_jobs_application_contact() {
		$published = 3;
		$expired   = 2;

		$this->create_job_listings_with_meta( '_application', 'email@example.com', $published, $expired );

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();
		$this->assertEquals( $published + $expired, $data['jobs_app_contact'] );
	}

	/**
	 * Tests that get_usage_data() returns the correct number of job listings
	 * with a company name.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_jobs_company_name() {
		$published = 3;
		$expired   = 2;

		$this->create_job_listings_with_meta( '_company_name', 'Automattic', $published, $expired );

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();
		$this->assertEquals( $published + $expired, $data['jobs_company_name'] );
	}

	/**
	 * Tests that get_usage_data() returns the correct number of job listings
	 * with a company website.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_jobs_company_website() {
		$published = 3;
		$expired   = 2;

		$this->create_job_listings_with_meta( '_company_website', 'automattic.com', $published, $expired );

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();
		$this->assertEquals( $published + $expired, $data['jobs_company_site'] );
	}

	/**
	 * Tests that get_usage_data() returns the correct number of job listings
	 * with a company tagline.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_jobs_company_tagline() {
		$published = 3;
		$expired   = 2;

		$this->create_job_listings_with_meta( '_company_tagline', 'We are passionate about making the web a better place.', $published, $expired );

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();
		$this->assertEquals( $published + $expired, $data['jobs_company_tagline'] );
	}

	/**
	 * Tests that get_usage_data() returns the correct number of job listings
	 * with a company twitter handle.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_jobs_company_twitter() {
		$published = 3;
		$expired   = 2;

		$this->create_job_listings_with_meta( '_company_twitter', '@automattic', $published, $expired );

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();
		$this->assertEquals( $published + $expired, $data['jobs_company_twitter'] );
	}

	/**
	 * Tests that get_usage_data() returns the correct number of job listings
	 * with a company video.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_jobs_company_video() {
		$published = 3;
		$expired   = 2;

		$this->create_job_listings_with_meta( '_company_video', 'youtube.com/1234', $published, $expired );

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();
		$this->assertEquals( $published + $expired, $data['jobs_company_video'] );
	}

	/**
	 * Tests that get_usage_data() returns the correct number of job listings
	 * with an expiry date.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_jobs_expiry() {
		$published = 3;
		$expired   = 2;

		$this->create_job_listings_with_meta( '_job_expires', '2018-01-01', $published, $expired );

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();
		$this->assertEquals( $published + $expired, $data['jobs_expiry'] );
	}

	/**
	 * Tests that get_usage_data() returns the correct number of job listings
	 * with the Position Filled box checked.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_jobs_filled() {
		$published = 3;
		$expired   = 2;

		$this->create_job_listings_with_meta( '_filled', '1', $published, $expired, array( '0' ) );

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();
		$this->assertEquals( $published + $expired, $data['jobs_filled'] );
	}

	/**
	 * Tests that get_usage_data() returns the correct number of job listings
	 * with the Featured Listing box checked.
	 *
	 * @since 1.30.0
	 * @covers WP_Job_Manager_Usage_Tracking_Data::get_usage_data
	 */
	public function test_jobs_featured() {
		$published = 3;
		$expired   = 2;

		$this->create_job_listings_with_meta( '_featured', '1', $published, $expired, array( '0' ) );

		$data = WP_Job_Manager_Usage_Tracking_Data::get_usage_data();
		$this->assertEquals( $published + $expired, $data['jobs_featured'] );
	}


	/**
	 * Creates job listings with the given meta values. This will also create
	 * some listings with values for the meta parameter that should be
	 * considered empty (e.g. spaces) and some entries with other statuses
	 * (such as draft). For tracking data, only the published and expired
	 * entries should be counted.
	 *
	 * @param string $meta_name  the name of the meta parameter to set.
	 * @param string $meta_value the desired value of the meta parameter.
	 * @param int $published     the number of published listings to create.
	 * @param int $expired       the number of expired listings to create.
	 * @param int $other_values  other values for which to create listings (optional).
	 */
	private function create_job_listings_with_meta( $meta_name, $meta_value, $published, $expired, $other_values = array() ) {
		// Create published listings.
		$this->factory->job_listing->create_many( $published, array(
			'meta_input' => array(
				$meta_name => $meta_value,
			),
		) );

		// Create expired listings.
		$this->factory->job_listing->create_many( $expired, array(
			'post_status' => 'expired',
			'meta_input' => array(
				$meta_name => $meta_value,
			),
		) );

		// Create listings with empty values.
		$empty_values = array( '', '   ', "\n\t", " \n \t " );
		foreach ( $empty_values as $val ) {
			$this->factory->job_listing->create( array(
				'meta_input' => array(
					$meta_name => $val,
				),
			) );
		}

		// Create listings with other statuses.
		$statuses = array( 'future', 'draft', 'pending', 'private', 'trash' );
		foreach ( $statuses as $status ) {
			$params = array(
				'post_status' => $status,
				'meta_input'  => array(
					$meta_name => $meta_value,
				),
			);

			if ( 'future' == $status ) {
				$params['post_date'] = '3018-02-15 00:00:00';
			}

			$this->factory->job_listing->create( $params );
		}

		// Create listings with other values.
		foreach ( $other_values as $val ) {
			$this->factory->job_listing->create( array(
				'meta_input' => array(
					$meta_name => $val,
				)
			) );
		}
	}
}
