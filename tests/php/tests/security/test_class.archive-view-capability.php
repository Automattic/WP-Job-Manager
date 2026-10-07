<?php
/**
 * Regression tests for the browse / View Job Capability gate on the plain front-end
 * archive queries: ?post_type=job_listing and term archives for listing taxonomies.
 *
 * These queries render listing titles and excerpts through the theme and previously
 * reached the database with no WPJM gate: the capability options only covered the
 * feeds, REST and search paths. Tests drive real main queries via go_to() so
 * parse_query and pre_get_posts run exactly as on a live request.
 *
 * @package wp-job-manager/tests
 */
class Tests_Archive_View_Capability extends WPJM_BaseTest {

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
	 * Runs the plain post_type archive main query and returns the selected post IDs.
	 *
	 * @return int[]
	 */
	private function archive_post_ids() {
		$this->go_to( '/?post_type=' . WP_Job_Manager_Post_Types::PT_LISTING );

		global $wp_query;

		return wp_list_pluck( $wp_query->posts, 'ID' );
	}

	/**
	 * With no capabilities configured the archive is unrestricted.
	 */
	public function test_archive_unrestricted_by_default() {
		$listing_id = $this->factory->job_listing->create();
		$this->logout();

		$this->assertContains(
			$listing_id,
			$this->archive_post_ids(),
			'With no capabilities configured, the archive must be unaffected.'
		);
	}

	/**
	 * A viewer denied by the browse capability receives no listings.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_archive_query_for_listings
	 */
	public function test_archive_empty_for_browse_denied_viewer() {
		update_option( 'job_manager_browse_job_listings_capability', [ 'manage_options' ] );
		$listing_id = $this->factory->job_listing->create();
		$this->logout();

		$this->assertNotContains(
			$listing_id,
			$this->archive_post_ids(),
			'A browse-denied viewer must receive no listings from the archive.'
		);
	}

	/**
	 * A viewer denied by the View Job Capability receives no listings when anonymous.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_archive_query_for_listings
	 */
	public function test_archive_empty_for_view_cap_denied_anonymous() {
		update_option( 'job_manager_view_job_listing_capability', [ 'read' ] );
		$listing_id = $this->factory->job_listing->create();
		$this->logout();

		$this->assertNotContains(
			$listing_id,
			$this->archive_post_ids(),
			'A view-cap-denied anonymous viewer must receive no listings from the archive.'
		);
	}

	/**
	 * A denied logged-in viewer keeps their own listings, matching the feed gate.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_archive_query_for_listings
	 */
	public function test_archive_restricts_denied_viewer_to_own_listings() {
		update_option( 'job_manager_view_job_listing_capability', [ 'manage_options' ] );

		$employer_a = $this->factory->user->create( [ 'role' => 'employer' ] );
		$employer_b = $this->factory->user->create( [ 'role' => 'employer' ] );
		$own        = $this->factory->job_listing->create( [ 'post_author' => $employer_a ] );
		$other      = $this->factory->job_listing->create( [ 'post_author' => $employer_b ] );

		wp_set_current_user( $employer_a );
		$post_ids = $this->archive_post_ids();

		$this->assertContains( $own, $post_ids, 'A denied viewer must keep their own listings.' );
		$this->assertNotContains( $other, $post_ids, 'A denied viewer must not receive other authors\' listings.' );
	}

	/**
	 * A capable viewer is unaffected.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_archive_query_for_listings
	 */
	public function test_archive_includes_listing_for_capable_viewer() {
		update_option( 'job_manager_view_job_listing_capability', [ 'read' ] );
		$listing_id = $this->factory->job_listing->create();
		$this->login_as_admin();

		$this->assertContains(
			$listing_id,
			$this->archive_post_ids(),
			'A capable viewer must still receive listings from the archive.'
		);
	}

	/**
	 * Ordinary queries for other post types are untouched even when the viewer is denied.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_archive_query_for_listings
	 */
	public function test_other_post_type_archives_unaffected() {
		update_option( 'job_manager_view_job_listing_capability', [ 'read' ] );
		update_option( 'job_manager_browse_job_listings_capability', [ 'read' ] );

		$post_id = $this->factory->post->create( [ 'post_status' => 'publish' ] );
		$this->logout();
		$this->go_to( '/?post_type=post' );

		global $wp_query;
		$this->assertContains(
			$post_id,
			wp_list_pluck( $wp_query->posts, 'ID' ),
			'Archives of other post types must not be gated.'
		);
	}
}
