<?php
/**
 * Regression tests for the View Job Capability gate on taxonomy term feeds.
 *
 * Term feeds (/job-category/x/feed/) carry no post_type query var — the listing
 * constraint comes from the term join — so the feed gate's post_type check alone
 * misses them and restricted listing bodies leak to logged-out viewers. These
 * tests drive the real main query via go_to() so parse_query and pre_get_posts
 * run exactly as they do on a live request.
 *
 * @package wp-job-manager/tests
 */
class Tests_Taxonomy_Feed_View_Capability extends WPJM_BaseTest {

	const TAXONOMY = 'job_listing_category';

	private $term_id;

	public function setUp(): void {
		parent::setUp();

		update_option( 'job_manager_enable_categories', 1 );
		delete_option( 'job_manager_browse_job_listings_capability' );

		// The vulnerable configuration requires the taxonomy to be publicly
		// queryable, which register_post_types() only does when the theme
		// declares job-manager-templates. Re-register with support active.
		add_theme_support( 'job-manager-templates' );
		$this->reregister_post_types();

		$this->term_id = $this->factory->term->create( [ 'taxonomy' => self::TAXONOMY ] );
	}

	public function tearDown(): void {
		delete_option( 'job_manager_view_job_listing_capability' );
		delete_option( 'job_manager_browse_job_listings_capability' );
		remove_theme_support( 'job-manager-templates' );
		$this->reregister_post_types();
		parent::tearDown();
	}

	/**
	 * Re-runs the production registration so taxonomy visibility reflects the
	 * current theme support, working around the post_type_exists() guard.
	 */
	private function reregister_post_types() {
		unregister_post_type( WP_Job_Manager_Post_Types::PT_LISTING );
		foreach ( [ 'job_listing_category', 'job_listing_type' ] as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				unregister_taxonomy( $taxonomy );
			}
		}
		WPJM()->post_types->register_post_types();
	}

	/**
	 * Creates a published listing in the test term.
	 *
	 * @param array $args Overrides for the listing post.
	 * @return int Listing ID.
	 */
	private function create_listing_in_term( $args = [] ) {
		$listing_id = $this->factory->job_listing->create( $args );
		wp_set_object_terms( $listing_id, [ $this->term_id ], self::TAXONOMY );

		return $listing_id;
	}

	/**
	 * Runs the term feed main query and returns the selected post IDs.
	 *
	 * @return int[]
	 */
	private function term_feed_post_ids() {
		$term = get_term( $this->term_id, self::TAXONOMY );
		$this->go_to( '/?' . self::TAXONOMY . '=' . $term->slug . '&feed=rss2' );

		global $wp_query;
		$this->assertTrue( $wp_query->is_feed(), 'Sanity: the request must parse as a feed.' );
		$this->assertTrue( $wp_query->is_tax(), 'Sanity: the request must parse as a term query.' );

		return wp_list_pluck( $wp_query->posts, 'ID' );
	}

	/**
	 * A denied anonymous viewer receives no listings from a term feed.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_feed_query_for_listings
	 */
	public function test_term_feed_omits_restricted_listing_for_anonymous() {
		update_option( 'job_manager_view_job_listing_capability', [ 'read' ] );
		$listing_id = $this->create_listing_in_term();
		$this->logout();

		$this->assertNotContains(
			$listing_id,
			$this->term_feed_post_ids(),
			'A restricted listing must be excluded from the term feed for a denied viewer.'
		);
	}

	/**
	 * Positive control: a capable viewer still receives the listing.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_feed_query_for_listings
	 */
	public function test_term_feed_includes_listing_for_capable_viewer() {
		update_option( 'job_manager_view_job_listing_capability', [ 'read' ] );
		$listing_id = $this->create_listing_in_term();
		$this->login_as_admin();

		$this->assertContains(
			$listing_id,
			$this->term_feed_post_ids(),
			'A capable viewer must still receive the listing in the term feed.'
		);
	}

	/**
	 * Positive control: with no view capability configured the feed is unrestricted.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_feed_query_for_listings
	 */
	public function test_term_feed_unrestricted_when_no_capability_configured() {
		$listing_id = $this->create_listing_in_term();
		$this->logout();

		$this->assertContains(
			$listing_id,
			$this->term_feed_post_ids(),
			'With no view capability configured, the term feed must be unaffected.'
		);
	}

	/**
	 * A denied logged-in viewer is restricted to their own listings, matching the
	 * behaviour of the listing feeds.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_feed_query_for_listings
	 */
	public function test_term_feed_restricts_denied_viewer_to_own_listings() {
		update_option( 'job_manager_view_job_listing_capability', [ 'manage_options' ] );

		$employer_a = $this->factory->user->create( [ 'role' => 'employer' ] );
		$employer_b = $this->factory->user->create( [ 'role' => 'employer' ] );
		$own        = $this->create_listing_in_term( [ 'post_author' => $employer_a ] );
		$other      = $this->create_listing_in_term( [ 'post_author' => $employer_b ] );

		wp_set_current_user( $employer_a );
		$post_ids = $this->term_feed_post_ids();

		$this->assertContains( $own, $post_ids, 'A denied viewer must keep their own listings.' );
		$this->assertNotContains( $other, $post_ids, 'A denied viewer must not receive other authors\' listings.' );
	}

	/**
	 * Password-protected listings are excluded from term feeds even for capable
	 * viewers, matching the listing feeds.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_feed_query_for_listings
	 */
	public function test_term_feed_excludes_password_protected_listing() {
		$listing_id = $this->create_listing_in_term( [ 'post_password' => 'secret' ] );
		$this->login_as_admin();

		$this->assertNotContains(
			$listing_id,
			$this->term_feed_post_ids(),
			'A password-protected listing must be excluded from the term feed.'
		);
	}

	/**
	 * Term feeds for taxonomies unrelated to job listings are not gated.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_feed_query_for_listings
	 */
	public function test_unrelated_term_feed_unaffected() {
		update_option( 'job_manager_view_job_listing_capability', [ 'read' ] );

		$category_id = $this->factory->term->create( [ 'taxonomy' => 'category' ] );
		$post_id     = $this->factory->post->create( [ 'post_status' => 'publish' ] );
		wp_set_object_terms( $post_id, [ $category_id ], 'category' );

		$this->logout();
		$term = get_term( $category_id, 'category' );
		$this->go_to( '/?cat=' . $category_id . '&feed=rss2' );

		global $wp_query;
		$this->assertTrue( $wp_query->is_feed(), 'Sanity: the request must parse as a feed.' );
		$this->assertContains(
			$post_id,
			wp_list_pluck( $wp_query->posts, 'ID' ),
			'An ordinary category feed must not be gated. Term slug: ' . $term->slug
		);
	}
}
