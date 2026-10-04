<?php
/**
 * Characterization tests for the scheduled-listing date contract on the job
 * submission form. These pin behavior other code paths depend on, so a rework
 * of the date handling (parser, posted-field plumbing) fails here instead of
 * regressing silently.
 *
 * @package wp-job-manager
 */
class WP_Test_Submit_Job_Scheduled_Date_Contract extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';
	}

	public function tearDown(): void {
		unset( $_POST, $_REQUEST );
		$_POST    = [];
		$_REQUEST = [];
		parent::tearDown();
	}

	/**
	 * Without JavaScript, the visible schedule input posts its display text
	 * verbatim (the ISO value normally comes from a JS-created hidden field), so
	 * apply_scheduled_date() must accept any strtotime()-parseable future date —
	 * not just `Y-m-d`. A listing scheduled that way publishes on the chosen
	 * date, never silently "now".
	 *
	 * @covers WP_Job_Manager_Form_Submit_Job::apply_scheduled_date
	 */
	public function test_apply_scheduled_date_accepts_human_readable_future_date() {
		$timestamp  = strtotime( '+2 years' );
		$human_date = gmdate( 'F j, Y', $timestamp );

		$job_data = [];
		$applied  = WP_Job_Manager_Form_Submit_Job::apply_scheduled_date( $job_data, $human_date );

		$this->assertTrue( $applied, "The human-readable future date '{$human_date}' is accepted for scheduling." );
		$this->assertStringStartsWith(
			wp_date( 'Y-m-d', strtotime( $human_date ) ),
			$job_data['post_date'],
			'The listing is scheduled for the chosen date, not published immediately.'
		);
	}

	/**
	 * The ISO format the datepicker JS posts keeps working.
	 *
	 * @covers WP_Job_Manager_Form_Submit_Job::apply_scheduled_date
	 */
	public function test_apply_scheduled_date_accepts_iso_future_date() {
		$iso_date = gmdate( 'Y-m-d', strtotime( '+2 years' ) );

		$job_data = [];
		$applied  = WP_Job_Manager_Form_Submit_Job::apply_scheduled_date( $job_data, $iso_date );

		$this->assertTrue( $applied, 'An ISO future date is accepted for scheduling.' );
		$this->assertStringStartsWith( $iso_date, $job_data['post_date'] );
	}

	/**
	 * A required date-type field with no posted value must fail validation.
	 * The `empty` flag is computed by the before_sanitize callback that
	 * get_posted_fields() wires up for every field; a dedicated
	 * get_posted_{type}_field handler that skips that callback silently
	 * disables the required check for its field type.
	 *
	 * @covers WP_Job_Manager_Form_Submit_Job::validate_fields
	 */
	public function test_required_date_field_empty_submission_fails_validation() {
		$this->login_as_employer();

		$form   = WP_Job_Manager_Form_Submit_Job::instance();
		$mirror = new ReflectionClass( $form );

		$date_field = [
			'label'       => 'Required test date',
			'type'        => 'date',
			'required'    => true,
			'priority'    => 1,
			'placeholder' => '',
			'sanitizer'   => null,
		];

		$fields_prop = $mirror->getProperty( 'fields' );
		$fields_prop->setAccessible( true );
		$original_fields = $fields_prop->getValue( $form );
		$fields_prop->setValue( $form, [ 'job' => [ 'test_required_date' => $date_field ] ] );

		$_POST['test_required_date'] = '';

		try {
			$get_posted = $mirror->getMethod( 'get_posted_fields' );
			$get_posted->setAccessible( true );
			$values = $get_posted->invoke( $form );

			$fields = $fields_prop->getValue( $form );
			$this->assertTrue(
				$fields['job']['test_required_date']['empty'],
				'An empty posted value marks the date field as empty.'
			);

			$validate = $mirror->getMethod( 'validate_fields' );
			$validate->setAccessible( true );
			$result = $validate->invoke( $form, $values );

			$this->assertWPError( $result, 'Submitting an empty required date field fails validation.' );
			$this->assertStringContainsString( 'Required test date', $result->get_error_message() );
		} finally {
			$fields_prop->setValue( $form, $original_fields );
		}
	}
}
