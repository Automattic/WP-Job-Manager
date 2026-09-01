<?php
/**
 * Tests that the listing edit step is included in the renewal flow only when
 * the "Renewal Edits" setting is on and published edits don't require moderation.
 *
 * @package wp-job-manager
 */
class WP_Test_Renewal_Edits extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';

		update_option( 'job_manager_renewal_days', 5 );
		update_option( 'job_manager_user_edit_published_submissions', 'yes' );
	}

	public function tearDown(): void {
		delete_option( 'job_manager_renewal_days' );
		delete_option( 'job_manager_renewal_allow_edits' );
		delete_option( 'job_manager_user_edit_published_submissions' );
		unset( $_GET, $_POST, $_REQUEST );
		$_GET     = [];
		$_POST    = [];
		$_REQUEST = [];
		$this->reset_form_instance();
		parent::tearDown();
	}

	/**
	 * The form's static instance persists renewal step wiring across tests, so
	 * each test needs a fresh instance built under its own option state.
	 */
	private function reset_form_instance() {
		$class    = new ReflectionClass( 'WP_Job_Manager_Form_Submit_Job' );
		$instance = $class->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		$helper_class    = new ReflectionClass( 'WP_Job_Manager_Helper_Renewals' );
		$helper_instance = $helper_class->getProperty( 'instance' );
		$helper_instance->setAccessible( true );
		$helper_instance->setValue( null, null );
	}

	/**
	 * Sets up `$_GET` with a valid renew action and nonce for the given job.
	 *
	 * @param int $job_id Job listing to renew.
	 */
	private function set_renew_action_query( $job_id ) {
		$_GET['job_id'] = $job_id;
		$_GET['action'] = 'renew';
		$_GET['nonce']  = wp_create_nonce( 'job_manager_renew_job_' . $job_id );
	}

	private function create_renewable_job( $user_id ) {
		return $this->factory->job_listing->create(
			[
				'post_author' => $user_id,
				'post_status' => 'publish',
			]
		);
	}

	/**
	 * By default (setting off), the renewal flow keeps its current behavior:
	 * the edit ("submit") step is removed.
	 */
	public function test_edit_step_removed_by_default() {
		$this->login_as_employer();
		$job_id = $this->create_renewable_job( get_current_user_id() );
		update_post_meta( $job_id, '_job_expires', wp_date( 'Y-m-d', strtotime( '+1 day' ) ) );

		$this->set_renew_action_query( $job_id );
		$this->reset_form_instance();

		$form = WP_Job_Manager_Form_Submit_Job::instance();

		$this->assertArrayNotHasKey( 'submit', $form->get_steps() );
	}

	/**
	 * With the setting on and moderation not required, the edit step stays in
	 * the renewal flow.
	 */
	public function test_edit_step_kept_when_allowed() {
		update_option( 'job_manager_renewal_allow_edits', 1 );

		$this->login_as_employer();
		$job_id = $this->create_renewable_job( get_current_user_id() );
		update_post_meta( $job_id, '_job_expires', wp_date( 'Y-m-d', strtotime( '+1 day' ) ) );

		$this->set_renew_action_query( $job_id );
		$this->reset_form_instance();

		$form = WP_Job_Manager_Form_Submit_Job::instance();

		$this->assertArrayHasKey( 'submit', $form->get_steps() );
		$this->assertSame( 'Renew Preview', $form->get_steps()['preview']['name'] );
	}

	/**
	 * The setting has no effect when published edits require moderation: the
	 * renewal must not be able to take the listing offline mid-flow.
	 */
	public function test_edit_step_removed_when_moderation_required() {
		update_option( 'job_manager_renewal_allow_edits', 1 );
		update_option( 'job_manager_user_edit_published_submissions', 'yes_moderated' );

		$this->login_as_employer();
		$job_id = $this->create_renewable_job( get_current_user_id() );
		update_post_meta( $job_id, '_job_expires', wp_date( 'Y-m-d', strtotime( '+1 day' ) ) );

		$this->set_renew_action_query( $job_id );
		$this->reset_form_instance();

		$form = WP_Job_Manager_Form_Submit_Job::instance();

		$this->assertArrayNotHasKey( 'submit', $form->get_steps() );
	}

	/**
	 * Completing the renewal preview step still extends the expiry and
	 * republishes the listing when the edit step was part of the flow.
	 */
	public function test_renewal_completes_after_allowed_edit_step() {
		update_option( 'job_manager_renewal_allow_edits', 1 );

		$this->login_as_employer();
		$job_id = $this->create_renewable_job( get_current_user_id() );
		update_post_meta( $job_id, '_job_expires', wp_date( 'Y-m-d', strtotime( '+1 day' ) ) );

		$this->set_renew_action_query( $job_id );
		$this->reset_form_instance();

		$form  = WP_Job_Manager_Form_Submit_Job::instance();
		$class = new ReflectionClass( $form );

		$job_id_prop = $class->getProperty( 'job_id' );
		$job_id_prop->setAccessible( true );
		$job_id_prop->setValue( $form, $job_id );

		$steps     = array_keys( $form->get_steps() );
		$step_prop = $class->getProperty( 'step' );
		$step_prop->setAccessible( true );
		$step_prop->setValue( $form, array_search( 'preview', $steps, true ) );

		$nonce                   = wp_create_nonce( 'preview-job-' . $job_id );
		$_POST                   = [
			'continue'    => '1',
			'job_id'      => $job_id,
			'_wpjm_nonce' => $nonce,
		];
		$_REQUEST['_wpjm_nonce'] = $nonce;

		$form->process();

		$this->assertEquals( 'publish', get_post_status( $job_id ) );
		$this->assertFalse( WP_Job_Manager_Helper_Renewals::job_can_be_renewed( get_post( $job_id ) ) );
	}

	/**
	 * Field values edited on the renewal submit step persist on the listing
	 * and the job still completes the renewal (status publish, expiry extended).
	 */
	public function test_edited_fields_persist_through_renewal() {
		update_option( 'job_manager_renewal_allow_edits', 1 );

		$this->login_as_employer();
		$user_id = get_current_user_id();
		$job_id  = $this->create_renewable_job( $user_id );
		update_post_meta( $job_id, '_job_expires', wp_date( 'Y-m-d', strtotime( '+1 day' ) ) );

		$this->set_renew_action_query( $job_id );
		$this->reset_form_instance();

		$form  = WP_Job_Manager_Form_Submit_Job::instance();
		$class = new ReflectionClass( $form );

		$job_id_prop = $class->getProperty( 'job_id' );
		$job_id_prop->setAccessible( true );
		$job_id_prop->setValue( $form, $job_id );

		$step_prop = $class->getProperty( 'step' );
		$step_prop->setAccessible( true );

		$steps     = array_keys( $form->get_steps() );
		$submit_ix = array_search( 'submit', $steps, true );
		$step_prop->setValue( $form, $submit_ix );

		// Submit the edit step with changed field values.
		$nonce                                      = wp_create_nonce( 'submit-job-' . $job_id );
		$_POST['job']                               = [
			'job_title'       => 'Renewed Engineer',
			'job_location'    => 'Remote (HQ)',
			'job_type'        => 'full-time',
			'job_description' => 'Updated description.',
			'application'     => 'apply@example.com',
		];
		$_POST['company']                           = [
			'company_name' => 'Acme Co',
		];
		$_POST                                       = array_merge(
			$_POST,
			[
				'submit_job'  => '1',
				'job_id'      => $job_id,
				'_wpjm_nonce' => $nonce,
			]
		);
		$_REQUEST['_wpjm_nonce']                    = $nonce;
		$_REQUEST['job']                            = $_POST['job'];
		$_REQUEST['company']                        = $_POST['company'];

		$form->submit_handler();

		$this->assertSame( $submit_ix + 1, $form->get_step() );

		// Continue the renewal preview step.
		$preview_ix       = array_search( 'preview', $steps, true );
		$step_prop->setValue( $form, $preview_ix );

		$nonce                   = wp_create_nonce( 'preview-job-' . $job_id );
		$_POST                   = [
			'continue'    => '1',
			'job_id'      => $job_id,
			'_wpjm_nonce' => $nonce,
		];
		$_REQUEST['_wpjm_nonce'] = $nonce;

		$form->process();

		$this->assertSame( 'Renewed Engineer', get_post( $job_id )->post_title );
		$this->assertSame( 'Remote (HQ)', get_post_meta( $job_id, '_job_location', true ) );
		$this->assertSame( 'apply@example.com', get_post_meta( $job_id, '_application', true ) );
		$this->assertSame( 'publish', get_post_status( $job_id ) );
		$this->assertFalse( WP_Job_Manager_Helper_Renewals::job_can_be_renewed( get_post( $job_id ) ) );
	}

	/**
	 * The "Edit listing" button on the renewal preview step navigates back to
	 * the submit (edit) step.
	 */
	public function test_edit_job_navigates_back_from_preview() {
		update_option( 'job_manager_renewal_allow_edits', 1 );

		$this->login_as_employer();
		$job_id = $this->create_renewable_job( get_current_user_id() );
		update_post_meta( $job_id, '_job_expires', wp_date( 'Y-m-d', strtotime( '+1 day' ) ) );

		$this->set_renew_action_query( $job_id );
		$this->reset_form_instance();

		$form  = WP_Job_Manager_Form_Submit_Job::instance();
		$class = new ReflectionClass( $form );

		$job_id_prop = $class->getProperty( 'job_id' );
		$job_id_prop->setAccessible( true );
		$job_id_prop->setValue( $form, $job_id );

		$steps       = array_keys( $form->get_steps() );
		$submit_ix   = array_search( 'submit', $steps, true );
		$preview_ix  = array_search( 'preview', $steps, true );
		$step_prop   = $class->getProperty( 'step' );
		$step_prop->setAccessible( true );
		$step_prop->setValue( $form, $preview_ix );

		$nonce                   = wp_create_nonce( 'preview-job-' . $job_id );
		$_POST                   = [
			'edit_job'    => '1',
			'job_id'      => $job_id,
			'_wpjm_nonce' => $nonce,
		];
		$_REQUEST['_wpjm_nonce'] = $nonce;

		$form->process();

		$this->assertSame( $submit_ix, $form->get_step() );
	}
}
