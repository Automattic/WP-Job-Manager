<?php
/**
 * Tests that the `file` form-field template emits a native `accept` attribute
 * derived from the field's allowed types, so the OS file picker can pre-filter.
 *
 * @package wp-job-manager
 */
class WP_Test_File_Field_Template extends WPJM_BaseTest {

	/**
	 * Render the file-field template and return its HTML.
	 *
	 * @param array  $field Field definition passed to the template.
	 * @param string $key   Field key.
	 * @return string
	 */
	private function render_file_field( $field, $key = 'company_logo' ) {
		ob_start();
		get_job_manager_template( 'form-fields/file-field.php', [ 'key' => $key, 'field' => $field ] );
		return ob_get_clean();
	}

	/**
	 * A field that restricts types emits a spec-valid, comma-separated `accept`
	 * with each extension prefixed by a dot, while `data-file_types` is preserved.
	 */
	public function test_restricted_field_outputs_accept_and_preserves_data_file_types() {
		$html = $this->render_file_field(
			[
				'allowed_mime_types' => [
					'jpg'  => 'image/jpeg',
					'jpeg' => 'image/jpeg',
					'png'  => 'image/png',
				],
			]
		);

		$this->assertStringContainsString( 'accept=".jpg,.jpeg,.png"', $html );
		$this->assertStringContainsString( 'data-file_types="jpg|jpeg|png"', $html );
	}

	/**
	 * Pipe-separated extension groups (as produced by the
	 * job_manager_get_allowed_mime_types() fallback) are split into individual
	 * dot-prefixed tokens, and the `accept` value never contains a pipe separator.
	 */
	public function test_pipe_separated_groups_are_split() {
		// No allowed_mime_types on the field, so the template falls back to
		// job_manager_get_allowed_mime_types(), whose first key is the group `jpg|jpeg|jpe`.
		$html = $this->render_file_field( [] );

		$this->assertMatchesRegularExpression( '/accept="[^"]+"/', $html );
		$this->assertStringContainsString( 'accept=".jpg,.jpeg,.jpe', $html );

		preg_match( '/accept="([^"]*)"/', $html, $matches );
		$this->assertNotEmpty( $matches );
		$this->assertStringNotContainsString( '|', $matches[1] );
	}

	/**
	 * Extensions that already carry a leading dot are normalized so the `accept`
	 * value never contains a doubled `..` token.
	 */
	public function test_leading_dot_extensions_are_normalized() {
		$html = $this->render_file_field(
			[
				'allowed_mime_types' => [
					'.jpg' => 'image/jpeg',
					'png'  => 'image/png',
				],
			]
		);

		$this->assertStringContainsString( 'accept=".jpg,.png"', $html );
		$this->assertStringNotContainsString( '..jpg', $html );
	}

	/**
	 * When no types are allowed at all, no `accept` attribute is emitted rather
	 * than an empty one.
	 */
	public function test_no_accept_when_no_types_allowed() {
		add_filter( 'job_manager_mime_types', '__return_empty_array' );

		$html = $this->render_file_field( [] );

		remove_filter( 'job_manager_mime_types', '__return_empty_array' );

		$this->assertStringNotContainsString( 'accept=', $html );
	}
}
