<?php
/**
 * Tests for sanitize_posted_field() and parse_email_list() handling of
 * comma-separated email addresses at the frontend form layer.
 *
 * Covers Automattic/WP-Job-Manager#2353 review feedback: previously the
 * frontend sanitize_posted_field() ran before validate_fields() and
 * destroyed multi-email values via sanitize_email().
 *
 * @package wp-job-manager
 */

require_once __DIR__ . '/class-wp-job-manager-form-test-wrapper.php';

class WP_Test_Sanitize_Posted_Field_Email_List extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		if ( ! class_exists( 'WP_Job_Manager_Form_Submit_Job', false ) ) {
			include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';
		}
	}

	/**
	 * Concrete wrapper that exposes the protected helpers
	 * sanitize_posted_field() and parse_email_list() for direct testing.
	 */
	private function make_form(): WP_Job_Manager_Form_Test_Wrapper {
		return new WP_Job_Manager_Form_Test_Wrapper();
	}

	public function test_email_sanitizer_accepts_two_address_list() {
		$form  = $this->make_form();
		$value = $form->call_sanitize_posted_field( 'hr@example.com, jobs@example.com', 'email' );

		$this->assertSame( 'hr@example.com, jobs@example.com', $value );
	}

	public function test_email_sanitizer_accepts_semicolon_list() {
		$form  = $this->make_form();
		$value = $form->call_sanitize_posted_field( 'hr@example.com;jobs@example.com', 'email' );

		$this->assertSame( 'hr@example.com, jobs@example.com', $value );
	}

	public function test_email_sanitizer_rejects_partial_list_to_empty() {
		$form  = $this->make_form();
		$value = $form->call_sanitize_posted_field( 'hr@example.com, not-an-email', 'email' );

		$this->assertSame( '', $value );
	}

	public function test_email_sanitizer_accepts_single_email() {
		$form  = $this->make_form();
		$value = $form->call_sanitize_posted_field( 'hr@example.com', 'email' );

		$this->assertSame( 'hr@example.com', $value );
	}

	public function test_url_or_email_sanitizer_accepts_list() {
		$form  = $this->make_form();
		$value = $form->call_sanitize_posted_field( 'hr@example.com, jobs@example.com', 'url_or_email' );

		$this->assertSame( 'hr@example.com, jobs@example.com', $value );
	}

	public function test_url_or_email_sanitizer_accepts_single_email() {
		$form  = $this->make_form();
		$value = $form->call_sanitize_posted_field( 'hr@example.com', 'url_or_email' );

		$this->assertSame( 'hr@example.com', $value );
	}

	public function test_url_or_email_sanitizer_accepts_single_url() {
		$form  = $this->make_form();
		$value = $form->call_sanitize_posted_field( 'https://example.com/apply', 'url_or_email' );

		$this->assertSame( 'https://example.com/apply', $value );
	}

	public function test_url_or_email_sanitizer_normalizes_bare_url() {
		$form  = $this->make_form();
		$value = $form->call_sanitize_posted_field( 'example.com/jobs', 'url_or_email' );

		$this->assertSame( 'http://example.com/jobs', $value );
	}

	public function test_url_or_email_sanitizer_partial_list_falls_through_to_url() {
		$form  = $this->make_form();
		$value = $form->call_sanitize_posted_field( 'a@example.com, not-an-email', 'url_or_email' );

		// All-or-nothing: invalid token in list → fall through to URL sanitizer (esc_url_raw
		// drops the space after the comma).
		$this->assertSame( 'http://a@example.com,+not-an-email', $value );
	}

	public function test_parse_email_list_returns_null_for_single_email() {
		$form = $this->make_form();

		$this->assertNull( $form->call_parse_email_list( 'hr@example.com' ) );
	}

	public function test_parse_email_list_returns_null_for_invalid_token() {
		$form = $this->make_form();

		$this->assertNull( $form->call_parse_email_list( 'hr@example.com, not-an-email' ) );
	}

	public function test_parse_email_list_returns_null_at_cap() {
		$form = $this->make_form();
		// Default cap is 10; 11 valid emails must fall back.
		$emails = array_map( fn( $i ) => "u{$i}@example.com", range( 1, 11 ) );
		$value  = $form->call_parse_email_list( implode( ',', $emails ) );

		$this->assertNull( $value );
	}

	public function test_parse_email_list_returns_array_under_cap() {
		$form   = $this->make_form();
		$result = $form->call_parse_email_list( 'a@example.com, b@example.com' );

		$this->assertSame( [ 'a@example.com', 'b@example.com' ], $result );
	}
}
