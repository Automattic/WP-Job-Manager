<?php
/**
 * Regression tests for the View Job Capability gate on the core WordPress search
 * query (`/?s=...`) and search feeds (`/?s=...&feed=rss2`).
 *
 * The `job_listing` post type is registered with `exclude_from_search => false`, so
 * the default WordPress search query, which spans every searchable post type,
 * includes listings and renders their title and full body. That path never reaches
 * the [jobs] shortcode, the AJAX handler, the REST collection/search gates, or the
 * single-listing view, so without a dedicated gate a denied viewer can read
 * restricted listing bodies through ordinary site search and its RSS/Atom feeds.
 *
 * Covers the configuration where anonymous users may browse listing indexes
 * (`job_manager_browse_job_listings_capability` empty) but must satisfy a
 * capability to view individual listing details
 * (`job_manager_view_job_listing_capability` set).
 *
 * @package wp-job-manager/tests
 */
class Tests_Core_Search_View_Capability extends WPJM_BaseTest {

	const SENTINEL = 'SENTINELCORESEARCHVIEWCAP';

	public function setUp(): void {
		parent::setUp();
		// Browsing is open to everyone; viewing details requires the `read` capability,
		// which anonymous users do not have.
		delete_option( 'job_manager_browse_job_listings_capability' );
		update_option( 'job_manager_view_job_listing_capability', [ 'read' ] );
	}

	public function tearDown(): void {
		delete_option( 'job_manager_view_job_listing_capability' );
		delete_option( 'job_manager_browse_job_listings_capability' );
		parent::tearDown();
	}

	private function create_restricted_listing( $args = [] ) {
		return $this->factory->job_listing->create(
			array_merge(
				[
					'post_title'   => self::SENTINEL . ' title',
					'post_content' => self::SENTINEL . ' body token',
				],
				$args
			)
		);
	}

	/**
	 * Runs a front-end search as the main query and returns the selected post IDs.
	 *
	 * go_to() parses the URL into query vars and runs it as the global main query,
	 * which fires the pre_get_posts gate under test exactly as a real request would.
	 */
	private function search_post_ids( $url ) {
		$this->go_to( $url );
		global $wp_query;

		return wp_list_pluck( $wp_query->posts, 'ID' );
	}

	/**
	 * A view-restricted (non-password) listing must not appear in core search for an
	 * anonymous viewer.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_search_query_for_listings
	 */
	public function test_search_excludes_restricted_listing_for_anonymous() {
		$post_id = $this->create_restricted_listing();
		$this->logout();

		$this->assertNotContains(
			$post_id,
			$this->search_post_ids( home_url( '/?s=' . self::SENTINEL ) ),
			'A view-restricted listing must not appear in core search for a denied viewer.'
		);
	}

	/**
	 * Positive control: a viewer who satisfies the view capability still finds the
	 * listing, so the gate does not over-block.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_search_query_for_listings
	 */
	public function test_search_includes_listing_for_capable_viewer() {
		$post_id = $this->create_restricted_listing();
		$this->login_as_admin();

		$this->assertContains(
			$post_id,
			$this->search_post_ids( home_url( '/?s=' . self::SENTINEL ) ),
			'A capable viewer must still find the listing in core search.'
		);
	}

	/**
	 * When listings are unrestricted (the default), core search is left untouched.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_search_query_for_listings
	 */
	public function test_search_includes_listing_when_unrestricted() {
		delete_option( 'job_manager_view_job_listing_capability' );
		$post_id = $this->create_restricted_listing();
		$this->logout();

		$this->assertContains(
			$post_id,
			$this->search_post_ids( home_url( '/?s=' . self::SENTINEL ) ),
			'With no view capability configured, listings remain searchable by everyone.'
		);
	}

	/**
	 * A mixed search by a denied viewer still returns other post types; only the
	 * listing is withheld.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_search_query_for_listings
	 */
	public function test_search_preserves_other_post_types_for_denied_viewer() {
		$listing_id = $this->create_restricted_listing();
		$post_id    = $this->factory->post->create(
			[ 'post_title' => self::SENTINEL . ' ordinary post', 'post_status' => 'publish' ]
		);
		$this->logout();

		$ids = $this->search_post_ids( home_url( '/?s=' . self::SENTINEL ) );

		$this->assertContains( $post_id, $ids, 'An ordinary post must still be searchable for a denied viewer.' );
		$this->assertNotContains( $listing_id, $ids, 'The restricted listing must be withheld from the search.' );
	}

	/**
	 * The search RSS feed (`/?s=...&feed=rss2`) is also a search query and must withhold
	 * restricted listings from a denied viewer.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_search_query_for_listings
	 */
	public function test_search_feed_excludes_restricted_listing_for_anonymous() {
		$post_id = $this->create_restricted_listing();
		$this->logout();

		$this->assertNotContains(
			$post_id,
			$this->search_post_ids( home_url( '/?s=' . self::SENTINEL . '&feed=rss2' ) ),
			'A view-restricted listing must not appear in the search RSS feed for a denied viewer.'
		);
	}

	/**
	 * An explicit listing-only core search must fail closed rather than falling back to
	 * ordinary posts when the listing post type is removed.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_search_query_for_listings
	 */
	public function test_explicit_listing_search_excludes_restricted_listing_for_anonymous() {
		$post_id = $this->create_restricted_listing();
		$this->logout();

		$this->assertNotContains(
			$post_id,
			$this->search_post_ids( home_url( '/?s=' . self::SENTINEL . '&post_type=job_listing' ) ),
			'A listing-only search must fail closed for a denied viewer.'
		);
	}

	/**
	 * A signed-in author denied by the view capability does NOT get their own listing
	 * back from core search. Intentionally stricter than the single-item route and feed
	 * gate, mirroring the REST search gate: the search query spans every searchable post
	 * type, so honouring author ownership cannot be done without affecting other types.
	 * Their own listings stay reachable via the job dashboard and the single-item view.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_search_query_for_listings
	 */
	public function test_search_excludes_own_listing_for_denied_signed_in_author() {
		// Require a capability the author does not hold, so they are denied by the view cap
		// yet still own the listing (the `read` cap from setUp is held by every logged-in user).
		update_option( 'job_manager_view_job_listing_capability', [ 'manage_options' ] );

		$author_id = $this->factory->user->create( [ 'role' => 'employer' ] );
		$post_id   = $this->create_restricted_listing( [ 'post_author' => $author_id ] );
		wp_set_current_user( $author_id );

		$this->assertTrue(
			job_manager_user_can_view_job_listing( $post_id ),
			'The author should still be permitted to view their own listing individually.'
		);

		$this->assertNotContains(
			$post_id,
			$this->search_post_ids( home_url( '/?s=' . self::SENTINEL ) ),
			'Core search intentionally fails closed: a denied author does not get own listings here.'
		);
	}

	/**
	 * When browsing itself is gated, a denied viewer gets no listings from search.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_search_query_for_listings
	 */
	public function test_search_excludes_listing_when_browse_denied() {
		update_option( 'job_manager_browse_job_listings_capability', [ 'read' ] );
		$post_id = $this->create_restricted_listing();
		$this->logout();

		$this->assertNotContains(
			$post_id,
			$this->search_post_ids( home_url( '/?s=' . self::SENTINEL ) ),
			'A browse-denied viewer must not enumerate listings via core search.'
		);
	}
}
