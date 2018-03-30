<?php

class WP_Test_WP_Job_Manager_Job_Categories_Test extends WPJM_REST_TestCase {

	/**
	 * @group rest
	 */
	function test_wp_v2_has_job_categories_route() {
		$this->login_as( $this->default_user_id );
		$response = $this->get( '/wp/v2' );
		$this->assertResponseStatus( $response, 200 );
		$data = $response->get_data();

		$routes =  array_keys( $data['routes'] );
		$this->assertTrue( in_array( '/wp/v2/job-categories', $routes ) );
	}

	/**
	 * @group rest
	 */
	function test_get_job_categories_success() {
		$this->login_as( $this->default_user_id );
		$response = $this->get( '/wp/v2/job-categories' );
		$this->assertResponseStatus( $response, 200 );
	}
}
