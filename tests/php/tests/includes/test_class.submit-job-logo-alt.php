<?php
/**
 * Tests that the company_logo_alt field persists alt text to the attachment
 * during front-end job submission.
 *
 * @package wp-job-manager
 */
class WP_Test_Submit_Job_Logo_Alt extends WPJM_BaseTest {

	/**
	 * Filtered uploads base URL.
	 *
	 * @var string
	 */
	private $base_url = 'http://example.org/wp-content/uploads';

	/**
	 * Filtered uploads base directory.
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
	 * Writes a minimal valid PNG into the uploads base dir and returns its URL.
	 *
	 * @param string $filename File name to create.
	 * @return string Public URL of the created file.
	 */
	private function write_local_image( $filename ) {
		$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC' );
		file_put_contents( trailingslashit( $this->base_dir ) . $filename, $png );
		return trailingslashit( $this->base_url ) . $filename;
	}

	/**
	 * Invokes the form's protected create_attachment().
	 *
	 * @param string $attachment_url Value to pass to create_attachment().
	 * @param string $alt_text       Alt text to store.
	 * @return int Attachment ID.
	 */
	private function create_attachment( $attachment_url, $alt_text = '' ) {
		$form   = WP_Job_Manager_Form_Submit_Job::instance();
		$method = new ReflectionMethod( $form, 'create_attachment' );
		$method->setAccessible( true );

		return $method->invoke( $form, $attachment_url, $alt_text );
	}

	/**
	 * Invokes the form's protected set_attachment_alt_text().
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $alt_text      Alt text to store.
	 */
	private function set_attachment_alt_text( $attachment_id, $alt_text ) {
		$form   = WP_Job_Manager_Form_Submit_Job::instance();
		$method = new ReflectionMethod( $form, 'set_attachment_alt_text' );
		$method->setAccessible( true );

		$method->invoke( $form, $attachment_id, $alt_text );
	}

	/**
	 * Creating an attachment with alt text sets _wp_attachment_image_alt.
	 */
	public function test_attachment_created_with_alt_text() {
		$local_url = $this->write_local_image( 'alt-upload.png' );

		$attachment_id = $this->create_attachment( $local_url, 'Alt text for logo' );

		$this->assertGreaterThan( 0, $attachment_id, 'Attachment must be created.' );
		$this->assertSame(
			'Alt text for logo',
			get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'_wp_attachment_image_alt must be set to the provided alt text.'
		);
	}

	/**
	 * Creating an attachment with empty alt text does not set meta or excerpt.
	 */
	public function test_attachment_created_without_alt_text() {
		$local_url = $this->write_local_image( 'no-alt-upload.png' );

		$attachment_id = $this->create_attachment( $local_url, '' );

		$this->assertGreaterThan( 0, $attachment_id, 'Attachment must be created.' );
		$this->assertSame(
			'',
			get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'_wp_attachment_image_alt must be empty when no alt text is provided.'
		);
	}

	/**
	 * A reusable attachment (same hash) does not overwrite a previously saved alt.
	 */
	public function test_reusable_attachment_does_not_overwrite_alt() {
		// Reuse only applies to a logged-in user's own attachments.
		$this->login_as_employer();

		$local_url = $this->write_local_image( 'reusable-alt.png' );

		// First upload with alt.
		$attachment_id = $this->create_attachment( $local_url, 'First alt' );
		$this->assertGreaterThan( 0, $attachment_id );

		$this->assertSame(
			'First alt',
			get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'First alt must be set.'
		);

		// Second upload of the same file with a different alt — must not overwrite.
		$attachment_id_2 = $this->create_attachment( $local_url, 'Second alt' );

		// Expect the same attachment reused.
		$this->assertEquals( $attachment_id, $attachment_id_2, 'Same file must reuse the existing attachment.' );
		$this->assertSame(
			'First alt',
			get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'Reuse must not overwrite the existing alt text.'
		);
	}

	/**
	 * set_attachment_alt_text with empty alt is a no-op.
	 */
	public function test_set_alt_text_noop_when_empty() {
		$attachment_id = $this->factory->attachment->create(
			[
				'post_title'   => 'test.png',
				'post_content' => '',
				'post_excerpt' => '',
				'post_mime_type' => 'image/png',
			]
		);

		$this->set_attachment_alt_text( $attachment_id, '' );

		$this->assertSame(
			'',
			get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'Setting empty alt must not create meta.'
		);
	}

	/**
	 * set_attachment_alt_text with blank string is also a no-op.
	 */
	public function test_set_alt_text_noop_when_whitespace() {
		$attachment_id = $this->factory->attachment->create(
			[
				'post_title'   => 'test.png',
				'post_content' => '',
				'post_excerpt' => '',
				'post_mime_type' => 'image/png',
			]
		);

		$this->set_attachment_alt_text( $attachment_id, '   ' );

		$this->assertSame(
			'',
			get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'Whitespace-only alt must not create meta.'
		);
	}

	/**
	 * get_the_company_logo_alt returns the existing alt text from the attachment.
	 */
	public function test_get_the_company_logo_alt_from_attachment() {
		$post_id = $this->factory->post->create(
			[
				'post_type'  => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_title' => 'Test Job',
			]
		);

		$attachment_id = $this->write_local_image_no_attachment( 'logo.png' );

		// Manually set alt and attach thumbnail.
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', 'Custom logo alt' );
		set_post_thumbnail( $post_id, $attachment_id );

		$this->assertSame(
			'Custom logo alt',
			get_the_company_logo_alt( get_post( $post_id ) ),
			'get_the_company_logo_alt must return the attachment alt text.'
		);
	}

	/**
	 * Writes a local PNG and returns an attachment ID for it.
	 *
	 * @param string $filename File name.
	 * @return int Attachment ID.
	 */
	private function write_local_image_no_attachment( $filename ) {
		$url = $this->write_local_image( $filename );
		return $this->create_attachment( $url, '' );
	}
}
