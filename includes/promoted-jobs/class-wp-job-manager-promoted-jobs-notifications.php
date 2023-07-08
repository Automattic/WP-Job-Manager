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
	const NOTIFICATION_URL = 'https://wpjobmanager.com/wp-json/promoted-jobs/v1/site/update';

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
	 * Get the notification URL.
	 *
	 * @return string
	 */
	private function get_notification_url() {
		return add_query_arg(
			[
				'site_url' => site_url(),
				'feed_url' => rest_url( 'wpjm-internal/v1/promoted-jobs' ),
			],
			self::NOTIFICATION_URL
		);
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
		return '1' === get_post_meta( $post_id, '_promoted', true );
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
	 * @access private
	 * @param int $retry Number of times this job has been retried.
	 * @return void
	 */
	public function send_notification( $retry = 0 ) {
		$response = wp_safe_remote_post( $this->get_notification_url() );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			if ( ! $this->has_scheduled_retry() && $retry < self::NUMBER_OF_RETRIES ) {
				// Retry in RETRY_INTERVAL seconds.
				wp_schedule_single_event(
					time() + self::RETRY_INTERVAL,
					self::RETRY_JOB_NAME,
					[ $retry + 1 ]
				);
			}
		} else {
			// Clear any scheduled retries.
			wp_unschedule_hook( self::RETRY_JOB_NAME );
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
