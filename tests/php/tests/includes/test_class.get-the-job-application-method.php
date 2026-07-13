<?php
/**
 * Tests for get_the_job_application_method() with single and comma-separated
 * email addresses, and a URL regression case. Covers the resolver path for
 * issue Automattic/WP-Job-Manager#2353.
 *
 * @package wp-job-manager
 */

class WP_Test_Get_The_Job_Application_Method extends WPJM_BaseTest {

	/**
	 * @var int
	 */
	private $listing_id;

	public function tearDown(): void {
		if ( $this->listing_id ) {
			wp_delete_post( $this->listing_id, true );
			$this->listing_id = 0;
		}
		parent::tearDown();
	}

	private function create_listing( $application ) {
		$this->listing_id = wp_insert_post(
			[
				'post_type'  => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_title' => 'Test Listing',
				'post_status' => 'publish',
			]
		);
		update_post_meta( $this->listing_id, '_application', $application );
		return $this->listing_id;
	}

	public function test_single_email_returns_email_type() {
		$this->create_listing( 'hr@example.com' );

		$method = get_the_job_application_method( $this->listing_id );

		$this->assertIsObject( $method );
		$this->assertSame( 'email', $method->type );
		$this->assertSame( 'hr@example.com', $method->raw_email );
	}

	public function test_two_emails_comma_separated_returns_email_type() {
		$this->create_listing( 'hr@example.com, jobs@example.com' );

		$method = get_the_job_application_method( $this->listing_id );

		$this->assertIsObject( $method );
		$this->assertSame( 'email', $method->type );
		$this->assertSame( 'hr@example.com, jobs@example.com', $method->raw_email );
	}

	public function test_two_emails_semicolon_separated_returns_email_type() {
		$this->create_listing( 'hr@example.com; jobs@example.com' );

		$method = get_the_job_application_method( $this->listing_id );

		$this->assertIsObject( $method );
		$this->assertSame( 'email', $method->type );
		$this->assertSame( 'hr@example.com, jobs@example.com', $method->raw_email );
	}

	public function test_three_emails_returns_email_type() {
		$this->create_listing( 'a@example.com, b@example.com, c@example.com' );

		$method = get_the_job_application_method( $this->listing_id );

		$this->assertIsObject( $method );
		$this->assertSame( 'email', $method->type );
		$this->assertSame( 'a@example.com, b@example.com, c@example.com', $method->raw_email );
	}

	public function test_url_returns_url_type() {
		$this->create_listing( 'https://example.com/apply' );

		$method = get_the_job_application_method( $this->listing_id );

		$this->assertIsObject( $method );
		$this->assertSame( 'url', $method->type );
		$this->assertSame( 'https://example.com/apply', $method->url );
	}

	public function test_bare_string_without_at_returns_url_type() {
		$this->create_listing( 'example.com/jobs' );

		$method = get_the_job_application_method( $this->listing_id );

		$this->assertIsObject( $method );
		$this->assertSame( 'url', $method->type );
		$this->assertSame( 'http://example.com/jobs', $method->url );
	}

	public function test_email_list_with_invalid_token_falls_back_to_url() {
		$this->listing_id = wp_insert_post(
			[
				'post_type'   => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_title'  => 'Test Listing',
				'post_status' => 'publish',
			]
		);
		// Bypass the registered sanitize_callback so the resolver sees the
		// un-sanitized mixed value (the sanitizer test covers the save path).
		global $wpdb;
		$wpdb->insert(
			$wpdb->postmeta,
			[
				'post_id'    => $this->listing_id,
				'meta_key'   => '_application',
				'meta_value' => 'hr@example.com, not-an-email',
			],
			[ '%d', '%s', '%s' ]
		);
		wp_cache_delete( $this->listing_id, 'post_meta' );

		$method = get_the_job_application_method( $this->listing_id );

		$this->assertIsObject( $method );
		$this->assertSame( 'url', $method->type );
	}

	public function test_empty_returns_false() {
		$this->create_listing( '' );

		$method = get_the_job_application_method( $this->listing_id );

		$this->assertFalse( $method );
	}
}
