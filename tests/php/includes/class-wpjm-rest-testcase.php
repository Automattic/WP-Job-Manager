<?php
/**
 * Base class for testing Controllers
 *
 * @package wpjm/tests
 */

/**
 * Class WPJM_REST_TestCase
 */
class WPJM_REST_TestCase extends WPJM_BaseTest {

	public static function setUpBeforeClass() {
		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		if ( !isset( $wp_rest_server ) ) {
			$wp_rest_server = new WP_REST_Server();
			do_action( 'rest_api_init' );
		}
	}
	/**
	 * A REST Server.
	 *
	 * @var WP_REST_Server
	 */
	private $rest_server;

	/**
	 * An Environment
	 *
	 * @var WPJM_REST_Environment
	 */
	private $environment;

	/**
	 * Get Environment
	 *
	 * @return WPJM_REST_Environment
	 */
	protected function environment() {
		return $this->environment;
	}

	/**
	 * Get REST Server
	 *
	 * @return WP_REST_Server
	 */
	protected function rest_server() {
		return $this->rest_server;
	}

	/**
	 * Set this up.
	 */
	function setUp() {
		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		parent::setUp();
		$this->rest_server = $wp_rest_server;
		$this->environment = WPJM()->rest_api()->get_bootstrap()->environment();
	}

	/**
	 * Expect a clas Exists.
	 *
	 * @param string $cls Class Name.
	 */
	function assertClassExists( $cls ) {
		$this->assertNotFalse( class_exists( $cls ), $cls . ': should exist' );
	}

	/**
	 * Expect a model is valid
	 *
	 * @param WPJM_REST_Interfaces_Model $model The model.
	 */
	function assertModelValid( $model ) {
		$this->assertTrue( $model->validate() );
	}

	/**
	 * Ensure we got a certain response code
	 *
	 * @param WP_REST_Response $response The Response.
	 * @param int              $status_code Expected status code.
	 */
	function assertResponseStatus( $response, $status_code ) {
		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( $status_code, $response->get_status() );
	}

	/**
	 * Have WP_REST_Server Dispatch an HTTP request
	 *
	 * @param string $endpoint The Endpoint.
	 * @param string $method Http mehod.
	 * @param array  $args Any Data/Args.
	 * @return WP_REST_Response
	 */
	function request( $endpoint, $method, $args = array() ) {
		$request = new WP_REST_Request( $method, $endpoint );
		foreach ( $args as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $this->rest_server()->dispatch( $request );
	}

	/**
	 * Have WP_REST_Server Dispatch a GET HTTP request
	 *
	 * @param string $endpoint The Endpoint.
	 * @param array  $args Any Data/Args.
	 * @return WP_REST_Response
	 */
	function get( $endpoint, $args = array() ) {
		return $this->request( $endpoint, 'GET', $args );
	}

	/**
	 * Have WP_REST_Server Dispatch a POST HTTP request
	 *
	 * @param string $endpoint The Endpoint.
	 * @param array  $args Any Data/Args.
	 * @return WP_REST_Response
	 */
	function post( $endpoint, $args = array() ) {
		return $this->request( $endpoint, 'POST', $args );
	}

	/**
	 * Have WP_REST_Server Dispatch a PUT HTTP request
	 *
	 * @param string $endpoint The Endpoint.
	 * @param array  $args Any Data/Args.
	 * @return WP_REST_Response
	 */
	function put( $endpoint, $args = array() ) {
		return $this->request( $endpoint, 'PUT', $args );
	}

	/**
	 * Have WP_REST_Server Dispatch a DELETE HTTP request
	 *
	 * @param string $endpoint The Endpoint.
	 * @param array  $args Any Data/Args.
	 * @return WP_REST_Response
	 */
	function delete( $endpoint, $args = array() ) {
		return $this->request( $endpoint, 'DELETE', $args );
	}
}

