<?php
/**
 * Regression tests covering keyword search and the listings transient cache:
 * password-protected listings must not surface to keyword search regardless of viewer
 * auth state, and cached results must not cross auth boundaries.
 *
 * @package wp-job-manager/tests
 */

class Tests_Password_Protected_Listing_Search extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		$this->disable_job_listing_cache();
	}

	public function tearDown(): void {
		$this->disable_job_listing_cache();
		parent::tearDown();
	}

	protected function disable_job_listing_cache() {
		remove_filter( 'get_job_listings_cache_results', '__return_true' );
		remove_filter( 'get_job_listings_cache_results', '__return_false' );
		add_filter( 'get_job_listings_cache_results', '__return_false' );
	}

	protected function enable_job_listing_cache() {
		remove_filter( 'get_job_listings_cache_results', '__return_false' );
		remove_filter( 'get_job_listings_cache_results', '__return_true' );
		add_filter( 'get_job_listings_cache_results', '__return_true' );
	}

	/**
	 * @covers ::get_job_listings
	 * @covers ::get_job_listings_keyword_search
	 */
	public function test_subscriber_keyword_search_excludes_password_protected() {
		$protected = $this->factory->job_listing->create(
			[
				'post_password' => 'secret',
				'post_content'  => 'sentinel-ULTRAVIOLET protected description',
			]
		);
		$public = $this->factory->job_listing->create(
			[
				'post_content' => 'sentinel-VANILLA public description',
			]
		);

		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		$this->login_as( $subscriber_id );

		$results = get_job_listings( [ 'search_keywords' => 'ULTRAVIOLET' ] );
		$ids     = wp_list_pluck( $results->posts, 'ID' );

		$this->assertNotContains(
			$protected,
			$ids,
			'Subscriber keyword search must not surface password-protected listings.'
		);
	}

	/**
	 * @covers ::get_job_listings
	 * @covers ::get_job_listings_keyword_search
	 */
	public function test_anonymous_keyword_search_excludes_password_protected() {
		$protected = $this->factory->job_listing->create(
			[
				'post_password' => 'secret',
				'post_content'  => 'sentinel-INDIGO protected description',
			]
		);
		$this->logout();

		$results = get_job_listings( [ 'search_keywords' => 'INDIGO' ] );
		$ids     = wp_list_pluck( $results->posts, 'ID' );

		$this->assertNotContains( $protected, $ids );
	}

	/**
	 * @covers ::get_job_listings
	 *
	 * Sanity / no-regression: public listings still match by keyword.
	 */
	public function test_keyword_search_still_returns_public_listings() {
		$public = $this->factory->job_listing->create(
			[
				'post_content' => 'sentinel-CITRINE public description',
			]
		);
		$this->logout();

		$results = get_job_listings( [ 'search_keywords' => 'CITRINE' ] );
		$ids     = wp_list_pluck( $results->posts, 'ID' );

		$this->assertContains( $public, $ids );
	}

	/**
	 * @covers ::get_job_listings
	 *
	 * Sanity / no-regression: a search with no matching keyword returns an empty result-set.
	 */
	public function test_keyword_search_with_no_match_returns_empty() {
		$this->factory->job_listing->create();
		$this->logout();

		$results = get_job_listings( [ 'search_keywords' => 'never-going-to-match-MOSS' ] );

		$this->assertEmpty( wp_list_pluck( $results->posts, 'ID' ) );
	}

	/**
	 * @covers ::get_job_listings
	 *
	 * Cache amplification: when an authenticated user populates the cache for a keyword,
	 * a subsequent anonymous request for the same keyword must not read the authed result.
	 */
	public function test_cache_key_per_user_blocks_amplification() {
		$protected = $this->factory->job_listing->create(
			[
				'post_password' => 'secret',
				'post_content'  => 'sentinel-MERIDIAN protected description',
			]
		);

		$this->enable_job_listing_cache();

		$subscriber_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		$this->login_as( $subscriber_id );
		// Prime the cache as the subscriber.
		get_job_listings( [ 'search_keywords' => 'MERIDIAN' ] );

		// Read as anonymous on the same keyword.
		$this->logout();
		$anon_results = get_job_listings( [ 'search_keywords' => 'MERIDIAN' ] );
		$anon_ids     = wp_list_pluck( $anon_results->posts, 'ID' );

		$this->assertNotContains(
			$protected,
			$anon_ids,
			'Anonymous request must not read a cached result populated by a different user.'
		);
	}
}
