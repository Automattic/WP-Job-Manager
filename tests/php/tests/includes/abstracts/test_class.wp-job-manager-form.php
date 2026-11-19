<?php
/**
 * Covers includes/abstracts/abstract-wp-job-manager-form.php.
 *
 * @package wp-job-manager
 */

require_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';

/**
 * Concrete form exposing the protected upload handler.
 */
class WPJM_Upload_Test_Form extends WP_Job_Manager_Form {
	public function do_upload_file( $field_key, $field ) {
		return $this->upload_file( $field_key, $field );
	}
}

class WP_Test_WP_Job_Manager_Form extends WPJM_BaseTest {

	/**
	 * Field key the uploads under test use.
	 *
	 * @var string
	 */
	private $field_key = 'company_logo';

	/**
	 * Field key the `job_manager_mime_types` filter was last called with.
	 *
	 * @var string|null
	 */
	private $captured_mime_types_field = null;

	public function tearDown(): void {
		unset( $_FILES[ $this->field_key ] );
		remove_filter( 'submit_job_wp_handle_upload_overrides', [ $this, 'override_upload_action' ] );
		remove_filter( 'job_manager_mime_types', [ $this, 'capture_mime_types_field' ], 10 );
		parent::tearDown();
	}

	/**
	 * A field with no `allowed_mime_types` of its own is validated against the types allowed for that field, not the
	 * generic list. Without this, a `company_logo` upload was checked against the pdf/doc defaults, so the webp the
	 * form itself advertises was rejected on the non-AJAX path.
	 */
	public function test_upload_file_uses_the_field_specific_allowed_mime_types() {
		$this->stage_upload( DIR_TESTDATA . '/images/test-image.webp', 'test-image.webp', 'image/webp' );

		$url = $this->upload_file( [] );

		$this->assertStringEndsWith( '.webp', $url );
	}

	/**
	 * An explicit `allowed_mime_types` on the field still wins over the field's defaults.
	 */
	public function test_upload_file_honours_an_explicit_allowed_mime_types() {
		$this->stage_upload( DIR_TESTDATA . '/images/test-image.webp', 'test-image.webp', 'image/webp' );

		$this->expectException( Exception::class );

		$this->upload_file( [ 'allowed_mime_types' => [ 'png' => 'image/png' ] ] );
	}

	/**
	 * The field key reaches the `job_manager_mime_types` filter, so a site can vary the allowed types per field.
	 */
	public function test_upload_file_passes_the_field_key_to_the_mime_types_filter() {
		$this->stage_upload( DIR_TESTDATA . '/images/test-image.webp', 'test-image.webp', 'image/webp' );
		add_filter( 'job_manager_mime_types', [ $this, 'capture_mime_types_field' ], 10, 2 );

		$this->upload_file( [] );

		$this->assertSame( $this->field_key, $this->captured_mime_types_field );
	}

	public function capture_mime_types_field( $allowed_mime_types, $field ) {
		$this->captured_mime_types_field = $field;

		return $allowed_mime_types;
	}

	public function override_upload_action( $args ) {
		$args['action'] = 'test-wpjm-upload';

		return $args;
	}

	/**
	 * Put a copy of a test image in $_FILES, as a form submission would.
	 */
	private function stage_upload( $path, $name, $type ) {
		$tmp_name = wp_tempnam( $path );
		copy( $path, $tmp_name );

		$_FILES[ $this->field_key ] = [
			'tmp_name' => $tmp_name,
			'name'     => $name,
			'type'     => $type,
			'error'    => 0,
			'size'     => filesize( $path ),
		];

		add_filter( 'submit_job_wp_handle_upload_overrides', [ $this, 'override_upload_action' ] );
	}

	/**
	 * Run the form's upload handler for the field under test.
	 *
	 * @param array $field Field definition.
	 * @return string|array Uploaded file URL(s).
	 */
	private function upload_file( $field ) {
		$form = new WPJM_Upload_Test_Form();

		return $form->do_upload_file( $this->field_key, $field );
	}
}
