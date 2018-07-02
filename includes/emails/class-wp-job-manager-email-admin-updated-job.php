<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Email notification to administrator when a job is updated.
 *
 * @package wp-job-manager
 * @since 1.31.0
 * @extends WP_Job_Manager_Email
 */
class WP_Job_Manager_Email_Admin_Updated_Job extends WP_Job_Manager_Email_Template {
	/**
	 * Get the unique email notification key.
	 *
	 * @return string
	 */
	public static function get_key() {
		return 'admin_updated_job';
	}

	/**
	 * Get the friendly name for this email notification.
	 *
	 * @return string
	 */
	public static function get_name() {
		return __( 'Admin Notice of Updated Listing', 'wp-job-manager' );
	}

	/**
	 * Get the description for this email notification.
	 *
	 * @type abstract
	 * @return string
	 */
	public static function get_description() {
		return __( 'Send a notice to the site administrator when a job is updated on the frontend.', 'wp-job-manager' );
	}

	/**
	 * Get the email subject.
	 *
	 * @return string
	 */
	public function get_subject() {
		$args = $this->get_args();

		/**
		 * Job listing post object.
		 *
		 * @var WP_Post $job
		 */
		$job = $args['job'];

		// translators: Placeholder %s is the job listing post title.
		return sprintf( __( 'Job Listing Updated: %s', 'wp-job-manager' ), $job->post_title );
	}

	/**
	 * Get `From:` address header value. Can be simple email or formatted `Firstname Lastname <email@example.com>`.
	 *
	 * @return string|bool Email from value or false to use WordPress' default.
	 */
	public function get_from() {
		return false;
	}

	/**
	 * Get array or comma-separated list of email addresses to send message.
	 *
	 * @return string|array
	 */
	public function get_to() {
		return get_option( 'admin_email', false );
	}

	/**
	 * Checks the arguments and returns whether the email notification is properly set up.
	 *
	 * @return bool
	 */
	public function is_valid() {
		$args = $this->get_args();
		return isset( $args['job'] )
				&& $args['job'] instanceof WP_Post
				&& $this->get_to();
	}
}
