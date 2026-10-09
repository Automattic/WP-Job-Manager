<?php

/**
 * Tests for scheduled job listing date handling.
 *
 * @group forms
 */
class WP_Test_Job_Manager_Form_Submit_Job extends \WPJM_BaseTest {

	/**
	 * @var string Original timezone_string option, restored in tearDown.
	 */
	private $original_timezone;

	/**
	 * @var string Original gmt_offset option, restored in tearDown.
	 */
	private $original_gmt_offset;

	public function setUp(): void {
		parent::setUp();
		$this->original_timezone   = get_option( 'timezone_string' );
		$this->original_gmt_offset = get_option( 'gmt_offset' );
	}

	public function tearDown(): void {
		update_option( 'timezone_string', $this->original_timezone );
		update_option( 'gmt_offset', $this->original_gmt_offset );
		parent::tearDown();
	}

	/**
	 * Set the site timezone the way WP Settings > General does.
	 *
	 * @param string $timezone_string Olson timezone identifier, or '' for a manual offset.
	 * @param float  $gmt_offset      Offset in hours, used only when $timezone_string is ''.
	 */
	private function set_site_timezone( $timezone_string, $gmt_offset ) {
		update_option( 'timezone_string', $timezone_string );
		update_option( 'gmt_offset', $gmt_offset );
	}

	public function test_scheduled_date_stores_local_and_gmt_in_utc_timezone() {
		$this->set_site_timezone( 'UTC', 0 );

		$job_data = [];
		$result   = WP_Job_Manager_Form_Submit_Job::apply_scheduled_date( $job_data, '2030-01-02' );

		$this->assertTrue( $result );
		$this->assertSame( '2030-01-02 00:00:00', $job_data['post_date'] );
		$this->assertSame( '2030-01-02 00:00:00', $job_data['post_date_gmt'] );
	}

	public function test_scheduled_date_stores_local_and_gmt_in_non_utc_timezone() {
		$this->set_site_timezone( 'Australia/Sydney', 0 );

		// Australia/Sydney is UTC+11 in January (daylight saving time).
		$job_data = [];
		$result   = WP_Job_Manager_Form_Submit_Job::apply_scheduled_date( $job_data, '2030-01-02' );

		$this->assertTrue( $result );
		$this->assertSame( '2030-01-02 00:00:00', $job_data['post_date'] );
		$this->assertSame( '2030-01-01 13:00:00', $job_data['post_date_gmt'] );
	}

	public function test_scheduled_date_gmt_honors_manual_fractional_offset() {
		// Manual offset (no Olson name), as with a UTC+5:30 site. The GMT projection
		// must reflect the offset even though post_date itself is the local wall clock.
		$this->set_site_timezone( '', 5.5 );

		$job_data = [];
		$result   = WP_Job_Manager_Form_Submit_Job::apply_scheduled_date( $job_data, '2030-01-02' );

		$this->assertTrue( $result );
		$this->assertSame( '2030-01-02 00:00:00', $job_data['post_date'] );
		$this->assertSame( '2030-01-01 18:30:00', $job_data['post_date_gmt'] );
	}

	public function test_scheduled_date_preserves_time_with_time_input() {
		$this->set_site_timezone( 'UTC', 0 );

		$job_data = [];
		$result   = WP_Job_Manager_Form_Submit_Job::apply_scheduled_date( $job_data, '2030-01-02 09:30' );

		$this->assertTrue( $result );
		$this->assertSame( '2030-01-02 09:30:00', $job_data['post_date'] );
		$this->assertSame( '2030-01-02 09:30:00', $job_data['post_date_gmt'] );
	}

	public function test_scheduled_date_falls_back_to_now_when_time_is_invalid() {
		$this->assert_fallback_to_now_for( '2030-01-02 25:99' );
	}

	public function test_scheduled_date_falls_back_to_now_when_in_the_past() {
		$this->set_site_timezone( 'UTC', 0 );

		$this->assert_fallback_to_now_for( '2000-01-01' );
	}

	public function test_scheduled_date_falls_back_to_now_when_empty() {
		$this->assert_fallback_to_now_for( '' );
	}

	public function test_scheduled_date_falls_back_to_now_when_invalid() {
		$this->assert_fallback_to_now_for( 'not-a-date' );
	}

	/**
	 * Assert apply_scheduled_date falls back to the current time for a given input.
	 *
	 * The method sets post_date from a current_time() call, so a second read (also
	 * current_time) could cross a second boundary and flake. Compare against a range
	 * instead of requiring an exact match.
	 *
	 * @param string $scheduled_date The scheduled date input.
	 */
	private function assert_fallback_to_now_for( $scheduled_date ) {
		$start    = time();
		$job_data = [];
		$result   = WP_Job_Manager_Form_Submit_Job::apply_scheduled_date( $job_data, $scheduled_date );
		$end      = time() + 1;

		$this->assertFalse( $result );
		$this->assertTrue(
			$job_data['post_date'] >= current_time( 'mysql', 0, $start ) && $job_data['post_date'] <= current_time( 'mysql', 0, $end ),
			'post_date is not within the expected time window.'
		);
		$this->assertTrue(
			$job_data['post_date_gmt'] >= current_time( 'mysql', 1, $start ) && $job_data['post_date_gmt'] <= current_time( 'mysql', 1, $end ),
			'post_date_gmt is not within the expected time window.'
		);
	}
}
