<?php
/**
 * Regression test: view-capability-restricted job listings must not be enumerated in
 * public XML sitemaps.
 *
 * The core WordPress sitemap and the Yoast/Jetpack integrations filtered only for filled
 * positions and omitted the `job_manager_view_job_listing_capability` gate, so a board an
 * operator restricted to certain roles was still enumerated (and, once followed, its
 * metadata disclosed) to anonymous crawlers. Sitemaps are generated for anonymous
 * requesters, who can never satisfy a configured view capability.
 *
 * @package wp-job-manager
 */
class Tests_Sitemap_View_Capability extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		$this->reregister_post_type();
	}

	public function tearDown(): void {
		delete_option( 'job_manager_view_job_listing_capability' );
		delete_option( 'job_manager_browse_job_listings_capability' );
		parent::tearDown();
	}

	/**
	 * Restricts browsing listing indexes to administrators.
	 */
	private function restrict_browsing_to_admins() {
		update_option( 'job_manager_browse_job_listings_capability', [ 'manage_options' ] );
	}

	/**
	 * Restricts viewing listing details to administrators.
	 */
	private function restrict_viewing_to_admins() {
		update_option( 'job_manager_view_job_listing_capability', [ 'manage_options' ] );
	}

	/**
	 * The core sitemap post-type list as WordPress passes it: public post-type objects
	 * keyed by name.
	 *
	 * @return array
	 */
	private function sitemap_post_types() {
		return get_post_types( [ 'public' => true ], 'objects' );
	}

	/**
	 * When the View Job Capability restricts an anonymous viewer, job listings are dropped
	 * from the core sitemap entirely.
	 *
	 * @covers WP_Job_Manager_Post_Types::sitemaps_maybe_hide_restricted_post_type
	 */
	public function test_restricted_listings_are_removed_from_core_sitemap_for_anonymous() {
		$this->restrict_viewing_to_admins();
		$this->logout();

		$filtered = WP_Job_Manager_Post_Types::instance()->sitemaps_maybe_hide_restricted_post_type( $this->sitemap_post_types() );

		$this->assertArrayNotHasKey(
			\WP_Job_Manager_Post_Types::PT_LISTING,
			$filtered,
			'Restricted job listings must not appear in the core sitemap for a denied viewer.'
		);
	}

	/**
	 * Control: with no view capability configured, job listings stay in the sitemap.
	 *
	 * @covers WP_Job_Manager_Post_Types::sitemaps_maybe_hide_restricted_post_type
	 */
	public function test_listings_remain_in_core_sitemap_when_unrestricted() {
		delete_option( 'job_manager_view_job_listing_capability' );
		$this->logout();

		$filtered = WP_Job_Manager_Post_Types::instance()->sitemaps_maybe_hide_restricted_post_type( $this->sitemap_post_types() );

		$this->assertArrayHasKey(
			\WP_Job_Manager_Post_Types::PT_LISTING,
			$filtered,
			'With no view capability configured, job listings must remain in the sitemap.'
		);
	}

	/**
	 * Positive control: a viewer who satisfies the capability keeps job listings in the
	 * sitemap, so the gate does not over-block.
	 *
	 * @covers WP_Job_Manager_Post_Types::sitemaps_maybe_hide_restricted_post_type
	 */
	public function test_listings_remain_in_core_sitemap_for_capable_viewer() {
		$this->restrict_viewing_to_admins();
		$this->login_as_admin();

		$filtered = WP_Job_Manager_Post_Types::instance()->sitemaps_maybe_hide_restricted_post_type( $this->sitemap_post_types() );

		$this->assertArrayHasKey(
			\WP_Job_Manager_Post_Types::PT_LISTING,
			$filtered,
			'A capable viewer must still see job listings in the sitemap.'
		);
	}

	/**
	 * The Yoast per-entry filter drops a restricted listing for an anonymous crawler and
	 * keeps it otherwise.
	 */
	public function test_yoast_entry_is_skipped_for_restricted_listing() {
		require_once JOB_MANAGER_PLUGIN_DIR . '/includes/3rd-party/yoast.php';

		$job_id = $this->factory->job_listing->create( [ 'post_status' => 'publish' ] );
		$post   = get_post( $job_id );
		$url    = [ 'loc' => get_permalink( $job_id ) ];

		$this->restrict_viewing_to_admins();
		$this->logout();
		$this->assertFalse(
			wpjm_yoast_skip_filled_job_listings( $url, 'post', $post ),
			'Yoast must skip a view-restricted listing for a denied crawler.'
		);

		delete_option( 'job_manager_view_job_listing_capability' );
		$this->assertSame(
			$url,
			wpjm_yoast_skip_filled_job_listings( $url, 'post', $post ),
			'Yoast must keep the listing when unrestricted.'
		);
	}

	/**
	 * The Jetpack per-post filter skips a restricted listing for an anonymous crawler and
	 * keeps it otherwise.
	 */
	public function test_jetpack_post_is_skipped_for_restricted_listing() {
		require_once JOB_MANAGER_PLUGIN_DIR . '/includes/3rd-party/jetpack.php';

		$job_id = $this->factory->job_listing->create( [ 'post_status' => 'publish' ] );
		$post   = get_post( $job_id );

		$this->restrict_viewing_to_admins();
		$this->logout();
		$this->assertTrue(
			wpjm_jetpack_skip_filled_job_listings( false, $post ),
			'Jetpack must skip a view-restricted listing for a denied crawler.'
		);

		delete_option( 'job_manager_view_job_listing_capability' );
		$this->assertFalse(
			wpjm_jetpack_skip_filled_job_listings( false, $post ),
			'Jetpack must keep the listing when unrestricted.'
		);
	}

	/**
	 * A browse-capability restriction also drops listings from the core sitemap: a
	 * sitemap is a listing index, which is exactly what the browse capability gates.
	 *
	 * @covers WP_Job_Manager_Post_Types::sitemaps_maybe_hide_restricted_post_type
	 */
	public function test_restricted_listings_are_removed_from_core_sitemap_for_browse_denied() {
		$this->restrict_browsing_to_admins();
		$this->logout();

		$filtered = WP_Job_Manager_Post_Types::instance()->sitemaps_maybe_hide_restricted_post_type( $this->sitemap_post_types() );

		$this->assertArrayNotHasKey(
			\WP_Job_Manager_Post_Types::PT_LISTING,
			$filtered,
			'Browse-restricted job listings must not appear in the core sitemap.'
		);
	}

	/**
	 * The Yoast and Jetpack per-entry filters also honour the browse capability.
	 */
	public function test_plugin_entries_are_skipped_for_browse_denied() {
		require_once JOB_MANAGER_PLUGIN_DIR . '/includes/3rd-party/yoast.php';
		require_once JOB_MANAGER_PLUGIN_DIR . '/includes/3rd-party/jetpack.php';

		$job_id = $this->factory->job_listing->create( [ 'post_status' => 'publish' ] );
		$post   = get_post( $job_id );
		$url    = [ 'loc' => get_permalink( $job_id ) ];

		$this->restrict_browsing_to_admins();
		$this->logout();

		$this->assertFalse(
			wpjm_yoast_skip_filled_job_listings( $url, 'post', $post ),
			'Yoast must skip listings for a browse-denied crawler.'
		);
		$this->assertTrue(
			wpjm_jetpack_skip_filled_job_listings( false, $post ),
			'Jetpack must skip listings for a browse-denied crawler.'
		);
	}

	/**
	 * The legacy All in One SEO Pack filter drops restricted listings and keeps
	 * everything else.
	 *
	 * @covers ::wpjm_aiosp_sitemap_filter_filled_jobs
	 */
	public function test_aiosp_legacy_filter_drops_restricted_listing() {
		require_once JOB_MANAGER_PLUGIN_DIR . '/includes/3rd-party/all-in-one-seo-pack.php';

		$job_id  = $this->factory->job_listing->create( [ 'post_status' => 'publish' ] );
		$page_id = $this->factory->post->create( [ 'post_type' => 'page' ] );
		$posts   = [ get_post( $job_id ), get_post( $page_id ) ];

		$this->restrict_viewing_to_admins();
		$this->logout();

		$filtered_ids = wp_list_pluck( wpjm_aiosp_sitemap_filter_filled_jobs( $posts ), 'ID' );
		$this->assertNotContains( $job_id, $filtered_ids, 'AIOSP must drop a view-restricted listing.' );
		$this->assertContains( $page_id, $filtered_ids, 'AIOSP must keep non-listing posts.' );

		delete_option( 'job_manager_view_job_listing_capability' );
		$filtered_ids = wp_list_pluck( wpjm_aiosp_sitemap_filter_filled_jobs( $posts ), 'ID' );
		$this->assertContains( $job_id, $filtered_ids, 'AIOSP must keep the listing when unrestricted.' );
	}

	/**
	 * The AIOSEO v4 exclusion filter adds listing IDs only when a restriction is
	 * configured.
	 *
	 * @covers ::wpjm_aioseo_sitemap_exclude_restricted_listings
	 */
	public function test_aioseo_v4_excludes_listing_ids_when_restricted() {
		require_once JOB_MANAGER_PLUGIN_DIR . '/includes/3rd-party/all-in-one-seo-pack.php';

		$job_id  = $this->factory->job_listing->create( [ 'post_status' => 'publish' ] );
		$page_id = $this->factory->post->create( [ 'post_type' => 'page' ] );
		$this->logout();

		$this->assertSame(
			[],
			wpjm_aioseo_sitemap_exclude_restricted_listings( [], 'general' ),
			'No IDs may be excluded on an unrestricted board.'
		);

		$this->restrict_viewing_to_admins();
		$excluded = wpjm_aioseo_sitemap_exclude_restricted_listings( [], 'general' );
		$this->assertContains( $job_id, $excluded, 'Restricted boards must exclude every listing ID.' );
		$this->assertNotContains( $page_id, $excluded, 'Only listing IDs may be excluded.' );

		delete_option( 'job_manager_view_job_listing_capability' );
		$this->restrict_browsing_to_admins();
		$this->assertContains(
			$job_id,
			wpjm_aioseo_sitemap_exclude_restricted_listings( [], 'general' ),
			'A browse-capability restriction must also exclude listing IDs.'
		);
	}
}
