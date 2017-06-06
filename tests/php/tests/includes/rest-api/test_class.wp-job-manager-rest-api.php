<?php

class WP_Test_WP_Job_Manager_REST_API extends WPJM_REST_TestCase {

	function test_wpjm_root_success() {
		$response = $this->get( '/wpjm/v1' );
		$this->assertResponseStatus( $response, 200 );
	}

	function test_wpjm_root_methods() {
		$response = $this->get( '/wpjm/v1' );
		$this->assertResponseStatus( $response, 200 );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'routes', $data );
		$this->assertArrayHasKey( '/wpjm/v1/settings', $data['routes'] );
	}
}