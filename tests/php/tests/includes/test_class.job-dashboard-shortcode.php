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

	/**
	 * @covers \WP_Job_Manager\Job_Dashboard_Shortcode::handle_actions
	 * @runInSeparateProcess
	 */
	public function test_handle_actions_marks_expired_listing_filled_when_setting_enabled() {
		update_option( 'job_manager_enable_filled_expired', '1' );

		$job = get_post( $this->factory->job_listing->create( [
			'post_status' => 'expired',
			'post_author' => $this->user_id,
			'meta_input'  => [ '_filled' => '0' ],
		] ) );

		$actions = \WP_Job_Manager\Job_Dashboard_Shortcode::instance()->get_job_actions( $job );
		$nonce    = wp_create_nonce( $actions['mark_filled']['nonce'] );

		add_filter( 'job_manager_should_run_shortcode_action_handler', '__return_true' );

		$_REQUEST = [
			'action'   => 'mark_filled',
			'job_id'   => $job->ID,
			'_wpnonce' => $nonce,
		];

		// Intercept the exit inside Redirect_Message::redirect so assertions run.
		register_shutdown_function( function () use ( $job ) {
			$this->assertSame( 1, (int) get_post_meta( $job->ID, '_filled', true ) );
		} );

		\WP_Job_Manager\Job_Dashboard_Shortcode::instance()->handle_actions();
	}

	/**
	 * @covers \WP_Job_Manager\Job_Dashboard_Shortcode::handle_actions
	 * @runInSeparateProcess
	 */
	public function test_handle_actions_refuses_mark_filled_on_non_owner_expired_listing() {
		$other_user_id = $this->factory->user->create();
		$job           = get_post( $this->factory->job_listing->create( [
			'post_status' => 'expired',
			'post_author' => $other_user_id,
			'meta_input'  => [ '_filled' => '0' ],
		] ) );

		$actions = \WP_Job_Manager\Job_Dashboard_Shortcode::instance()->get_job_actions( $job );
		// Non-owner cannot manage job, so get_job_actions() returns [] — use nonce action name directly.
		$nonce = wp_create_nonce( 'job_manager_my_job_actions' );

		add_filter( 'job_manager_should_run_shortcode_action_handler', '__return_true' );

		$_REQUEST = [
			'action'   => 'mark_filled',
			'job_id'   => $job->ID,
			'_wpnonce' => $nonce,
		];

		register_shutdown_function( function () use ( $job ) {
			$this->assertSame( '0', get_post_meta( $job->ID, '_filled', true ) );
		} );

		\WP_Job_Manager\Job_Dashboard_Shortcode::instance()->handle_actions();
	}

	/**
	 * @covers \WP_Job_Manager\Job_Dashboard_Shortcode::handle_actions
	 * @runInSeparateProcess
	 */
	public function test_handle_actions_refuses_invalid_nonce_on_expired_listing() {
		update_option( 'job_manager_enable_filled_expired', '1' );

		$job = get_post( $this->factory->job_listing->create( [
			'post_status' => 'expired',
			'post_author' => $this->user_id,
			'meta_input'  => [ '_filled' => '0' ],
		] ) );

		$_REQUEST = [
			'action'   => 'mark_filled',
			'job_id'   => $job->ID,
			'_wpnonce' => 'bad-nonce',
		];

		add_filter( 'job_manager_should_run_shortcode_action_handler', '__return_true' );

		register_shutdown_function( function () use ( $job ) {
			$this->assertSame( '0', get_post_meta( $job->ID, '_filled', true ) );
		} );

		\WP_Job_Manager\Job_Dashboard_Shortcode::instance()->handle_actions();
	}
}
