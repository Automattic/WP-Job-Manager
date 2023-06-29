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

	/**
	 * The URL to notify. Sending this notification will trigger a sync of the site's jobs.
	 *
	 * @var string
	 */
	const NOTIFICATION_URL = 'https://wpjobmanager.com/wp-json/promoted-jobs/v1/site/{site_id}/update';

	/**
	 * The fields we are watching for changes.
	 *
	 * @var array
	 */
	private $watched_fields;

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
		$this->watched_fields = [
			'post' => [ 'post_title', 'post_content', 'post_status' ],
			'meta' => [ '_company_name', '_application' ],
		];
		$this->init_options();
		add_action( 'job_manager_promoted_jobs_notification', [ $this, 'run_scheduled_promoted_jobs_notification' ] );

		add_action( 'post_updated', [ $this, 'post_updated' ], 10, 3 );
		add_action( 'update_postmeta', [ $this, 'meta_updated' ], 10, 4 );
	}

	/**
	 * Checks if we should send a notification to wpjobmanager.com before a post is updated.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post_after Post object after the update.
	 * @param WP_Post $post_before Post object before the update.
	 */
	public function post_updated( $post_id, $post_after, $post_before ) {
		if ( ! $this->should_notify( $post_id ) ) {
			return;
		}
		$keys        = $this->watched_fields['post'];
		$post_before = array_filter(
			(array) $post_before,
			function( $key ) use ( $keys ) {
				return in_array( $key, $keys, true );
			},
			ARRAY_FILTER_USE_KEY
		);
		$post_after  = array_filter(
			(array) $post_after,
			function( $key ) use ( $keys ) {
				return in_array( $key, $keys, true );
			},
			ARRAY_FILTER_USE_KEY
		);
		if ( $this->post_has_changed( $post_before, $post_after ) ) {
			$this->send_notification();
		}
	}

	/**
	 * Checks if we should send a notification to wpjobmanager.com after a post meta is updated.
	 *
	 * @param int    $meta_id Meta ID.
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public function meta_updated( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( ! $this->should_notify( $post_id ) ) {
			return;
		}
		if ( in_array( $meta_key, $this->watched_fields['meta'], true ) ) {
			$current_value = get_post_meta( $post_id, $meta_key, true );
			if ( $current_value !== $meta_value ) {
				$this->send_notification();
			}
		}
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
					'retries'            => 0,
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
		if ( 'job_listing' !== get_post_type( $post_id ) ) {
			return false;
		}
		if ( ! $this->is_promoted_job( $post_id ) ) {
			return false;
		}
		return true;
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
	 * Schedule a cron job to run the notification task if API call failed, unschedule if it succeeds.
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
			++$notification_meta['retries'];

			$this->schedule_cron_job();
		} else {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( 200 !== $body['data']['status'] ) {
				$notification_meta['last_error_message'] = $body['code'] . ': ' . $body['message'];
				$notification_meta['should_notify_jobs'] = true;
				++$notification_meta['retries'];

				$this->schedule_cron_job();
			} else {
				$notification_meta['last_error_message'] = '';
				$notification_meta['should_notify_jobs'] = false;
				$notification_meta['retries']            = 0;

				$this->unschedule_cron_job();
			}
		}

		update_option( 'job_manager_promoted_jobs_notification', $notification_meta );
	}

	/**
	 * Schedule a cron job to run the notification task.
	 *
	 * @access private
	 * @return void
	 */
	public function run_scheduled_promoted_jobs_notification() {
		$notification_meta = get_option( 'job_manager_promoted_jobs_notification' );
		if ( $notification_meta['should_notify_jobs'] && $notification_meta['retries'] < 3 ) {
			$this->send_notification();
		}
		if ( $notification_meta['retries'] >= 3 ) {
			$this->unschedule_cron_job();
			$notification_meta['retries']            = 0;
			$notification_meta['should_notify_jobs'] = false;
			update_option( 'job_manager_promoted_jobs_notification', $notification_meta );
		}
	}

	/**
	 * Schedule a cron job to run the notification task.
	 *
	 * @return void
	 */
	private function schedule_cron_job() {
		if ( ! wp_next_scheduled( 'job_manager_promoted_jobs_notification' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'job_manager_promoted_jobs_notification' );
		}
	}

	/**
	 * Unschedule a cron job to run the notification task.
	 *
	 * @return void
	 */
	private function unschedule_cron_job() {
		$timestamp = wp_next_scheduled( 'job_manager_promoted_jobs_notification' );
		wp_unschedule_event( $timestamp, 'job_manager_promoted_jobs_notification' );
	}
}

WP_Job_Manager_Promoted_Jobs_Notifications::instance();
