<?php

class WP_Test_WP_Job_Manager_Template extends WPJM_BaseTest {
	public function setUp(): void {
		parent::setUp();
		$this->reregister_post_type();
	}

	/**
	 * @since 2.5.0
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
	 * @since 2.5.0
	 * @covers ::get_the_job_application_deadline
	 */
	public function test_get_the_job_application_deadline_no_expiry() {
		$job_id = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => '' ] ] );

		$this->assertFalse( get_the_job_application_deadline( $job_id ) );
	}

	/**
	 * @since 2.5.0
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
	 * @since 2.5.0
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
	 * @since 2.5.0
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
	 * @since 2.5.0
	 * @covers ::the_job_application_deadline
	 */
	public function test_the_job_application_deadline_no_expiry_outputs_nothing() {
		$job_id = $this->factory->job_listing->create( [ 'meta_input' => [ '_job_expires' => '' ] ] );

		ob_start();
		the_job_application_deadline( $job_id );
		$output = ob_get_clean();

		$this->assertSame( '', $output );
	}
}
