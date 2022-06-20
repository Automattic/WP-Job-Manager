<?php
/**
 * Base class for testing Controllers
 *
 * @package wp-job-manager/tests
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
	 * A REST Server.
	 *
	 * @var WP_REST_Server
	 */
	private $rest_server;

	public static function setUpBeforeClass() {
		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server;

		if ( ! isset( $wp_rest_server ) ) {
			$wp_rest_server = new WP_REST_Server();
			do_action( 'rest_api_init' );
		}

		parent::setUpBeforeClass();
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
	public function setUp() {
		/** @var WP_REST_Server $wp_rest_server */
		global $wp_rest_server, $wp_version;
		parent::setUp();

		// Only post-4.9.1 versions of WordPress will correctly return 401 for unauthorized requests.
		// See https://core.trac.wordpress.org/changeset/42421.
		if ( version_compare( $wp_version, '4.9.1', '<=' ) ) {
			$this->markTestSkipped( 'Older versions of WordPress have REST API authorization issues.' );
			return;
		}

		$this->reregister_post_type();
		$this->disable_manage_job_listings_cap();

		WP_Job_Manager_REST_API::init();

		// Ensure the role gets created.
		WP_Job_Manager_Install::install();
		wp_roles()->init_roles();
		wp_cache_flush();

		$this->default_user_id = get_current_user_id();
		$this->login_as_admin();
		$this->rest_server = $wp_rest_server;
	}

	/**
	 * Expect a clas Exists.
	 *
	 * @param string $cls Class Name.
	 */
	protected function assertClassExists( $cls ) {
		$this->assertNotFalse( class_exists( $cls ), $cls . ': should exist' );
	}

	/**
	 * Expect a model is valid
	 *
	 * @param WP_Job_Manager_REST_Interfaces_Model $model The model.
	 *
	 * @throws WP_Job_Manager_REST_Exception
	 */
	protected function assertModelValid( $model ) {
		$this->assertTrue( $model->validate() );
	}

	/**
	 * Ensure we got a certain response code
	 *
	 * @param WP_REST_Response $response The Response.
	 * @param int              $status_code Expected status code.
	 */
	protected function assertResponseStatus( $response, $status_code ) {
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertEquals( $status_code, $response->get_status() );
	}

	/**
	 * Have WP_REST_Server Dispatch an HTTP request
	 *
	 * @param string $endpoint The Endpoint.
	 * @param string $method Http method.
	 * @param array  $args_or_body Any Data/Args.
	 * @return WP_REST_Response
	 */
	protected function request( $endpoint, $method, $args_or_body = [] ) {
		$this->beforeRequest();

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
	protected function get( $endpoint, $args = [] ) {
		return $this->request( $endpoint, 'GET', $args );
	}

	/**
	 * Have WP_REST_Server Dispatch a POST HTTP request
	 *
	 * @param string $endpoint The Endpoint.
	 * @param array  $args Any Data/Args.
	 * @return WP_REST_Response
	 */
	protected function post( $endpoint, $args = [] ) {
		return $this->request( $endpoint, 'POST', $args );
	}

	/**
	 * Have WP_REST_Server Dispatch a PUT HTTP request
	 *
	 * @param string $endpoint The Endpoint.
	 * @param array  $args Any Data/Args.
	 * @return WP_REST_Response
	 */
	protected function put( $endpoint, $args = [] ) {
		return $this->request( $endpoint, 'PUT', $args );
	}

	/**
	 * Have WP_REST_Server Dispatch a DELETE HTTP request
	 *
	 * @param string $endpoint The Endpoint.
	 * @param array  $args Any Data/Args.
	 * @return WP_REST_Response
	 */
	protected function delete( $endpoint, $args = [] ) {
		return $this->request( $endpoint, 'DELETE', $args );
	}

	/**
	 * Runs before requests.
	 */
	protected function beforeRequest() {
		// Overload.
	}
}

