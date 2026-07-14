<?php
/**
 * Tests the `form_id` shortcode attribute plumbing for `[submit_job_form]`.
 *
 * Verifies that the `submit_job_form_fields` filter receives the active form id
 * (2nd argument) when one is set via the shortcode, that it receives an empty
 * string when not (backward compatibility), and that the id round-trips through
 * the submit form template + handler so validation uses the same field set that
 * rendered.
 *
 * @package wp-job-manager
 */
class WP_Test_Submit_Job_Form_Id extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';
	}

	public function tearDown(): void {
		$_POST    = [];
		$_REQUEST = [];
		parent::tearDown();
	}

	/**
	 * Build a fresh form instance, reset fields cache, and re-prep it.
	 *
	 * The form is a singleton, so its `$fields` cache from a previous test
	 * would otherwise leak across cases.
	 */
	private function fresh_form() {
		$form  = WP_Job_Manager_Form_Submit_Job::instance();
		$class = new ReflectionClass( $form );

		$fields = $class->getProperty( 'fields' );
		$fields->setAccessible( true );
		$fields->setValue( $form, [] );

		$current = $class->getProperty( 'current_form_id' );
		$current->setAccessible( true );
		$current->setValue( $form, '' );

		return $form;
	}

	/**
	 * The filter receives the shortcode's `form_id` as the 2nd argument.
	 */
	public function test_filter_receives_form_id_from_shortcode_atts() {
		$form    = $this->fresh_form();
		$capture = [ 'fields' => null, 'form_id' => null ];

		$callback = function ( $fields, $form_id ) use ( &$capture ) {
			$capture['fields']  = $fields;
			$capture['form_id'] = $form_id;
			return $fields;
		};
		add_filter( 'submit_job_form_fields', $callback, 10, 2 );

		$form->submit( [ 'form_id' => 'classifieds' ] );

		remove_filter( 'submit_job_form_fields', $callback, 10 );

		$this->assertSame( 'classifieds', $capture['form_id'] );
		$this->assertIsArray( $capture['fields'] );
		$this->assertArrayHasKey( 'job', $capture['fields'] );
	}

	/**
	 * When no `form_id` is provided, the filter receives an empty string and a
	 * single-argument callback still works.
	 */
	public function test_filter_receives_empty_string_when_no_form_id() {
		$form    = $this->fresh_form();
		$capture = [ 'fields' => null, 'form_id' => null ];

		$callback = function ( $fields, $form_id = null ) use ( &$capture ) {
			$capture['fields']  = $fields;
			$capture['form_id'] = $form_id;
			return $fields;
		};
		add_filter( 'submit_job_form_fields', $callback, 10, 2 );

		$form->submit( [] );

		remove_filter( 'submit_job_form_fields', $callback, 10 );

		$this->assertSame( '', $capture['form_id'] );

		// Backward compatibility: a single-arg callback (no 2nd parameter) still fires.
		$bc_seen = false;
		$bc_cb   = function ( $fields ) use ( &$bc_seen ) {
			$bc_seen = true;
			return $fields;
		};
		add_filter( 'submit_job_form_fields', $bc_cb, 10, 1 );
		$this->fresh_form()->submit( [] );
		remove_filter( 'submit_job_form_fields', $bc_cb, 10 );

		$this->assertTrue( $bc_seen );
	}

	/**
	 * `output()` captures the `form_id` from `$atts` onto the instance before
	 * the view is dispatched, so the field filter sees it.
	 */
	public function test_output_stores_form_id_on_instance() {
		$form  = $this->fresh_form();
		$class = new ReflectionClass( $form );
		$prop  = $class->getProperty( 'current_form_id' );
		$prop->setAccessible( true );

		ob_start();
		$form->output( [ 'form_id' => 'company-ads' ] );
		ob_end_clean();

		$this->assertSame( 'company-ads', $prop->getValue( $form ) );
	}

	/**
	 * `submit_handler` reads `form_id` from POST so the field set used during
	 * validation matches the field set used to render the form.
	 */
	public function test_submit_handler_round_trips_form_id_from_post() {
		$form  = $this->fresh_form();
		$class = new ReflectionClass( $form );
		$prop  = $class->getProperty( 'current_form_id' );
		$prop->setAccessible( true );

		// Replicate what the submit template echoes when a form_id is set.
		$_POST = [ 'form_id' => 'classifieds' ];

		$form->submit_handler();

		$this->assertSame( 'classifieds', $prop->getValue( $form ) );
	}
}
