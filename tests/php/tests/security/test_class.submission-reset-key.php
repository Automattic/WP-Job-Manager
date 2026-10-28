<?php
/**
 * Tests that starting a new submission requires the current submitting key, which the
 * "Create A New Job" link carries.
 *
 * Tests that reach the reset run in separate processes so earlier output cannot send headers
 * before its setcookie() calls.
 *
 * @package wp-job-manager
 */
class Tests_Submission_Reset_Key extends WPJM_BaseTest {

	const JOB_ID_COOKIE  = 'wp-job-manager-submitting-job-id';
	const JOB_KEY_COOKIE = 'wp-job-manager-submitting-job-key';

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';

		$_GET     = [];
		$_POST    = [];
		$_REQUEST = [];
		unset( $_COOKIE[ self::JOB_ID_COOKIE ], $_COOKIE[ self::JOB_KEY_COOKIE ] );
	}

	public function tearDown(): void {
		unset( $_COOKIE[ self::JOB_ID_COOKIE ], $_COOKIE[ self::JOB_KEY_COOKIE ] );
		$_GET     = [];
		$_POST    = [];
		$_REQUEST = [];
		parent::tearDown();
	}

	/**
	 * Creates a guest listing in preview with resume cookies set for it.
	 *
	 * @return array{0: int, 1: string} Job ID and submitting key.
	 */
	private function create_guest_submission() {
		$this->logout();

		$job_id = $this->factory->job_listing->create(
			[
				'post_author' => 0,
				'post_status' => 'preview',
			]
		);
		$key    = 'submitting-key-' . $job_id;
		update_post_meta( $job_id, '_submitting_key', $key );

		$_COOKIE[ self::JOB_ID_COOKIE ]  = (string) $job_id;
		$_COOKIE[ self::JOB_KEY_COOKIE ] = $key;

		return [ $job_id, $key ];
	}

	/**
	 * Runs the submit form's process() for a `new=1` request.
	 *
	 * @param array $query Query arguments in addition to `new=1`.
	 * @return bool True if process() tried to redirect.
	 */
	private function process_new_request( $query ) {
		$_GET = array_merge(
			[
				'job_manager_form' => 'submit-job',
				'new'              => '1',
			],
			$query
		);

		$stop_redirect = function () {
			throw new Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $stop_redirect );

		$redirected = false;
		try {
			$form = new WP_Job_Manager_Form_Submit_Job();
			$form->process();
		} catch ( Exception $e ) {
			$redirected = 'redirect' === $e->getMessage();
		}

		remove_filter( 'wp_redirect', $stop_redirect );

		return $redirected;
	}

	/**
	 * A `new=1` request without the key keeps the in-progress submission.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_new_without_key_keeps_submission() {
		[ $job_id, $key ] = $this->create_guest_submission();

		$this->process_new_request( [] );

		$this->assertSame( $key, get_post_meta( $job_id, '_submitting_key', true ), 'A reset without the key must not discard the submission.' );
	}

	/**
	 * A `new=1` request with a different key keeps the in-progress submission.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_new_with_wrong_key_keeps_submission() {
		[ $job_id, $key ] = $this->create_guest_submission();

		$this->process_new_request( [ 'key' => 'not-the-key' ] );

		$this->assertSame( $key, get_post_meta( $job_id, '_submitting_key', true ), 'A reset with the wrong key must not discard the submission.' );
	}

	/**
	 * A `new=1` request without the key resumes the in-progress submission, as a plain visit does.
	 */
	public function test_new_without_key_resumes_submission() {
		[ $job_id ] = $this->create_guest_submission();
		$_GET       = [
			'job_manager_form' => 'submit-job',
			'new'              => '1',
		];

		$form = new WP_Job_Manager_Form_Submit_Job();

		$this->assertSame( $job_id, $form->get_job_id() );
	}

	/**
	 * Control: with the matching key, the form doesn't resume the submission it's about to discard.
	 */
	public function test_new_with_matching_key_does_not_resume_submission() {
		[ , $key ] = $this->create_guest_submission();
		$_GET      = [
			'job_manager_form' => 'submit-job',
			'new'              => '1',
			'key'              => $key,
		];

		$form = new WP_Job_Manager_Form_Submit_Job();

		$this->assertSame( 0, $form->get_job_id() );
	}

	/**
	 * A malformed key is ignored without errors.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_new_with_array_key_keeps_submission() {
		[ $job_id, $key ] = $this->create_guest_submission();
		$_GET             = [
			'job_manager_form' => 'submit-job',
			'new'              => '1',
			'key'              => [ $key ],
		];

		// Called directly, so a PHP warning fails the test instead of being caught as a redirect.
		$form = new WP_Job_Manager_Form_Submit_Job();
		$form->process();

		$this->assertSame( $key, get_post_meta( $job_id, '_submitting_key', true ) );
	}

	/**
	 * A malformed job ID cookie is ignored without errors.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_new_with_array_job_id_cookie_keeps_submission() {
		[ $job_id, $key ]              = $this->create_guest_submission();
		$_COOKIE[ self::JOB_ID_COOKIE ] = [ (string) $job_id ];
		$_GET                          = [
			'job_manager_form' => 'submit-job',
			'new'              => '1',
			'key'              => $key,
		];

		// Called directly, so a PHP warning fails the test instead of being caught as a redirect.
		$form = new WP_Job_Manager_Form_Submit_Job();
		$form->process();

		$this->assertSame( $key, get_post_meta( $job_id, '_submitting_key', true ) );
	}

	/**
	 * Control: the "Create A New Job" link, which carries the key, still starts over.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_new_with_matching_key_resets_submission() {
		[ $job_id, $key ] = $this->create_guest_submission();

		$redirected = $this->process_new_request( [ 'key' => $key ] );

		$this->assertSame( '', get_post_meta( $job_id, '_submitting_key', true ) );
		$this->assertTrue( $redirected );
	}
}
