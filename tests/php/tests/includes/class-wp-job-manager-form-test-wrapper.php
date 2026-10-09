<?php
/**
 * Test wrapper for sanitize_posted_field() / parse_email_list() exposure.
 *
 * Loaded before the test class so the parent concrete form class is
 * available at class-declaration time.
 *
 * @package wp-job-manager
 */

if ( ! class_exists( 'WP_Job_Manager_Form', false ) ) {
	require_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
}

if ( ! class_exists( 'WP_Job_Manager_Form_Submit_Job', false ) ) {
	require_once JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-submit-job.php';
}

/**
 * Concrete subclass of WP_Job_Manager_Form_Submit_Job that exposes the
 * protected helpers sanitize_posted_field() and parse_email_list().
 */
class WP_Job_Manager_Form_Test_Wrapper extends WP_Job_Manager_Form_Submit_Job {
	public function __construct() {}

	public function call_sanitize_posted_field( $value, $sanitizer = null ) {
		return $this->sanitize_posted_field( $value, $sanitizer );
	}

	public function call_parse_email_list( $value ) {
		return $this->parse_email_list( $value );
	}
}
