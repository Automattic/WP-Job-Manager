<?php
/**
 * Regression test: the front-end edit-job form must not echo a refused attachment's URL
 * back to the submitter.
 *
 * The submit-job form blanks unusable posted attachment IDs before re-rendering
 * (scrub_unusable_attachment_field_values(), since 2.4.7), but the edit-job form
 * overrides submit_handler() and must apply the same scrub: otherwise an employer
 * editing their own listing can post a foreign attachment ID as `current_company_logo`
 * and read that attachment's file URL back from the re-rendered form.
 *
 * @package wp-job-manager
 */
class Tests_Edit_Job_Attachment_URL_Disclosure extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-edit-job.php';
		add_filter( 'job_manager_job_is_editable', '__return_true' );
	}

	public function tearDown(): void {
		remove_filter( 'job_manager_job_is_editable', '__return_true' );
		$_POST    = [];
		$_REQUEST = [];
		parent::tearDown();
	}

	/**
	 * Posts an edit of the current user's own listing with the given company-logo value
	 * and returns the logo field's rendered value after the handler ran.
	 *
	 * @param int    $job_id     Listing being edited (owned by the current user).
	 * @param string $logo_value Value posted as the current company logo.
	 * @return mixed
	 */
	private function rendered_logo_value_after_edit( $job_id, $logo_value ) {
		$_POST = [
			'job_manager_form'     => 'edit-job',
			'submit_job'           => '1',
			'job_id'               => (string) $job_id,
			'job_title'            => 'Edited Title',
			'job_description'      => 'Edited description.',
			'application'          => 'owner@example.test',
			'company_name'         => 'OwnerCo',
			'current_company_logo' => $logo_value,
			'_wpjm_nonce'          => wp_create_nonce( 'submit-job-' . $job_id ),
		];
		foreach ( $_POST as $key => $value ) {
			$_REQUEST[ $key ] = $value;
		}

		$form = new WP_Job_Manager_Form_Edit_Job();
		$form->submit_handler();

		$fields_prop = ( new ReflectionClass( $form ) )->getProperty( 'fields' );
		$fields_prop->setAccessible( true );

		return $fields_prop->getValue( $form )['company']['company_logo']['value'];
	}

	/**
	 * A foreign attachment ID posted into the edit form's logo field is blanked before
	 * re-render, matching the submit form.
	 */
	public function test_foreign_attachment_id_is_scrubbed_on_edit() {
		$owner   = $this->factory->user->create( [ 'role' => 'administrator' ] );
		$private = $this->factory->post->create(
			[
				'post_status' => 'private',
				'post_author' => $owner,
			]
		);
		$foreign = $this->factory->attachment->create_object(
			'private-memo.png',
			$private,
			[
				'post_mime_type' => 'image/png',
				'post_author'    => $owner,
			]
		);

		$this->login_as_employer();
		$job_id = $this->factory->job_listing->create( [ 'post_author' => get_current_user_id() ] );

		$this->assertSame(
			'',
			$this->rendered_logo_value_after_edit( $job_id, (string) $foreign ),
			'A foreign attachment ID must be blanked from the re-rendered edit form.'
		);
	}

	/**
	 * Control: an attachment the editor owns survives, so re-editing a listing with its
	 * own logo keeps working.
	 */
	public function test_owned_attachment_id_survives_edit_scrub() {
		$this->login_as_employer();
		$job_id = $this->factory->job_listing->create( [ 'post_author' => get_current_user_id() ] );
		$owned  = $this->factory->attachment->create_object(
			'my-logo.png',
			0,
			[
				'post_mime_type' => 'image/png',
				'post_author'    => get_current_user_id(),
			]
		);

		$this->assertSame(
			(string) $owned,
			(string) $this->rendered_logo_value_after_edit( $job_id, (string) $owned ),
			'An attachment the editor owns must survive the scrub.'
		);
	}
}
