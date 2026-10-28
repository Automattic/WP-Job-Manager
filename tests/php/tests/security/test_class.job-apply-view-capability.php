<?php
/**
 * Tests that the [job_apply] shortcode respects the view capability, like [job].
 *
 * @package wp-job-manager/tests
 */
class Tests_Job_Apply_View_Capability extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		delete_option( 'job_manager_view_job_listing_capability' );
	}

	public function tearDown(): void {
		delete_option( 'job_manager_view_job_listing_capability' );
		parent::tearDown();
	}

	/**
	 * Creates a published listing with an application email.
	 *
	 * @return int
	 */
	private function create_listing() {
		return $this->factory->job_listing->create( [ 'meta_input' => [ '_application' => 'apply-gate@example.test' ] ] );
	}

	/**
	 * A visitor who may not view the listing gets no application details.
	 *
	 * @covers WP_Job_Manager_Shortcodes::output_job_apply
	 */
	public function test_job_apply_empty_for_view_denied_visitor() {
		$listing_id = $this->create_listing();
		update_option( 'job_manager_view_job_listing_capability', [ 'manage_options' ] );
		$this->logout();

		$this->assertStringNotContainsString( 'job_application_email', do_shortcode( '[job_apply id="' . $listing_id . '"]' ) );
	}

	/**
	 * Control: with no capabilities configured the application details render.
	 *
	 * @covers WP_Job_Manager_Shortcodes::output_job_apply
	 */
	public function test_job_apply_renders_when_unrestricted() {
		$listing_id = $this->create_listing();
		$this->logout();

		$this->assertStringContainsString( 'job_application_email', do_shortcode( '[job_apply id="' . $listing_id . '"]' ) );
	}
}
