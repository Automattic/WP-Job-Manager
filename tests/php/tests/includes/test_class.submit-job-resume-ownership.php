<?php
/**
 * Tests that stale submission cookies cannot resume another user's job draft.
 *
 * @package wp-job-manager
 */
class WP_Test_Submit_Job_Resume_Ownership extends WPJM_BaseTest {

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
		delete_option( 'job_manager_paid_listings_flow' );
	}

	public function tearDown(): void {
		delete_option( 'job_manager_paid_listings_flow' );
		unset( $_COOKIE[ self::JOB_ID_COOKIE ], $_COOKIE[ self::JOB_KEY_COOKIE ] );
		$_GET     = [];
		$_POST    = [];
		$_REQUEST = [];
		parent::tearDown();
	}

	/**
	 * @return array[] Paid-listings flow values.
	 */
	public function paid_listings_flows() {
		return [
			'default flow'      => [ '' ],
			'before-submit flow' => [ 'before' ],
		];
	}

	/**
	 * Creates a resumable job listing.
	 *
	 * @param int    $author_id Author user ID, or 0 for a guest submission.
	 * @param string $status    Job status.
	 * @return array{0: int, 1: string} Job ID and submitting key.
	 */
	private function create_resumable_job( $author_id, $status = 'preview' ) {
		$job_id = $this->factory->job_listing->create(
			[
				'post_author' => $author_id,
				'post_status' => $status,
			]
		);
		$key    = 'resume-key-' . $job_id;

		update_post_meta( $job_id, '_submitting_key', $key );

		return [ $job_id, $key ];
	}

	/**
	 * Sets the resume cookies for a job listing.
	 *
	 * @param int    $job_id Job listing ID.
	 * @param string $key    Submitting key.
	 */
	private function set_resume_cookies( $job_id, $key ) {
		$_COOKIE[ self::JOB_ID_COOKIE ]  = (string) $job_id;
		$_COOKIE[ self::JOB_KEY_COOKIE ] = $key;
	}

	/**
	 * Instantiates the submit form.
	 *
	 * @param string $flow Paid-listings flow option.
	 * @return WP_Job_Manager_Form_Submit_Job
	 */
	private function load_form( $flow = '' ) {
		if ( '' === $flow ) {
			delete_option( 'job_manager_paid_listings_flow' );
		} else {
			update_option( 'job_manager_paid_listings_flow', $flow );
		}

		return new WP_Job_Manager_Form_Submit_Job();
	}

	/**
	 * Instantiates the submit form with resume cookies set.
	 *
	 * @param int    $job_id Job listing ID.
	 * @param string $key    Submitting key.
	 * @param string $flow   Paid-listings flow option.
	 * @return WP_Job_Manager_Form_Submit_Job
	 */
	private function load_form_from_cookie( $job_id, $key, $flow = '' ) {
		$this->set_resume_cookies( $job_id, $key );

		return $this->load_form( $flow );
	}

	/**
	 * Asserts that the expected resume cookies remain available to the request.
	 *
	 * @param int    $job_id Job listing ID.
	 * @param string $key    Submitting key.
	 */
	private function assert_resume_cookies_preserved( $job_id, $key ) {
		$this->assertSame( (string) $job_id, $_COOKIE[ self::JOB_ID_COOKIE ] );
		$this->assertSame( $key, $_COOKIE[ self::JOB_KEY_COOKIE ] );
	}

	/**
	 * Asserts that stale resume cookies were removed from the current request.
	 */
	private function assert_resume_cookies_cleared() {
		$this->assertArrayNotHasKey( self::JOB_ID_COOKIE, $_COOKIE );
		$this->assertArrayNotHasKey( self::JOB_KEY_COOKIE, $_COOKIE );
	}

	/**
	 * An owner can resume their own preview listing.
	 *
	 * @dataProvider paid_listings_flows
	 * @param string $flow Paid-listings flow option.
	 */
	public function test_logged_in_user_can_resume_own_listing( $flow ) {
		$this->login_as_employer();
		[ $job_id, $key ] = $this->create_resumable_job( get_current_user_id() );

		$form = $this->load_form_from_cookie( $job_id, $key, $flow );

		$this->assertSame( $job_id, $form->get_job_id() );
		$this->assert_resume_cookies_preserved( $job_id, $key );
	}

	/**
	 * A different logged-in user cannot resume another user's preview listing.
	 *
	 * @dataProvider paid_listings_flows
	 * @param string $flow Paid-listings flow option.
	 */
	public function test_logged_in_user_cannot_resume_another_users_listing( $flow ) {
		$this->login_as_employer();
		[ $job_id, $key ] = $this->create_resumable_job( get_current_user_id() );

		$this->login_as_employer_b();
		$this->set_resume_cookies( $job_id, $key );
		WPJM()->validate_job_posting_cookies();
		$form = $this->load_form( $flow );

		$this->assertSame( 0, $form->get_job_id() );
		$this->assert_resume_cookies_preserved( $job_id, $key );

		$this->login_as_employer();
		$form = $this->load_form( $flow );

		$this->assertSame( $job_id, $form->get_job_id() );
	}

	/**
	 * A guest can resume a guest-authored preview listing when the key matches.
	 *
	 * @dataProvider paid_listings_flows
	 * @param string $flow Paid-listings flow option.
	 */
	public function test_guest_can_resume_guest_listing( $flow ) {
		$this->logout();
		[ $job_id, $key ] = $this->create_resumable_job( 0 );

		$this->assertSame( 0, (int) get_post_field( 'post_author', $job_id ) );

		$form = $this->load_form_from_cookie( $job_id, $key, $flow );

		$this->assertSame( $job_id, $form->get_job_id() );
		$this->assert_resume_cookies_preserved( $job_id, $key );
	}

	/**
	 * A guest-authored listing can be resumed after the guest logs in.
	 *
	 * @dataProvider paid_listings_flows
	 * @param string $flow Paid-listings flow option.
	 */
	public function test_guest_can_log_in_and_resume_guest_listing( $flow ) {
		$this->logout();
		[ $job_id, $key ] = $this->create_resumable_job( 0 );
		$this->set_resume_cookies( $job_id, $key );

		$this->login_as_employer();
		WPJM()->validate_job_posting_cookies();
		$form = $this->load_form( $flow );

		$this->assertSame( $job_id, $form->get_job_id() );
		$this->assert_resume_cookies_preserved( $job_id, $key );
	}

	/**
	 * A registered user's listing remains resumable after their session expires.
	 *
	 * @dataProvider paid_listings_flows
	 * @param string $flow Paid-listings flow option.
	 */
	public function test_user_can_resume_listing_after_session_expires_and_is_restored( $flow ) {
		$this->login_as_employer();
		[ $job_id, $key ] = $this->create_resumable_job( get_current_user_id() );
		$this->set_resume_cookies( $job_id, $key );

		$this->login_as( 0 );
		WPJM()->validate_job_posting_cookies();
		$form = $this->load_form( $flow );

		$this->assertSame( 0, $form->get_job_id() );
		$this->assert_resume_cookies_preserved( $job_id, $key );

		$this->login_as_employer();
		$form = $this->load_form( $flow );

		$this->assertSame( $job_id, $form->get_job_id() );
	}

	/**
	 * A guest cannot resume a listing authored by a registered user.
	 *
	 * @dataProvider paid_listings_flows
	 * @param string $flow Paid-listings flow option.
	 */
	public function test_guest_cannot_resume_registered_users_listing( $flow ) {
		$this->login_as_employer();
		[ $job_id, $key ] = $this->create_resumable_job( get_current_user_id() );

		$this->logout();
		$this->set_resume_cookies( $job_id, $key );
		WPJM()->validate_job_posting_cookies();
		$form = $this->load_form( $flow );

		$this->assertSame( 0, $form->get_job_id() );
		$this->assert_resume_cookies_preserved( $job_id, $key );
	}

	/**
	 * The ownership check also applies to pending-payment resumes.
	 */
	public function test_logged_in_user_can_resume_own_pending_payment_listing() {
		$this->login_as_employer();
		[ $job_id, $key ] = $this->create_resumable_job( get_current_user_id(), 'pending_payment' );

		$allow_pending_payment = static function ( $statuses ) {
			$statuses[] = 'pending_payment';
			return $statuses;
		};
		add_filter( 'job_manager_valid_submit_job_statuses', $allow_pending_payment );

		try {
			$form = $this->load_form_from_cookie( $job_id, $key, 'before' );
			$this->assertSame( $job_id, $form->get_job_id() );
		} finally {
			remove_filter( 'job_manager_valid_submit_job_statuses', $allow_pending_payment );
		}
	}

	/**
	 * Cookie validation runs after post types and before submitted forms are loaded.
	 */
	public function test_cookie_validation_runs_on_early_init() {
		$this->assertSame( 0, has_action( 'init', [ WPJM()->post_types, 'register_post_types' ] ) );
		$this->assertSame( 1, has_action( 'init', [ WPJM(), 'validate_job_posting_cookies' ] ) );
		$this->assertSame( 10, has_action( 'init', [ WPJM()->forms, 'load_posted_form' ] ) );
	}

	/**
	 * An incorrect submitting key is rejected and removed during early validation.
	 */
	public function test_incorrect_submitting_key_is_rejected_and_cookies_are_cleared() {
		$this->logout();
		[ $job_id ] = $this->create_resumable_job( 0 );
		$this->set_resume_cookies( $job_id, 'incorrect-key' );

		WPJM()->validate_job_posting_cookies();

		$this->assert_resume_cookies_cleared();
	}

	/**
	 * The form still rejects an invalid key if early validation did not run.
	 */
	public function test_form_rejects_incorrect_submitting_key_without_clearing_cookies() {
		$this->logout();
		[ $job_id ] = $this->create_resumable_job( 0 );

		$form = $this->load_form_from_cookie( $job_id, 'incorrect-key' );

		$this->assertSame( 0, $form->get_job_id() );
		$this->assert_resume_cookies_preserved( $job_id, 'incorrect-key' );
	}

	/**
	 * A non-resumable job status is rejected and removed during early validation.
	 */
	public function test_non_resumable_status_is_rejected_and_cookies_are_cleared() {
		$this->logout();
		[ $job_id, $key ] = $this->create_resumable_job( 0, 'draft' );
		$this->set_resume_cookies( $job_id, $key );

		WPJM()->validate_job_posting_cookies();

		$this->assert_resume_cookies_cleared();
	}

	/**
	 * Missing jobs are rejected without leaving a stale-cookie loop.
	 */
	public function test_missing_job_is_rejected_and_cookies_are_cleared() {
		$this->logout();
		$this->set_resume_cookies( 99999999, 'missing-key' );

		WPJM()->validate_job_posting_cookies();

		$this->assert_resume_cookies_cleared();
	}

	/**
	 * A matching key on a different post type cannot be used as a resume target.
	 */
	public function test_non_job_post_is_rejected_and_cookies_are_cleared() {
		$this->logout();
		$post_id = $this->factory->post->create(
			[
				'post_author' => 0,
				'post_status' => 'preview',
			]
		);
		$key     = 'resume-key-' . $post_id;
		update_post_meta( $post_id, '_submitting_key', $key );
		$this->set_resume_cookies( $post_id, $key );

		WPJM()->validate_job_posting_cookies();

		$this->assert_resume_cookies_cleared();
	}

	/**
	 * @return array[] Incomplete cookie pairs.
	 */
	public function incomplete_resume_cookie_pairs() {
		return [
			'missing job ID' => [ [ self::JOB_KEY_COOKIE => 'resume-key' ] ],
			'missing key'    => [ [ self::JOB_ID_COOKIE => '123' ] ],
		];
	}

	/**
	 * An incomplete resume cookie pair is removed during early validation.
	 *
	 * @dataProvider incomplete_resume_cookie_pairs
	 * @param array $cookies Resume cookies for the request.
	 */
	public function test_incomplete_resume_cookie_pair_is_cleared( $cookies ) {
		$_COOKIE = array_merge( $_COOKIE, $cookies );

		WPJM()->validate_job_posting_cookies();

		$this->assert_resume_cookies_cleared();
	}

	/**
	 * An empty resume cookie pair is removed during early validation.
	 */
	public function test_empty_resume_cookie_pair_is_cleared() {
		$this->set_resume_cookies( 0, '' );

		WPJM()->validate_job_posting_cookies();

		$this->assert_resume_cookies_cleared();
	}
}
