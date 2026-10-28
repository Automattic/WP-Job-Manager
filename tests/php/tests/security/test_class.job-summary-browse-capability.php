<?php
/**
 * Tests that the [job_summary] shortcode respects the listing capabilities: a random summary
 * lists listings, so it needs the browse capability; a summary of one listing by ID needs the
 * view capability, like [job].
 *
 * @package wp-job-manager/tests
 */
class Tests_Job_Summary_Browse_Capability extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		delete_option( 'job_manager_browse_job_listings_capability' );
		delete_option( 'job_manager_view_job_listing_capability' );
	}

	public function tearDown(): void {
		delete_option( 'job_manager_browse_job_listings_capability' );
		delete_option( 'job_manager_view_job_listing_capability' );
		parent::tearDown();
	}

	/**
	 * Creates a published listing with a recognizable title.
	 *
	 * @return int
	 */
	private function create_listing() {
		return $this->factory->job_listing->create( [ 'post_title' => 'Summary Gate Listing' ] );
	}

	/**
	 * A visitor who may not browse listings gets nothing from a random summary.
	 *
	 * @covers WP_Job_Manager_Shortcodes::output_job_summary
	 */
	public function test_random_summary_empty_for_browse_denied_visitor() {
		$this->create_listing();
		update_option( 'job_manager_browse_job_listings_capability', [ 'manage_options' ] );
		$this->logout();

		$this->assertSame( '', do_shortcode( '[job_summary]' ) );
	}

	/**
	 * A summary of one listing by ID only needs the view capability.
	 *
	 * @covers WP_Job_Manager_Shortcodes::output_job_summary
	 */
	public function test_summary_by_id_renders_for_browse_denied_visitor_who_may_view() {
		$listing_id = $this->create_listing();
		update_option( 'job_manager_browse_job_listings_capability', [ 'manage_options' ] );
		$this->logout();

		$this->assertStringContainsString( 'Summary Gate Listing', do_shortcode( '[job_summary id="' . $listing_id . '"]' ) );
	}

	/**
	 * A summary of one listing by ID is empty when the visitor may not view it.
	 *
	 * @covers WP_Job_Manager_Shortcodes::output_job_summary
	 */
	public function test_summary_by_id_empty_for_view_denied_visitor() {
		$listing_id = $this->create_listing();
		update_option( 'job_manager_view_job_listing_capability', [ 'manage_options' ] );
		$this->logout();

		$this->assertStringNotContainsString( 'Summary Gate Listing', do_shortcode( '[job_summary id="' . $listing_id . '"]' ) );
	}

	/**
	 * Control: with no capabilities configured the summary renders.
	 *
	 * @covers WP_Job_Manager_Shortcodes::output_job_summary
	 */
	public function test_summary_renders_when_unrestricted() {
		$this->create_listing();
		$this->logout();

		$this->assertStringContainsString( 'Summary Gate Listing', do_shortcode( '[job_summary]' ) );
	}

	/**
	 * Control: a user who may browse listings still sees the summary.
	 *
	 * @covers WP_Job_Manager_Shortcodes::output_job_summary
	 */
	public function test_summary_renders_for_browse_allowed_user() {
		$this->create_listing();
		update_option( 'job_manager_browse_job_listings_capability', [ 'manage_options' ] );
		$this->login_as_admin();

		$this->assertStringContainsString( 'Summary Gate Listing', do_shortcode( '[job_summary]' ) );
	}
}
