<?php
/**
 * Tests that the admin job-listing save path enforces the
 * `allowed_mime_types` whitelist on `file` fields registered through the
 * `job_manager_job_listing_data_fields` filter, matching the frontend
 * behavior in WP_Job_Manager_Form_Submit_Job::validate_fields().
 *
 * @package wp-job-manager
 */

// WP_Job_Manager_Writepanels is loaded by the existing writepanels test file in this directory.

class WP_Test_WP_Job_Manager_Writepanels_File_Mime extends WPJM_BaseTest {

	const FIELD_KEY     = '_job_attachment';
	const TRANSIENT_KEY = 'wpjm_job_listing_file_type_errors_';

	/**
	 * @var array
	 */
	private $registered_field;

	public function setUp(): void {
		parent::setUp();
		$this->enable_manage_job_listings_cap();

		$this->registered_field = [
			self::FIELD_KEY => [
				'label'             => 'Attachment',
				'type'              => 'file',
				'priority'          => 9,
				'data_type'         => 'string',
				'show_in_admin'     => true,
				'show_in_rest'      => true,
				'allowed_mime_types' => [
					'pdf' => 'application/pdf',
				],
			],
		];

		add_filter( 'job_manager_job_listing_data_fields', [ $this, 'register_field' ] );
	}

	public function tearDown(): void {
		remove_filter( 'job_manager_job_listing_data_fields', [ $this, 'register_field' ] );
		delete_transient( self::TRANSIENT_KEY . $this->last_job_id );
		unset( $_POST, $_REQUEST );
		$_POST    = [];
		$_REQUEST = [];
		$this->registered_field = null;
		$this->last_job_id      = 0;
		parent::tearDown();
	}

	public function register_field( $fields ) {
		return array_merge( $fields, $this->registered_field );
	}

	/**
	 * Mock a save request against a fresh job listing and invoke the
	 * writepanels save routine directly.
	 */
	private function save_with( $attachment_value, $existing_meta = '' ) {
		$this->login_as_admin();

		global $post;
		$job_id = $this->factory->job_listing->create();
		if ( '' !== $existing_meta ) {
			update_post_meta( $job_id, self::FIELD_KEY, $existing_meta );
		}

		$post = get_post( $job_id );

		$_POST                       = [];
		$_POST['post_status']        = 'publish';
		$_POST['original_post_status'] = 'publish';
		$_POST[ self::FIELD_KEY ]    = $attachment_value;

		$writepanels = WP_Job_Manager_Writepanels::instance();
		$writepanels->save_job_listing_data( $job_id, $post );

		$this->last_job_id = $job_id;
	}

	public function test_disallowed_file_extension_is_rejected() {
		$bad_url = 'https://example.com/evil.exe';

		$this->save_with( $bad_url );

		$stored = get_post_meta( $this->last_job_id, self::FIELD_KEY, true );
		$this->assertSame( '', $stored, 'Disallowed file extension must not be stored.' );

		$errors = get_transient( self::TRANSIENT_KEY . $this->last_job_id );
		$this->assertIsArray( $errors, 'Rejection transient should be set.' );
		$this->assertCount( 1, $errors );
		$this->assertSame( 'Attachment', $errors[0]['field'] );
		$this->assertNotEmpty( $errors[0]['rejected'], 'Rejected list should not be empty.' );
		$this->assertSame( [ 'pdf' ], $errors[0]['allowed'] );
	}

	public function test_allowed_file_extension_is_stored() {
		$good_url = 'https://example.com/file.pdf';

		$this->save_with( $good_url );

		$stored = get_post_meta( $this->last_job_id, self::FIELD_KEY, true );
		$this->assertSame( $good_url, $stored, 'Allowed file extension must be stored.' );

		$errors = get_transient( self::TRANSIENT_KEY . $this->last_job_id );
		$this->assertEmpty( $errors, 'No rejection transient should be set for allowed files.' );
	}

	public function test_existing_meta_is_preserved_when_rejected() {
		$existing = 'https://example.com/old.pdf';

		$this->save_with( 'https://example.com/evil.exe', $existing );

		$stored = get_post_meta( $this->last_job_id, self::FIELD_KEY, true );
		$this->assertSame( $existing, $stored, 'Previously-stored meta must be preserved when a rejection happens.' );
		$this->assertNotEmpty( get_transient( self::TRANSIENT_KEY . $this->last_job_id ), 'Rejection transient should still be set so the notice renders.' );
	}

	public function test_attachment_id_is_rejected_as_non_url() {
		$admin_id   = $this->factory->user->create( [ 'role' => 'administrator' ] );
		$attachment = wp_insert_attachment(
			[
				'post_title'     => 'Test PDF',
				'post_mime_type' => 'application/pdf',
				'post_status'    => 'inherit',
				'post_author'    => $admin_id,
			],
			'test.pdf'
		);

		$this->save_with( (string) $attachment );

		$stored = get_post_meta( $this->last_job_id, self::FIELD_KEY, true );
		$this->assertSame( '', $stored, 'Numeric attachment IDs must not bypass the mime check.' );
		$this->assertNotEmpty( get_transient( self::TRANSIENT_KEY . $this->last_job_id ), 'Numeric IDs with no extension must register a rejection notice.' );
	}

	public function test_query_string_is_stripped_before_check() {
		$bad_url = 'https://example.com/evil.exe?v=1';

		$this->save_with( $bad_url );

		$stored = get_post_meta( $this->last_job_id, self::FIELD_KEY, true );
		$this->assertSame( '', $stored, 'Query string on a disallowed URL must not bypass the type check.' );
		$this->assertNotEmpty( get_transient( self::TRANSIENT_KEY . $this->last_job_id ) );
	}

	public function test_pipe_separated_extensions_are_flattened_in_notice() {
		// Override the registered field to use a pipe-delimited key like the repo's logo default.
		$this->registered_field[ self::FIELD_KEY ]['allowed_mime_types'] = [
			'jpg|jpeg|jpe' => 'image/jpeg',
		];
		remove_filter( 'job_manager_job_listing_data_fields', [ $this, 'register_field' ] );
		add_filter( 'job_manager_job_listing_data_fields', [ $this, 'register_field' ] );

		$this->save_with( 'https://example.com/photo.png' );

		$errors = get_transient( self::TRANSIENT_KEY . $this->last_job_id );
		$this->assertIsArray( $errors );
		$this->assertSame( [ 'jpg', 'jpeg', 'jpe' ], $errors[0]['allowed'] );
	}
}
