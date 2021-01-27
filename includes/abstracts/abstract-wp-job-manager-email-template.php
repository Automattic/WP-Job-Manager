<?php
/**
 * File containing the class WP_Job_Manager_Email_Template.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_Email_Template
 */
abstract class WP_Job_Manager_Email_Template extends WP_Job_Manager_Email {
	/**
	 * Get the template path for overriding templates.
	 *
	 * @type abstract
	 * @return string
	 */
	public static function get_template_path() {
		return 'job_manager';
	}

	/**
	 * Get the default template path that WP Job Manager should look for the templates.
	 *
	 * @type abstract
	 * @return string
	 */
	public static function get_template_default_path() {
		return '';
	}

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
	 * @return string
	 */
	public function get_template( $plain_text = false ) {
		$template_file = $this->locate_template( $plain_text );
		if ( ! $template_file ) {
			return '';
		}
		$args  = $this->get_args();
		$email = $this;

		ob_start();
		include $template_file;
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
		$class_name            = get_class( $this );
		$template_path         = call_user_func( [ $class_name, 'get_template_path' ] );
		$template_default_path = call_user_func( [ $class_name, 'get_template_default_path' ] );
		return locate_job_manager_template( $this->get_template_file_name( $plain_text ), $template_path, $template_default_path );
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
		$email_notification_key = call_user_func( [ $class_name, 'get_key' ] );
		$template_name          = str_replace( '_', '-', $email_notification_key );
		return self::generate_template_file_name( $template_name, $plain_text );
	}

	/**
	 * Generate the file name for the email template.
	 *
	 * @param string $template_name
	 * @param bool   $plain_text
	 * @return string
	 */
	public static function generate_template_file_name( $template_name, $plain_text = false ) {
		$file_name_parts = [ 'emails' ];
		if ( $plain_text ) {
			$file_name_parts[] = 'plain';
		}

		$file_name_parts[] = $template_name . '.php';

		return implode( '/', $file_name_parts );
	}
}
