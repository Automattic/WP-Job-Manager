<?php

require_once JOB_MANAGER_PLUGIN_DIR . '/includes/admin/class-wp-job-manager-settings.php';
require_once WPJM_Unit_Tests_Bootstrap::instance()->includes_dir . '/stubs/class-wp-job-manager-admin-settings-stub.php';

class WP_Test_WP_Job_Manager_Settings extends WPJM_BaseTest {

	public function test_input_capabilities_should_not_fail_on_invalid_capabilities_provided() {
		$stub = WP_Job_Manager_Admin_Settings_Stub::instance();

		$values_to_test = array(
			null,
			0,
			'',
			'invalid',
			array(),
			new stdClass(),
		);

		$this->setOutputCallback( function() {} );
		$this->expectNotToPerformAssertions();

		foreach ( $values_to_test as $value ) {
			$stub->test_input_capabilities( [ 'name' => 'test' ], [], $value );
		}
	}

}

/**
 * Regression coverage for the `job_manager_direct_apply_url` setting and the
 * `templates/job-application.php` rendering branch it enables.
 */
class WP_Test_Job_Application_Direct_Apply_Template extends WPJM_BaseTest {

	/**
	 * Reset the option and the script queue after each test so a failed assertion
	 * (or an early-throwing render) does not leak state into the next case.
	 */
	public function tear_down() {
		delete_option( 'job_manager_direct_apply_url' );
		wp_dequeue_script( 'wp-job-manager-job-application' );
		parent::tearDown();
	}

	/**
	 * Render the job-application template after priming the global $post so
	 * `get_the_job_application_method()` resolves correctly in test scope.
	 *
	 * @param int $job_id Job listing ID.
	 * @return string Rendered HTML.
	 */
	private function render_job_application_template( $job_id ) {
		global $post;
		$post = get_post( $job_id );
		setup_postdata( $post );

		ob_start();
		get_job_manager_template( 'job-application.php' );
		$html = ob_get_clean();

		wp_reset_postdata();
		return $html;
	}

	/**
	 * Default (setting off): URL applications render the intermediate `<input>` panel.
	 */
	public function test_default_off_renders_input_button_for_url_application() {
		delete_option( 'job_manager_direct_apply_url' );
		wp_dequeue_script( 'wp-job-manager-job-application' );

		$job_id = $this->factory->job_listing->create(
			[
				'meta_input' => [ '_application' => 'https://example.com/apply' ],
			]
		);

		$html = $this->render_job_application_template( $job_id );

		$this->assertStringContainsString( '<input', $html );
		$this->assertStringContainsString( 'application_button', $html );
		$this->assertStringNotContainsString( '<a class="application_button', $html );
		$this->assertTrue(
			wp_script_is( 'wp-job-manager-job-application', 'enqueued' ),
			'Default (off) mode must enqueue the slide-toggle JS.'
		);
	}

	/**
	 * Setting on + URL application: renders an `<a>` direct link and skips the JS enqueue.
	 */
	public function test_direct_apply_on_renders_anchor_for_url_application() {
		update_option( 'job_manager_direct_apply_url', '1' );
		wp_dequeue_script( 'wp-job-manager-job-application' );

		$job_id = $this->factory->job_listing->create(
			[
				'meta_input' => [ '_application' => 'https://example.com/apply' ],
			]
		);

		$html = $this->render_job_application_template( $job_id );

		$this->assertStringContainsString( '<a class="application_button button"', $html );
		$this->assertStringContainsString( 'href="https://example.com/apply"', $html );
		$this->assertStringContainsString( 'rel="nofollow"', $html );
		$this->assertStringNotContainsString( '<input type="button" class="application_button', $html );

		$this->assertFalse(
			wp_script_is( 'wp-job-manager-job-application', 'enqueued' ),
			'Direct-apply mode must skip enqueuing the application-details JS.'
		);
	}

	/**
	 * Setting on + email application: still renders the `<input>` panel; setting is
	 * scoped to URL applications only.
	 */
	public function test_direct_apply_on_renders_input_button_for_email_application() {
		update_option( 'job_manager_direct_apply_url', '1' );
		wp_dequeue_script( 'wp-job-manager-job-application' );

		$job_id = $this->factory->job_listing->create(
			[
				'meta_input' => [ '_application' => 'apply@example.com' ],
			]
		);

		$html = $this->render_job_application_template( $job_id );

		$this->assertStringContainsString( '<input type="button" class="application_button', $html );
		$this->assertStringNotContainsString( '<a class="application_button', $html );
		$this->assertTrue(
			wp_script_is( 'wp-job-manager-job-application', 'enqueued' ),
			'Email applications must still enqueue the slide-toggle JS in direct-apply mode.'
		);
	}

	/**
	 * Direct-apply URL must not echo the "please visit host" paragraph from
	 * `templates/job-application-url.php` — that copy is redundant once a direct link
	 * is rendered. The core handler short-circuits under
	 * `WP_Job_Manager_Post_Types::application_details_url()` in direct-apply mode.
	 */
	public function test_direct_apply_on_does_not_render_url_details_paragraph() {
		update_option( 'job_manager_direct_apply_url', '1' );

		$job_id = $this->factory->job_listing->create(
			[
				'meta_input' => [ '_application' => 'https://example.com/apply' ],
			]
		);

		$html = $this->render_job_application_template( $job_id );

		$this->assertStringNotContainsString(
			'To apply for this job please visit',
			$html,
			'Direct-apply mode must suppress the URL details paragraph.'
		);
	}

	/**
	 * Regression: in direct-apply mode the addon extension surface must still fire so
	 * third-party listeners on `job_manager_application_details_url` see the event.
	 */
	public function test_direct_apply_on_fires_application_details_hook_for_url() {
		update_option( 'job_manager_direct_apply_url', '1' );
		$fired = 0;
		$callback = function () use ( &$fired ) {
			$fired++;
		};
		add_action( 'job_manager_application_details_url', $callback );

		try {
			$job_id = $this->factory->job_listing->create(
				[
					'meta_input' => [ '_application' => 'https://example.com/apply' ],
				]
			);

			$this->render_job_application_template( $job_id );
			$this->assertGreaterThan( 0, $fired, '`job_manager_application_details_url` must fire in direct-apply mode.' );
		} finally {
			remove_action( 'job_manager_application_details_url', $callback );
		}
	}
}

