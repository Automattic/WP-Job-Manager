<?php
/**
 * @group helper
 * @group helper-api
 */
class WP_Test_WP_Job_Manager_Helper_API extends WPJM_BaseTest {

	public function setUp(): void {
		parent::setUp();
		$this->enable_transport_faker();
		$transport                 = $this->get_request_transport();
		$transport->headers_matter = true;
	}

	public function tearDown(): void {
		parent::tearDown();
		$this->disable_transport_faker();
	}

	/**
	 * Tests the WP_Job_Manager_Helper_API::instance() always returns the same `WP_Job_Manager_Helper_API` instance.
	 *
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper_API::instance
	 */
	public function test_wp_job_manager_api_instance() {
		$instance = WP_Job_Manager_Helper_API::instance();
		// check the class.
		$this->assertInstanceOf( 'WP_Job_Manager_Helper_API', $instance, 'Job Manager Helper API object is instance of WP_Job_Manager_Helper_API class' );

		// check it always returns the same object.
		$this->assertSame( WP_Job_Manager_Helper_API::instance(), $instance, 'WP_Job_Manager_Helper_API::instance() must always return the same object' );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper_API::plugin_information
	 */
	public function test_plugin_information_valid() {
		$base_args = $this->get_base_args();
		$data = [
			'name'         => 'Sample Plugin',
			'slug'         => 'sample-plugin',
			'version'      => '1.0.0',
			'last_updated' => '2023-09-22',
			'author'       => 'John Doe',
			'requires'     => 'WordPress 5.0+',
			'tested'       => 'WordPress 5.8',
			'homepage'     => 'https://www.example.com/sample-plugin',
			'sections'     => array(
				'description' => 'This is a sample plugin for demonstration purposes.',
				'changelog'   => 'Version 1.0.0 - Initial release',
			),
			'download_link' => 'https://www.example.com/sample-plugin/sample-plugin.zip',
		];
		$this->mock_http_request( '/wp-json/wpjmcom-licensing/v1/plugin-information', $data);
		$instance = new WP_Job_Manager_Helper_API();
		$response = $instance->plugin_information( $base_args );

		// If a request was made that we don't expect, `$response` would be false.

		$this->assertInstanceOf(stdClass::class, $response);
		$this->assertEquals($data['name'], $response->name);
		$this->assertEquals($data['slug'], $response->slug);
		$this->assertEquals($data['version'], $response->version);
		$this->assertEquals($data['last_updated'], $response->last_updated);
		$this->assertEquals($data['author'], $response->author);
		$this->assertEquals($data['requires'], $response->requires);
		$this->assertEquals($data['tested'], $response->tested);
		$this->assertEquals($data['homepage'], $response->homepage);

		$this->assertEquals($data['sections']['description'], $response->sections['description']);
		$this->assertEquals($data['sections']['changelog'], $response->sections['changelog']);

		$this->assertEquals($data['download_link'], $response->download_link);

		$expectedPlugin = $data['slug'] . '/' . $data['slug'] . '.php';
		$this->assertEquals($expectedPlugin, $response->plugin);
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper_API::plugin_information
	 */
	public function test_plugin_information_invalid() {
		$base_args = $this->get_base_args();
		$instance  = new WP_Job_Manager_Helper_API();
		$response  = $instance->plugin_information( $base_args );

		$this->assertFalse( $response );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper_API::activate
	 */
	public function test_activate_valid() {
		$base_args = $this->get_base_args();


		$this->mock_http_request( '/wp-json/wpjmcom-licensing/v1/activate',
			[
				$base_args['api_product_id'] => [
					'success' => true,
					'remaining_activations' => -1,
				],
			]
		);
		$instance = new WP_Job_Manager_Helper_API();
		$response = $instance->activate( $base_args );

		// If a request was made that we don't expect, `$response` would be false.
		$this->assertEquals( [
			'activated' => true,
			'success' => true,
			'remaining' => -1
		], $response );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper_API::activate
	 */
	public function test_activate_invalid() {
		$base_args = $this->get_base_args();
		$instance  = new WP_Job_Manager_Helper_API();

		$this->mock_http_request( '/wp-json/wpjmcom-licensing/v1/activate',
			[
				$base_args['api_product_id'] => [
					'success' => false,
					'error_message' => 'some error',
					'error_code' => 101,
				],
			]
		);
		$response  = $instance->activate( $base_args );

		// For activation, we return the error from the request (if there was one).
		$this->assertEquals( [
			'error' => 'some error',
			'error_code' => 101,
		], $response );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper_API::deactivate
	 */
	public function test_deactivate_valid() {
		$base_args = $this->get_base_args();
		$this->mock_http_request( '/wp-json/wpjmcom-licensing/v1/deactivate',
			$this->default_valid_response()
		);
		$instance = new WP_Job_Manager_Helper_API();
		$response = $instance->deactivate( $base_args );

		// If a request was made that we don't expect, `$response` would be false.
		$this->assertEquals( $this->default_valid_response(), $response );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper_API::deactivate
	 */
	public function test_deactivate_invalid() {
		$base_args = $this->get_base_args();
		$this->mock_http_request( '/wp-json/wpjmcom-licensing/v1/deactivate',
			[
				'error_code' => 'license_not_found'
			],
			404
		);
		$instance  = new WP_Job_Manager_Helper_API();
		$response  = $instance->deactivate( $base_args );

		$this->assertFalse( $response );
	}

	/**
	 * Mocks HTTP requests to a specified endpoint with a given response body.
	 *
	 * @param string $endpoint The request URI/path of the endpoint to mock the response for.
	 * @param mixed $body The response body to return for the specified endpoint.
	 * @param int $status The HTTP status code to return for the specified endpoint.
	 *
	 * @return void
	 */
	protected function mock_http_request($endpoint, $body, $status = 200) {
		add_filter('pre_http_request', function($preempt, $args, $url) use ($endpoint, $body, $status) {
			if ($endpoint === wp_parse_url($url, PHP_URL_PATH)) {
				return array(
					'headers' => array(),
					'body' => wp_json_encode($body),
					'response' => array(
						'code' => $status,
						'message' => 200 === $status ? 'OK' : 'Error'
					),
					'cookies' => array(),
				);
			}
			return $preempt;
		}, 10, 3);
	}

	private function get_base_args() {
		return [
			'instance'       => site_url(),
			'plugin_name'    => 'test',
			'version'        => '1.0.0',
			'api_product_id' => 'test',
			'license_key'    => 'abcd',
			'email'          => 'test@local.dev',
		];
	}

	protected function set_expected_response( $test_data ) {
		$transport = $this->get_request_transport();
		if ( ! isset( $test_data['request'] ) ) {
			$test_data['request'] = [];
		}
		if ( ! isset( $test_data['request']['url'] ) ) {
			$test_data['request']['url'] = $this->build_url( $test_data['args'] );
		}
		if ( ! isset( $test_data['request']['headers'] ) ) {
			$test_data['request']['headers'] = [
				'Accept' => 'application/json',
			];
		}
		if ( ! isset( $test_data['response'] ) ) {
			$test_data['response'] = $this->default_valid_response();
		}
		$transport->add_fake_request( $test_data['request'], [ 'body' => $test_data['response'] ] );
	}

	protected function default_valid_response() {
		return [ 'success' => true ];
	}

	protected function default_invalid_response() {
		// Prebaked response in Requests_Transport_Faker.
		return [
			'error_code' => 'http_request_failed',
			'error'      => 'Computer says no',
		];
	}

	protected function build_url( $args ) {
		// The legacy endpoints are temporary. For now, translate `license_key` => `licence_key` at this point.
		$args = $this->updateArrayKey( $args, 'license_key', 'licence_key' );

		return 'https://wpjobmanager.com/?' . http_build_query( $args, '', '&' );
	}

	private function updateArrayKey( $array, $oldKey, $newKey ) {
		if ( array_key_exists( $oldKey, $array ) ) {
			$newArray = array();
			foreach ( $array as $key => $value ) {
				if ( $key === $oldKey ) {
					$newArray[ $newKey ] = $value;
				} else {
					$newArray[ $key ] = $value;
				}
			}
			return $newArray;
		}
		return $array;
	}
}
