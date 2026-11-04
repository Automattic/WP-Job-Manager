<?php
/**
 * Regression tests: the job listing feeds serve published listings only.
 *
 * The `expired` status is registered `'public' => true`, so a feed query that does not pin
 * `post_status` includes expired listings. The plugin's own job_feed() pins `publish`; the
 * gate for the default RSS / Atom endpoints must do the same.
 *
 * @package wp-job-manager/tests
 */

class Tests_Feed_Expired_Listings extends WPJM_BaseTest {

	/**
	 * @covers WP_Job_Manager_Post_Types::gate_feed_query_for_listings
	 */
	public function test_default_feed_omits_expired_listings() {
		$published = $this->factory->job_listing->create( [ 'post_title' => 'Live listing' ] );
		$expired   = $this->factory->job_listing->create(
			[
				'post_title'  => 'Expired listing',
				'post_status' => 'expired',
			]
		);

		$this->go_to( '/?post_type=job_listing&feed=rss2' );

		$ids = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );

		$this->assertContains( $published, $ids, 'Published listings must stay in the feed.' );
		$this->assertNotContains( $expired, $ids, 'Expired listings must not be served by the feed.' );
	}

	/**
	 * The gate pins the published status on the feed query itself, so taxonomy feeds that
	 * run through the same gate are covered too.
	 *
	 * @covers WP_Job_Manager_Post_Types::gate_feed_query_for_listings
	 */
	public function test_feed_gate_pins_published_status() {
		$previous_main_query = isset( $GLOBALS['wp_the_query'] ) ? $GLOBALS['wp_the_query'] : null;

		$query          = new WP_Query();
		$query->is_feed = true;
		$query->set( 'post_type', WP_Job_Manager_Post_Types::PT_LISTING );
		// is_main_query() compares identity against the global query.
		$GLOBALS['wp_the_query'] = $query;

		try {
			WP_Job_Manager_Post_Types::instance()->gate_feed_query_for_listings( $query );
		} finally {
			$GLOBALS['wp_the_query'] = $previous_main_query;
		}

		$this->assertSame( 'publish', $query->get( 'post_status' ) );
	}
}
