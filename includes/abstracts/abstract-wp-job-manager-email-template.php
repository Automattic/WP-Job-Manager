<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Abstract class for an email notification built using templates.
 *
 * @package wp-job-manager
 *
 * @since 1.31.0
 */

abstract class WP_Job_Manager_Email_Template extends WP_Job_Manager_Email {

	/**
	 * Get the rich text version of the email content.
	 *
	 * @return string
	 */
	public function get_rich_content() {
		return $this->get_template( false );
	}

	/**
	 * Get the plaintext version of the email content.
	 *
	 * @return string
	 */
	public function get_plain_content() {
		if ( $this->has_template( true ) ) {
			return $this->get_template( true );
		}
		return parent::get_plain_content();
	}

	/**
	 * Get the contents of a template.
	 *
	 * @param bool $plain_text
	 * @return string|bool
	 */
	public function get_template( $plain_text = false ) {
		$template = $this->locate_template( $plain_text );
		if ( ! $template ) {
			return false;
		}
		$args = $this->get_args();
		$email = $this;

		ob_start();
		include $template;
		return ob_get_clean();
	}

	/**
	 * Check to see if a template exists for this email.
	 *
	 * @param bool $plain_text
	 * @return bool
	 */
	public function has_template( $plain_text = false ) {
		$template_file = $this->locate_template( $plain_text );
		return $template_file && file_exists( $template_file );
	}

	/**
	 * Locate template for this email.
	 *
	 * @param bool $plain_text
	 * @return string
	 */
	protected function locate_template( $plain_text ) {
		return locate_job_manager_template( $this->get_template_file_name( $plain_text ) );
	}

	/**
	 * Generate the file name for the email template.
	 *
	 * @param bool $plain_text
	 * @return string
	 */
	protected function get_template_file_name( $plain_text = false ) {
		$class_name = get_class( $this );
		// PHP 5.2: Using `call_user_func()` but `$class_name::get_key()` preferred.
		$email_notification_key = call_user_func( array( $class_name, 'get_key') );
		return WP_Job_Manager_Email_Notifications::get_template_file_name( $email_notification_key, $plain_text );
	}
}
