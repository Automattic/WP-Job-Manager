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
	 * Checks if there is an update for the plugin using the WPJobManager.com API.
	 *
	 * @param array $args The arguments to pass to the endpoint.
	 * @return array|false The response, or false if the request failed.
	 */
	public function plugin_update_check( $args ) {
		$args            = wp_parse_args( $args );
		$args['wc-api']  = 'wp_plugin_licencing_update_api';
		$args['request'] = 'pluginupdatecheck';
		return $this->request( $args );
	}

	/**
	 * Sends and receives data related to plugin information from the WPJobManager.com API.
	 *
	 * @param array $args  The arguments to pass to the endpoint.
	 * @return array|false The response, or false if the request failed.
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
	 * @param array $args The arguments to pass to the API.
	 * @return array|false JSON response or false if failed.
	 */
	public function activate( $args ) {
		$args         = wp_parse_args( $args );
		$product_slug = $args['api_product_id'];
		$response     = $this->bulk_activate( $args['licence_key'], [ $product_slug ] );
		if ( false === $response || ! array_key_exists( $product_slug, $response ) ) {
			return false;
		}
		$item = $response[ $product_slug ];
		// Add keys used previously for backwards compatibility.
		if ( $item['success'] ) {
			return [
				'success'   => true,
				'activated' => true,
				'remaining' => $item['remaining_activations'],
			];
		}
		return [
			'error'      => $item['error_message'],
			'error_code' => $item['error_code'],
		];
	}

	/**
	 * Attempt to activate multiple WPJM products with a single licence key.
	 *
	 * @param string $licence_key The licence key to activate.
	 * @param array  $product_slugs The slugs of the products to activate.
	 * @return array|false The response, or false if the request failed.
	 */
	public function bulk_activate( $licence_key, $product_slugs ) {
		return $this->request_endpoint(
			'wp-json/wpjmcom-licensing/v1/activate',
			[
				'method' => 'POST',
				'body'   => wp_json_encode(
					[
						'site_url'      => $this->get_site_url(),
						'license_key'   => $licence_key,
						'product_slugs' => $product_slugs,
					]
				),
			]
		);
	}

	/**
	 * Attempt to deactivate a plugin licence.
	 *
	 * @param array|string $args
	 * @return array|false JSON response or false if failed.
	 */
	public function deactivate( $args ) {
		$args            = wp_parse_args( $args );
		$args['wc-api']  = 'wp_plugin_licencing_activation_api';
		$args['request'] = 'deactivate';
		return $this->request( $args, false );
	}

	/**
	 * Make a licence helper API request.
	 *
	 * @param array $args The arguments to pass to the API.
	 * @param bool  $return_error If we should return the error details or not.
	 *
	 * @return array|false The response as an array, or false if the request failed.
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

		$args     = wp_parse_args( $args, $defaults );
		$response = wp_safe_remote_get(
			$this->get_api_base_url() . '?' . http_build_query( $args, '', '&' ),
			[
				'timeout' => 10,
				'headers' => [
					'Accept' => 'application/json',
				],
			]
		);

		return $this->decode_response( $response, $return_error );
	}

	/**
	 * Make a licence helper API request to a WP REST API Endpoint.
	 *
	 * @param string $endpoint The endpoint to make the API request to.
	 * @param array  $args The arguments to pass to the request.
	 * @param bool   $return_error If we should return the error details or not.
	 *
	 * @return array|false The response, an error if $return_error is true, or false if the request failed and $return_error is false.
	 */
	protected function request_endpoint( $endpoint, $args, $return_error = false ) {
		$defaults = [
			'timeout' => 10,
			'headers' => [
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
			],
		];
		$args     = wp_parse_args( $args, $defaults );

		$response = wp_safe_remote_request(
			$this->get_api_base_url() . $endpoint,
			$args
		);

		return $this->decode_response( $response, $return_error );
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

	/**
	 * Decode the response from the WPJobManager.com API.
	 *
	 * @param array|WP_Error $response The response, or an error.
	 * @param bool           $return_error If we should return the detailed error or not.
	 * @return array|false The response as an array, or false if the request failed and $return_error is false.
	 */
	protected function decode_response( $response, $return_error ) {
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			if ( $return_error ) {
				if ( is_wp_error( $response ) ) {
					return [
						'error_code' => $response->get_error_code(),
						'error'      => $response->get_error_message(),
					];
				}
				return [
					'error_code' => wp_remote_retrieve_response_code( $response ),
					'error'      => 'Error code: ' . wp_remote_retrieve_response_code( $response ),
				];
			}
			return false;
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( is_array( $result ) ) {
			return $result;
		}

		return false;
	}
}
