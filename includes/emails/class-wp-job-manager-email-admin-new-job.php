<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Email notification to administrator when a new job is submitted.
 *
 * @package wp-job-manager
 * @since 1.31.0
 * @extends WP_Job_Manager_Email
 */
class WP_Job_Manager_Email_Admin_New_Job extends WP_Job_Manager_Email_Template {
	/**
	 * Get the unique email notification key.
	 *
	 * @return string
	 */
	public static function get_key() {
		return 'admin-notice-new-listing';
	}

	/**
	 * Get the friendly name for this email notification.
	 *
	 * @return string
	 */
	public static function get_name() {
		return __( 'Admin Notice of New Listing', 'wp-job-manager' );
	}

	/**
	 * Expand arguments as necessary for the generation of the email.
	 *
	 * @param $args
	 * @return mixed
	 */
	protected function prepare_args( $args ) {
		if ( isset( $args['job_id'] ) ) {
			$job = get_post( $args['job_id'] );
			if ( $job instanceof WP_Post ) {
				$args['job'] = $job;
			}
		}
		if ( ! empty( $args['user_id'] ) ) {
			$user = get_user_by( 'ID', $args['user_id'] );
			if ( $user instanceof WP_User ) {
				$args['user'] = $user;
			}
		}
		return parent::prepare_args( $args );
	}

	/**
	 * Get the email subject.
	 *
	 * @return string
	 */
	public function get_subject() {
		$args = $this->get_args();

		/**
		 * @var WP_Post $job
		 */
		$job = $args['job'];
		return sprintf( __( 'New Job Listing Submitted: %s', 'wp-job-manager' ), $job->post_title );
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
