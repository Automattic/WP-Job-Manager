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

	public function data_ids_that_are_not_ids() {
		return [
			'text'           => [ 'x' ],
			'text and digit' => [ 'x1' ],
			'zero as text'   => [ '0x' ],
			'negative zero'  => [ '-0' ],
			'spaces'         => [ ' ' ],
		];
	}

	/**
	 * An ID that isn't a listing's ID shows nothing, in each shortcode that takes one, whether
	 * or not the visitor may browse listings.
	 *
	 * @dataProvider data_ids_that_are_not_ids
	 * @covers WP_Job_Manager_Shortcodes::output_job_summary
	 * @covers WP_Job_Manager_Shortcodes::output_job
	 * @covers WP_Job_Manager_Shortcodes::output_job_apply
	 */
	public function test_shortcodes_show_nothing_for_an_id_that_is_not_an_id( $id ) {
		$this->create_listing();

		foreach ( [ 'browse allowed', 'browse denied' ] as $case ) {
			if ( 'browse denied' === $case ) {
				update_option( 'job_manager_browse_job_listings_capability', [ 'manage_options' ] );
			}
			$this->logout();

			foreach ( [ 'job_summary', 'job', 'job_apply' ] as $shortcode ) {
				$output = do_shortcode( '[' . $shortcode . ' id="' . $id . '"]' );

				$this->assertStringNotContainsString( 'Summary Gate Listing', $output, "$shortcode, $case" );
				$this->assertStringNotContainsString( 'job_listing', $output, "$shortcode, $case" );
			}
		}
	}

	/**
	 * Control: a listing's ID still shows that listing in each shortcode.
	 *
	 * @covers WP_Job_Manager_Shortcodes::output_job
	 * @covers WP_Job_Manager_Shortcodes::output_job_apply
	 */
	public function test_shortcodes_show_the_listing_for_its_id() {
		$listing_id = $this->create_listing();
		$this->logout();

		$this->assertStringContainsString( 'Summary Gate Listing', do_shortcode( '[job_summary id="' . $listing_id . '"]' ) );
		$this->assertStringContainsString( 'Summary Gate Listing', do_shortcode( '[job id="' . $listing_id . '"]' ) );
		$this->assertStringContainsString( 'job_application', do_shortcode( '[job_apply id="' . $listing_id . '"]' ) );
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
