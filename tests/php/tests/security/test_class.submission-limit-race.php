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

	/**
	 * The employer running each test's submission.
	 *
	 * @var int
	 */
	private $user;

	/**
	 * A preview listing owned by that employer.
	 *
	 * @var int
	 */
	private $job_id;

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';

		// The lock only guards a submission-limit check that can fail, so these tests
		// configure a limit; individual tests override the value where it matters.
		update_option( 'job_manager_submission_limit', 5 );

		$this->login_as_employer();
		$this->user   = get_current_user_id();
		$this->job_id = $this->factory->job_listing->create(
			[
				'post_status' => 'preview',
				'post_author' => $this->user,
			]
		);
	}

	public function tearDown(): void {
		remove_filter( 'submit_job_post_status', [ $this, 'capture_lock_state' ] );
		$_REQUEST = [];
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
		$lock_name                       = $this->submission_lock_name();
		$this->lock_state_during_publish = $wpdb->get_var( $wpdb->prepare( 'SELECT IS_USED_LOCK(%s)', $lock_name ) );
		return $status;
	}

	/**
	 * The production lock-name definition (scoped to the current user), read via
	 * reflection so the tests never hard-code the scheme independently (which would
	 * silently pass against a stale name).
	 *
	 * @return string
	 */
	private function submission_lock_name() {
		$form   = ( new ReflectionClass( WP_Job_Manager_Form_Submit_Job::class ) )->newInstanceWithoutConstructor();
		$method = new ReflectionMethod( $form, 'submission_lock_name' );
		$method->setAccessible( true );

		return $method->invoke( $form );
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
		add_filter( 'submit_job_post_status', [ $this, 'capture_lock_state' ] );
		$this->continue_from_preview( $this->job_id );

		$this->assertNotNull(
			$this->lock_state_during_publish,
			'The submission lock must be held while the listing is being published.'
		);
		$this->assertNotSame(
			'preview',
			get_post_status( $this->job_id ),
			'The listing should have been promoted out of preview.'
		);
	}

	/**
	 * The lock must be released once publishing completes, not leaked.
	 */
	public function test_submission_lock_released_after_publish() {
		$this->continue_from_preview( $this->job_id );

		global $wpdb;
		$lock_name = $this->submission_lock_name();
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

		add_filter( 'submit_job_post_status', [ $this, 'capture_lock_state' ] );
		$this->continue_from_preview( $this->job_id );

		$this->assertNull(
			$this->lock_state_during_publish,
			'No lock should be taken when the submission-limit check cannot fail.'
		);
		$this->assertNotSame(
			'preview',
			get_post_status( $this->job_id ),
			'The listing should still publish without the lock.'
		);
	}

	/**
	 * The submission limit is still enforced on the publish path.
	 */
	public function test_submission_limit_still_enforced() {
		update_option( 'job_manager_submission_limit', 1 );

		// One already-published listing consumes the quota of 1.
		$this->factory->job_listing->create(
			[
				'post_status' => 'publish',
				'post_author' => $this->user,
			]
		);

		$form = $this->continue_from_preview( $this->job_id );

		$this->assertSame(
			'preview',
			get_post_status( $this->job_id ),
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

		// One scheduled listing consumes the quota of 1.
		$this->factory->job_listing->create(
			[
				'post_status' => 'future',
				'post_author' => $this->user,
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ),
			]
		);

		$this->continue_from_preview( $this->job_id );

		$this->assertSame(
			'preview',
			get_post_status( $this->job_id ),
			'A scheduled listing must count against the limit like any committed submission.'
		);
	}

	/**
	 * The lock is best-effort: when it cannot be acquired (backend without GET_LOCK,
	 * timeout behind a slow publish, transient database error), the publish proceeds
	 * on the unserialized path — protected by the status re-read — instead of failing.
	 */
	public function test_publish_proceeds_when_lock_unavailable() {
		$form = $this->continue_from_preview(
			$this->job_id,
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
			get_post_status( $this->job_id ),
			'The publish must proceed when the submission lock cannot be acquired.'
		);
		$this->assertEmpty(
			$this->rendered_errors( $form ),
			'The best-effort fallback must not surface a lock error.'
		);
	}

	/**
	 * On the SQLite integration, GET_LOCK has no implementation: the statement is
	 * translated to a truthy expression that returns the string '1=1' with no error,
	 * rather than MySQL's '1'. That must be treated as "lock not held" and the publish
	 * must still proceed (best-effort) — a fail-closed reading refused every first-time
	 * preview publish on SQLite, which is why this fix was previously held back.
	 */
	public function test_publish_proceeds_when_get_lock_returns_sqlite_sentinel() {
		update_option( 'job_manager_submission_limit', 5 );

		$form = $this->continue_from_preview(
			$this->job_id,
			function () {
				return new class() extends WP_Job_Manager_Form_Submit_Job {
					protected function run_get_lock( $lock_name ) {
						return '1=1';
					}
				};
			}
		);

		$this->assertNotSame(
			'preview',
			get_post_status( $this->job_id ),
			"SQLite's '1=1' GET_LOCK result must not fail closed; the listing must still publish."
		);
		$this->assertEmpty(
			$this->rendered_errors( $form ),
			'No lock error must be surfaced on a backend without GET_LOCK.'
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
		$job_id = $this->job_id;

		// The subclass publishes the listing at lock-acquire time — i.e. after the handler
		// first read it as `preview` but before it re-reads under the lock — standing in
		// for a racing request that won the lock first.
		$form = $this->continue_from_preview(
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

		// Prime this request's object cache with the `preview` row, then publish via a
		// direct UPDATE — bypassing wp_update_post and clean_post_cache — the way a
		// concurrent request's commit appears to this process: visible to SQL, invisible
		// to the object cache.
		get_post( $this->job_id );
		$wpdb->update( $wpdb->posts, [ 'post_status' => 'publish' ], [ 'ID' => $this->job_id ] );

		$this->assertSame(
			'preview',
			get_post( $this->job_id )->post_status,
			'Fixture precondition: the object cache still holds the stale preview row.'
		);

		$form = $this->continue_from_preview( $this->job_id );

		$this->assertEmpty(
			$this->rendered_errors( $form ),
			'A racing publish this process has not seen must not raise a spurious limit error.'
		);
		clean_post_cache( $this->job_id );
		$this->assertSame(
			'publish',
			get_post_status( $this->job_id ),
			'The listing published by the racing request must remain published.'
		);
	}
}
