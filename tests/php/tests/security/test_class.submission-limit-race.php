<?php
/**
 * Regression tests: publishing a preview listing must serialize the submission-limit
 * check under a per-user advisory lock, so concurrent "continue" requests cannot each
 * pass a stale count and exceed the configured limit (CWE-367 TOCTOU).
 *
 * The race itself cannot be reproduced deterministically in a single-threaded test, so
 * these assert the lock is held across the check-and-publish critical section and is
 * released afterwards, and that the limit is still enforced on the publish path.
 *
 * @package wp-job-manager
 */
class Tests_Submission_Limit_Race extends WPJM_BaseTest {

	/**
	 * Value of IS_USED_LOCK captured while the listing is being published.
	 *
	 * @var mixed
	 */
	private $lock_state_during_publish = 'unchecked';

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';
	}

	public function tearDown(): void {
		remove_filter( 'submit_job_post_status', [ $this, 'capture_lock_state' ] );
		$_POST    = [];
		$_REQUEST = [];
		$_GET     = [];
		parent::tearDown();
	}

	/**
	 * Captures whether the per-user submission lock is held at publish time.
	 *
	 * IS_USED_LOCK returns the connection id holding the lock, or NULL when it is free.
	 *
	 * @param string $status Post status being applied.
	 *
	 * @return string
	 */
	public function capture_lock_state( $status ) {
		global $wpdb;
		$lock_name                       = 'wpjm_submit_' . get_current_user_id();
		$this->lock_state_during_publish = $wpdb->get_var( $wpdb->prepare( 'SELECT IS_USED_LOCK(%s)', $lock_name ) );
		return $status;
	}

	/**
	 * Creates a preview listing owned by the given user.
	 *
	 * @param int $author Author user ID.
	 *
	 * @return int Job listing ID.
	 */
	private function create_preview_listing( $author ) {
		return $this->factory->job_listing->create(
			[
				'post_status' => 'preview',
				'post_author' => $author,
			]
		);
	}

	/**
	 * Drives preview_handler() the way a "Continue" front-end POST does.
	 *
	 * @param int $job_id Job listing ID.
	 */
	private function continue_publish( $job_id ) {
		$_POST = [
			'job_id'      => $job_id,
			'continue'    => '1',
			'_wpjm_nonce' => wp_create_nonce( 'preview-job-' . $job_id ),
		];
		foreach ( $_POST as $key => $value ) {
			$_REQUEST[ $key ] = $value;
		}

		$form = new WP_Job_Manager_Form_Submit_Job();
		$form->preview_handler();
	}

	/**
	 * The per-user submission lock must be held while the listing is being published.
	 */
	public function test_submission_lock_held_during_publish() {
		$user = $this->factory->user->create( [ 'role' => 'author' ] );
		wp_set_current_user( $user );
		$job_id = $this->create_preview_listing( $user );

		add_filter( 'submit_job_post_status', [ $this, 'capture_lock_state' ] );
		$this->continue_publish( $job_id );

		$this->assertNotNull(
			$this->lock_state_during_publish,
			'The submission lock must be held while the listing is being published.'
		);
		$this->assertNotEquals(
			'preview',
			get_post_status( $job_id ),
			'The listing should have been promoted out of preview.'
		);
	}

	/**
	 * The lock must be released once publishing completes, not leaked.
	 */
	public function test_submission_lock_released_after_publish() {
		$user = $this->factory->user->create( [ 'role' => 'author' ] );
		wp_set_current_user( $user );
		$job_id = $this->create_preview_listing( $user );

		$this->continue_publish( $job_id );

		global $wpdb;
		$lock_name = 'wpjm_submit_' . $user;
		// IS_FREE_LOCK returns 1 when the lock is not held by any session.
		$is_free = $wpdb->get_var( $wpdb->prepare( 'SELECT IS_FREE_LOCK(%s)', $lock_name ) );
		$this->assertEquals(
			'1',
			(string) $is_free,
			'The submission lock must be released after publishing.'
		);
	}

	/**
	 * The submission limit is still enforced on the publish path.
	 */
	public function test_submission_limit_still_enforced() {
		update_option( 'job_manager_submission_limit', 1 );
		$user = $this->factory->user->create( [ 'role' => 'author' ] );
		wp_set_current_user( $user );

		// One already-published listing consumes the quota of 1.
		$this->factory->job_listing->create(
			[
				'post_status' => 'publish',
				'post_author' => $user,
			]
		);

		$job_id = $this->create_preview_listing( $user );
		$this->continue_publish( $job_id );

		update_option( 'job_manager_submission_limit', '' );

		$this->assertEquals(
			'preview',
			get_post_status( $job_id ),
			'A listing over the submission limit must not be published.'
		);
	}
}
