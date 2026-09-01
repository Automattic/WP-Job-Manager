<?php
/**
 * Regression tests for the author filter on the RSS / Atom job feed.
 *
 * No View Job Capability is configured here: browsing and viewing are both open,
 * so the only constraint on the feed query is the requested `?author=` filter.
 * When that filter is supplied but yields no valid IDs (`?author=abc`,
 * `?author[]=1`), the feed must fail closed without leaking orphaned listings
 * (post_author 0). Applying the `[0]` sentinel as `author__in => [0]` would match
 * those orphaned listings instead of excluding them.
 *
 * @package wp-job-manager/tests
 */
class Tests_Feed_Author_Filter extends WPJM_BaseTest {

	/**
	 * Closures attached to job_manager_get_listings_location_meta_keys by this fixture,
	 * remembered so tearDown can remove only the filters we installed without disturbing
	 * callbacks owned by other tests in the suite.
	 *
	 * @var array
	 */
	private $location_filter_callbacks = [];

	public function tearDown(): void {
		unset( $_GET['author'] );
		unset( $_GET['search_location'] );
		remove_filter( 'job_manager_get_listings_location_meta_keys', '__return_empty_array' );
		foreach ( $this->location_filter_callbacks as $callback ) {
			remove_filter( 'job_manager_get_listings_location_meta_keys', $callback );
		}
		$this->location_filter_callbacks = [];
		parent::tearDown();
	}

	/**
	 * Mirrors the helper in test_class.feed-view-capability.php: captures the
	 * job_feed output and returns the post IDs its query selected.
	 */
	private function job_feed_post_ids() {
		ob_start();
		try {
			@WP_Job_Manager_Post_Types::instance()->job_feed();
			ob_get_clean();
		} catch ( Exception $e ) {
			ob_get_clean();
			throw $e;
		}

		global $wp_query;

		return wp_list_pluck( $wp_query->posts, 'ID' );
	}

	/**
	 * A scalar author filter that yields no valid IDs must not leak orphaned listings.
	 *
	 * @covers WP_Job_Manager_Post_Types::job_feed
	 */
	public function test_job_feed_invalid_author_excludes_orphan_listing() {
		$orphan_id = $this->factory->job_listing->create( [ 'post_author' => 0 ] );

		$_GET['author'] = 'abc';

		$this->assertNotContains(
			$orphan_id,
			$this->job_feed_post_ids(),
			'An orphaned listing must not leak when the author filter yields no valid IDs.'
		);
	}

	/**
	 * An array-shaped author filter (unsupported) fails closed and must not leak orphans.
	 *
	 * @covers WP_Job_Manager_Post_Types::job_feed
	 */
	public function test_job_feed_array_author_excludes_orphan_listing() {
		$orphan_id = $this->factory->job_listing->create( [ 'post_author' => 0 ] );

		$_GET['author'] = [ '1' ];

		$this->assertNotContains(
			$orphan_id,
			$this->job_feed_post_ids(),
			'An orphaned listing must not leak when an array-shaped author filter is supplied.'
		);
	}

	/**
	 * Positive control: a valid author filter still returns that author's listing,
	 * so the fix does not over-block.
	 *
	 * @covers WP_Job_Manager_Post_Types::job_feed
	 */
	public function test_job_feed_valid_author_returns_listing() {
		$author_id  = $this->factory->user->create( [ 'role' => 'employer' ] );
		$listing_id = $this->factory->job_listing->create( [ 'post_author' => $author_id ] );

		$_GET['author'] = (string) $author_id;

		$this->assertContains(
			$listing_id,
			$this->job_feed_post_ids(),
			'A valid author filter must still return that author\'s listing.'
		);
	}

	/**
	 * Extending the location meta keys via the filter must let the feed return a listing
	 * whose location only matches against the added meta key. Mirrors the shortcode test
	 * so the filter is covered in both code paths.
	 *
	 * @since $$next-version$$
	 * @covers WP_Job_Manager_Post_Types::job_feed
	 */
	public function test_job_feed_location_meta_keys_filter_includes_extra_listing() {
		$listing_id = $this->factory->job_listing->create();
		update_post_meta( $listing_id, '_address_region', 'Boise, Idaho' );

		$_GET['search_location'] = 'Boise';

		// Sanity check: the listing is on a custom meta key the feed cannot see yet.
		$this->assertNotContains(
			$listing_id,
			$this->job_feed_post_ids(),
			'Sanity: without the filter, the listing is not in the feed for \"Boise\".'
		);

		$add_address_region = function ( $location_meta_keys ) {
			$location_meta_keys[] = '_address_region';
			return $location_meta_keys;
		};

		add_filter( 'job_manager_get_listings_location_meta_keys', $add_address_region );
		$this->location_filter_callbacks[] = $add_address_region;

		$this->assertContains(
			$listing_id,
			$this->job_feed_post_ids(),
			'With the filter extended, the listing appears in the feed for \"Boise\".'
		);
	}

	/**
	 * If the filter returns an empty array the feed must fall back to the defaults
	 * rather than build a meta_query of only `relation => OR`.
	 *
	 * @since $$next-version$$
	 * @covers WP_Job_Manager_Post_Types::job_feed
	 */
	public function test_job_feed_location_meta_keys_filter_empty_falls_back_to_defaults() {
		$seattle_listing_id = $this->factory->job_listing->create();
		update_post_meta( $seattle_listing_id, '_job_location', 'Seattle' );

		$nonmatching_listing_id = $this->factory->job_listing->create();
		update_post_meta( $nonmatching_listing_id, '_job_location', 'Portland' );

		$_GET['search_location'] = 'Seattle';

		add_filter( 'job_manager_get_listings_location_meta_keys', '__return_empty_array' );

		$post_ids = $this->job_feed_post_ids();
		$this->assertContains(
			$seattle_listing_id,
			$post_ids,
			'Empty filter result must fall back to defaults so _job_location still matches.'
		);
		$this->assertNotContains(
			$nonmatching_listing_id,
			$post_ids,
			'Empty filter result must still apply location filtering rather than returning an unfiltered feed.'
		);
	}
}
