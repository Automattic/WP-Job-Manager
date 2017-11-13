<?php

class WP_Test_WP_Job_Manager_REST_API extends WPJM_REST_TestCase {

	/**
	 * @group rest
	 */
	function test_wpjm_root_success() {
		$response = $this->get( '/wpjm/v1' );
		$this->assertResponseStatus( $response, 200 );
	}

	/**
	 * @group rest
	 */
	function test_wpjm_root_methods() {
		$response = $this->get( '/wpjm/v1' );
		$this->assertResponseStatus( $response, 200 );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'routes', $data );
		$this->assertArrayHasKey( '/wpjm/v1/settings', $data['routes'] );
	}

	/**
	 * @group rest
	 */
	function test_is_rest_api_enabled_defaults_to_option_value() {
		$this->assertTrue( WP_Job_Manager_REST_API::is_rest_api_enabled() );
	}

	/**
	 * @group rest
	 */
	function test_is_rest_api_enabled_controlled_via_filter() {
		add_filter( 'job_manager_rest_api_enabled', '__return_false' );
		$this->assertFalse( WP_Job_Manager_REST_API::is_rest_api_enabled() );
		remove_filter( 'job_manager_rest_api_enabled', '__return_false' );
	}
}
