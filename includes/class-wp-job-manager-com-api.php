<?php
/**
 * File containing the class \WP_Job_Manager\WP_Job_Manager_Com_API.
 *
 * @package wp-job-manager
 * @since   1.40.0
 */

namespace WP_Job_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * \WP_Job_Manager\WP_Job_Manager_Com_API
 */
class WP_Job_Manager_Com_API {

	const API_BASE_URL = 'https://wpjobmanager.com/wp-json/';

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Get the main instance.
	 *
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get notices.
	 *
	 * @param int|null $max_age The max age (seconds) of the source data.
	 *
	 * @return array
	 */
	public function get_notices( $max_age = null ) {
		$response = $this->request(
			'wpjmcom-notices/v1/notices',
			[
				'version' => JOB_MANAGER_VERSION,
				'lang'    => determine_locale(),
			],
			DAY_IN_SECONDS,
			$max_age
		);

		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			return [];
		}

		return $response['notices'];
	}

	/**
	 * Make a GET request to the API and caches content.
	 *
	 * @param string   $path      The request path.
	 * @param array    $args      The request GET parameters.
	 * @param int      $cache_ttl The cache TTL.
	 * @param int|null $max_age   The max age (seconds) of the source data. If older than this the data will be fetched again.
	 * @return array|\WP_Error
	 */
	protected function request( $path, $args, $cache_ttl = DAY_IN_SECONDS, $max_age = null ) {
		$transient_key = $this->get_request_transient_key( $path, $args );
		$cached_data   = get_transient( $transient_key );

		// If the cached data is too old, ignore it.
		if ( $max_age && is_array( $cached_data ) ) {
			$age = time() - ( $cached_data['_fetched'] ?? 0 );
			if ( $age > $max_age ) {
				$cached_data = false;
			}
		}

		// If no cached data or malformed, fetch it.
		if ( false === $cached_data || ! is_array( $cached_data ) || ! isset( $cached_data['_remote_data'] ) ) {
			$response = wp_safe_remote_get(
				add_query_arg(
					$args,
					$this->get_api_base_url() . $path
				),
				[
					'timeout' => 10,
					'headers' => [
						'Accept'       => 'application/json',
						'Content-Type' => 'application/json',
					],
				]
			);

			// If WP_Error return without caching.
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			// If HTTP error return without caching.
			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				$error_code = wp_remote_retrieve_response_code( $response );
				return new \WP_Error( $error_code, 'Error code: ' . $error_code );
			}

			$remote_data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! is_null( $remote_data ) ) {
				$cached_data = [
					'_fetched'     => time(),
					'_remote_data' => $remote_data,
				];
				set_transient( $transient_key, $cached_data, $cache_ttl );
			}

			return $remote_data;
		}

		return $cached_data['_remote_data'];
	}

	/**
	 * Returns the API base URL.
	 *
	 * @return string
	 */
	private function get_api_base_url() {
		// For backwards compatibility we check and use the JOB_MANAGER_DEV_API_BASE_URL constant.
		if (
			defined( 'JOB_MANAGER_VERSION' )
			&& defined( 'JOB_MANAGER_DEV_API_BASE_URL' )
			&& '-dev' === substr( JOB_MANAGER_VERSION, -4 )
		) {
			return JOB_MANAGER_DEV_API_BASE_URL;
		}

		/**
		 * Filters the wpjobmanager.com API URL.
		 *
		 * @since 1.40.0
		 *
		 * @param string $api_url The API url.
		 *
		 * @return string The API url.
		 */
		return apply_filters( 'wpjm_wpjmcom_api_url', self::API_BASE_URL );
	}

	/**
	 * Generates the transient key for a given request.
	 *
	 * @param string $path The request path.
	 * @param array  $args The request parameters.
	 *
	 * @return string The transient key.
	 */
	private function get_request_transient_key( string $path, array $args ) {
		$data = [
			'path' => $path,
			'args' => $args,
		];
		return 'wpjmcom_' . md5( wp_json_encode( $data ) );
	}
}
