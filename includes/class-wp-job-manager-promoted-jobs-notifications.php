<?php
/**
 * File containing the class WP_Job_Manager_Promoted_Jobs_Notifications.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notifies wpjobmanager.com when a Job Listing changes.
 *
 * @since $$next-version$$
 */
class WP_Job_Manager_Promoted_Jobs_Notifications {

	const NOTIFICATION_URL = 'https://wpjobmanager.com/wp-json/promoted-jobs/v1/site/{site_id}/update';

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  $$next-version$$
	 */
	private static $instance = null;

	/**
	 * Allows for accessing single instance of class.
	 *
	 * @since  $$next-version$$
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_trash_post', [ $this, 'promoted_jobs_trash_post' ] );
	}

	/**
	 * Get the site ID.
	 *
	 * @return int|string
	 */
	private function get_site_id() {
		return get_current_blog_id();
	}

	/**
	 * Get the notification URL.
	 *
	 * @return string
	 */
	private function get_notification_url() {
		return str_replace( '{site_id}', $this->get_site_id(), self::NOTIFICATION_URL );
	}

	/**
	 * Check if a job is promoted.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function is_promoted_job( $post_id ) {
		return get_post_meta( $post_id, '_promoted', true );
	}

	/**
	 * Notify wpjobmanager.com when a Job Listing is trashed.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function promoted_jobs_trash_post( $post_id ) {
		if ( 'job_listing' !== get_post_type( $post_id ) ) {
			return;
		}
		if ( ! $this->is_promoted_job( $post_id ) ) {
			return;
		}
		$this->notify_change( $post_id );
	}

	/**
	 * Notify wpjobmanager.com when a Job Listing changes.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function notify_change( $post_id ) {
		$response = wp_remote_post( $this->get_notification_url() );
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			// Todo: Schedule a job to run the same task.
			// Todo: maybe log the error message?
		}
	}
}

WP_Job_Manager_Promoted_Jobs_Notifications::instance();
