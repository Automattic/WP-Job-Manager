<?php
/**
 * Tests that the frontend job submission form only creates attachments from
 * files that resolve to a local path for this site. A file-field value that
 * still points at a remote host is not one of our own uploads, so it must not
 * be turned into an attachment (which would otherwise trigger a server-side
 * read of the remote URL during metadata generation).
 *
 * @package wp-job-manager
 */
class WP_Test_Submit_Job_Attachment_Local extends WPJM_BaseTest {

	/**
	 * Filtered uploads base URL (deliberately port-less so URL-to-path mapping
	 * is deterministic regardless of the test host's port).
	 *
	 * @var string
	 */
	private $base_url = 'http://example.org/wp-content/uploads';

	/**
	 * Filtered uploads base directory (a real writable temp dir).
	 *
	 * @var string
	 */
	private $base_dir;

	public function setUp(): void {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';

		$this->base_dir = trailingslashit( get_temp_dir() ) . 'wpjm-uploads-' . uniqid();
		wp_mkdir_p( $this->base_dir );

		add_filter( 'upload_dir', [ $this, 'filter_upload_dir' ] );
	}

	public function tearDown(): void {
		remove_filter( 'upload_dir', [ $this, 'filter_upload_dir' ] );

		unset( $_POST, $_REQUEST );
		$_POST    = [];
		$_REQUEST = [];
		parent::tearDown();
	}

	/**
	 * Points wp_upload_dir() at our deterministic base URL / directory.
	 *
	 * @param array $dirs Upload directory data.
	 * @return array
	 */
	public function filter_upload_dir( $dirs ) {
		$dirs['baseurl'] = $this->base_url;
		$dirs['basedir'] = $this->base_dir;
		$dirs['url']     = $this->base_url;
		$dirs['path']    = $this->base_dir;
		return $dirs;
	}

	/**
	 * Invokes the form's protected create_attachment() with a given value.
	 *
	 * @param string $attachment_url Value to pass to create_attachment().
	 * @return int Attachment ID (0 when nothing is attached).
	 */
	private function create_attachment( $attachment_url ) {
		$form   = WP_Job_Manager_Form_Submit_Job::instance();
		$method = new ReflectionMethod( $form, 'create_attachment' );
		$method->setAccessible( true );

		return $method->invoke( $form, $attachment_url );
	}

	/**
	 * Writes a minimal valid PNG into the uploads base dir and returns its URL.
	 *
	 * @param string $filename File name to create.
	 * @return string Public URL of the created file.
	 */
	private function write_local_image( $filename ) {
		// 1x1 transparent PNG.
		$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC' );
		file_put_contents( trailingslashit( $this->base_dir ) . $filename, $png );
		return trailingslashit( $this->base_url ) . $filename;
	}

	/**
	 * A value pointing at a remote host is not attached.
	 */
	public function test_remote_url_is_not_attached() {
		$before = count( get_posts( [ 'post_type' => 'attachment', 'fields' => 'ids', 'numberposts' => -1 ] ) );

		$this->assertSame(
			0,
			$this->create_attachment( 'http://example.com/remote/logo.jpg' ),
			'A remote URL must not be turned into an attachment.'
		);

		$after = count( get_posts( [ 'post_type' => 'attachment', 'fields' => 'ids', 'numberposts' => -1 ] ) );
		$this->assertSame( $before, $after, 'No attachment post should be created for a remote URL.' );
	}

	/**
	 * An https value pointing at a remote host is not attached either.
	 */
	public function test_remote_https_url_is_not_attached() {
		$this->assertSame(
			0,
			$this->create_attachment( 'https://example.com/remote/logo.png' ),
			'A remote HTTPS URL must not be turned into an attachment.'
		);
	}

	/**
	 * A URL under this site's uploads directory is still attached — the normal
	 * flow where the form round-trips an already-uploaded file must keep working.
	 */
	public function test_local_upload_url_is_attached() {
		$local_url = $this->write_local_image( 'local-logo.png' );

		$attachment_id = $this->create_attachment( $local_url );

		$this->assertGreaterThan(
			0,
			$attachment_id,
			'A URL under the site uploads directory must be attached.'
		);
		$this->assertSame(
			'attachment',
			get_post_type( $attachment_id ),
			'The created post must be an attachment.'
		);
	}
}
