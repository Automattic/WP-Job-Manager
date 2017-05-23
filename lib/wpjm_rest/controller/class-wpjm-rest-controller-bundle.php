<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // End if().

/**
 * Class Mixtape_Rest_Api_Controller_Bundle
 * Represents a collection of Mixtape_Rest_Api_Controller instances, sharing a common prefix
 *
 * @package rest-api
 */
class WPJM_REST_Controller_Bundle implements WPJM_REST_Interfaces_Controller_Bundle {

	/**
	 * @var string|null the prefix of this bundle
	 */
	protected $bundle_prefix = null;
	/**
	 * @var array collection of Mixtape_Rest_Api_Controller subclasses
	 */
	protected $endpoints = array();

	function start() {
		WPJM_REST_Expect::that( null !== $this->bundle_prefix, 'api_prefix should be defined' );
		add_action( 'rest_api_init', array( $this, 'register' ) );
		return $this;
	}

	/**
	 * bootstrap registry
	 * register all endpoints
	 */
	function register() {
		/**
		 * add/remove endpoints. Useful for extensions
		 *
		 * @param $endpoints array an array of Mixtape_Rest_Api_Controller
		 * @param $bundle WPJM_REST_Controller_Bundle the bundle instance
		 * @return array
		 */
		$this->endpoints = (array) apply_filters(
			'mixtape_rest_api_controller_bundle_get_endpoints',
			$this->get_endpoints(),
			$this
		);

		foreach ( $this->endpoints as $endpoint ) {
			$endpoint->register( $this );
		}
	}

	function get_endpoints() {
		return array();
	}

	function get_bundle_prefix() {
		return $this->bundle_prefix;
	}
}
