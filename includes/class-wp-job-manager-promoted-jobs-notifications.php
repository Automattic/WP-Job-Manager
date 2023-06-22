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
		add_action( 'edit_job_form_save_job_data', [ $this, 'promoted_jobs_save_post' ], 10, 2 );
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
	 * Get post meta of the fields we are watching for changes.
	 *
	 * @param int $post_id Post ID.
	 */
	private function get_meta_fields( $post_id ) {
		return [
			'job_title'       => get_post_meta( $post_id, '_job_title', true ),
			'job_description' => get_post_meta( $post_id, '_job_description', true ),
			'company_name'    => get_post_meta( $post_id, '_company_name', true ),
			'application'     => get_post_meta( $post_id, '_application', true ),
			'job_location'    => get_post_meta( $post_id, '_job_location', true ),
		];
	}

	/**
	 * Map values to the format we are watching for changes.
	 *
	 * @param array $values Values of the job fields.
	 */
	private function map_data_fields( $values ) {
		return [
			'job_title'       => $values['job']['job_title'],
			'job_description' => $values['job']['job_description'],
			'company_name'    => $values['company']['company_name'],
			'application'     => $values['job']['application'],
			'job_location'    => $values['job']['job_location'],
		];
	}

	/**
	 * Determine if any job field has changed.
	 *
	 * @param array $current_values Current values of the job.
	 * @param array $new_values New values of the job.
	 */
	private function post_has_changed( $current_values, $new_values ) {
		foreach ( $current_values as $key => $value ) {
			if ( $value !== $new_values[ $key ] ) {
				return true;
			}
		}
		return false;
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
	 * Check if we should notify wpjobmanager.com of a change.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function should_notify( $post_id ) {
		if ( ! $this->is_promoted_job( $post_id ) ) {
			return false;
		}
		if ( ! $this->get_site_id() ) {
			return false;
		}
		return true;
	}

	/**
	 * Notify wpjobmanager.com when a Job Listing is trashed.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function promoted_jobs_trash_post( $post_id ) {
		if ( ! $this->should_notify( $post_id ) ) {
			return;
		}
		$this->notify_change( $post_id );
	}

	/**
	 * Notify wpjobmanager.com when a Job Listing is updated.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $values Values.
	 * @return void
	 */
	public function promoted_jobs_save_post( $post_id, $values ) {
		if ( ! $this->should_notify( $post_id ) ) {
			return;
		}
		if ( $this->post_has_changed( $this->get_meta_fields( $post_id ), $this->map_data_fields( $values ) ) ) {
			$this->notify_change( $post_id );
		}
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
