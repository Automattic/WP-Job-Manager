<?php
/**
 * @group rest
 */
class WP_Test_WP_Job_Manager_Job_Categories_Test extends WPJM_REST_TestCase {

	function test_get_success_when_guest() {
		$this->logout();
		$response = $this->get( '/wp/v2/job-categories' );
		$this->assertResponseStatus( $response, 200 );
	}

	function test_post_fail_when_guest() {
		$this->logout();
		$response = $this->post( '/wp/v2/job-categories', array(
			'name' => 'REST Test' . microtime( true ),
		) );
		$this->assertResponseStatus( $response, 401 );
	}

	function test_post_success_when_admin() {
		$this->login_as_admin();
		$response = $this->post( '/wp/v2/job-categories', array(
			'name' => 'REST Test' . microtime( true ),
		) );
		$this->assertResponseStatus( $response, 201 );
	}

	function test_wp_v2_has_job_categories_route() {
		$this->login_as( $this->default_user_id );
		$response = $this->get( '/wp/v2' );
		$this->assertResponseStatus( $response, 200 );
		$data = $response->get_data();

		$routes =  array_keys( $data['routes'] );
		$this->assertTrue( in_array( '/wp/v2/job-categories', $routes ) );
	}

	function test_get_job_categories_success() {
		$this->login_as( $this->default_user_id );
		$response = $this->get( '/wp/v2/job-categories' );
		$this->assertResponseStatus( $response, 200 );
	}
}
