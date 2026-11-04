<?php
/**
 * Tests for how the job submission form validates file field values posted as URLs.
 *
 * A file field URL usually names a fresh upload that only becomes an attachment when the
 * listing is saved, so it has to be a file the current visitor uploaded, a value already
 * saved on the listing being edited, or a media library attachment the visitor may use.
 *
 * @package wp-job-manager/tests
 */

class Tests_Submit_Job_File_URL_Ownership extends WPJM_BaseTest {

	const TRACKING_COOKIE = 'wp-job-manager-uploaded-files-key';

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';
	}

	public function tearDown(): void {
		unset( $_COOKIE[ self::TRACKING_COOKIE ] );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * A URL inside the uploads directory that nobody recorded as the visitor's upload is refused.
	 */
	public function test_unrecorded_file_url_in_uploads_is_rejected() {
		$this->login_as_employer();

		$result = $this->validate_ownership( $this->uploads_url( '2026/11/someone-elses-file-' . uniqid() . '.png' ) );

		$this->assertWPError( $result, 'A file URL the visitor did not upload must be refused.' );
	}

	/**
	 * A protected configuration file inside the uploads directory cannot be claimed as a field value.
	 */
	public function test_uploads_guard_file_is_rejected() {
		$this->login_as_employer();

		$result = $this->validate_ownership( $this->uploads_url( 'woocommerce_uploads/.htaccess' ) );

		$this->assertWPError( $result, 'A guard file in the uploads directory must be refused.' );
	}

	/**
	 * Another user's media library attachment cannot be bound to a listing by posting its URL.
	 */
	public function test_other_users_attachment_url_is_rejected() {
		$owner         = $this->factory->user->create( [ 'role' => 'administrator' ] );
		$attachment_id = $this->factory->attachment->create_object(
			'2026/11/private-logo-' . uniqid() . '.png',
			0,
			[
				'post_mime_type' => 'image/png',
				'post_author'    => $owner,
			]
		);

		$this->login_as_employer();

		$result = $this->validate_ownership( wp_get_attachment_url( $attachment_id ) );

		$this->assertWPError( $result, 'Another user\'s attachment URL must be refused.' );
	}

	/**
	 * Control: the visitor's own attachment is usable by URL, like it is by ID.
	 */
	public function test_own_attachment_url_is_accepted() {
		$user = $this->factory->user->create( [ 'role' => 'employer' ] );
		wp_set_current_user( $user );

		$attachment_id = $this->factory->attachment->create_object(
			'2026/11/own-logo-' . uniqid() . '.png',
			0,
			[
				'post_mime_type' => 'image/png',
				'post_author'    => $user,
			]
		);

		$this->assertTrue( $this->validate_ownership( wp_get_attachment_url( $attachment_id ) ) );
	}

	/**
	 * A file uploaded during the current request is usable.
	 */
	public function test_file_uploaded_during_the_request_is_accepted() {
		$this->login_as_employer();
		$url = $this->uploads_url( '2026/11/fresh-upload-' . uniqid() . '.png' );

		job_manager_record_submitted_upload( $url );

		$this->assertTrue( $this->validate_ownership( $url ) );
	}

	/**
	 * A signed-in visitor's upload from an earlier request is usable with no cookie at all,
	 * and belongs to that account alone.
	 */
	public function test_signed_in_upload_record_works_without_cookie() {
		$user = $this->factory->user->create( [ 'role' => 'employer' ] );
		$url  = $this->uploads_url( '2026/11/earlier-upload-' . uniqid() . '.png' );

		set_transient( 'jm_uploads_user_' . $user, [ $url ], DAY_IN_SECONDS );

		wp_set_current_user( $user );
		$this->assertArrayNotHasKey( self::TRACKING_COOKIE, $_COOKIE );
		$this->assertTrue( $this->validate_ownership( $url ), 'A signed-in visitor\'s recorded upload must be usable without the cookie.' );

		$other = $this->factory->user->create( [ 'role' => 'employer' ] );
		wp_set_current_user( $other );
		$this->assertWPError( $this->validate_ownership( $url ), 'Another account must not be able to use the record.' );
	}

	/**
	 * A guest's upload from an earlier request is usable through their browser key, and only
	 * with that key.
	 */
	public function test_guest_upload_record_requires_the_browser_key() {
		$key = str_repeat( 'a1B2', 8 );
		$url = $this->uploads_url( '2026/11/guest-upload-' . uniqid() . '.png' );

		set_transient( 'jm_uploads_' . md5( $key ), [ $url ], DAY_IN_SECONDS );

		$this->assertWPError( $this->validate_ownership( $url ), 'Without the browser key the record must not apply.' );

		$_COOKIE[ self::TRACKING_COOKIE ] = $key;
		$this->assertTrue( $this->validate_ownership( $url ), 'With the browser key the guest\'s upload must be usable.' );
	}

	/**
	 * A guest's upload stays usable when they sign in partway through the submission.
	 */
	public function test_guest_upload_stays_usable_after_signing_in() {
		$key = str_repeat( 'Zx9y', 8 );
		$url = $this->uploads_url( '2026/11/guest-then-user-' . uniqid() . '.png' );

		set_transient( 'jm_uploads_' . md5( $key ), [ $url ], DAY_IN_SECONDS );
		$_COOKIE[ self::TRACKING_COOKIE ] = $key;

		$this->login_as_employer();

		$this->assertTrue( $this->validate_ownership( $url ) );
	}

	/**
	 * A value already saved on the listing being edited stays usable, whoever uploaded it.
	 */
	public function test_saved_listing_file_url_is_accepted() {
		$user = $this->factory->user->create( [ 'role' => 'employer' ] );
		wp_set_current_user( $user );

		$job_id = $this->factory->job_listing->create( [ 'post_author' => $user ] );
		$url    = $this->uploads_url( '2026/11/saved-file-' . uniqid() . '.pdf' );
		update_post_meta( $job_id, '_portfolio_file', $url );

		$form = $this->form( $job_id );

		$usable = new ReflectionMethod( $form, 'is_usable_file_url' );
		$usable->setAccessible( true );

		$this->assertTrue( $usable->invoke( $form, esc_url( $url ), 'company', 'portfolio_file' ) );
		$this->assertFalse( $usable->invoke( $form, esc_url( $this->uploads_url( '2026/11/not-saved.pdf' ) ), 'company', 'portfolio_file' ) );
	}

	/**
	 * A URL fragment does not smuggle a file past the field's allowed file types.
	 */
	public function test_file_url_fragment_does_not_bypass_the_file_type_check() {
		$this->login_as_employer();

		$url = $this->uploads_url( '2026/11/payload-' . uniqid() . '.php#.jpg' );
		job_manager_record_submitted_upload( $url );

		$form   = $this->form();
		$values = $this->passing_values( $form );

		$values['company']['company_logo'] = $url;

		$validate = new ReflectionMethod( $form, 'validate_fields' );
		$validate->setAccessible( true );

		try {
			$result = $validate->invoke( $form, $values );
		} catch ( Exception $e ) {
			$this->assertStringContainsString( 'needs to be one of the following file types', $e->getMessage() );
			return;
		}

		$this->fail( 'The file type check must refuse a disallowed file with a fragment suffix. Got: ' . var_export( $result, true ) );
	}

	/**
	 * Control: a recorded upload of an allowed type passes the same full validation.
	 */
	public function test_recorded_upload_of_allowed_type_passes_validation() {
		$this->login_as_employer();

		$url = $this->uploads_url( '2026/11/logo-' . uniqid() . '.jpg' );
		job_manager_record_submitted_upload( $url );

		$form   = $this->form();
		$values = $this->passing_values( $form );

		$values['company']['company_logo'] = $url;

		$validate = new ReflectionMethod( $form, 'validate_fields' );
		$validate->setAccessible( true );

		$this->assertTrue( $validate->invoke( $form, $values ) );
	}

	private function uploads_url( $path ) {
		return trailingslashit( wp_upload_dir()['baseurl'] ) . $path;
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
	 * @param string $file_url Posted file URL.
	 * @return bool|WP_Error
	 */
	private function validate_ownership( $file_url ) {
		$form = $this->form();

		$validate = new ReflectionMethod( $form, 'validate_attachment_ownership' );
		$validate->setAccessible( true );

		return $validate->invoke( $form, [ 'company' => [ 'company_logo' => $file_url ] ] );
	}

	/**
	 * Builds a $values array, and field state, that passes every check in validate_fields()
	 * other than the ones a test sets out to hit.
	 *
	 * @param WP_Job_Manager_Form_Submit_Job $form Submit form.
	 * @return array
	 */
	private function passing_values( $form ) {
		$fields_prop = ( new ReflectionClass( $form ) )->getProperty( 'fields' );
		$fields_prop->setAccessible( true );
		$fields = $fields_prop->getValue( $form );

		$values = [];
		foreach ( $fields as $group_key => $group_fields ) {
			foreach ( $group_fields as $key => $field ) {
				$fields[ $group_key ][ $key ]['empty']    = false;
				$fields[ $group_key ][ $key ]['required'] = false;

				$values[ $group_key ][ $key ] = '';
			}
		}

		$fields_prop->setValue( $form, $fields );

		$values['job']['application'] = 'employer@example.com';

		return $values;
	}
}
