<?php
/**
 * File containing the class WP_Job_Manager_Promoted_Jobs_Status_Handler.
 *
 * @package wp-job-manager
 */

/**
 * Handles functionality related to the Promoted Jobs Status Update.
 *
 * @since 1.42.0
 */
class WP_Job_Manager_Promoted_Jobs_Status_Handler {

	/**
	 * The name of the cron hook for updating the promoted job status.
	 */
	const CRON_HOOK = 'job_manager_promoted_jobs_status_update';

	/**
	 * The name of the option that stores the last time the check was made.
	 */
	const LAST_CHECK_OPTION_KEY = self::CRON_HOOK . '_last_check';

	/**
	 * The name of the option that stores the time interval (in seconds) between update fetches from the site
	 * triggered by the webhook.
	 */
	const WEBHOOK_INTERVAL_OPTION_KEY = 'job_manager_promoted_jobs_webhook_interval';

	/**
	 * The name of the option that stores the time interval (in seconds) between update fetches from the site
	 * triggered by the cron.
	 */
	const CRON_INTERVAL_OPTION_KEY = 'job_manager_promoted_jobs_cron_interval';

	/**
	 * Constructor.
	 */
	public function __construct() {}

	/**
	 * Initialize the status handler, sets up the cron job and hooks for fetching updates.
	 *
	 * @since 1.42.0
	 */
	public function init() {
		add_action( self::CRON_HOOK, [ $this, 'fetch_updates' ] );
	}

	/**
	 * Fetches updates for promoted jobs from the site feed.
	 * Updates the promotion status of the jobs accordingly.
	 */
	public function fetch_updates() {
		$current_time = time();
		if ( ! $this->should_update( $current_time ) ) {
			return;
		}

		// We always update the last execution time, even if the request fails.
		update_option( self::LAST_CHECK_OPTION_KEY, $current_time, false );

		$jobs     = $this->request_site_feed();
		$statuses = wp_list_pluck( $jobs, 'wpjm_status', 'wpjm_id' );
		foreach ( $statuses as $job_id => $job_status ) {
			WP_Job_Manager_Promoted_Jobs::update_promotion( $job_id, '1' === $job_status );
		}
	}

	/**
	 * Checks if the update logic should be executed or not.
	 *
	 * @param int $current_time The current time.
	 * @return bool True if the update logic should be executed, false otherwise.
	 */
	private function should_update( $current_time ) {
		$interval = $this->get_current_interval();
		if ( ! $interval ) {
			// If the interval is not set or is zero, we don't update.
			return false;
		}
		$last_execution = (int) get_option( self::LAST_CHECK_OPTION_KEY, 0 );
		return $current_time - $last_execution >= $interval;
	}

	/**
	 * Get the current interval for fetching updates. This is either the interval for the cron or the webhook, depending
	 * on the context of the current request.
	 *
	 * @return int The current interval for fetching updates.
	 */
	private function get_current_interval() {
		$option_name = wp_doing_cron() ? self::CRON_INTERVAL_OPTION_KEY : self::WEBHOOK_INTERVAL_OPTION_KEY;
		return (int) get_option( $option_name, 0 );
	}

	/**
	 * Requests the site feed for promoted jobs.
	 * Retrieves and decodes the response, returning the jobs data as an array.
	 *
	 * @return array An array of promoted jobs.
	 */
	private function request_site_feed() {
		$url      = $this->get_site_feed_url();
		$response = wp_remote_get( $url );
		$this->update_interval( $response, 'X-WPJM-Cron-Interval', self::CRON_INTERVAL_OPTION_KEY );
		$this->update_interval( $response, 'X-WPJM-Webhook-Interval', self::WEBHOOK_INTERVAL_OPTION_KEY );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return [];
		}
		$body = wp_remote_retrieve_body( $response );
		$json = \json_decode( $body, true );
		if ( ! array_key_exists( 'jobs', $json ) ) {
			return [];
		}
		return $json['jobs'];
	}

	/**
	 * Gets the URL for the site feed of promoted jobs in WPJMCOM.
	 *
	 * @return string The site feed URL in WPJMCOM.
	 */
	private function get_site_feed_url() {
		return add_query_arg( 'site', home_url( '', 'https' ), WP_Job_Manager_Helper_API::get_wpjmcom_url() . '/wp-json/promoted-jobs/v1/site/jobs' );
	}

	/**
	 * Updates the interval option with the value from the response header from the feed.
	 *
	 * @param array|\WP_Error $response The HTTP response from the feed.
	 * @param string          $header_name The name of the header to check for the interval value.
	 * @param string          $option_name The name of the option to update with the interval value.
	 * @return void
	 */
	private function update_interval( $response, $header_name, $option_name ) {
		$headers = wp_remote_retrieve_headers( $response );
		if ( isset( $headers[ $header_name ] ) ) {
			$header = $headers [ $header_name ];
			if ( 'false' === $header ) {
				$header = '0';
			} elseif ( ! rest_is_integer( $header ) ) {
				return;
			}
			$value = (int) $header;
			if ( self::WEBHOOK_INTERVAL_OPTION_KEY === $option_name && 0 === $value ) {
				// If the webhook interval is zero, we don't remove the option, to not block webhook updates.
				return;
			}
			if ( 0 === $value ) {
				// If the interval is zero, we remove the option, because it is the default anyway.
				delete_option( $option_name );
			} else {
				update_option( $option_name, $value, false );
			}
		}
	}

	/**
	 * Initialize the default options for the Promoted Jobs Status Handler.
	 *
	 * @return void
	 */
	public static function initialize_defaults() {
		$webhook_interval = get_option( self::WEBHOOK_INTERVAL_OPTION_KEY, 5 * MINUTE_IN_SECONDS );
		update_option( self::LAST_CHECK_OPTION_KEY, time() - $webhook_interval, false );
		add_option( self::WEBHOOK_INTERVAL_OPTION_KEY, $webhook_interval, '', false );
		add_option( self::CRON_INTERVAL_OPTION_KEY, 12 * HOUR_IN_SECONDS, '', false );
	}

}
