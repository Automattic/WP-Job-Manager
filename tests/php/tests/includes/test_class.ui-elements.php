<?php

namespace WP_Job_Manager;

use WP_Job_Manager\UI\UI_Elements;

/**
 * @group ui
 */
class WP_Test_UI_Elements extends \WPJM_BaseTest {

	/**
	 * @var string Original date_format option, restored in tearDown.
	 */
	private $original_date_format;

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
		$this->original_date_format = get_option( 'date_format' );
		$this->original_timezone    = get_option( 'timezone_string' );
		$this->original_gmt_offset  = get_option( 'gmt_offset' );
		// Use an unambiguous calendar-date format so assertions compare the date directly.
		update_option( 'date_format', 'Y-m-d' );
	}

	public function tearDown(): void {
		update_option( 'date_format', $this->original_date_format );
		update_option( 'timezone_string', $this->original_timezone );
		update_option( 'gmt_offset', $this->original_gmt_offset );
		parent::tearDown();
	}

	/**
	 * Set the site timezone the way WP Settings > General does.
	 *
	 * Both options must be written together: wp_timezone() prefers timezone_string
	 * and only falls back to gmt_offset when it is empty, so a stale value in either
	 * one would silently decide the timezone for the test.
	 *
	 * @param string $timezone_string Olson timezone identifier, or '' for a manual offset.
	 * @param float  $gmt_offset      Offset in hours, used only when $timezone_string is ''.
	 */
	private function set_site_timezone( $timezone_string, $gmt_offset ) {
		update_option( 'timezone_string', $timezone_string );
		update_option( 'gmt_offset', $gmt_offset );
	}

	/**
	 * Extract the value of the datetime attribute from rel_time() output.
	 *
	 * @param string $html rel_time() output.
	 *
	 * @return string
	 */
	private function get_datetime_attr( $html ) {
		$this->assertMatchesRegularExpression( '/datetime="([^"]+)"/', $html );
		preg_match( '/datetime="([^"]+)"/', $html, $matches );
		return $matches[1];
	}

	/**
	 * Extract the value of the title attribute from rel_time() output.
	 *
	 * @param string $html rel_time() output.
	 *
	 * @return string
	 */
	private function get_title_attr( $html ) {
		$this->assertMatchesRegularExpression( '/title="([^"]+)"/', $html );
		preg_match( '/title="([^"]+)"/', $html, $matches );
		return $matches[1];
	}

	/**
	 * Timezones spanning negative, positive, and zero UTC offsets, configured both
	 * ways WP Settings > General allows: as an Olson timezone_string, and as a manual
	 * gmt_offset with an empty timezone_string (including a half-hour offset, where
	 * wp_timezone() returns a fixed offset zone rather than a named one).
	 *
	 * @return array
	 */
	public function data_timezones() {
		return [
			'negative offset (UTC-5)'            => [ 'America/Panama', 0 ],
			'positive offset (UTC+9)'            => [ 'Asia/Tokyo', 0 ],
			'zero offset (UTC)'                  => [ 'UTC', 0 ],
			'manual negative offset (UTC-5)'     => [ '', -5 ],
			'manual half-hour offset (UTC+5:30)' => [ '', 5.5 ],
		];
	}

	/**
	 * A DateTimeInterface built end-of-day in site time (as core listing expiry is)
	 * must render its own calendar date, not the next day, on any offset.
	 *
	 * This is the case that pins the reported bug: the negative-offset (UTC-5)
	 * row is the one that renders "2026-08-14" under the old date_i18n() code and
	 * "2026-08-13" after the fix. The positive/zero-offset rows are unaffected by
	 * the bug and guard against a fix that would break them.
	 *
	 * @dataProvider data_timezones
	 *
	 * @param string $timezone_string Timezone string, or '' for a manual offset.
	 * @param float  $gmt_offset      Manual offset in hours.
	 */
	public function test_rel_time_datetime_interface_renders_calendar_date( $timezone_string, $gmt_offset ) {
		$this->set_site_timezone( $timezone_string, $gmt_offset );

		$expiration = new \DateTimeImmutable( '2026-08-13 23:59:59', wp_timezone() );

		$this->assertSame( '2026-08-13', $this->get_datetime_attr( UI_Elements::rel_time( $expiration ) ) );
	}

	/**
	 * A bare Y-m-d string (as the Application Deadline add-on passes its closing date)
	 * is a floating calendar date and must render as-is, not shifted one day earlier.
	 *
	 * @dataProvider data_timezones
	 *
	 * @param string $timezone_string Timezone string, or '' for a manual offset.
	 * @param float  $gmt_offset      Manual offset in hours.
	 */
	public function test_rel_time_date_string_renders_calendar_date( $timezone_string, $gmt_offset ) {
		$this->set_site_timezone( $timezone_string, $gmt_offset );

		$this->assertSame( '2026-08-13', $this->get_datetime_attr( UI_Elements::rel_time( '2026-08-13' ) ) );
	}

	/**
	 * The human-readable relative text (wrapped by the format string) is preserved.
	 */
	public function test_rel_time_preserves_relative_text_format() {
		$this->set_site_timezone( 'America/Panama', 0 );

		$expiration = new \DateTimeImmutable( '2026-08-13 23:59:59', wp_timezone() );
		$html       = UI_Elements::rel_time( $expiration, 'Expires in %s' );

		$this->assertStringContainsString( 'Expires in ', $html );
		$this->assertStringContainsString( human_time_diff( $expiration->getTimestamp() ), $html );
	}

	/**
	 * An empty or whitespace-only string yields no output, rather than rendering
	 * the current time (which date_create() would otherwise return).
	 */
	public function test_rel_time_empty_string_renders_nothing() {
		$this->assertSame( '', UI_Elements::rel_time( '' ) );
		$this->assertSame( '', UI_Elements::rel_time( '   ' ) );
	}

	/**
	 * A non-empty but unparseable string yields no output: date_create() returns
	 * false, so the helper renders nothing rather than falling back to the current
	 * time or emitting a malformed element.
	 */
	public function test_rel_time_unparseable_string_renders_nothing() {
		$this->assertSame( '', UI_Elements::rel_time( 'not a date' ) );
	}

	/**
	 * The datetime attribute must be a machine-readable HTML date string (Y-m-d)
	 * regardless of the site's display date_format, while the title attribute keeps
	 * the localized display date. A non-ISO date_format is used so the two diverge.
	 */
	public function test_rel_time_datetime_attribute_is_machine_readable() {
		update_option( 'date_format', 'F j, Y' );
		$this->set_site_timezone( 'America/Panama', -5 );

		$expiration = new \DateTimeImmutable( '2026-08-13 23:59:59', wp_timezone() );
		$html       = UI_Elements::rel_time( $expiration );

		$this->assertSame( '2026-08-13', $this->get_datetime_attr( $html ), 'datetime must be machine-readable Y-m-d.' );
		$this->assertSame( 'August 13, 2026', $this->get_title_attr( $html ), 'title must be the localized display date.' );
	}

	/**
	 * A numeric timestamp is honored whether passed as an int or a numeric string:
	 * both resolve via the is_numeric branch and render the same calendar date.
	 *
	 * The instant is built at midday site time so it sits well inside the local
	 * calendar day on every offset, letting the expected date be asserted literally
	 * rather than recomputed with the same wp_date() call the code under test makes.
	 *
	 * @dataProvider data_timezones
	 *
	 * @param string $timezone_string Timezone string, or '' for a manual offset.
	 * @param float  $gmt_offset      Manual offset in hours.
	 */
	public function test_rel_time_numeric_timestamp_renders_calendar_date( $timezone_string, $gmt_offset ) {
		$this->set_site_timezone( $timezone_string, $gmt_offset );

		$timestamp = ( new \DateTimeImmutable( '2026-08-13 12:00:00', wp_timezone() ) )->getTimestamp();

		$this->assertSame( '2026-08-13', $this->get_datetime_attr( UI_Elements::rel_time( $timestamp ) ) );
		$this->assertSame( '2026-08-13', $this->get_datetime_attr( UI_Elements::rel_time( (string) $timestamp ) ) );
	}
}
