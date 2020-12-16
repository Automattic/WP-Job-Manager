<?php
/**
 * File containing the class WP_Job_Manager_Helper_API.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Helper_API
 */
class WP_Job_Manager_Helper_API {

	const API_BASE_URL = 'https://wpjobmanager.com/';

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.29.0
	 */
	private static $instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.29.0
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Sends and receives data to and from the server API
	 *
	 * @param array|string $args
	 * @return object|bool $response.
	 */
	public function plugin_update_check( $args ) {
		$args            = wp_parse_args( $args );
		$args['wc-api']  = 'wp_plugin_licencing_update_api';
		$args['request'] = 'pluginupdatecheck';
		return $this->request( $args );
	}

	/**
	 * Sends and receives data to and from the server API
	 *
	 * @param array|string $args
	 * @return object $response.
	 */
	public function plugin_information( $args ) {
		$args            = wp_parse_args( $args );
		$args['wc-api']  = 'wp_plugin_licencing_update_api';
		$args['request'] = 'plugininformation';
		return $this->request( $args );
	}

	/**
	 * Attempt to activate a plugin licence.
	 *
	 * @param array|string $args
	 * @return boolean|string JSON response or false if failed.
	 */
	public function activate( $args ) {
		$args            = wp_parse_args( $args );
		$args['wc-api']  = 'wp_plugin_licencing_activation_api';
		$args['request'] = 'activate';
		$response        = $this->request( $args, true );
		if ( false === $response ) {
			return false;
		}
		return $response;
	}

	/**
	 * Attempt to deactivate a plugin licence.
	 *
	 * @param array|string $args
	 * @return boolean|string JSON response or false if failed.
	 */
	public function deactivate( $args ) {
		$args            = wp_parse_args( $args );
		$args['wc-api']  = 'wp_plugin_licencing_activation_api';
		$args['request'] = 'deactivate';
		$response        = $this->request( $args, false );
		if ( false === $response ) {
			return false;
		}
		return $response;
	}

	/**
	 * Make a licence helper API request.
	 *
	 * @param array $args
	 * @param bool  $return_error
	 *
	 * @return array|bool|mixed|object
	 */
	protected function request( $args, $return_error = false ) {
		$defaults = [
			'instance'       => $this->get_site_url(),
			'plugin_name'    => '',
			'version'        => '',
			'api_product_id' => '',
			'licence_key'    => '',
			'email'          => '',
		];

		$args    = wp_parse_args( $args, $defaults );
		$request = wp_safe_remote_get(
			$this->get_api_base_url() . '?' . http_build_query( $args, '', '&' ),
			[
				'timeout' => 10,
				'headers' => [
					'Accept' => 'application/json',
				],
			]
		);

		if ( is_wp_error( $request ) || 200 !== wp_remote_retrieve_response_code( $request ) ) {
			if ( $return_error ) {
				if ( is_wp_error( $request ) ) {
					return [
						'error_code' => $request->get_error_code(),
						'error'      => $request->get_error_message(),
					];
				}
				return [
					'error_code' => wp_remote_retrieve_response_code( $request ),
					'error'      => 'Error code: ' . wp_remote_retrieve_response_code( $request ),
				];
			}
			return false;
		}

		$response = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( is_array( $response ) ) {
			return $response;
		}

		return false;
	}

	/**
	 * Returns the site URL that is MU safe.
	 *
	 * @return string
	 */
	private function get_site_url() {
		if ( is_multisite() || is_network_admin() ) {
			return network_site_url();
		}
		return site_url();
	}

	/**
	 * Returns the API base URL.
	 *
	 * @return string
	 */
	private function get_api_base_url() {
		if (
			defined( 'JOB_MANAGER_VERSION' )
			&& defined( 'JOB_MANAGER_DEV_API_BASE_URL' )
			&& '-dev' === substr( JOB_MANAGER_VERSION, -4 )
		) {
			return JOB_MANAGER_DEV_API_BASE_URL;
		}
		return self::API_BASE_URL;
	}
}
