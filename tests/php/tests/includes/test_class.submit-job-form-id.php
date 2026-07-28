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

		$cache_key = $class->getProperty( 'fields_cache_key' );
		$cache_key->setAccessible( true );
		$cache_key->setValue( $form, '' );

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

	/**
	 * Malformed `form_id` POST values are discarded instead of being passed
	 * through to the `submit_job_form_fields` filter or the nonce action.
	 */
	public function test_malformed_form_id_post_value_is_discarded() {
		$form  = $this->fresh_form();
		$class = new ReflectionClass( $form );
		$prop  = $class->getProperty( 'current_form_id' );
		$prop->setAccessible( true );

		// Disallowed characters: slashes, spaces, dots, longer than 32.
		$_POST = [ 'form_id' => '../etc/passwd has spaces' ];

		$form->submit_handler();

		$this->assertSame( '', $prop->getValue( $form ) );
	}

	/**
	 * A nonce minted for a different `form_id` than the posted one must not
	 * pass `check_submit_form_nonce_field()` — `die()` is the expected outcome.
	 */
	public function test_nonce_with_mismatched_form_id_is_rejected() {
		// Suppress the wp_die() blast so PHP unit's `expectException` can catch it.
		// Reuses the helper shipped on WPJM_BaseTest.
		add_filter( 'wp_die_handler', [ $this, 'return_do_not_die' ] );

		$form = $this->fresh_form();
		$user = self::factory()->user->create();
		wp_set_current_user( $user );

		// Render-time form id: "classifieds". We mint the nonce against that.
		$render_form_id = 'classifieds';
		$nonce          = wp_create_nonce( 'submit-job-0-form-' . $render_form_id );

		// Submitter posts a different, less-restrictive form id along with the
		// nonce minted for the rendered form. The check must not let it through.
		$_POST    = [ 'submit_job' => '1', 'form_id' => 'reduced', '_wpjm_nonce' => $nonce ];
		$_REQUEST = $_POST;

		// After the nonce check fails, wp_nonce_ays() falls into the
		// `wp_die()` path which we just suppressed. The signature of
		// "rejected" is that the handler did not save / validate / proceed;
		// the simplest observable is that current_form_id stays empty
		// (the post form_id was tweaked by the test but since we got past
		// that point we expect no side effects on the form instance).
		$form->submit_handler();

		remove_filter( 'wp_die_handler', [ $this, 'return_do_not_die' ] );

		// Confirm we did not advance the request: the handler didn't save
		// anything (a successful handler would have bumped $this->step).
		$step_prop = ( new ReflectionClass( $form ) )->getProperty( 'step' );
		$step_prop->setAccessible( true );
		$this->assertSame( 0, $step_prop->getValue( $form ), 'Step must not advance when nonce mismatches.' );
	}

	/**
	 * Cached `$fields` are reused when `current_form_id` matches the cache key,
	 * and rebuilt when it differs — so two `[submit_job_form form_id="…"]` blocks
	 * on the same page each render their own field set.
	 */
	public function test_init_fields_cache_invalidates_when_form_id_changes() {
		$form = $this->fresh_form();

		$call_count = 0;
		$seen_ids   = [];
		$cb         = function ( $fields, $form_id ) use ( &$call_count, &$seen_ids ) {
			++$call_count;
			$seen_ids[] = $form_id;
			return $fields;
		};
		add_filter( 'submit_job_form_fields', $cb, 10, 2 );

		// First render — cache miss, filter runs once with `classifieds`.
		$form->submit( [ 'form_id' => 'classifieds' ] );

		// Cached on second render with same form id.
		$form->submit( [ 'form_id' => 'classifieds' ] );
		$this->assertSame( 1, $call_count, 'Same form id should hit the cache.' );

		// Different form id — cache miss, filter runs again.
		$form->submit( [ 'form_id' => 'reduced' ] );
		$this->assertSame( 2, $call_count, 'Different form id should rebuild.' );
		$this->assertSame( [ 'classifieds', 'reduced' ], $seen_ids );

		remove_filter( 'submit_job_form_fields', $cb, 10 );
	}

	/**
	 * When a job listing carries a `_form_id` post meta saved at submission,
	 * instantiating the edit form seeds `current_form_id` from meta so the
	 * dashboard edit page renders the same field set without any shortcode
	 * `form_id` having to be threaded through by the caller.
	 */
	public function test_edit_job_reads_form_id_from_post_meta() {
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-edit-job.php';

		// Inject a job post + meta that simulates a listing submitted through
		// `[submit_job_form form_id="classifieds"]`.
		$job_id = self::factory()->post->create(
			[
				'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_status' => 'publish',
				'post_author' => self::factory()->user->create(),
			]
		);
		update_post_meta( $job_id, '_form_id', 'classifieds' );

		// Simulate the dashboard's edit link: it forwards `job_id` via `_REQUEST`.
		$_REQUEST = [ 'job_id' => $job_id ];
		wp_set_current_user( get_post( $job_id )->post_author );

		$form = WP_Job_Manager_Form_Edit_Job::instance();

		$this->assertSame( 'classifieds', $form->current_form_id );
	}
}
