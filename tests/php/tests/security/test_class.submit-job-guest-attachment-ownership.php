<?php
/**
 * Regression tests: a visitor without an account is never treated as the owner of an
 * attachment in the job submission form's file fields.
 *
 * Guest uploads are stored with `post_author` 0, and a logged-out visitor's user ID is
 * also 0, so an author comparison alone would authorize every guest for every other
 * guest's attachment — disclosing its file URL through the re-rendered form, and letting
 * it be bound to the attacker's own listing. A guest's own files are instead covered by
 * the per-visitor upload record and by the listing they are mid-submitting.
 *
 * @package wp-job-manager/tests
 */

class Tests_Submit_Job_Guest_Attachment_Ownership extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';
		$this->logout();
	}

	/**
	 * Creates an attachment the way a guest submission stores one: author 0, parented to a
	 * guest listing that is not publicly visible.
	 *
	 * @return int Attachment ID.
	 */
	private function create_guest_attachment() {
		$listing_id = $this->factory->job_listing->create(
			[
				'post_author' => 0,
				'post_status' => 'preview',
			]
		);

		return $this->factory->attachment->create_object(
			'2026/11/guest-logo-' . uniqid() . '.png',
			$listing_id,
			[
				'post_mime_type' => 'image/png',
				'post_author'    => 0,
			]
		);
	}

	/**
	 * @covers WP_Job_Manager_Form_Submit_Job::is_attachment_authorized_for_current_user
	 */
	public function test_guest_cannot_use_another_guests_attachment_id() {
		$foreign = $this->create_guest_attachment();

		$result = $this->validate_ownership( (string) $foreign );

		$this->assertWPError( $result, 'A guest must not pass the ownership check for another guest\'s attachment.' );
	}

	/**
	 * The re-rendered form must not resolve another guest's attachment ID to its file URL.
	 *
	 * @covers WP_Job_Manager_Form_Submit_Job::scrub_unusable_attachment_field_values
	 */
	public function test_foreign_guest_attachment_id_is_scrubbed_from_render_value() {
		$foreign = $this->create_guest_attachment();

		$form = $this->form();

		$fields_prop = ( new ReflectionClass( $form ) )->getProperty( 'fields' );
		$fields_prop->setAccessible( true );
		$fields = $fields_prop->getValue( $form );
		$fields['company']['company_logo']['value'] = (string) $foreign;
		$fields_prop->setValue( $form, $fields );

		$scrub = new ReflectionMethod( $form, 'scrub_unusable_attachment_field_values' );
		$scrub->setAccessible( true );
		$scrub->invoke( $form );

		$this->assertSame(
			'',
			$fields_prop->getValue( $form )['company']['company_logo']['value'],
			'Another guest\'s attachment ID must be blanked before the form re-renders.'
		);
	}

	/**
	 * Control: a guest continuing their own submission keeps the attachment already saved
	 * on that listing.
	 *
	 * @covers WP_Job_Manager_Form_Submit_Job::is_existing_listing_attachment
	 */
	public function test_guest_keeps_attachment_saved_on_their_own_submission() {
		$listing_id = $this->factory->job_listing->create(
			[
				'post_author' => 0,
				'post_status' => 'preview',
			]
		);
		$logo_id    = $this->factory->attachment->create_object(
			'2026/11/own-guest-logo-' . uniqid() . '.png',
			$listing_id,
			[
				'post_mime_type' => 'image/png',
				'post_author'    => 0,
			]
		);
		set_post_thumbnail( $listing_id, $logo_id );

		// The form binds to the listing the guest is mid-submitting (submitting-key cookie).
		$result = $this->validate_ownership( (string) $logo_id, $listing_id );

		$this->assertTrue( $result, 'A guest must keep the logo already saved on their own submission.' );
	}

	/**
	 * Control: a signed-in user still owns their own attachment.
	 *
	 * @covers WP_Job_Manager_Form_Submit_Job::is_attachment_authorized_for_current_user
	 */
	public function test_signed_in_owner_still_passes() {
		$user = $this->factory->user->create( [ 'role' => 'employer' ] );
		$own  = $this->factory->attachment->create_object(
			'2026/11/own-logo-' . uniqid() . '.png',
			0,
			[
				'post_mime_type' => 'image/png',
				'post_author'    => $user,
			]
		);

		wp_set_current_user( $user );

		$this->assertTrue( $this->validate_ownership( (string) $own ) );
	}

	/**
	 * Returns the submit form with its fields initialised and its job ID pinned.
	 *
	 * @param int $job_id Job ID to bind the form to; 0 for a fresh submission.
	 * @return WP_Job_Manager_Form_Submit_Job
	 */
	private function form( $job_id = 0 ) {
		$form = WP_Job_Manager_Form_Submit_Job::instance();
		$form->init_fields();

		$job_id_prop = ( new ReflectionClass( $form ) )->getProperty( 'job_id' );
		$job_id_prop->setAccessible( true );
		$job_id_prop->setValue( $form, $job_id );

		return $form;
	}

	/**
	 * Runs validate_attachment_ownership() for a company_logo value.
	 *
	 * @param string $value  Posted file field value.
	 * @param int    $job_id Listing the form is bound to; 0 for a fresh submission.
	 * @return bool|WP_Error
	 */
	private function validate_ownership( $value, $job_id = 0 ) {
		$form = $this->form( $job_id );

		$validate = new ReflectionMethod( $form, 'validate_attachment_ownership' );
		$validate->setAccessible( true );

		return $validate->invoke( $form, [ 'company' => [ 'company_logo' => $value ] ] );
	}
}
