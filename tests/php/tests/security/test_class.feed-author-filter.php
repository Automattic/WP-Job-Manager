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

	public function tearDown(): void {
		unset( $_GET['author'] );
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
}
