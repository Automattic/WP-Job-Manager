<?php
/**
 * Regression tests: deleting a job listing's attachment removes only the attachment,
 * never a file that another attachment still references.
 *
 * @package wp-job-manager/tests
 */

class Tests_Shared_Attachment_Files extends WPJM_BaseTest {

	/**
	 * @covers WP_Job_Manager_Post_Types::keep_shared_attachment_files
	 */
	public function test_deleting_listing_attachment_keeps_a_file_another_attachment_uses() {
		$file     = $this->create_upload();
		$original = $this->create_attachment( $file, 0 );
		$listing  = $this->factory->job_listing->create();
		$planted  = $this->create_attachment( $file, $listing );

		wp_delete_attachment( $planted, true );

		$this->assertNull( get_post( $planted ), 'The attachment itself must be deleted.' );
		$this->assertFileExists( $file, 'A file another attachment references must be kept.' );

		// With the last reference gone, deleting the remaining attachment removes the file.
		wp_delete_attachment( $original, true );

		$this->assertFileDoesNotExist( $file );
	}

	/**
	 * Control: a listing attachment that is the only reference still cleans up its file.
	 *
	 * @covers WP_Job_Manager_Post_Types::keep_shared_attachment_files
	 */
	public function test_deleting_sole_listing_attachment_removes_its_file() {
		$file       = $this->create_upload();
		$listing    = $this->factory->job_listing->create();
		$attachment = $this->create_attachment( $file, $listing );

		wp_delete_attachment( $attachment, true );

		$this->assertFileDoesNotExist( $file );
	}

	/**
	 * Control: attachments that don't belong to a listing keep WordPress's own behavior.
	 *
	 * @covers WP_Job_Manager_Post_Types::keep_shared_attachment_files
	 */
	public function test_non_listing_attachments_keep_default_behavior() {
		$file  = $this->create_upload();
		$this->create_attachment( $file, 0 );
		$other = $this->create_attachment( $file, 0 );

		wp_delete_attachment( $other, true );

		$this->assertFileDoesNotExist( $file );
	}

	private function create_upload() {
		$upload = wp_upload_bits( uniqid( 'shared-file-', false ) . '.pdf', null, "%PDF-1.4\n%%EOF\n" );
		$this->assertEmpty( $upload['error'] );

		return $upload['file'];
	}

	private function create_attachment( $file, $parent_id ) {
		$attachment_id = wp_insert_attachment(
			[
				'post_mime_type' => 'application/pdf',
				'post_title'     => 'Uploaded file',
				'post_status'    => 'inherit',
			],
			$file,
			$parent_id
		);
		$this->assertGreaterThan( 0, $attachment_id );

		return $attachment_id;
	}
}
