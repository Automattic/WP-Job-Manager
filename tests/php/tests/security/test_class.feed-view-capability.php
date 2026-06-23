<?php
/**
 * Regression tests for the View Job Capability gate on RSS / Atom feeds.
 *
 * Covers the configuration where anonymous users may browse listing indexes
 * (`job_manager_browse_job_listings_capability` empty) but must satisfy a
 * capability to view individual listing details
 * (`job_manager_view_job_listing_capability` set). The feeds must withhold
 * listing details from a denied viewer, consistently with the single listing
 * view and the REST single-item route.
 *
 * @package wp-job-manager/tests
 */
class Tests_Feed_View_Capability extends WPJM_BaseTest {

	const SENTINEL = 'SENTINEL_FEED_VIEWCAP_LEAK';

	private $author_id;

	public function setUp(): void {
		parent::setUp();
		// Browsing is open to everyone; viewing details requires the `read` capability,
		// which anonymous users do not have.
		delete_option( 'job_manager_browse_job_listings_capability' );
		update_option( 'job_manager_view_job_listing_capability', [ 'read' ] );

		// Listings must have a real (non-zero) author: the feed gate restricts denied
		// viewers via `author__in => [0]`, the fail-closed sentinel used throughout the
		// feed code. Production listings always have a submitter, so an author-0 post
		// (the factory default) would not reflect a real listing.
		$this->author_id = $this->factory->user->create( [ 'role' => 'employer' ] );
	}

	public function tearDown(): void {
		delete_option( 'job_manager_view_job_listing_capability' );
		parent::tearDown();
	}

	private function create_restricted_listing() {
		$post_id = $this->factory->job_listing->create(
			[
				'post_author'  => $this->author_id,
				'post_content' => self::SENTINEL,
				'post_title'   => self::SENTINEL . ' title',
			]
		);
		update_post_meta( $post_id, '_company_name', self::SENTINEL . ' company' );
		update_post_meta( $post_id, '_job_location', self::SENTINEL . ' location' );

		return $post_id;
	}

	/**
	 * Captures the custom job_feed output.
	 *
	 * Mirrors the helper in test_class.capability-restricted-listing-output.php.
	 */
	private function capture_job_feed() {
		ob_start();
		try {
			@WP_Job_Manager_Post_Types::instance()->job_feed();
			$out = ob_get_clean();
		} catch ( Exception $e ) {
			$out = ob_get_clean();
			throw $e;
		}

		return (string) $out;
	}

	/**
	 * Returns the post IDs the job_feed query selected, after rendering the feed.
	 *
	 * Asserting on the resulting query (rather than the rendered RSS string) tests the
	 * gate directly and is immune to the feed template's once-per-process rendering
	 * behaviour: query_posts() populates the global query before the body is rendered.
	 */
	private function job_feed_post_ids() {
		$this->capture_job_feed();
		global $wp_query;

		return wp_list_pluck( $wp_query->posts, 'ID' );
	}

	/**
	 * @covers WP_Job_Manager_Post_Types::job_feed
	 */
	public function test_job_feed_omits_restricted_listing_for_anonymous() {
		$post_id = $this->create_restricted_listing();
		$this->logout();

		$this->assertNotContains(
			$post_id,
			$this->job_feed_post_ids(),
			'Restricted listing must be excluded from the feed for a denied viewer.'
		);
	}

	/**
	 * Positive control: a viewer who satisfies the view capability still receives the listing,
	 * so the gate does not over-block.
	 *
	 * @covers WP_Job_Manager_Post_Types::job_feed
	 */
	public function test_job_feed_includes_restricted_listing_for_capable_viewer() {
		$post_id = $this->create_restricted_listing();
		$this->login_as_admin();

		$this->assertContains(
			$post_id,
			$this->job_feed_post_ids(),
			'A capable viewer must still receive the listing in the feed.'
		);
	}

	/**
	 * The core feed gate restricts a denied viewer to their own listings.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_feed_query_for_listings
	 */
	public function test_core_feed_query_restricts_denied_anonymous_viewer() {
		$this->logout();

		$previous_main_query = isset( $GLOBALS['wp_the_query'] ) ? $GLOBALS['wp_the_query'] : null;

		$query           = new WP_Query();
		$query->is_feed  = true;
		$query->set( 'post_type', WP_Job_Manager_Post_Types::PT_LISTING );
		// is_main_query() compares identity against the global query.
		$GLOBALS['wp_the_query'] = $query;

		try {
			WP_Job_Manager_Post_Types::instance()->gate_feed_query_for_listings( $query );
		} finally {
			$GLOBALS['wp_the_query'] = $previous_main_query;
		}

		// Anonymous viewer (user id 0) is fail-closed to no author.
		$this->assertSame( [ 0 ], $query->get( 'author__in' ) );
	}
}
