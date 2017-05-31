<?php
/**
 * Controller Bundle
 *
 * A collection of WPJM_REST_Rest_Api_Controller, sharing a common prefix.
 *
 * @package Mixtape/REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface WPJM_REST_Interfaces_Rest_Api_Controller_Bundle
 */
interface WPJM_REST_Interfaces_Controller_Bundle {

	/**
	 * Register REST Routes
	 *
	 * @return mixed
	 */
	public function register();

	/**
	 * Get all the Endpoints
	 *
	 * @return mixed
	 */
	public function get_endpoints();

	/**
	 * Get the Prefix
	 *
	 * @return string
	 */
	public function get_bundle_prefix();
}
