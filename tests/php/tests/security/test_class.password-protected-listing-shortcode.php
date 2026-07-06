<?php
/**
 * Regression tests covering the [job] and [job_apply] shortcodes: a password-protected
 * listing embedded via these shortcodes must not leak its content or application details to
 * an anonymous visitor, while super admins retain access (parity with
 * WP_Job_Manager_Post_Types::job_content()).
 *
 * @package wp-job-manager/tests
 */

class Tests_Password_Protected_Listing_Shortcode extends WPJM_BaseTest {

	/**
	 * @covers WP_Job_Manager_Shortcodes::output_job
	 */
	public function test_job_shortcode_hides_protected_description_from_anonymous() {
		$protected = $this->factory->job_listing->create(
			[
				'post_password' => 'secret',
				'post_content'  => 'sentinel-COBALT protected description',
			]
		);
		$this->logout();

		$output = do_shortcode( '[job id="' . $protected . '"]' );

		$this->assertStringNotContainsString(
			'sentinel-COBALT',
			$output,
			'[job] must not render a password-protected listing description to anonymous visitors.'
		);
	}

	/**
	 * @covers WP_Job_Manager_Shortcodes::output_job_apply
	 */
	public function test_job_apply_shortcode_hides_application_email_from_anonymous() {
		$protected = $this->factory->job_listing->create(
			[
				'post_password' => 'secret',
				'meta_input'    => [ '_application' => 'apply-sentinel@example.test' ],
			]
		);
		$this->logout();

		$output = do_shortcode( '[job_apply id="' . $protected . '"]' );

		$this->assertStringNotContainsString(
			'apply-sentinel@example.test',
			html_entity_decode( $output ),
			'[job_apply] must not render a password-protected listing application method to anonymous visitors.'
		);
	}

	/**
	 * No-regression: a public (non-protected) listing still renders through [job].
	 *
	 * @covers WP_Job_Manager_Shortcodes::output_job
	 */
	public function test_job_shortcode_still_renders_public_listing() {
		$public = $this->factory->job_listing->create(
			[
				'post_content' => 'sentinel-VANILLA public description',
			]
		);
		$this->logout();

		$output = do_shortcode( '[job id="' . $public . '"]' );

		$this->assertStringContainsString(
			'sentinel-VANILLA',
			$output,
			'[job] must still render non-protected listings.'
		);
	}

	/**
	 * Parity with job_content(): a super admin keeps access to protected content via [job].
	 *
	 * @covers WP_Job_Manager_Shortcodes::output_job
	 */
	public function test_job_shortcode_allows_super_admin() {
		$protected = $this->factory->job_listing->create(
			[
				'post_password' => 'secret',
				'post_content'  => 'sentinel-MERIDIAN protected description',
			]
		);
		$this->login_as_admin();

		$output = do_shortcode( '[job id="' . $protected . '"]' );

		$this->assertStringContainsString(
			'sentinel-MERIDIAN',
			$output,
			'A super admin must retain access to protected listings via [job], matching job_content().'
		);
	}
}
