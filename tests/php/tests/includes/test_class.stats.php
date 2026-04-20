<?php

namespace WP_Job_Manager;

class WP_Test_Stats extends \WPJM_BaseTest {

	//setup
	public function setUp(): void {
		parent::setUp();
		update_option( Stats::OPTION_ENABLE_STATS, true );
		Stats::instance()->migrate_db();

		// WP_UnitTestCase wipes filters between tests; Stats_Script is a singleton
		// whose constructor only registers hooks once. Re-add the action for each test.
		add_action( 'wp_ajax_job_manager_log_stat', [ Stats_Script::instance(), 'ajax_log_stat' ] );
		add_action( 'wp_ajax_nopriv_job_manager_log_stat', [ Stats_Script::instance(), 'ajax_log_stat' ] );

		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$this->clear_stats_transients();

		add_filter( 'wp_die_ajax_handler', [ $this, 'return_do_not_die' ] );
		add_filter( 'wp_doing_ajax', '__return_true' );
	}

	public function tearDown(): void {
		remove_filter( 'wp_die_ajax_handler', [ $this, 'return_do_not_die' ] );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		$this->clear_stats_transients();
		$_POST = [];
		parent::tearDown();

	}

	private function clear_stats_transients() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_wpjm\_%' OR option_name LIKE '\_transient\_timeout\_wpjm\_%'" );
		wp_cache_flush();
	}

	/**
	 * Helper to build the $_POST payload with a valid nonce tied to a page-level post_id.
	 *
	 * @param int   $page_post_id   Page-level post_id used for nonce binding.
	 * @param array $stats_payload  Array of stat rows to JSON-encode into the `stats` field.
	 * @return void
	 */
	private function set_request( $page_post_id, $stats_payload ) {
		$_POST = [
			'post_id'     => $page_post_id,
			'stats'       => wp_json_encode( $stats_payload ),
			'_ajax_nonce' => wp_create_nonce( 'wpjm_log_stat_' . $page_post_id ),
		];
	}

	private function invoke_ajax() {
		ob_start();
		try {
			do_action( 'wp_ajax_job_manager_log_stat' );
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			// wp_die may throw; swallow to let the test inspect DB state.
		}
		ob_end_clean();
	}

	public function test_log_stat_creates_record() {
		$job_id = $this->factory->job_listing->create();

		Stats::instance()->log_stat( 'test_stat', [ 'post_id' => $job_id, 'count' => 1 ] );

		$stats = Stats::instance()->get_stats( null, $job_id );

		$this->assertNotEmpty( $stats );
		$this->assertEquals( 'test_stat', $stats[0]->name );

	}

	public function test_log_stat_exits_when_disabled() {
		update_option( Stats::OPTION_ENABLE_STATS, false );
		Stats::instance()->log_stat( 'test_stat', [ 'post_id' => 1, 'count' => 1 ] );

		$stats = Stats::instance()->get_stats( null, 1 );

		$this->assertEmpty( $stats );

	}

	public function test_log_stat_increases_count() {
		$post_id = $this->factory->job_listing->create();

		Stats::instance()->log_stat( 'test_stat', [ 'post_id' => $post_id, 'count' => 1 ] );
		Stats::instance()->log_stat( 'test_stat', [ 'post_id' => $post_id, 'count' => 1 ] );

		$stats = Stats::instance()->get_stats( 'test_stat', $post_id );

		$this->assertNotEmpty( $stats );
		$this->assertEquals( 2, $stats[0]->count );

	}

	public function test_batch_log_stats_creates_records() {
		$post_a = $this->factory->job_listing->create();
		$post_b = $this->factory->job_listing->create();

		$stats = [
			[ 'name' => 'test_stat', 'post_id' => $post_a, 'count' => 1 ],
			[ 'name' => 'test_stat', 'post_id' => $post_b, 'count' => 2 ],
			[ 'name' => 'test_stat_2', 'post_id' => $post_a, 'count' => 1 ],
		];

		Stats::instance()->batch_log_stats( $stats );

		$stats = Stats::instance()->get_stats();

		$this->assertCount( 3, $stats );

	}

	public function test_batch_log_stats_increases_counts() {
		$post_a = $this->factory->job_listing->create();
		$post_b = $this->factory->job_listing->create();

		$stats = [
			[ 'name' => 'test_stat', 'post_id' => $post_a, 'count' => 1 ],
			[ 'name' => 'test_stat', 'post_id' => $post_a, 'count' => 1 ],
			[ 'name' => 'test_stat', 'post_id' => $post_b, 'count' => 2 ],
		];

		Stats::instance()->batch_log_stats( $stats );
		Stats::instance()->batch_log_stats( $stats );

		$stats = Stats::instance()->get_stats();

		$this->assertCount( 2, $stats );
		$this->assertEquals( 4, $stats[0]->count );
		$this->assertEquals( 4, $stats[1]->count );

	}

	public function test_delete_stats_deletes_post_stats() {
		$post_a = $this->factory->job_listing->create();
		$post_b = $this->factory->job_listing->create();

		$stats = [
			[ 'name' => 'test_stat', 'post_id' => $post_a, 'count' => 1 ],
			[ 'name' => 'test_stat', 'post_id' => $post_b, 'count' => 2 ],
			[ 'name' => 'test_stat_2', 'post_id' => $post_a, 'count' => 1 ],
		];

		Stats::instance()->batch_log_stats( $stats );

		Stats::instance()->delete_stats( $post_a );

		$stats = Stats::instance()->get_stats( null, $post_a );

		$this->assertEmpty( $stats );

	}

	public function test_job_listing_stats_counts_totals() {

		$job_id = $this->factory->job_listing->create();

		Stats::instance()->batch_log_stats( [
			[ 'name' => 'test_stat', 'post_id' => $job_id, 'count' => 1 ],
			[ 'name' => 'test_stat', 'post_id' => $job_id, 'count' => 1 ],
		] );

		$job_stats = new Job_Listing_Stats( $job_id );

		$total = $job_stats->get_event_total( 'test_stat' );

		$this->assertEquals( 2, $total );
	}

	public function test_job_listing_stats_counts_daily_stats() {

		$job_id = $this->factory->job_listing->create();

		Stats::instance()->batch_log_stats( [
			[ 'name' => 'test_stat', 'post_id' => $job_id, 'count' => 1, 'date' => '2020-01-01' ],
			[ 'name' => 'test_stat', 'post_id' => $job_id, 'count' => 1, 'date' => '2020-01-01' ],
			[ 'name' => 'test_stat', 'post_id' => $job_id, 'count' => 1, 'date' => '2020-01-02' ],
		] );

		$job_stats = new Job_Listing_Stats( $job_id, [
			new \DateTime( '2020-01-01' ),
			new \DateTime( '2020-01-02' ),
		] );

		$daily = $job_stats->get_event_daily( 'test_stat' );

		$this->assertEquals( [ '2020-01-01' => 2, '2020-01-02' => 1 ], $daily );

	}

	public function test_ajax_stats_logged() {
		$job_id = $this->factory->job_listing->create();

		$this->set_request( $job_id, [
			[ 'post_id' => $job_id, 'name' => 'job_view' ],
			[ 'post_id' => $job_id, 'name' => 'job_view_unique' ],
		] );

		$this->invoke_ajax();

		$stats = Stats::instance()->get_stats( null, $job_id );

		$this->assertEquals( [ 'job_view', 'job_view_unique' ], wp_list_pluck( $stats, 'name' ) );
	}

	public function test_ajax_rejects_old_generic_nonce() {
		$job_id = $this->factory->job_listing->create();

		$_POST = [
			'post_id'     => $job_id,
			'stats'       => wp_json_encode( [
				[ 'post_id' => $job_id, 'name' => 'job_view' ],
			] ),
			'_ajax_nonce' => wp_create_nonce( 'ajax-nonce' ),
		];

		$this->invoke_ajax();

		$this->assertEmpty( Stats::instance()->get_stats( null, $job_id ) );
	}

	public function test_ajax_rejects_mismatched_post_id_nonce() {
		$job_a = $this->factory->job_listing->create();
		$job_b = $this->factory->job_listing->create();

		// Nonce is tied to job_a, but the request claims to come from the page for job_b.
		$_POST = [
			'post_id'     => $job_b,
			'stats'       => wp_json_encode( [
				[ 'post_id' => $job_a, 'name' => 'job_view' ],
			] ),
			'_ajax_nonce' => wp_create_nonce( 'wpjm_log_stat_' . $job_a ),
		];

		$this->invoke_ajax();

		$this->assertEmpty( Stats::instance()->get_stats( null, $job_a ) );
		$this->assertEmpty( Stats::instance()->get_stats( null, $job_b ) );
	}

	public function test_ajax_rejects_listing_stat_for_non_listing_post() {
		$page_id = $this->factory->post->create( [ 'post_type' => 'post', 'post_status' => 'publish' ] );

		$this->set_request( $page_id, [
			[ 'post_id' => $page_id, 'name' => 'job_view' ],
		] );

		$this->invoke_ajax();

		$this->assertEmpty( Stats::instance()->get_stats( null, $page_id ) );
	}

	public function test_ajax_rejects_non_published_listing() {
		$draft_id = $this->factory->job_listing->create( [ 'post_status' => 'draft' ] );

		$this->set_request( $draft_id, [
			[ 'post_id' => $draft_id, 'name' => 'job_view' ],
		] );

		$this->invoke_ajax();

		$this->assertEmpty( Stats::instance()->get_stats( null, $draft_id ) );
	}

	public function test_ajax_rejects_nonexistent_post() {
		$job_id = $this->factory->job_listing->create();

		$this->set_request( $job_id, [
			[ 'post_id' => 999999, 'name' => 'job_view' ],
		] );

		$this->invoke_ajax();

		$this->assertEmpty( Stats::instance()->get_stats( null, 999999 ) );
	}

	public function test_ajax_allows_search_view_for_page() {
		$page_id = $this->factory->post->create( [ 'post_type' => 'page', 'post_status' => 'publish' ] );

		$this->set_request( $page_id, [
			[ 'post_id' => $page_id, 'name' => 'search_view' ],
		] );

		$this->invoke_ajax();

		$stats = Stats::instance()->get_stats( 'search_view', $page_id );
		$this->assertNotEmpty( $stats );
	}

	public function test_ajax_rejects_impression_for_non_listing() {
		$page_id = $this->factory->post->create( [ 'post_type' => 'page', 'post_status' => 'publish' ] );

		$this->set_request( $page_id, [
			[ 'post_id' => $page_id, 'name' => 'job_search_impression' ],
		] );

		$this->invoke_ajax();

		$this->assertEmpty( Stats::instance()->get_stats( 'job_search_impression', $page_id ) );
	}

	public function test_ajax_clamps_count_to_one() {
		$job_id = $this->factory->job_listing->create();

		$this->set_request( $job_id, [
			[ 'post_id' => $job_id, 'name' => 'job_view', 'count' => 999 ],
		] );

		$this->invoke_ajax();

		$stats = Stats::instance()->get_stats( 'job_view', $job_id );
		$this->assertNotEmpty( $stats );
		$this->assertEquals( 1, $stats[0]->count );
	}

	public function test_ajax_forces_server_date() {
		$job_id = $this->factory->job_listing->create();

		$this->set_request( $job_id, [
			[ 'post_id' => $job_id, 'name' => 'job_view', 'date' => '2000-01-01' ],
		] );

		$this->invoke_ajax();

		$stats = Stats::instance()->get_stats( 'job_view', $job_id );
		$this->assertNotEmpty( $stats );
		$this->assertEquals( gmdate( 'Y-m-d' ), $stats[0]->date );
	}

	public function test_ajax_caps_batch_size() {
		$job_ids = [];
		for ( $i = 0; $i < 60; $i++ ) {
			$job_ids[] = $this->factory->job_listing->create();
		}

		$page_id = $job_ids[0];

		$payload = [];
		foreach ( $job_ids as $id ) {
			$payload[] = [ 'post_id' => $id, 'name' => 'job_view' ];
		}

		$this->set_request( $page_id, $payload );

		$this->invoke_ajax();

		$stats = Stats::instance()->get_stats( 'job_view' );
		$this->assertCount( 50, $stats );
	}

	public function test_ajax_server_dedup_blocks_duplicate_unique() {
		$job_id = $this->factory->job_listing->create();

		$this->set_request( $job_id, [
			[ 'post_id' => $job_id, 'name' => 'job_view_unique' ],
		] );
		$this->invoke_ajax();

		$this->set_request( $job_id, [
			[ 'post_id' => $job_id, 'name' => 'job_view_unique' ],
		] );
		$this->invoke_ajax();

		$stats = Stats::instance()->get_stats( 'job_view_unique', $job_id );
		$this->assertNotEmpty( $stats );
		$this->assertEquals( 1, $stats[0]->count );
	}

	public function test_ajax_non_unique_stats_not_deduped() {
		$job_id = $this->factory->job_listing->create();

		$this->set_request( $job_id, [
			[ 'post_id' => $job_id, 'name' => 'job_view' ],
		] );
		$this->invoke_ajax();

		$this->set_request( $job_id, [
			[ 'post_id' => $job_id, 'name' => 'job_view' ],
		] );
		$this->invoke_ajax();

		$stats = Stats::instance()->get_stats( 'job_view', $job_id );
		$this->assertNotEmpty( $stats );
		$this->assertEquals( 2, $stats[0]->count );
	}

	public function test_ajax_rate_limit_blocks_excess_requests() {
		$job_id = $this->factory->job_listing->create();

		// Seed the rate-limit transient near its ceiling so we don't need 60 iterations.
		set_transient( 'wpjm_rl_' . md5( '127.0.0.1' ), 60, MINUTE_IN_SECONDS );

		$this->set_request( $job_id, [
			[ 'post_id' => $job_id, 'name' => 'job_view' ],
		] );
		$this->invoke_ajax();

		$this->assertEmpty( Stats::instance()->get_stats( 'job_view', $job_id ) );
	}

	public function test_parse_stats_rejects_trashed_post() {
		$job_id = $this->factory->job_listing->create();
		wp_trash_post( $job_id );

		$result = Stats::instance()->log_stat( 'job_view', [ 'post_id' => $job_id, 'count' => 1 ] );

		$this->assertFalse( $result );
	}

	public function test_parse_stats_rejects_malformed_date() {
		$job_id = $this->factory->job_listing->create();

		$result = Stats::instance()->log_stat(
			'job_view',
			[ 'post_id' => $job_id, 'count' => 1, 'date' => 'not-a-date' ]
		);

		$this->assertFalse( $result );
	}

}
