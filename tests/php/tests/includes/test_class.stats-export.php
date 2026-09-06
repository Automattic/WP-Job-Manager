<?php

namespace WP_Job_Manager;

class WP_Test_Stats_Export extends \WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		update_option( Stats::OPTION_ENABLE_STATS, true );
		Stats::instance()->migrate_db();

		// WP_UnitTestCase wipes filters between tests; Stats_Export is a singleton
		// whose constructor only registers hooks once. Re-add the action for each test.
		add_action( 'admin_post_' . Stats_Export::NONCE_ACTION, [ Stats_Export::instance(), 'handle_export' ] );

		// The suite bootstrap defines DOING_AJAX, so wp_die() resolves the ajax handler.
		// Turn it into an exception so denial paths stop the handler without ending the test run.
		add_filter( 'wp_die_ajax_handler', [ $this, 'throw_on_die' ] );
	}

	public function tearDown(): void {
		remove_filter( 'wp_die_ajax_handler', [ $this, 'throw_on_die' ] );
		$_GET = [];
		parent::tearDown();
	}

	/**
	 * Return the wp_die handler that converts die calls into exceptions.
	 *
	 * @return callable
	 */
	public function throw_on_die() {
		return [ $this, 'die_with_exception' ];
	}

	/**
	 * Stop execution with an exception instead of dying.
	 *
	 * @throws \WPDieException Always.
	 */
	public function die_with_exception() {
		throw new \WPDieException();
	}

	/**
	 * Build the $_GET payload with a valid export nonce for a job.
	 *
	 * @param int $job_id Job listing post ID.
	 * @return void
	 */
	private function set_request( $job_id ) {
		$_GET = [
			'action'   => Stats_Export::NONCE_ACTION,
			'job_id'   => $job_id,
			'_wpnonce' => wp_create_nonce( Stats_Export::NONCE_ACTION . '_' . $job_id ),
		];
	}

	/**
	 * Invoke the export action. Denial paths raise WPDieException.
	 *
	 * @return void
	 */
	private function invoke_export() {
		do_action( 'admin_post_' . Stats_Export::NONCE_ACTION );
	}

	/**
	 * Assert the export request was denied before any output.
	 *
	 * @return void
	 */
	private function expect_denial() {
		$this->expectException( \WPDieException::class );
	}

	public function test_export_rejects_invalid_nonce() {
		$job_id = $this->factory->job_listing->create();
		$this->set_request( $job_id );
		$_GET['_wpnonce'] = 'invalid';

		$this->expect_denial();
		$this->invoke_export();
	}

	public function test_export_rejects_missing_job() {
		$this->set_request( 0 );

		$this->expect_denial();
		$this->invoke_export();
	}

	public function test_export_rejects_non_listing_post() {
		$post_id = $this->factory->post->create( [ 'post_type' => 'post' ] );
		$this->set_request( $post_id );

		$this->expect_denial();
		$this->invoke_export();
	}

	public function test_export_rejects_when_disabled() {
		update_option( Stats::OPTION_ENABLE_STATS, false );
		$job_id = $this->factory->job_listing->create();
		$this->set_request( $job_id );

		$this->expect_denial();
		$this->invoke_export();
	}

	public function test_export_rejects_user_without_permission() {
		$user_id = self::factory()->user->create();
		$job_id  = $this->factory->job_listing->create( [ 'post_author' => $user_id ] );

		// Request carries a valid nonce but is made by a user who cannot edit the job.
		$this->set_request( $job_id );
		wp_set_current_user( self::factory()->user->create() );

		$this->expect_denial();
		$this->invoke_export();
	}

	public function test_export_returns_no_rows_for_job_without_publish_date() {
		// get_post_datetime() returns false for a post with no date, so no rows are built.
		$job = new \WP_Post(
			(object) [
				'ID'        => 1,
				'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_date' => '',
			]
		);

		$this->assertSame( [], Stats_Export::instance()->get_csv_rows( $job ) );
	}

	public function test_export_csv_rows_zero_fill_days_without_stats() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );
		$job_id = $this->factory->job_listing->create(
			[ 'post_date' => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ) ]
		);
		$job    = get_post( $job_id );

		$yesterday = date( 'Y-m-d', strtotime( '-1 day' ) );
		$today     = date( 'Y-m-d' );

		Stats::instance()->batch_log_stats(
			[
				[ 'name' => Job_Listing_Stats::VIEW, 'post_id' => $job_id, 'count' => 3, 'date' => $yesterday ],
				[ 'name' => Job_Listing_Stats::VIEW_UNIQUE, 'post_id' => $job_id, 'count' => 2, 'date' => $yesterday ],
				[ 'name' => Job_Listing_Stats::SEARCH_IMPRESSION, 'post_id' => $job_id, 'count' => 7, 'date' => $yesterday ],
				[ 'name' => Job_Listing_Stats::APPLY_CLICK, 'post_id' => $job_id, 'count' => 1, 'date' => $yesterday ],
			]
		);

		$rows = Stats_Export::instance()->get_csv_rows( $job );

		$this->assertSame(
			[
				'Date',
				'Page views',
				'Unique visitors',
				'Search impressions',
				'Apply clicks',
			],
			$rows[0]
		);
		$this->assertSame( [ $yesterday, 3, 2, 7, 1 ], $rows[1] );
		$this->assertSame( [ $today, 0, 0, 0, 0 ], $rows[2] );
		$this->assertCount( 3, $rows );
	}

	public function test_export_url_contains_action_and_job_id() {
		$job_id = $this->factory->job_listing->create();

		$url = Stats_Export::get_export_url( $job_id );

		$this->assertStringContainsString( 'admin-post.php', $url );
		$this->assertStringContainsString( 'action=' . Stats_Export::NONCE_ACTION, $url );
		$this->assertStringContainsString( 'job_id=' . $job_id, $url );
	}
}
