<?php

use WP_Job_Manager\Job_Dashboard_Shortcode;

class WP_Test_Job_Dashboard_Shortcode extends WPJM_BaseTest {

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
}
