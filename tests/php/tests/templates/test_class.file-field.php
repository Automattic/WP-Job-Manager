<?php
/**
 * Covers templates/form-fields/file-field.php.
 *
 * @package wp-job-manager
 */
class WP_Test_File_Field_Template extends WPJM_BaseTest {

	public function tearDown(): void {
		remove_filter( 'job_manager_mime_types', '__return_empty_array' );
		parent::tearDown();
	}

	/**
	 * Render the file-field template and return the value of the input's `accept` attribute, or null when the
	 * attribute is absent.
	 *
	 * @param array  $field Field definition passed to the template.
	 * @param string $key   Field key.
	 * @return string|null
	 */
	private function render_accept_attribute( $field, $key = 'company_logo' ) {
		ob_start();
		get_job_manager_template( 'form-fields/file-field.php', [ 'key' => $key, 'field' => $field ] );
		$html = ob_get_clean();

		if ( ! preg_match( '/\saccept="([^"]*)"/', $html, $matches ) ) {
			return null;
		}

		return $matches[1];
	}

	/**
	 * A field that restricts types gets a spec-valid, comma-separated `accept` value.
	 */
	public function test_restricted_field_outputs_accept() {
		$accept = $this->render_accept_attribute(
			[
				'allowed_mime_types' => [
					'jpg|jpeg' => 'image/jpeg',
					'png'      => 'image/png',
				],
			]
		);

		$this->assertSame( '.jpg,.jpeg,.png', $accept );
	}

	/**
	 * A field with no allowed_mime_types falls back to the same types the server accepts, not WordPress's full mime
	 * set. The company_logo field configures its own types, so the fallback is what a custom file field gets.
	 */
	public function test_field_without_allowed_mime_types_falls_back_to_wpjm_types() {
		$this->assertSame( '.jpg,.jpeg,.jpe,.gif,.png,.pdf,.doc,.docx', $this->render_accept_attribute( [], 'custom_file' ) );
		$this->assertSame( '.jpg,.jpeg,.jpe,.gif,.png,.webp', $this->render_accept_attribute( [], 'company_logo' ) );
	}

	/**
	 * When no types are allowed at all, no `accept` attribute is emitted rather than an empty one.
	 */
	public function test_no_accept_when_no_types_allowed() {
		add_filter( 'job_manager_mime_types', '__return_empty_array' );

		$this->assertNull( $this->render_accept_attribute( [], 'custom_file' ) );
	}
}
