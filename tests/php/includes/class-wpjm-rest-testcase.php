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

	/**
	 * Admin ID
	 *
	 * @var int
	 */
	protected $admin_id;

	/**
	 * Default User ID
	 *
	 * @var int
	 */
	protected $default_user_id;

	public static function setUpBeforeClass() {
		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;
		if ( ! isset( $wp_rest_server ) ) {
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
	 * @var WP_Job_Manager_REST_Environment
	 */
	private $environment;

	/**
	 * Get Environment
	 *
	 * @return WP_Job_Manager_REST_Environment
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
		$admin = get_user_by( 'email', 'rest_api_admin_user@test.com' );
		if ( false === $admin ){
			$this->admin_id = wp_create_user(
				'rest_api_admin_user',
				'rest_api_admin_user',
				'rest_api_admin_user@test.com' );
			$admin = get_user_by( 'ID', $this->admin_id );
			$admin->set_role( 'administrator' );
		}

		$this->default_user_id = get_current_user_id();
		$this->login_as_admin();
		$this->rest_server = $wp_rest_server;
		$rest_api = WPJM()->rest_api();
		if ( empty( $rest_api) || is_wp_error( $rest_api ) ) {
			$this->markTestSkipped( 'Incompatible php version' );
		}
		$bootstrap = WPJM()->rest_api()->get_bootstrap();
		$this->bootstrap = WPJM()->rest_api()->get_bootstrap();
		$this->environment = $bootstrap->environment();
	}

	function login_as_admin() {
		return $this->login_as( $this->admin_id );
	}

	function login_as( $user_id ) {
		wp_set_current_user( $user_id );
		return $this;
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
	 * @param WP_Job_Manager_REST_Interfaces_Model $model The model.
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
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertEquals( $status_code, $response->get_status() );
	}

	/**
	 * Have WP_REST_Server Dispatch an HTTP request
	 *
	 * @param string $endpoint The Endpoint.
	 * @param string $method Http mehod.
	 * @param array  $args_or_body Any Data/Args.
	 * @return WP_REST_Response
	 */
	function request( $endpoint, $method, $args_or_body = array() ) {
		$request = new WP_REST_Request( $method, $endpoint );
		if ( is_array( $args_or_body ) ) {
			foreach ( $args_or_body as $key => $value ) {
				$request->set_param( $key, $value );
			}
		} else {
			$request->set_body( $args_or_body );
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

