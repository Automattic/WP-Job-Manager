<?php

class WP_Test_WP_Job_Manager_Template extends WPJM_BaseTest {
	public function setUp(): void {
		parent::setUp();
		$this->reregister_post_type();
		$this->original_timezone = get_option( 'timezone_string' );
	}

	public function tearDown(): void {
		update_option( 'timezone_string', $this->original_timezone ?? '' );
		parent::tearDown();
	}

	/**
	 * @since $$next-version$$
	 * @covers ::get_the_job_application_deadline
	 */
	public function test_get_the_job_application_deadline() {
		$job_id = $this->factory->job_listing->create(
			[ 'meta_input' => [ '_job_expires' => current_datetime()->add( new DateInterval( 'P1D' ) )->format( 'Y-m-d' ) ] ]
		);

		$result = get_the_job_application_deadline( $job_id );

		$this->assertInstanceOf( DateTimeImmutable::class, $result );
	}

	/**
	 * @since $$next-version$$
	 * @covers ::get_the_job_application_deadline
	 */
	public function test_get_the_job_application_deadline_no_expiry() {
		$job_id = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => '' ] ] );

		$this->assertFalse( get_the_job_application_deadline( $job_id ) );
	}

	/**
	 * @since $$next-version$$
	 * @covers ::get_the_job_application_deadline
	 */
	public function test_get_the_job_application_deadline_expired() {
		$job_id = $this->factory->job_listing->create(
			[
				'post_status' => 'expired',
				'meta_input'  => [ '_job_expires' => current_datetime()->sub( new DateInterval( 'P1D' ) )->format( 'Y-m-d' ) ],
			]
		);

		$this->assertFalse( get_the_job_application_deadline( $job_id ) );
	}

	/**
	 * @since $$next-version$$
	 * @covers ::get_the_job_application_deadline
	 */
	public function test_get_the_job_application_deadline_filled() {
		$job_id = $this->factory->job_listing->create(
			[
				'meta_input' => [
					'_job_expires' => current_datetime()->add( new DateInterval( 'P1D' ) )->format( 'Y-m-d' ),
					'_filled'      => 1,
				],
			]
		);

		$this->assertFalse( get_the_job_application_deadline( $job_id ) );
	}

	/**
	 * @since $$next-version$$
	 * @covers ::the_job_application_deadline
	 */
	public function test_the_job_application_deadline_outputs_machine_readable_time() {
		$job_id = $this->factory->job_listing->create(
			[ 'meta_input' => [ '_job_expires' => current_datetime()->add( new DateInterval( 'P1D' ) )->format( 'Y-m-d' ) ] ]
		);

		ob_start();
		the_job_application_deadline( $job_id );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'wpjm-local-time', $output );
		$this->assertMatchesRegularExpression( '/datetime="[^"]+T[^"]+"/', $output );
	}

	/**
	 * @since $$next-version$$
	 * @covers ::the_job_application_deadline
	 */
	public function test_the_job_application_deadline_no_expiry_outputs_nothing() {
		$job_id = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => '' ] ] );

		ob_start();
		the_job_application_deadline( $job_id );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}

	/**
	 * The deadline must be a real, offset-aware DateTimeImmutable in the site's timezone —
	 * not a naive datetime as `date()` would emit — so a regression to naive date handling
	 * can't pass silently in a UTC test environment.
	 *
	 * @since $$next-version$$
	 * @covers ::get_the_job_application_deadline
	 */
	public function test_get_the_job_application_deadline_non_utc_timezone_has_correct_offset() {
		update_option( 'timezone_string', 'America/New_York' );

		$job_id = $this->factory->job_listing->create(
			[ 'meta_input' => [ '_job_expires' => '2026-12-15' ] ]
		);

		$result = get_the_job_application_deadline( $job_id );

		$this->assertInstanceOf( DateTimeImmutable::class, $result );
		$this->assertSame( 'America/New_York', $result->getTimezone()->getName() );

		// Exact moment: 2026-12-15 23:59:59 in America/New_York (EST, -05:00).
		$this->assertSame( '2026-12-15T23:59:59-05:00', $result->format( 'c' ) );
	}

	/**
	 * DST boundary regression guard: the deadline must keep the post-transition offset
	 * (EST, -05:00) for an expiry just after US fall-back on 2026-11-02.
	 *
	 * @since $$next-version$$
	 * @covers ::get_the_job_application_deadline
	 */
	public function test_get_the_job_application_deadline_dst_boundary_keeps_post_transition_offset() {
		update_option( 'timezone_string', 'America/New_York' );

		$job_id = $this->factory->job_listing->create(
			[ 'meta_input' => [ '_job_expires' => '2026-11-03' ] ]
		);

		$result = get_the_job_application_deadline( $job_id );

		$this->assertInstanceOf( DateTimeImmutable::class, $result );
		$this->assertSame( 'America/New_York', $result->getTimezone()->getName() );

		// 2026-11-03 is post US fall-back, so still EST (-05:00).
		$this->assertSame( '2026-11-03T23:59:59-05:00', $result->format( 'c' ) );
	}

	/**
	 * When the site is configured with a raw UTC offset rather than a named IANA timezone,
	 * `Intl` rejects the offset as a `timeZone`, so the local-time JS would always append a
	 * redundant suffix. The fallback should leave the data attributes off entirely.
	 *
	 * @since $$next-version$$
	 * @covers ::the_job_application_deadline
	 */
	public function test_the_job_application_deadline_omits_optional_data_attrs_on_raw_offset() {
		update_option( 'timezone_string', '+05:30' );

		$job_id = $this->factory->job_listing->create(
			[ 'meta_input' => [ '_job_expires' => current_datetime()->add( new DateInterval( 'P1D' ) )->format( 'Y-m-d' ) ] ]
		);

		ob_start();
		the_job_application_deadline( $job_id );
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'data-site-timezone', $output );
		$this->assertStringNotContainsString( 'data-local-label', $output );
		$this->assertStringContainsString( 'datetime="', $output );
	}
}
