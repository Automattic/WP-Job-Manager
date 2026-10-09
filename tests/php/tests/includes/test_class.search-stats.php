<?php

namespace WP_Job_Manager;

class WP_Test_Search_Stats extends \WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		update_option( Stats::OPTION_ENABLE_STATS, true );
		Search_Stats::instance()->migrate_db();
	}

	public function test_log_search_creates_record_for_keyword() {
		Search_Stats::instance()->log_search( [ 'keyword' => 'Plumber' ] );

		$stats = Search_Stats::instance()->get_stats( 'keyword' );

		$this->assertCount( 1, $stats );
		$this->assertEquals( 'plumber', $stats[0]->value );
		$this->assertEquals( 1, $stats[0]->count );
	}

	public function test_log_search_normalizes_keyword() {
		Search_Stats::instance()->log_search( [ 'keyword' => '  Senior   PHP  Developer  ' ] );

		$stats = Search_Stats::instance()->get_stats( 'keyword' );

		$this->assertCount( 1, $stats );
		$this->assertEquals( 'senior php developer', $stats[0]->value );
	}

	public function test_log_search_logs_one_row_per_category_value() {
		Search_Stats::instance()->log_search( [ 'category' => [ '12', '5' ] ] );

		$stats = Search_Stats::instance()->get_stats( 'category' );

		$this->assertCount( 2, $stats );
		$this->assertEqualsCanonicalizing( [ '12', '5' ], wp_list_pluck( $stats, 'value' ) );
	}

	public function test_log_search_increments_count_for_repeated_search() {
		Search_Stats::instance()->log_search( [ 'location' => 'New York' ] );
		Search_Stats::instance()->log_search( [ 'location' => 'New York' ] );

		$stats = Search_Stats::instance()->get_stats( 'location' );

		$this->assertCount( 1, $stats );
		$this->assertEquals( 2, $stats[0]->count );
	}

	public function test_log_search_skips_empty_values() {
		$result = Search_Stats::instance()->log_search(
			[
				'keyword'  => '',
				'location' => '   ',
				'category' => [],
			]
		);

		$this->assertFalse( $result );
		$this->assertEmpty( Search_Stats::instance()->get_stats() );
	}

	public function test_log_search_noop_when_stats_disabled() {
		update_option( Stats::OPTION_ENABLE_STATS, false );

		$result = Search_Stats::instance()->log_search( [ 'keyword' => 'plumber' ] );

		update_option( Stats::OPTION_ENABLE_STATS, true );

		$this->assertFalse( $result );
		$this->assertEmpty( Search_Stats::instance()->get_stats() );
	}

	public function test_log_search_logs_multiple_filters_in_one_call() {
		Search_Stats::instance()->log_search(
			[
				'keyword'  => 'plumber',
				'location' => 'Chicago',
				'job_type' => [ 'full-time', 'part-time' ],
			]
		);

		$stats = Search_Stats::instance()->get_stats();

		$this->assertCount( 4, $stats );

		$by_filter = [];
		foreach ( $stats as $stat ) {
			$by_filter[ $stat->filter ][] = $stat->value;
		}

		$this->assertEquals( [ 'plumber' ], $by_filter['keyword'] );
		$this->assertEquals( [ 'Chicago' ], $by_filter['location'] );
		$this->assertEqualsCanonicalizing( [ 'full-time', 'part-time' ], $by_filter['job_type'] );
		foreach ( $stats as $stat ) {
			$this->assertEquals( 1, $stat->count );
		}
	}

	public function test_log_search_dedupes_repeated_value_in_same_call() {
		Search_Stats::instance()->log_search( [ 'category' => [ '12', '12' ] ] );

		$stats = Search_Stats::instance()->get_stats( 'category' );

		$this->assertCount( 1, $stats );
		$this->assertEquals( 1, $stats[0]->count );
	}

	public function test_ajax_get_listings_logs_search_and_fires_action_once() {
		$captured   = null;
		$call_count = 0;
		$listener   = function ( $filters ) use ( &$captured, &$call_count ) {
			$captured = $filters;
			$call_count++;
		};
		add_action( 'wpjm_search_stats_log', $listener );

		$_REQUEST['search_location']   = 'Chicago';
		$_REQUEST['search_keywords']   = 'welder';
		$_REQUEST['search_categories'] = null;
		$_REQUEST['filter_job_type']   = null;
		$_REQUEST['orderby']           = null;
		$_REQUEST['order']             = null;
		$_REQUEST['page']              = 1;
		$_REQUEST['per_page']          = 100;

		try {
			$instance = \WP_Job_Manager_Ajax::instance();

			add_filter( 'wp_die_ajax_handler', [ $this, 'return_do_not_die' ] );
			ob_start();
			$instance->get_listings();
			ob_end_clean();
		} finally {
			remove_filter( 'wp_die_ajax_handler', [ $this, 'return_do_not_die' ] );
			remove_action( 'wpjm_search_stats_log', $listener );
			unset( $_REQUEST['search_location'], $_REQUEST['search_keywords'], $_REQUEST['search_categories'], $_REQUEST['filter_job_type'], $_REQUEST['orderby'], $_REQUEST['order'], $_REQUEST['page'], $_REQUEST['per_page'] );
		}

		$this->assertEquals( 1, $call_count );
		$this->assertIsArray( $captured );
		$this->assertEquals(
			[
				'keyword'  => 'welder',
				'location' => 'Chicago',
				'category' => [],
				'job_type' => [],
			],
			$captured
		);

		$stats = Search_Stats::instance()->get_stats();
		$this->assertCount( 2, $stats );
	}

	public function test_ajax_get_listings_does_not_log_on_second_page() {
		$call_count = 0;
		$listener   = function () use ( &$call_count ) {
			$call_count++;
		};
		add_action( 'wpjm_search_stats_log', $listener );

		$_REQUEST['search_location']   = null;
		$_REQUEST['search_keywords']   = 'welder';
		$_REQUEST['search_categories'] = null;
		$_REQUEST['filter_job_type']   = null;
		$_REQUEST['orderby']           = null;
		$_REQUEST['order']             = null;
		$_REQUEST['page']              = 2;
		$_REQUEST['per_page']          = 100;

		try {
			$instance = \WP_Job_Manager_Ajax::instance();

			add_filter( 'wp_die_ajax_handler', [ $this, 'return_do_not_die' ] );
			ob_start();
			$instance->get_listings();
			ob_end_clean();
		} finally {
			remove_filter( 'wp_die_ajax_handler', [ $this, 'return_do_not_die' ] );
			remove_action( 'wpjm_search_stats_log', $listener );
			unset( $_REQUEST['search_location'], $_REQUEST['search_keywords'], $_REQUEST['search_categories'], $_REQUEST['filter_job_type'], $_REQUEST['orderby'], $_REQUEST['order'], $_REQUEST['page'], $_REQUEST['per_page'] );
		}

		$this->assertEquals( 0, $call_count );
		$this->assertEmpty( Search_Stats::instance()->get_stats() );
	}

	public function test_log_search_skips_when_rate_limited_by_ip() {
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';

		// Prime the transient by calling once.
		Search_Stats::instance()->log_search( [ 'keyword' => 'first' ] );

		// Second call within RATE_LIMIT_SECONDS should be dropped.
		Search_Stats::instance()->log_search( [ 'keyword' => 'second' ] );

		$stats = Search_Stats::instance()->get_stats( 'keyword' );
		$this->assertCount( 1, $stats );
		$this->assertEquals( 'first', $stats[0]->value );

		unset( $_SERVER['REMOTE_ADDR'] );
	}

	public function test_prune_deletes_rows_older_than_retention_window() {
		$old_date = gmdate( 'Y-m-d', time() - ( Search_Stats::RETENTION_DAYS + 1 ) * DAY_IN_SECONDS );
		$recent   = gmdate( 'Y-m-d', time() - 5 * DAY_IN_SECONDS );

		global $wpdb;
		$wpdb->insert(
			$wpdb->wpjm_search_stats,
			[
				'date'      => $old_date,
				'filter'    => 'keyword',
				'value'     => 'old-term',
				'value_hash' => md5( 'keyword|old-term' ),
				'count'     => 1,
			],
			[ '%s', '%s', '%s', '%s', '%d' ]
		);
		$wpdb->insert(
			$wpdb->wpjm_search_stats,
			[
				'date'      => $recent,
				'filter'    => 'keyword',
				'value'     => 'recent-term',
				'value_hash' => md5( 'keyword|recent-term' ),
				'count'     => 1,
			],
			[ '%s', '%s', '%s', '%s', '%d' ]
		);

		$deleted = Search_Stats::instance()->prune();

		$this->assertEquals( 1, $deleted );
		$remaining = Search_Stats::instance()->get_stats();
		$this->assertCount( 1, $remaining );
		$this->assertEquals( 'recent-term', $remaining[0]->value );
	}

}
