<?php
/**
 * Tests that the frontend job submission form reuses an existing attachment the
 * current user already owns instead of creating a duplicate every time an
 * identical file is uploaded, preventing unbounded Media Library / attachment
 * bloat from repeated logo uploads.
 *
 * @package wp-job-manager
 */
class WP_Test_Submit_Job_Attachment_Dedup extends WPJM_BaseTest {

	/**
	 * Absolute paths of files written into the uploads dir, removed on teardown.
	 *
	 * @var string[]
	 */
	private $created_files = [];

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';

		// create_attachment() normalises upload URLs to scheme://host/path, dropping any
		// port. The test site runs on a non-standard port (e.g. localhost:8889), which no
		// production uploads URL has, so strip it to mirror a real port-less environment
		// and let the URL resolve to a local upload the way it does on live sites.
		add_filter( 'upload_dir', [ $this, 'strip_upload_url_port' ] );
	}

	public function tearDown(): void {
		remove_filter( 'upload_dir', [ $this, 'strip_upload_url_port' ] );
		foreach ( $this->created_files as $path ) {
			if ( file_exists( $path ) ) {
				unlink( $path );
			}
		}
		$this->created_files = [];
		parent::tearDown();
	}

	/**
	 * upload_dir filter: removes the port from the upload URLs (paths untouched).
	 *
	 * @param array $dirs Upload directory data.
	 * @return array
	 */
	public function strip_upload_url_port( $dirs ) {
		foreach ( [ 'url', 'baseurl' ] as $key ) {
			$dirs[ $key ] = preg_replace( '#^(https?://[^/:]+):\d+#', '$1', $dirs[ $key ] );
		}
		return $dirs;
	}

	/**
	 * A minimal but valid 1x1 PNG so wp_generate_attachment_metadata() runs cleanly.
	 *
	 * @return string Raw PNG bytes.
	 */
	private function png_bytes() {
		return base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==' );
	}

	/**
	 * Writes a file with the given content into the current uploads dir and
	 * returns its public URL (mirroring a completed frontend upload).
	 *
	 * @param string $filename File name to write.
	 * @param string $content  Raw bytes.
	 * @return string Attachment URL.
	 */
	private function write_upload( $filename, $content ) {
		$upload_dir = wp_upload_dir();
		$path       = trailingslashit( $upload_dir['path'] ) . $filename;
		file_put_contents( $path, $content );
		$this->created_files[] = $path;

		return trailingslashit( $upload_dir['url'] ) . $filename;
	}

	/**
	 * Invokes the protected create_attachment() on the form with a given listing.
	 *
	 * @param string $attachment_url Uploaded file URL.
	 * @param int    $job_id         Listing the attachment belongs to.
	 * @return int Attachment ID.
	 */
	private function create_attachment_for( $attachment_url, $job_id ) {
		$form  = WP_Job_Manager_Form_Submit_Job::instance();
		$class = new ReflectionClass( $form );

		$job_id_prop = $class->getProperty( 'job_id' );
		$job_id_prop->setAccessible( true );
		$job_id_prop->setValue( $form, $job_id );

		$create = $class->getMethod( 'create_attachment' );
		$create->setAccessible( true );

		try {
			return $create->invoke( $form, $attachment_url );
		} finally {
			$job_id_prop->setValue( $form, 0 );
		}
	}

	/**
	 * Creates a listing owned by the current user.
	 *
	 * @return int Listing ID.
	 */
	private function create_listing() {
		return $this->factory->post->create(
			[
				'post_type'   => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_author' => get_current_user_id(),
			]
		);
	}

	/**
	 * Re-uploading the identical file (different name, same bytes — as
	 * wp_handle_upload() produces on a filename collision) must reuse the
	 * attachment the user already owns rather than inserting a duplicate.
	 */
	public function test_identical_upload_reuses_existing_owned_attachment() {
		$this->login_as_employer();
		$job_id = $this->create_listing();
		$bytes  = $this->png_bytes();

		$first_url  = $this->write_upload( 'company-logo.png', $bytes );
		$first_id   = $this->create_attachment_for( $first_url, $job_id );

		// Second listing, identical logo re-uploaded (collision-renamed on disk).
		$second_job = $this->create_listing();
		$second_url = $this->write_upload( 'company-logo-1.png', $bytes );
		$second_id  = $this->create_attachment_for( $second_url, $second_job );

		$this->assertGreaterThan( 0, $first_id, 'The first upload must create an attachment.' );
		$this->assertSame(
			$first_id,
			$second_id,
			'Re-uploading identical content the user already owns must reuse the existing attachment, not duplicate it.'
		);
	}

	/**
	 * Dedup is scoped to the current user: a different user uploading identical
	 * content must get their own attachment, never silently bound to another
	 * user's attachment (guards the #2995 / #3060 ownership boundary).
	 */
	public function test_identical_upload_by_different_user_is_not_reused() {
		$this->login_as_employer();
		$job_id    = $this->create_listing();
		$bytes     = $this->png_bytes();
		$owner_url = $this->write_upload( 'shared-logo.png', $bytes );
		$owner_id  = $this->create_attachment_for( $owner_url, $job_id );

		// A different employer uploads a byte-identical file.
		$this->login_as_employer_b();
		$other_job = $this->create_listing();
		$other_url = $this->write_upload( 'shared-logo-1.png', $bytes );
		$other_id  = $this->create_attachment_for( $other_url, $other_job );

		$this->assertGreaterThan( 0, $owner_id );
		$this->assertNotSame(
			$owner_id,
			$other_id,
			'A different user must not be given another user\'s attachment even for identical content.'
		);
	}
}
