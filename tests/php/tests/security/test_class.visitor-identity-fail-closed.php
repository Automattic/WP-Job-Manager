<?php
/**
 * Regression tests: checks keyed on the visitor's identity fail closed for visitors
 * without an account.
 *
 * A logged-out visitor's user ID is 0 and their request values can be empty strings.
 * Comparisons against stored values that can also be 0 or empty — an author-less post,
 * a meta key that was never written — must not authorize them.
 *
 * @package wp-job-manager/tests
 */

class Tests_Visitor_Identity_Fail_Closed extends WPJM_BaseTest {

	public function tearDown(): void {
		unset( $_GET['new'], $_GET['key'], $_COOKIE['wp-job-manager-submitting-job-key'], $_COOKIE['wp-job-manager-submitting-job-id'] );
		parent::tearDown();
	}

	/**
	 * A logged-out caller must not find anyone's listing on "their" dashboard: with user 0,
	 * the dashboard query's author filter would otherwise be dropped entirely.
	 *
	 * @covers WP_Job_Manager\Job_Dashboard_Shortcode::is_job_available_on_dashboard
	 */
	public function test_no_listing_is_available_on_a_guest_dashboard() {
		$owner  = $this->factory->user->create( [ 'role' => 'employer' ] );
		$job_id = $this->factory->job_listing->create(
			[
				'post_author' => $owner,
				'post_status' => 'pending',
			]
		);

		$this->logout();

		$this->assertFalse(
			( new WP_Job_Manager\Job_Dashboard_Shortcode() )->is_job_available_on_dashboard( get_post( $job_id ) ),
			'A visitor without an account must not have any listing on their dashboard.'
		);
	}

	/**
	 * Control: the owner still finds their listing on their dashboard.
	 *
	 * @covers WP_Job_Manager\Job_Dashboard_Shortcode::is_job_available_on_dashboard
	 */
	public function test_listing_is_available_on_the_owners_dashboard() {
		$owner  = $this->factory->user->create( [ 'role' => 'employer' ] );
		$job_id = $this->factory->job_listing->create(
			[
				'post_author' => $owner,
				'post_status' => 'pending',
			]
		);

		wp_set_current_user( $owner );

		$this->assertTrue(
			( new WP_Job_Manager\Job_Dashboard_Shortcode() )->is_job_available_on_dashboard( get_post( $job_id ) )
		);
	}

	/**
	 * An empty submitting key must not match a listing that never had one: both sides of
	 * the comparison are '' there.
	 *
	 * @covers WP_Job_Manager_Form::is_new_submission_request
	 */
	public function test_empty_submitting_key_is_not_a_new_submission_request() {
		// A listing with no _submitting_key meta, like one created in wp-admin.
		$job_id = $this->factory->job_listing->create();
		$this->assertSame( '', get_post_meta( $job_id, '_submitting_key', true ) );

		$_GET['new']                                     = '1';
		$_GET['key']                                     = '';
		$_COOKIE['wp-job-manager-submitting-job-key']    = '';
		$_COOKIE['wp-job-manager-submitting-job-id']     = (string) $job_id;

		$this->assertFalse( $this->is_new_submission_request(), 'An empty key must never be a valid submitting key.' );
	}

	/**
	 * Control: the real submitting key is still recognised.
	 *
	 * @covers WP_Job_Manager_Form::is_new_submission_request
	 */
	public function test_real_submitting_key_is_a_new_submission_request() {
		$job_id = $this->factory->job_listing->create();
		update_post_meta( $job_id, '_submitting_key', 'k3y-k3y-k3y' );

		$_GET['new']                                  = '1';
		$_GET['key']                                  = 'k3y-k3y-k3y';
		$_COOKIE['wp-job-manager-submitting-job-key'] = 'k3y-k3y-k3y';
		$_COOKIE['wp-job-manager-submitting-job-id']  = (string) $job_id;

		$this->assertTrue( $this->is_new_submission_request() );
	}

	private function is_new_submission_request() {
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';

		$form = WP_Job_Manager_Form_Submit_Job::instance();

		$method = new ReflectionMethod( $form, 'is_new_submission_request' );
		$method->setAccessible( true );

		return $method->invoke( $form );
	}
}
