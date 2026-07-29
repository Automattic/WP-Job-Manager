<?php

use WP_Job_Manager\Job_Dashboard_Shortcode;

class WP_Test_Job_Dashboard_Shortcode extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		update_option( 'job_manager_submission_duration', 30 );
	}

	/**
	 * @covers \WP_Job_Manager\Job_Dashboard_Shortcode::get_job_actions
	 */
	public function test_published_job_has_deactivate_action() {
		$user_id = $this->get_user_by_role( 'employer' );
		$this->login_as( $user_id );
		$job = get_post( $this->factory->job_listing->create( [ 'post_author' => $user_id ] ) );

		$actions = Job_Dashboard_Shortcode::instance()->get_job_actions( $job );

		$this->assertArrayHasKey( 'deactivate', $actions );
		$this->assertArrayNotHasKey( 'reactivate', $actions );
	}

	/**
	 * @covers \WP_Job_Manager\Job_Dashboard_Shortcode::get_job_actions
	 */
	public function test_deactivated_job_has_reactivate_action() {
		$user_id = $this->get_user_by_role( 'employer' );
		$this->login_as( $user_id );
		$job = get_post(
			$this->factory->job_listing->create(
				[
					'post_author' => $user_id,
					'post_status' => 'wpjm_deactivated',
				]
			)
		);

		$actions = Job_Dashboard_Shortcode::instance()->get_job_actions( $job );

		$this->assertArrayHasKey( 'reactivate', $actions );
		$this->assertArrayNotHasKey( 'deactivate', $actions );
	}

	/**
	 * @covers \WP_Job_Manager\Job_Dashboard_Shortcode::get_primary_action
	 */
	public function test_reactivate_is_primary_action_for_deactivated_job() {
		$user_id = $this->get_user_by_role( 'employer' );
		$this->login_as( $user_id );
		$job = get_post(
			$this->factory->job_listing->create(
				[
					'post_author' => $user_id,
					'post_status' => 'wpjm_deactivated',
				]
			)
		);

		$actions        = Job_Dashboard_Shortcode::instance()->get_job_actions( $job );
		$primary_action = Job_Dashboard_Shortcode::get_primary_action( $job, $actions );

		$this->assertSame( 'reactivate', $primary_action['name'] );
	}

	/**
	 * @covers \WP_Job_Manager\Job_Dashboard_Shortcode::get_job_actions
	 */
	public function test_other_users_job_has_no_actions() {
		$owner_id = $this->get_user_by_role( 'employer' );
		$job      = get_post( $this->factory->job_listing->create( [ 'post_author' => $owner_id ] ) );

		$viewer_id = $this->get_user_by_role( 'employer', '_b' );
		$this->login_as( $viewer_id );

		$actions = Job_Dashboard_Shortcode::instance()->get_job_actions( $job );

		$this->assertEmpty( $actions );
	}

	/**
	 * @covers \WP_Job_Manager\Job_Dashboard_Shortcode::handle_actions
	 */
	public function test_handle_deactivate_action_transitions_status() {
		$user_id = $this->get_user_by_role( 'employer' );
		$this->login_as( $user_id );
		$job_id = $this->factory->job_listing->create(
			[
				'post_author' => $user_id,
				'post_status' => 'publish',
			]
		);

		$this->handle_dashboard_action( 'deactivate', $job_id );

		$this->assertSame( 'wpjm_deactivated', get_post_status( $job_id ) );
	}

	/**
	 * @covers \WP_Job_Manager\Job_Dashboard_Shortcode::handle_actions
	 */
	public function test_handle_reactivate_action_transitions_status() {
		$user_id = $this->get_user_by_role( 'employer' );
		$this->login_as( $user_id );
		$job_id = $this->factory->job_listing->create(
			[
				'post_author'   => $user_id,
				'post_status'   => 'wpjm_deactivated',
				'meta_input'    => [
					'_job_expires' => '',
				],
			]
		);

		$this->handle_dashboard_action( 'reactivate', $job_id );

		$this->assertSame( 'publish', get_post_status( $job_id ) );
	}

	/**
	 * Reactivating a listing whose expiry date passed while deactivated must
	 * reset _job_expires, otherwise the hourly expiry cron would flip it back
	 * to expired on the next run.
	 *
	 * @covers \WP_Job_Manager\Job_Dashboard_Shortcode::handle_actions
	 */
	public function test_handle_reactivate_action_resets_stale_expiry() {
		$user_id = $this->get_user_by_role( 'employer' );
		$this->login_as( $user_id );

		$past_date = ( new DateTimeImmutable( '-10 days', wp_timezone() ) )->format( 'Y-m-d' );
		$job_id    = $this->factory->job_listing->create(
			[
				'post_author' => $user_id,
				'post_status' => 'wpjm_deactivated',
				'meta_input'  => [
					'_job_expires' => $past_date,
				],
			]
		);

		$this->handle_dashboard_action( 'reactivate', $job_id );

		$this->assertSame( 'publish', get_post_status( $job_id ) );

		$expires = WP_Job_Manager_Post_Types::instance()->get_job_expiration( $job_id );
		$this->assertNotEmpty( $expires, 'Reactivate must set a new expiry date.' );
		$this->assertGreaterThan(
			current_datetime(),
			$expires,
			'Reactivated expiry must be in the future, not the stale past date.'
		);
	}

	/**
	 * @covers \WP_Job_Manager\Job_Dashboard_Shortcode::handle_actions
	 */
	public function test_handle_action_rejected_for_non_owner() {
		$owner_id = $this->get_user_by_role( 'employer' );
		$job_id   = $this->factory->job_listing->create(
			[
				'post_author' => $owner_id,
				'post_status' => 'publish',
			]
		);

		$viewer_id = $this->get_user_by_role( 'employer', '_b' );
		$this->login_as( $viewer_id );

		$this->handle_dashboard_action( 'deactivate', $job_id );

		$this->assertSame( 'publish', get_post_status( $job_id ), 'Non-owner must not change the listing status.' );
	}

	/**
	 * Drive handle_actions() with a valid nonce for the given action and job,
	 * neutralising the redirect/exit so the test can assert the result.
	 *
	 * @param string $action Action name.
	 * @param int    $job_id Job post ID.
	 */
	private function handle_dashboard_action( $action, $job_id ) {
		$nonce = wp_create_nonce( 'job_manager_my_job_actions' );

		$_REQUEST['action']  = $action;
		$_REQUEST['job_id']  = $job_id;
		$_REQUEST['_wpnonce'] = $nonce;

		// Prevent wp_safe_redirect()/exit from terminating the test run.
		add_filter(
			'wp_redirect',
			static function () {
				throw new RuntimeException( 'redirect intercepted' );
			}
		);

		// Bypass the is_job_dashboard_page() gate since we're not on a real dashboard page in unit tests.
		add_filter(
			'job_manager_should_run_shortcode_action_handler',
			static function () {
				return true;
			}
		);

		try {
			Job_Dashboard_Shortcode::instance()->handle_actions();
		} catch ( RuntimeException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Expected: redirect was intercepted.
		} finally {
			remove_all_filters( 'wp_redirect' );
			remove_all_filters( 'job_manager_should_run_shortcode_action_handler' );
			unset( $_REQUEST['action'], $_REQUEST['job_id'], $_REQUEST['_wpnonce'] );
		}
	}
}
