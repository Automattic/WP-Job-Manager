<?php
/**
 * Regression tests: publishing a preview listing must serialize the submission-limit
 * check under a per-user advisory lock, so concurrent "continue" requests cannot each
 * pass a stale count and exceed the configured limit (CWE-367 TOCTOU).
 *
 * The race itself cannot be reproduced deterministically in a single-threaded test, so
 * these assert the observable contract of the fix: the lock is held across the
 * check-and-publish critical section and released afterwards; the lock is best-effort
 * (publishing proceeds when it cannot be held, protected by a fresh status re-read);
 * a listing already published by a racing request — including one whose publish this
 * request's object cache has not seen — is detected and not counted against the user;
 * and the limit is still enforced on the publish path.
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

		// The lock only guards a submission-limit check that can fail, so these tests
		// configure a limit; individual tests override the value where it matters.
		update_option( 'job_manager_submission_limit', 5 );
	}

	public function tearDown(): void {
		remove_filter( 'submit_job_post_status', [ $this, 'capture_lock_state' ] );
		delete_option( 'job_manager_submission_limit' );
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
		$lock_name                       = $this->submission_lock_name( get_current_user_id() );
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
	 * The form is built *after* the request superglobals are populated because the
	 * constructor resolves $this->job_id from them. An optional factory lets a test
	 * supply a subclass that instruments the locking; it is invoked at that same point.
	 *
	 * @param int           $job_id  Job listing ID.
	 * @param callable|null $factory Optional () => WP_Job_Manager_Form_Submit_Job.
	 *
	 * @return WP_Job_Manager_Form_Submit_Job The form that handled the request.
	 */
	private function continue_publish( $job_id, $factory = null ) {
		$_POST = [
			'job_id'      => $job_id,
			'continue'    => '1',
			'_wpjm_nonce' => wp_create_nonce( 'preview-job-' . $job_id ),
		];
		foreach ( $_POST as $key => $value ) {
			$_REQUEST[ $key ] = $value;
		}

		$form = null === $factory ? new WP_Job_Manager_Form_Submit_Job() : $factory();
		$form->preview_handler();

		return $form;
	}

	/**
	 * The production lock-name definition, read via reflection so the tests never
	 * hard-code the scheme independently (which would silently pass against a stale name).
	 *
	 * @param int $user_id User ID to scope the lock to.
	 *
	 * @return string
	 */
	private function submission_lock_name( $user_id ) {
		$form   = ( new ReflectionClass( WP_Job_Manager_Form_Submit_Job::class ) )->newInstanceWithoutConstructor();
		$method = new ReflectionMethod( $form, 'submission_lock_name' );
		$method->setAccessible( true );

		return $method->invoke( $form, $user_id );
	}

	/**
	 * Captures errors rendered by the form as a single string.
	 *
	 * @param WP_Job_Manager_Form_Submit_Job $form Form to read errors from.
	 *
	 * @return string
	 */
	private function rendered_errors( $form ) {
		ob_start();
		$form->show_errors();

		return trim( ob_get_clean() );
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
		$this->assertNotSame(
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
		$lock_name = $this->submission_lock_name( $user );
		// IS_FREE_LOCK returns 1 when the lock is not held by any session.
		$is_free = $wpdb->get_var( $wpdb->prepare( 'SELECT IS_FREE_LOCK(%s)', $lock_name ) );
		$this->assertSame(
			'1',
			(string) $is_free,
			'The submission lock must be released after publishing.'
		);
	}

	/**
	 * With no submission limit configured and no filter on the check, the lock is not
	 * taken at all — there is nothing for it to protect, and taking it would add a
	 * blocking query to every publish on default installs.
	 */
	public function test_lock_skipped_when_no_limit_configured() {
		delete_option( 'job_manager_submission_limit' );
		$user = $this->factory->user->create( [ 'role' => 'author' ] );
		wp_set_current_user( $user );
		$job_id = $this->create_preview_listing( $user );

		add_filter( 'submit_job_post_status', [ $this, 'capture_lock_state' ] );
		$this->continue_publish( $job_id );

		$this->assertNull(
			$this->lock_state_during_publish,
			'No lock should be taken when the submission-limit check cannot fail.'
		);
		$this->assertNotSame(
			'preview',
			get_post_status( $job_id ),
			'The listing should still publish without the lock.'
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
		$form   = $this->continue_publish( $job_id );

		$this->assertSame(
			'preview',
			get_post_status( $job_id ),
			'A listing over the submission limit must not be published.'
		);
		$this->assertNotEmpty(
			$this->rendered_errors( $form ),
			'The over-limit refusal must be explained to the user.'
		);
	}

	/**
	 * A scheduled listing counts towards the submission limit: `future` is a committed
	 * submission that WP-Cron publishes with no further check, so excluding it would
	 * let a user bypass the limit by scheduling every listing.
	 */
	public function test_scheduled_listings_count_towards_limit() {
		update_option( 'job_manager_submission_limit', 1 );
		$user = $this->factory->user->create( [ 'role' => 'author' ] );
		wp_set_current_user( $user );

		// One scheduled listing consumes the quota of 1.
		$this->factory->job_listing->create(
			[
				'post_status' => 'future',
				'post_author' => $user,
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ),
			]
		);

		$job_id = $this->create_preview_listing( $user );
		$this->continue_publish( $job_id );

		$this->assertSame(
			'preview',
			get_post_status( $job_id ),
			'A scheduled listing must count against the limit like any committed submission.'
		);
	}

	/**
	 * The lock is best-effort: when it cannot be acquired (backend without GET_LOCK,
	 * timeout behind a slow publish, transient database error), the publish proceeds
	 * on the unserialized path — protected by the status re-read — instead of failing.
	 */
	public function test_publish_proceeds_when_lock_unavailable() {
		$user = $this->factory->user->create( [ 'role' => 'author' ] );
		wp_set_current_user( $user );
		$job_id = $this->create_preview_listing( $user );

		$form = $this->continue_publish(
			$job_id,
			function () {
				return new class() extends WP_Job_Manager_Form_Submit_Job {
					protected function acquire_submission_lock() {
						return null;
					}
				};
			}
		);

		$this->assertNotSame(
			'preview',
			get_post_status( $job_id ),
			'The publish must proceed when the submission lock cannot be acquired.'
		);
		$this->assertEmpty(
			$this->rendered_errors( $form ),
			'The best-effort fallback must not surface a lock error.'
		);
	}

	/**
	 * If a concurrent "continue" request publishes the listing while this request waits on
	 * the lock, re-reading under the lock must skip the publish/limit check instead of
	 * counting the just-published listing against the user and raising a spurious limit
	 * error.
	 */
	public function test_stale_preview_published_by_race_is_not_counted() {
		update_option( 'job_manager_submission_limit', 1 );
		$user = $this->factory->user->create( [ 'role' => 'author' ] );
		wp_set_current_user( $user );
		$job_id = $this->create_preview_listing( $user );

		// The subclass publishes the listing at lock-acquire time — i.e. after the handler
		// first read it as `preview` but before it re-reads under the lock — standing in
		// for a racing request that won the lock first.
		$form = $this->continue_publish(
			$job_id,
			function () use ( $job_id ) {
				$form                 = new class() extends WP_Job_Manager_Form_Submit_Job {
					public $race_publish_id = 0;

					protected function acquire_submission_lock() {
						if ( $this->race_publish_id ) {
							wp_update_post(
								[
									'ID'          => $this->race_publish_id,
									'post_status' => 'publish',
								]
							);
						}

						return parent::acquire_submission_lock();
					}
				};
				$form->race_publish_id = $job_id;

				return $form;
			}
		);

		$this->assertSame(
			'publish',
			get_post_status( $job_id ),
			'The listing published by the racing request must remain published.'
		);
		$this->assertEmpty(
			$this->rendered_errors( $form ),
			'Re-reading the published listing under the lock must not raise a limit error.'
		);
	}

	/**
	 * The re-read must see a racing publish committed by ANOTHER process. Such a
	 * publish runs clean_post_cache() in its own process, which cannot invalidate this
	 * request's in-process object cache — so the handler must drop its cached copy
	 * before re-reading, or the guard reads stale `preview` and the uncached limit
	 * COUNT (which does see the publish) raises a spurious over-limit error.
	 */
	public function test_race_guard_survives_stale_object_cache() {
		global $wpdb;

		update_option( 'job_manager_submission_limit', 1 );
		$user = $this->factory->user->create( [ 'role' => 'author' ] );
		wp_set_current_user( $user );
		$job_id = $this->create_preview_listing( $user );

		// Prime this request's object cache with the `preview` row, then publish via a
		// direct UPDATE — bypassing wp_update_post and clean_post_cache — the way a
		// concurrent request's commit appears to this process: visible to SQL, invisible
		// to the object cache.
		get_post( $job_id );
		$wpdb->update( $wpdb->posts, [ 'post_status' => 'publish' ], [ 'ID' => $job_id ] );

		$this->assertSame(
			'preview',
			get_post( $job_id )->post_status,
			'Fixture precondition: the object cache still holds the stale preview row.'
		);

		$form = $this->continue_publish( $job_id );

		$this->assertEmpty(
			$this->rendered_errors( $form ),
			'A racing publish this process has not seen must not raise a spurious limit error.'
		);
		clean_post_cache( $job_id );
		$this->assertSame(
			'publish',
			get_post_status( $job_id ),
			'The listing published by the racing request must remain published.'
		);
	}
}
