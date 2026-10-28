<?php
/**
 * Tests that the job submission handler checks the request and the visitor's permission
 * before it processes posted files.
 *
 * @package wp-job-manager
 */
class Tests_Submit_Job_Request_Order extends WPJM_BaseTest {

	/**
	 * Paths written by wp_handle_upload() during the current test.
	 *
	 * @var string[]
	 */
	private $written_files = [];

	/**
	 * Temporary copies of the test image posted in $_FILES.
	 *
	 * @var string[]
	 */
	private $temp_files = [];

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';

		$this->written_files = [];
		$this->temp_files    = [];
		add_filter( 'wp_handle_upload', [ $this, 'record_written_file' ] );
		add_filter( 'submit_job_wp_handle_upload_overrides', [ $this, 'override_upload_action' ] );
	}

	public function tearDown(): void {
		remove_filter( 'wp_handle_upload', [ $this, 'record_written_file' ] );
		remove_filter( 'submit_job_wp_handle_upload_overrides', [ $this, 'override_upload_action' ] );

		foreach ( array_merge( $this->written_files, $this->temp_files ) as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}

		$_POST    = [];
		$_REQUEST = [];
		$_FILES   = [];
		parent::tearDown();
	}

	/**
	 * Records each file wp_handle_upload() writes.
	 *
	 * @param array $upload Upload result.
	 * @return array
	 */
	public function record_written_file( $upload ) {
		if ( ! empty( $upload['file'] ) ) {
			$this->written_files[] = $upload['file'];
		}
		return $upload;
	}

	/**
	 * Lets wp_handle_upload() accept a test file that was not sent through an HTTP upload.
	 *
	 * @param array $overrides Upload overrides.
	 * @return array
	 */
	public function override_upload_action( $overrides ) {
		$overrides['action'] = 'test-wpjm-upload';
		return $overrides;
	}

	/**
	 * Puts copies of a test image into $_FILES['company_logo'].
	 *
	 * @param int $count Number of files to post.
	 */
	private function post_company_logo_files( $count ) {
		$source = DIR_TESTDATA . '/images/test-image-iptc.jpg';
		$files  = [
			'name'     => [],
			'type'     => [],
			'tmp_name' => [],
			'error'    => [],
			'size'     => [],
		];

		for ( $i = 1; $i <= $count; $i++ ) {
			$tmp_name = wp_tempnam( $source );
			copy( $source, $tmp_name );
			$this->temp_files[] = $tmp_name;

			$files['name'][]     = 'logo-' . $i . '.jpg';
			$files['type'][]     = 'image/jpeg';
			$files['tmp_name'][] = $tmp_name;
			$files['error'][]    = 0;
			$files['size'][]     = filesize( $source );
		}

		$_FILES['company_logo'] = $files;
	}

	/**
	 * Runs submit_handler() for a POST with the given fields.
	 *
	 * @param array $fields POST fields.
	 * @return WP_Job_Manager_Form_Submit_Job
	 */
	private function run_submit_handler( $fields ) {
		$_POST = array_merge( [ 'job_manager_form' => 'submit-job' ], $fields );
		foreach ( $_POST as $key => $value ) {
			$_REQUEST[ $key ] = $value;
		}

		$form = new WP_Job_Manager_Form_Submit_Job();
		$form->submit_handler();

		return $form;
	}

	/**
	 * Requires an account to post and turns off registration, so guests may not post.
	 */
	private function disallow_guest_posting() {
		update_option( 'job_manager_user_requires_account', 1 );
		update_option( 'job_manager_enable_registration', 0 );
	}

	/**
	 * A visitor who may not post writes no file, even without submitting the form.
	 */
	public function test_visitor_who_cannot_post_writes_no_file() {
		$this->disallow_guest_posting();
		$this->logout();
		$this->post_company_logo_files( 1 );

		$this->run_submit_handler( [] );

		$this->assertSame( [], $this->written_files, 'Files from a visitor who may not post must not be written.' );
	}

	/**
	 * A visitor who may not post writes no file when the form is submitted either.
	 */
	public function test_visitor_who_cannot_post_writes_no_file_on_submit() {
		$this->disallow_guest_posting();
		$this->logout();
		$this->post_company_logo_files( 1 );

		$this->run_submit_handler( [ 'submit_job' => '1' ] );

		$this->assertSame( [], $this->written_files, 'Files from a visitor who may not post must not be written.' );
	}

	/**
	 * A logged-in user who may not post writes no file, and is told they lack permission.
	 */
	public function test_logged_in_user_who_cannot_post_writes_no_file() {
		$this->login_as_employer();
		add_filter( 'job_manager_user_can_post_job', '__return_false' );
		$this->post_company_logo_files( 1 );

		$form = $this->run_submit_handler(
			[
				'submit_job'  => '1',
				'_wpjm_nonce' => wp_create_nonce( 'submit-job-0' ),
			]
		);

		remove_filter( 'job_manager_user_can_post_job', '__return_false' );

		ob_start();
		$form->show_errors();
		$errors = ob_get_clean();

		$this->assertSame( [], $this->written_files );
		$this->assertStringContainsString( 'You do not have permission to post a listing.', $errors );
	}

	/**
	 * A logged-in submission with an invalid nonce writes no file.
	 */
	public function test_invalid_nonce_writes_no_file() {
		$this->login_as_employer();
		$this->post_company_logo_files( 1 );

		// The nonce failure ends in wp_die(), which uses the AJAX handler under the test bootstrap's
		// DOING_AJAX. Throw instead of exiting so the test can continue.
		$throw_handler = function () {
			return function ( $message ) {
				throw new WPDieException( is_string( $message ) ? $message : '' );
			};
		};
		add_filter( 'wp_die_ajax_handler', $throw_handler );

		$this->run_submit_handler(
			[
				'submit_job'  => '1',
				'_wpjm_nonce' => 'invalid',
			]
		);

		remove_filter( 'wp_die_ajax_handler', $throw_handler );

		$this->assertSame( [], $this->written_files, 'Files sent with an invalid nonce must not be written.' );
	}

	/**
	 * A POST that does not submit the form writes no file, even from a user who may post.
	 */
	public function test_post_without_submit_writes_no_file() {
		$this->login_as_employer();
		$this->post_company_logo_files( 1 );

		$this->run_submit_handler( [ '_wpjm_nonce' => wp_create_nonce( 'submit-job-0' ) ] );

		$this->assertSame( [], $this->written_files, 'Files must not be written unless the form is submitted.' );
	}

	/**
	 * A field that takes a single file writes only one, however many are posted.
	 */
	public function test_single_file_field_writes_one_file() {
		$this->login_as_employer();
		$this->post_company_logo_files( 3 );

		$this->run_submit_handler(
			[
				'submit_job'  => '1',
				'_wpjm_nonce' => wp_create_nonce( 'submit-job-0' ),
			]
		);

		$this->assertCount( 1, $this->written_files, 'A single-file field must write only one file.' );
	}

	/**
	 * Control: a logged-in submission with a valid nonce still uploads its file.
	 */
	public function test_valid_logged_in_submission_writes_file() {
		$this->login_as_employer();
		$this->post_company_logo_files( 1 );

		$this->run_submit_handler(
			[
				'submit_job'  => '1',
				'_wpjm_nonce' => wp_create_nonce( 'submit-job-0' ),
			]
		);

		$this->assertCount( 1, $this->written_files );
	}

	/**
	 * Control: when guests may post, a guest submission still uploads its file.
	 */
	public function test_guest_submission_writes_file_when_guests_may_post() {
		update_option( 'job_manager_user_requires_account', 0 );
		$this->logout();
		$this->post_company_logo_files( 1 );

		$this->run_submit_handler( [ 'submit_job' => '1' ] );

		$this->assertCount( 1, $this->written_files );
	}
}
