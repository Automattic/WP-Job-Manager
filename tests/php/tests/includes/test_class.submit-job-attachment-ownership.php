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
		delete_option( 'job_manager_job_dashboard_page_id' );
		delete_option( 'job_manager_submission_requires_approval' );
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

	/**
	 * Drives WP_Job_Manager_Form_Submit_Job::submit_handler() for a "Save draft"
	 * POST binding the given company_logo value, the way the draft-save POST does.
	 *
	 * The draft path deliberately skips validate_fields() (incomplete forms are
	 * allowed), so this exercises the shared attachment-ownership check directly.
	 *
	 * @param mixed $logo_value Value for the current_company_logo field.
	 * @return int The resulting job listing ID (0 when submission was rejected).
	 */
	private function save_draft_with_logo( $logo_value ) {
		// can_continue_later() requires a configured job dashboard page.
		update_option( 'job_manager_job_dashboard_page_id', $this->factory->post->create( [ 'post_type' => 'page' ] ) );

		$form  = WP_Job_Manager_Form_Submit_Job::instance();
		$class = new ReflectionClass( $form );

		// Reset singleton state so init_fields() rebuilds and the job starts fresh.
		foreach ( [
			'fields' => [],
			'job_id' => 0,
			'step'   => 0,
		] as $prop => $value ) {
			$property = $class->getProperty( $prop );
			$property->setAccessible( true );
			$property->setValue( $form, $value );
		}

		$nonce                   = wp_create_nonce( 'submit-job-0' );
		$_POST                   = [
			'job_manager_form'     => 'submit-job',
			'_wpjm_nonce'          => $nonce,
			'job_id'               => 0,
			'step'                 => 0,
			'save_draft'           => 'Save draft',
			'job_title'            => 'Attacker Draft With Foreign Logo',
			'job_location'         => 'Anywhere',
			'job_description'      => 'save-draft ownership poc',
			'application'          => 'someone@example.com',
			'company_name'         => 'DraftCo',
			'current_company_logo' => (string) $logo_value,
		];
		$_REQUEST['_wpjm_nonce'] = $nonce;

		$form->submit_handler();

		return $form->get_job_id();
	}

	/**
	 * Regression: the "Save draft" path (which skips validate_fields()) must still
	 * reject a company_logo pointing at another user's attachment. Before the shared
	 * ownership check, this bound the foreign attachment as the draft's featured image
	 * and the submitter's default company logo.
	 */
	public function test_save_draft_rejects_foreign_attachment() {
		$this->login_as_admin();
		$foreign_attachment = $this->create_attachment_owned_by( get_current_user_id() );

		$this->login_as_employer();
		$employer_id = get_current_user_id();

		$draft_id = $this->save_draft_with_logo( (string) $foreign_attachment );

		$this->assertSame( 0, $draft_id, 'A draft save with a foreign attachment must be rejected before the listing is created.' );

		$bound = get_posts(
			[
				'post_type'   => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_status' => 'any',
				'meta_key'    => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => $foreign_attachment, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'      => 'ids',
			]
		);
		$this->assertEmpty( $bound, 'The foreign attachment must not be bound to any listing as a featured image.' );

		$this->assertNotEquals(
			(string) $foreign_attachment,
			get_user_meta( $employer_id, '_company_logo', true ),
			'The foreign attachment must not persist as the submitter default company logo.'
		);
	}

	/**
	 * No-regression: the draft path still accepts a company_logo the submitter owns,
	 * creating the draft and binding their own attachment.
	 */
	public function test_save_draft_accepts_own_attachment() {
		$this->login_as_employer();
		$own_attachment = $this->create_attachment_owned_by( get_current_user_id() );

		$draft_id = $this->save_draft_with_logo( (string) $own_attachment );

		$this->assertGreaterThan( 0, $draft_id, 'A draft save with an owned attachment must succeed.' );
		$this->assertSame( 'draft', get_post_status( $draft_id ) );
		$this->assertEquals(
			$own_attachment,
			(int) get_post_meta( $draft_id, '_thumbnail_id', true ),
			'The submitter own attachment must bind as the draft featured image.'
		);
	}
}
