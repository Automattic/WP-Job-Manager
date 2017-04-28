<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Handles API requests for WP Job Manager.
 *
 * @package wp-job-manager
 * @since 1.0.0
 */
class WP_Job_Manager_API {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'query_vars', array( $this, 'add_query_vars'), 0 );
		add_action( 'parse_request', array( $this, 'api_requests'), 0 );
	}

	/**
	 * Adds query vars used in API calls.
	 *
	 * @param array $vars the query vars
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'job-manager-api';
		return $vars;
	}

	/**
	 * Adds endpoint for API requests.
	 */
	public function add_endpoint() {
		add_rewrite_endpoint( 'job-manager-api', EP_ALL );
	}

	/**
	 * API request - Trigger any API requests (handy for third party plugins/gateways).
	 */
	public function api_requests() {
		global $wp;

		if ( ! empty( $_GET['job-manager-api'] ) )
			$wp->query_vars['job-manager-api'] = $_GET['job-manager-api'];

		if ( ! empty( $wp->query_vars['job-manager-api'] ) ) {
			// Buffer, we won't want any output here
			ob_start();

			// Get API trigger
			$api = strtolower( esc_attr( $wp->query_vars['job-manager-api'] ) );

			// Load class if exists
			if ( has_action( 'job_manager_api_' . $api ) && class_exists( $api ) )
				$api_class = new $api();

			// Trigger actions
			do_action( 'job_manager_api_' . $api );

			// Done, clear buffer and exit
			ob_end_clean();
			die('1');
		}
	}
}

new WP_Job_Manager_API();
