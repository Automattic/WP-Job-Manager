<?php

class WP_Test_Job_Dashboard_Shortcode extends WPJM_BaseTest {

	private $user_id;

	public function setUp(): void {
		parent::setUp();
		$this->user_id = $this->factory->user->create();
		wp_set_current_user( $this->user_id );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		delete_option( 'job_manager_enable_filled_expired' );
		parent::tearDown();
	}

	/**
	 * @covers \WP_Job_Manager\Job_Dashboard_Shortcode::get_job_actions
	 */
	public function test_expired_listing_has_no_filled_action_by_default() {
		$job = get_post( $this->factory->job_listing->create( [
			'post_status' => 'expired',
			'post_author' => $this->user_id,
		] ) );

		$actions = \WP_Job_Manager\Job_Dashboard_Shortcode::instance()->get_job_actions( $job );

		$this->assertArrayNotHasKey( 'mark_filled', $actions );
		$this->assertArrayNotHasKey( 'mark_not_filled', $actions );
	}

	/**
	 * @covers \WP_Job_Manager\Job_Dashboard_Shortcode::get_job_actions
	 */
	public function test_expired_listing_offers_mark_filled_when_setting_enabled() {
		update_option( 'job_manager_enable_filled_expired', '1' );

		$job = get_post( $this->factory->job_listing->create( [
			'post_status' => 'expired',
			'post_author' => $this->user_id,
			'meta_input'  => [ '_filled' => '0' ],
		] ) );

		$actions = \WP_Job_Manager\Job_Dashboard_Shortcode::instance()->get_job_actions( $job );

		$this->assertArrayHasKey( 'mark_filled', $actions );
		$this->assertArrayNotHasKey( 'mark_not_filled', $actions );
	}

	/**
	 * @covers \WP_Job_Manager\Job_Dashboard_Shortcode::get_job_actions
	 */
	public function test_expired_filled_listing_offers_mark_not_filled_when_setting_enabled() {
		update_option( 'job_manager_enable_filled_expired', '1' );

		$job = get_post( $this->factory->job_listing->create( [
			'post_status' => 'expired',
			'post_author' => $this->user_id,
			'meta_input'  => [ '_filled' => '1' ],
		] ) );

		$actions = \WP_Job_Manager\Job_Dashboard_Shortcode::instance()->get_job_actions( $job );

		$this->assertArrayHasKey( 'mark_not_filled', $actions );
		$this->assertArrayNotHasKey( 'mark_filled', $actions );
	}

	/**
	 * @covers \WP_Job_Manager\Job_Dashboard_Shortcode::get_job_actions
	 */
	public function test_published_listing_offers_mark_filled_regardless_of_setting() {
		update_option( 'job_manager_enable_filled_expired', '0' );

		$job = get_post( $this->factory->job_listing->create( [
			'post_status' => 'publish',
			'post_author' => $this->user_id,
			'meta_input'  => [ '_filled' => '0' ],
		] ) );

		$actions = \WP_Job_Manager\Job_Dashboard_Shortcode::instance()->get_job_actions( $job );

		$this->assertArrayHasKey( 'mark_filled', $actions );
	}
}
