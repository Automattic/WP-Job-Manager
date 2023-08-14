<?php
/**
 * File containing the class WP_Job_Manager_Promoted_Jobs_Status_Handler.
 *
 * @package wp-job-manager
 */

/**
 * Handles functionality related to the Promoted Jobs Status Update.
 *
 * @since $$next-version$$
 */
class WP_Job_Manager_Promoted_Jobs_Status_Handler {

	/**
	 * The name of the cron hook for updating the promoted job status.
	 */
	const CRON_HOOK = 'job_manager_promoted_jobs_status_update';

	/**
	 * The name of the option that stores the last time the cron job was executed.
	 */
	const LAST_EXECUTION_OPTION_KEY = self::CRON_HOOK . '_last_execution';

	/**
	 * The name of the option that stores whether the site used promoted jobs or not.
	 */
	const USED_PROMOTED_JOBS_OPTION_KEY = 'job_manager_used_promoted_jobs';

	/**
	 * Time interval (in seconds) between update fetches from the site.
	 */
	private const UPDATE_INTERVAL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Constructor.
	 */
	public function __construct() {}

	/**
	 * Initialize the status handler, sets up the cron job and hooks for fetching updates.
	 *
	 * @since $$next-version$$
	 */
	public function init() {
		add_action( self::CRON_HOOK, [ $this, 'fetch_updates' ] );
	}

	/**
	 * Fetches updates for promoted jobs from the site feed.
	 * Updates the promotion status of the jobs accordingly.
	 */
	public function fetch_updates() {
		if ( ! get_option( self::USED_PROMOTED_JOBS_OPTION_KEY, false ) ) {
			// We don't fetch updates if the site doesn't have promoted jobs.
			return;
		}
		$last_execution_time = get_option( self::LAST_EXECUTION_OPTION_KEY, 0 );
		$current_time        = time();

		if ( $current_time - $last_execution_time < self::UPDATE_INTERVAL ) {
			// We block the execution if the last execution was less than self::UPDATE_INTERVAL seconds ago.
			return;
		}

		// We always update the last execution time, even if the request fails.
		update_option( self::LAST_EXECUTION_OPTION_KEY, $current_time );

		$jobs     = $this->request_site_feed();
		$statuses = wp_list_pluck( $jobs, 'wpjm_status', 'wpjm_id' );
		foreach ( $statuses as $job_id => $job_status ) {
			WP_Job_Manager_Promoted_Jobs::update_promotion( $job_id, '1' === $job_status );
		}
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

}
