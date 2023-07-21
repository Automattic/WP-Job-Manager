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
	 * The endpoint in WPJMCOM to notify. Sending this notification will trigger a sync of the site's jobs.
	 *
	 * @var string
	 */
	const NOTIFICATION_ENDPOINT = '/wp-json/promoted-jobs/v1/site/update';

	/**
	 * The name of the job that will be scheduled to run if notification fails.
	 *
	 * @var string
	 */
	const RETRY_JOB_NAME = 'job_manager_promoted_jobs_notification';

	/**
	 * The number of retries before we stop trying.
	 *
	 * @var int
	 */
	const NUMBER_OF_RETRIES = 3;

	/**
	 * The interval between retries.
	 *
	 * @var int
	 */
	const RETRY_INTERVAL = 60 * 60 * 24;

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
			'post' => [ 'post_name', 'post_title', 'post_content', 'post_status' ],
			'meta' => [ '_promoted' ],
		];
		add_action( 'post_updated', [ $this, 'post_updated' ], 10, 3 );
		add_action( 'update_postmeta', [ $this, 'meta_updated' ], 10, 4 );
		add_action( 'job_manager_promoted_jobs_notification', [ $this, 'send_notification' ] );
	}

	/**
	 * Checks if we should send a notification to wpjobmanager.com before a post is updated.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post_after Post object after the update.
	 * @param WP_Post $post_before Post object before the update.
	 */
	public function post_updated( $post_id, $post_after, $post_before ) {
		if ( ! WP_Job_Manager_Promoted_Jobs::is_promoted( $post_id ) ) {
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
		if ( ! WP_Job_Manager_Promoted_Jobs::is_promoted( $post_id ) ) {
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
	 * Get the notification URL.
	 *
	 * @return string
	 */
	private function get_notification_url() {
		return WP_Job_Manager_Helper_API::get_wpjmcom_url() . self::NOTIFICATION_ENDPOINT;
	}

	/**
	 * Get the data to send to the notification endpoint.
	 *
	 * @return array The data to send.
	 */
	private function get_notification_data() {
		$site_url = home_url();
		$feed_url = rest_url( '/wpjm-internal/v1/promoted-jobs' );
		$feed_url = substr( $feed_url, strlen( $site_url ) );
		return [
			'site_url' => $site_url,
			'feed_url' => $feed_url,
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
	 * Notify wpjobmanager.com when a Job Listing changes.
	 *
	 * @access private
	 * @param int $retry Number of times this job has been retried.
	 * @return void
	 */
	public function send_notification( $retry = 0 ) {
		// Clear any scheduled retries.
		wp_unschedule_hook( self::RETRY_JOB_NAME );
		$response = wp_safe_remote_post(
			$this->get_notification_url(),
			[
				'body' => $this->get_notification_data(),
			]
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			if ( ! $this->has_scheduled_retry() && $retry < self::NUMBER_OF_RETRIES ) {
				// Retry in RETRY_INTERVAL seconds.
				wp_schedule_single_event(
					time() + self::RETRY_INTERVAL,
					self::RETRY_JOB_NAME,
					[ $retry + 1 ]
				);
			}
		}
	}

	/**
	 * Check if a retry is scheduled.
	 *
	 * @return bool
	 */
	private function has_scheduled_retry() {
		for ( $i = 1; $i <= self::NUMBER_OF_RETRIES; $i++ ) {
			if ( wp_next_scheduled( self::RETRY_JOB_NAME, [ $i ] ) ) {
				return true;
			}
		}

		return false;
	}
}

WP_Job_Manager_Promoted_Jobs_Notifications::instance();
