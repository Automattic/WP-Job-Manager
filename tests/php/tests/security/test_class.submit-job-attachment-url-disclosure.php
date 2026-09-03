<?php
/**
 * Regression test: the job submission form must not echo a refused attachment's URL back
 * to the submitter.
 *
 * validate_attachment_ownership() refuses a numeric file value the submitter may not use,
 * but the form is then re-rendered with the posted values and
 * templates/form-fields/uploaded-file-html.php resolves a numeric value to its attachment
 * URL with no authorization check. A low-privilege user could therefore post a foreign
 * attachment ID as `current_company_logo`, have it refused, and read the file URL back
 * from the re-rendered form — reaching media WordPress itself withholds. The rendered
 * field value for an unusable attachment must be blanked.
 *
 * @package wp-job-manager
 */
class Tests_Submit_Job_Attachment_URL_Disclosure extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';
	}

	/**
	 * Sets the company_logo field's rendered value and runs the scrub, returning the value
	 * left for the template.
	 *
	 * @param mixed $value Value to place on the field before scrubbing.
	 * @return mixed
	 */
	private function scrub_company_logo_value( $value ) {
		$form = WP_Job_Manager_Form_Submit_Job::instance();
		$form->init_fields();

		$fields_prop = ( new ReflectionClass( $form ) )->getProperty( 'fields' );
		$fields_prop->setAccessible( true );
		$fields                                   = $fields_prop->getValue( $form );
		$fields['company']['company_logo']['value'] = $value;
		$fields_prop->setValue( $form, $fields );

		$scrub = new ReflectionMethod( $form, 'scrub_unusable_attachment_field_values' );
		$scrub->setAccessible( true );
		$scrub->invoke( $form );

		return $fields_prop->getValue( $form )['company']['company_logo']['value'];
	}

	/**
	 * A foreign attachment ID posted into the logo field is blanked before re-render, so
	 * its URL is never resolved by the template.
	 */
	public function test_foreign_attachment_id_is_scrubbed_from_render_value() {
		$owner   = $this->factory->user->create( [ 'role' => 'administrator' ] );
		$private = $this->factory->post->create( [ 'post_status' => 'private', 'post_author' => $owner ] );
		$foreign = $this->factory->attachment->create_object(
			'private-memo.png',
			$private,
			[ 'post_mime_type' => 'image/png', 'post_author' => $owner ]
		);

		// A low-privilege submitter who does not own the attachment.
		$attacker = $this->factory->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $attacker );

		$this->assertSame(
			'',
			$this->scrub_company_logo_value( (string) $foreign ),
			'A foreign attachment ID must be blanked from the re-rendered field value.'
		);
	}

	/**
	 * Control: an attachment the submitter owns survives the scrub, so the normal
	 * round-trip of a just-uploaded logo keeps working.
	 */
	public function test_owned_attachment_id_survives_scrub() {
		$user = $this->factory->user->create( [ 'role' => 'employer' ] );
		wp_set_current_user( $user );

		$owned = $this->factory->attachment->create_object(
			'my-logo.png',
			0,
			[ 'post_mime_type' => 'image/png', 'post_author' => $user ]
		);

		$this->assertSame(
			(string) $owned,
			(string) $this->scrub_company_logo_value( (string) $owned ),
			'An attachment the submitter owns must survive the scrub.'
		);
	}

	/**
	 * Control: a plain (non-numeric) value such as an uploaded file URL is left untouched.
	 */
	public function test_non_numeric_value_is_untouched() {
		$url = 'http://example.org/wp-content/uploads/2026/08/logo.png';

		$this->assertSame(
			$url,
			$this->scrub_company_logo_value( $url ),
			'A non-numeric file value must be left untouched.'
		);
	}
}
