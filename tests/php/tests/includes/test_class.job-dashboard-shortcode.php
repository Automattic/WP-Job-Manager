<?php

namespace WP_Job_Manager;

/**
 * Tests for Job_Dashboard_Shortcode grouping by status.
 *
 * @group job-dashboard
 */
class WP_Test_Job_Dashboard_Shortcode extends \WPJM_BaseTest {

	/**
	 * @var Job_Dashboard_Shortcode
	 */
	private $shortcode;

	public function setUp(): void {
		parent::setUp();
		$this->shortcode = Job_Dashboard_Shortcode::instance();

		// Set up the current user as the post author.
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );
	}

	/**
	 * Test that default shortcode atts do not enable grouping.
	 */
	public function test_default_atts_no_grouping() {
		$this->factory->job_listing->create( [ 'post_author' => get_current_user_id() ] );
		$output = $this->shortcode->output_job_dashboard( [] );

		$this->assertStringNotContainsString( 'jm-dashboard-group', $output );
		$this->assertStringContainsString( 'jm-dashboard-rows', $output );
	}

	/**
	 * Test that group_by_status="yes" enables grouping.
	 */
	public function test_group_by_status_att_enables_grouping() {
		$this->factory->job_listing->create_many( 3, [ 'post_author' => get_current_user_id() ] );

		$output = $this->shortcode->output_job_dashboard( [ 'group_by_status' => 'yes' ] );

		$this->assertStringContainsString( 'jm-dashboard-group', $output );
	}

	/**
	 * Test status-to-group mapping via reflection on the private method.
	 */
	public function test_group_jobs_by_status_mapping() {
		$publish_job   = $this->factory->job_listing->create_and_get( [ 'post_status' => 'publish', 'post_author' => get_current_user_id() ] );
		$future_job    = $this->factory->job_listing->create_and_get( [ 'post_status' => 'future', 'post_author' => get_current_user_id() ] );
		$pending_job   = $this->factory->job_listing->create_and_get( [ 'post_status' => 'pending', 'post_author' => get_current_user_id() ] );
		$pending_pay   = $this->factory->job_listing->create_and_get( [ 'post_status' => 'pending_payment', 'post_author' => get_current_user_id() ] );
		$draft_job     = $this->factory->job_listing->create_and_get( [ 'post_status' => 'draft', 'post_author' => get_current_user_id() ] );
		$expired_job   = $this->factory->job_listing->create_and_get( [ 'post_status' => 'expired', 'post_author' => get_current_user_id() ] );

		$method = new \ReflectionMethod( $this->shortcode, 'group_jobs_by_status' );
		$method->setAccessible( true );

		$groups = $method->invoke( $this->shortcode, [ $publish_job, $future_job, $pending_job, $pending_pay, $draft_job, $expired_job ] );

		$active_ids   = wp_list_pluck( $groups['active'], 'ID' );
		$pending_ids  = wp_list_pluck( $groups['pending'], 'ID' );
		$inactive_ids = wp_list_pluck( $groups['inactive'], 'ID' );

		$this->assertCount( 2, $groups['active'], 'Active should contain publish and future' );
		$this->assertCount( 3, $groups['pending'], 'Pending should contain pending, pending_payment, and draft' );
		$this->assertCount( 1, $groups['inactive'], 'Inactive should contain expired' );

		$this->assertContains( $publish_job->ID, $active_ids );
		$this->assertContains( $future_job->ID, $active_ids );
		$this->assertContains( $pending_job->ID, $pending_ids );
		$this->assertContains( $pending_pay->ID, $pending_ids );
		$this->assertContains( $draft_job->ID, $pending_ids );
		$this->assertContains( $expired_job->ID, $inactive_ids );
	}

	/**
	 * Test that a filled publish job stays in the active group.
	 */
	public function test_filled_job_stays_in_active_group() {
		$job_id = $this->factory->job_listing->create(
			[
				'post_status' => 'publish',
				'post_author' => get_current_user_id(),
				'meta_input'  => [ '_filled' => 1 ],
			]
		);
		$job = get_post( $job_id );

		$method = new \ReflectionMethod( $this->shortcode, 'group_jobs_by_status' );
		$method->setAccessible( true );
		$groups = $method->invoke( $this->shortcode, [ $job ] );

		$this->assertCount( 1, $groups['active'], 'Filled publish job should be in active' );
		$this->assertCount( 0, $groups['pending'] );
		$this->assertCount( 0, $groups['inactive'] );
	}

	/**
	 * Test that empty groups are not rendered in the template output.
	 */
	public function test_empty_groups_hidden() {
		$this->factory->job_listing->create_many( 2, [ 'post_status' => 'publish', 'post_author' => get_current_user_id() ] );

		$output = $this->shortcode->output_job_dashboard( [ 'group_by_status' => 'yes' ] );

		// Only Active group should be present.
		$this->assertStringContainsString( 'jm-dashboard-group--active', $output );
		$this->assertStringNotContainsString( 'jm-dashboard-group--pending', $output );
		$this->assertStringNotContainsString( 'jm-dashboard-group--inactive', $output );
	}

	/**
	 * Test that search works across groups.
	 */
	public function test_search_filters_all_groups() {
		$this->factory->job_listing->create(
			[
				'post_title'  => 'Unique Search Target',
				'post_author' => get_current_user_id(),
			]
		);
		$this->factory->job_listing->create(
			[
				'post_status' => 'pending',
				'post_title'  => 'Another Job',
				'post_author' => get_current_user_id(),
			]
		);
		$this->factory->job_listing->create(
			[
				'post_status' => 'expired',
				'post_title'  => 'Yet Another',
				'post_author' => get_current_user_id(),
			]
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test env.
		$_GET['search'] = 'Unique Search Target';

		try {
			$output = $this->shortcode->output_job_dashboard( [ 'group_by_status' => 'yes' ] );

			// Should find the matching job in the active group.
			$this->assertStringContainsString( 'jm-dashboard-group--active', $output );

			// Empty groups (pending, inactive) should be hidden.
			$this->assertStringNotContainsString( 'jm-dashboard-group--pending', $output );
			$this->assertStringNotContainsString( 'jm-dashboard-group--inactive', $output );
		} finally {
			unset( $_GET['search'] );
		}
	}

	/**
	 * Test that the flat (non-grouped) path still works for backward compatibility.
	 */
	public function test_flat_non_grouped_default_is_backward_compatible() {
		$this->factory->job_listing->create_many( 3, [ 'post_author' => get_current_user_id() ] );

		$output = $this->shortcode->output_job_dashboard( [] );

		$this->assertStringContainsString( 'jm-dashboard-rows', $output );
		$this->assertStringNotContainsString( 'jm-dashboard-group', $output );
	}

}
