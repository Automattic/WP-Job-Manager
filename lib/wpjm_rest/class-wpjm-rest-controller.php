<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // End if().

class WPJM_REST_Controller extends WP_REST_Controller {
	const HTTP_CREATED     = 201;
	const HTTP_SUCCESS     = 200;
	const HTTP_BAD_REQUEST = 400;
	const HTTP_NOT_FOUND   = 404;

	/**
	 * @var WPJM_REST_Controller_Bundle the bundle this belongs to
	 */
	protected $controller_bundle;
	/**
	 * @var string the endpoint base
	 */
	protected $base = null;

	/**
	 * @var null|WPJM_REST_Environment optional, an enviromnent
	 */
	protected $environment = null;

	/**
	 * WPJM_REST_Rest_Api_Controller constructor.
	 *
	 * @param $controller_bundle WPJM_REST_Controller_Bundle
	 * @param null|WPJM_REST_Environment                             $environment
	 * @throws WPJM_REST_Exception
	 */
	public function __construct( $controller_bundle = null, $environment = null ) {
		$this->controller_bundle = $controller_bundle;
		if ( empty( $this->base ) ) {
			throw new WPJM_REST_Exception( 'Need to put a string with a backslash in $base' );
		}
		$this->set_environment( $environment );
	}

	public function set_controller_bundle( $controller_bundle ) {
		$this->controller_bundle = $controller_bundle;
	}

	/**
	 * @param WPJM_REST_Environment|null $environment
	 * @return WPJM_REST_Controller
	 */
	public function set_environment( $environment ) {
		$this->environment = $environment;
		return $this;
	}

	public function register() {
		throw new WPJM_REST_Exception( 'override me' );
	}

	protected function succeed( $data ) {
		return new WP_REST_Response( $data, self::HTTP_SUCCESS );
	}

	protected function created( $data ) {
		return new WP_REST_Response( $data, self::HTTP_CREATED );
	}

	protected function fail_with( $data ) {
		return new WP_REST_Response( $data, self::HTTP_BAD_REQUEST );
	}

	protected function not_found( $message ) {
		return $this->respond( new WP_REST_Response( array(
			'message' => $message,
		), self::HTTP_NOT_FOUND ) );
	}

	public function respond( $thing ) {
		return rest_ensure_response( $thing );
	}
}
