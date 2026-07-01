<?php
/**
 * Tests that the frontend job submission form rejects file-field attachment IDs
 * the current user is not authorized to reuse, preventing a submitter from
 * binding another user's attachment to their own listing (object-level IDOR).
 *
 * @package wp-job-manager
 */
class WP_Test_Submit_Job_Attachment_Ownership extends WPJM_BaseTest {

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
	 * Creates an attachment owned by the given user.
	 *
	 * @param int $author_id Owner user ID.
	 * @return int Attachment ID.
	 */
	private function create_attachment_owned_by( $author_id ) {
		return wp_insert_attachment(
			[
				'post_title'     => 'Logo ' . $author_id,
				'post_mime_type' => 'image/png',
				'post_status'    => 'inherit',
				'post_author'    => $author_id,
			],
			'logo-' . $author_id . '.png'
		);
	}

	/**
	 * Runs the form's field validation against a single company_logo value.
	 *
	 * @param mixed $logo_value Value to validate for the company_logo field.
	 * @throws Exception When validation rejects the value.
	 * @return bool|WP_Error validate_fields() result.
	 */
	private function validate_company_logo( $logo_value ) {
		$form  = WP_Job_Manager_Form_Submit_Job::instance();
		$class = new ReflectionClass( $form );

		$fields_prop = $class->getProperty( 'fields' );
		$fields_prop->setAccessible( true );
		$fields_prop->setValue(
			$form,
			[
				'company' => [
					'company_logo' => [
						'label'              => 'Logo',
						'type'               => 'file',
						'required'           => false,
						'file_limit'         => 1,
						'allowed_mime_types' => [ 'png' => 'image/png' ],
					],
				],
			]
		);

		$validate = $class->getMethod( 'validate_fields' );
		$validate->setAccessible( true );

		return $validate->invoke( $form, [ 'company' => [ 'company_logo' => $logo_value ] ] );
	}

	/**
	 * A submitter cannot validate a company_logo pointing at an attachment owned
	 * by another user.
	 */
	public function test_foreign_attachment_id_is_rejected() {
		$this->login_as_admin();
		$admin_attachment = $this->create_attachment_owned_by( get_current_user_id() );

		$this->login_as_employer();

		$this->expectException( Exception::class );
		$this->validate_company_logo( (string) $admin_attachment );
	}

	/**
	 * A submitter can validate a company_logo pointing at an attachment they own —
	 * the normal reuse-your-own-logo flow must keep working.
	 */
	public function test_own_attachment_id_is_accepted() {
		$this->login_as_employer();
		$own_attachment = $this->create_attachment_owned_by( get_current_user_id() );

		$this->assertTrue(
			$this->validate_company_logo( (string) $own_attachment ),
			'A user must be able to reuse an attachment they own.'
		);
	}

	/**
	 * An empty / "no logo" value (0) is not treated as an attachment and passes
	 * validation unchanged.
	 */
	public function test_empty_logo_value_is_accepted() {
		$this->login_as_employer();

		$this->assertTrue(
			$this->validate_company_logo( '0' ),
			'A zero/empty logo value must not be rejected.'
		);
	}

	/**
	 * A user with capability to edit the attachment (e.g. an admin handling
	 * another user's listing) is authorized even when they do not own it.
	 */
	public function test_editor_capability_authorizes_foreign_attachment() {
		$this->login_as_employer();
		$employer_attachment = $this->create_attachment_owned_by( get_current_user_id() );

		$this->login_as_admin();

		$this->assertTrue(
			$this->validate_company_logo( (string) $employer_attachment ),
			'A user who can edit the attachment must be authorized to reuse it.'
		);
	}
}
