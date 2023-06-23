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
	 * The fields we are watching for changes.
	 *
	 * @var array
	 */
	private $meta_fields;

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
		$this->meta_fields = [ 'job_title', 'job_description', 'company_name', 'application', 'job_location' ];
		$this->init_options();
		add_action( 'wp_trash_post', [ $this, 'promoted_job_trashed' ] );
		add_action( 'job_manager_edit_job_listing', [ $this, 'promoted_job_updated' ], 10, 2 );
		add_action( 'job_manager_save_job_listing', [ $this, 'promoted_job_updated_admin' ], 10, 2 );
		add_action( 'job_manager_promoted_jobs_notification', [ $this, 'run_scheduled_promoted_jobs_notification' ] );
	}

	/**
	 * Initialize options.
	 */
	public function init_options() {
		if ( ! get_option( 'job_manager_promoted_jobs_notification' ) ) {
			add_option(
				'job_manager_promoted_jobs_notification',
				[
					'last_run'           => 0,
					'last_error_message' => 0,
					'should_notify_jobs' => false,
				]
			);
		}
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
		$post = get_post( $post_id );
		foreach ( $this->meta_fields as $field ) {
			if ( 'job_description' === $field ) {
				$meta_fields[ $field ] = $post->post_content;
				continue;
			}
			if ( 'job_title' === $field ) {
				$meta_fields[ $field ] = $post->post_title;
				continue;
			}
			$meta_fields[ $field ] = get_post_meta( $post_id, '_' . $field, true );
		}
		return $meta_fields;
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
	 * Extract required job listing meta from the $_POST array.
	 *
	 * @param WP_Post $post Post object.
	 */
	private function get_post_data( $post ) {
		$post_data = [];
		foreach ( $this->meta_fields as $field ) {
			if ( 'job_description' === $field ) {
				$post_data[ $field ] = $post->post_content;
				continue;
			}
			if ( 'job_title' === $field ) {
				$post_data[ $field ] = $post->post_title;
				continue;
			}
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Input sanitized in registered post meta config;
			$post_data[ $field ] = isset( $_POST[ "_$field" ] ) ? wp_unslash( $_POST[ "_$field" ] ) : '';
		}
		return $post_data;
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
	public function promoted_job_trashed( $post_id ) {
		if ( ! $this->should_notify( $post_id ) ) {
			return;
		}
		$this->send_notification();
	}

	/**
	 * Notify wpjobmanager.com when a Job Listing is updated.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $values Values.
	 * @return void
	 */
	public function promoted_job_updated( $post_id, $values ) {
		if ( ! $this->should_notify( $post_id ) ) {
			return;
		}

		if ( $this->post_has_changed( $this->get_meta_fields( $post_id ), $this->map_data_fields( $values ) ) ) {
			$this->send_notification();
		}
	}

	/**
	 * Notify wpjobmanager.com when a Job Listing is updated on admin.
	 * Values are available in the $_POST global, sanitized and validated.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function promoted_job_updated_admin( $post_id, $post ) {
		if ( ! $this->should_notify( $post_id ) ) {
			return;
		}

		if ( $this->post_has_changed( $this->get_meta_fields( $post_id ), $this->get_post_data( $post ) ) ) {
			$this->send_notification();
		}
	}

	/**
	 * Notify wpjobmanager.com when a Job Listing changes.
	 *
	 * @return void
	 */
	private function send_notification() {
		$response = wp_remote_post( $this->get_notification_url() );
		$this->maybe_schedule_rerun( $response );
	}

	/**
	 * Schedule a cron job to run the notification task if API call failed.
	 *
	 * @param WP_Error|array $response Response from wp_remote_post.
	 * @return void
	 */
	public function maybe_schedule_rerun( $response ) {
		$notification_meta             = get_option( 'job_manager_promoted_jobs_notification' );
		$notification_meta['last_run'] = time();

		if ( is_wp_error( $response ) ) {
			$notification_meta['last_error_message'] = $response->get_error_message();
			$notification_meta['should_notify_jobs'] = true;

			if ( ! wp_next_scheduled( 'job_manager_promoted_jobs_notification' ) ) {
				wp_schedule_event( time(), 'twicedaily', 'job_manager_promoted_jobs_notification' );
			}
		} else {
			$notification_meta['should_notify_jobs'] = false;
		}

		update_option( 'job_manager_should_notify_promoted_jobs', $notification_meta );
	}

	/**
	 * Schedule a cron job to run the notification task.
	 *
	 * @return void
	 */
	public function run_scheduled_promoted_jobs_notification() {
		$notification_meta = get_option( 'job_manager_promoted_jobs_notification' );
		if ( $notification_meta['should_notify_jobs'] ) {
			$this->send_notification();
		}
	}
}

WP_Job_Manager_Promoted_Jobs_Notifications::instance();
