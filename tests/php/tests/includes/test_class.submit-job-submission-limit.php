<?php
/**
 * Tests that the submission limit is enforced when a listing is promoted from
 * the `preview` step to a published status, not only on form page load.
 *
 * @package wp-job-manager
 */
class WP_Test_Submit_Job_Submission_Limit extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';

		// Deterministic promotion target.
		update_option( 'job_manager_submission_requires_approval', 0 );
	}

	public function tearDown(): void {
		delete_option( 'job_manager_submission_limit' );
		delete_option( 'job_manager_submission_requires_approval' );
		unset( $_POST, $_REQUEST );
		$_POST    = [];
		$_REQUEST = [];
		parent::tearDown();
	}

	/**
	 * Drives WP_Job_Manager_Form_Submit_Job::preview_handler() for the "continue"
	 * action against a specific job, the way the preview step POST does.
	 *
	 * @param int $job_id Job listing to promote.
	 */
	private function continue_from_preview( $job_id ) {
		$form  = WP_Job_Manager_Form_Submit_Job::instance();
		$class = new ReflectionClass( $form );

		$job_id_prop = $class->getProperty( 'job_id' );
		$job_id_prop->setAccessible( true );
		$job_id_prop->setValue( $form, $job_id );

		$step_prop = $class->getProperty( 'step' );
		$step_prop->setAccessible( true );
		$step_prop->setValue( $form, 1 );

		$nonce                   = wp_create_nonce( 'preview-job-' . $job_id );
		$_POST                   = [
			'continue'    => '1',
			'job_id'      => $job_id,
			'_wpjm_nonce' => $nonce,
		];
		$_REQUEST['_wpjm_nonce'] = $nonce;

		$form->preview_handler();
	}

	/**
	 * A user over the submission limit cannot publish a stockpiled `preview`
	 * listing by completing the preview step.
	 */
	public function test_preview_listing_not_promoted_when_over_limit() {
		$this->login_as_employer();
		$user_id = get_current_user_id();

		update_option( 'job_manager_submission_limit', 1 );

		// Two published listings put the user over the limit of 1.
		$this->factory->job_listing->create_many( 2, [ 'post_author' => $user_id ] );

		$preview_id = $this->factory->job_listing->create(
			[
				'post_author' => $user_id,
				'post_status' => 'preview',
			]
		);

		$this->continue_from_preview( $preview_id );

		$this->assertEquals(
			'preview',
			get_post_status( $preview_id ),
			'Preview listing must not be promoted when the user is over the submission limit.'
		);
	}

	/**
	 * The limit is inclusive: a user who already has exactly the maximum number
	 * of listings cannot publish another previewed listing.
	 */
	public function test_preview_listing_not_promoted_at_exactly_limit() {
		$this->login_as_employer();
		$user_id = get_current_user_id();

		update_option( 'job_manager_submission_limit', 1 );

		// Exactly at the limit of 1.
		$this->factory->job_listing->create( [ 'post_author' => $user_id ] );

		$preview_id = $this->factory->job_listing->create(
			[
				'post_author' => $user_id,
				'post_status' => 'preview',
			]
		);

		$this->continue_from_preview( $preview_id );

		$this->assertEquals(
			'preview',
			get_post_status( $preview_id ),
			'A user at exactly the limit must not be able to publish another listing.'
		);
	}

	/**
	 * The limit re-check does not interfere with the normal submission flow:
	 * a user under the limit can publish their previewed listing.
	 */
	public function test_preview_listing_promoted_when_under_limit() {
		$this->login_as_employer();
		$user_id = get_current_user_id();

		update_option( 'job_manager_submission_limit', 5 );

		$preview_id = $this->factory->job_listing->create(
			[
				'post_author' => $user_id,
				'post_status' => 'preview',
			]
		);

		$this->continue_from_preview( $preview_id );

		$this->assertEquals(
			'publish',
			get_post_status( $preview_id ),
			'Preview listing should publish normally when the user is under the limit.'
		);
	}

	/**
	 * Renewing an already-counted `expired` listing is exempt from the limit,
	 * since republishing it does not increase the user's listing count.
	 */
	public function test_expired_listing_renewal_not_blocked_when_over_limit() {
		$this->login_as_employer();
		$user_id = get_current_user_id();

		update_option( 'job_manager_submission_limit', 1 );

		// A published listing plus the expired one puts the user over the limit.
		$this->factory->job_listing->create( [ 'post_author' => $user_id ] );

		$expired_id = $this->factory->job_listing->create(
			[
				'post_author' => $user_id,
				'post_status' => 'expired',
			]
		);

		$this->continue_from_preview( $expired_id );

		$this->assertEquals(
			'publish',
			get_post_status( $expired_id ),
			'Expired listing renewal must not be blocked by the submission limit.'
		);
	}
}
