<?php

class WP_Test_WP_Job_Manager_Controllers_Status extends WPJM_REST_TestCase {

	/**
	 * @group rest
	 */
	function test_get_succeed_when_user_not_admin() {
		$this->login_as( $this->default_user_id );
		$response = $this->get( '/wpjm/v1/status' );
		$this->assertResponseStatus( $response, 200 );
	}

	/**
	 * @group rest
	 */
	function test_get_index_response() {
		$this->login_as( $this->default_user_id );
		$response = $this->get( '/wpjm/v1/status' );
		$this->assertResponseStatus( $response, 200 );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'run_page_setup', $data );
		$this->assertInternalType( 'bool', $data['run_page_setup'] );
	}

	/**
	 * @group rest
	 */
	function test_get_show_response_succeed_when_valid_key() {
		$this->login_as( $this->default_user_id );
		$response = $this->get( '/wpjm/v1/status/run_page_setup' );
		$this->assertResponseStatus( $response, 200 );
		$data = $response->get_data();
		$this->assertInternalType( 'bool', $data );
	}

	/**
	 * @group rest
	 */
	function test_get_show_response_not_found_when_valid_key() {
		$this->login_as( $this->default_user_id );
		$response = $this->get( '/wpjm/v1/status/invalid' );
		$this->assertResponseStatus( $response, 404 );
	}

	/**
	 * @group rest
	 */
	function test_delete_not_found() {
		$response = $this->delete( '/wpjm/v1/status/run_page_setup' );
		$this->assertResponseStatus( $response, 404 );
	}

	/**
	 * @group rest
	 */
	function test_post_created_key_value_from_request_body() {
		$response = $this->post( '/wpjm/v1/status/run_page_setup', 'true' );
		$this->assertResponseStatus( $response, 201 );
	}

	/**
	 * @group rest
	 */
	function test_post_created_key_value_from_value_param() {
		$response = $this->post( '/wpjm/v1/status/run_page_setup', array(
			'value' => true,
		) );
		$this->assertResponseStatus( $response, 201 );
	}

	/**
	 * @group rest
	 */
	function test_put_ok_key_value_from_value_param() {
		$response = $this->put( '/wpjm/v1/status/run_page_setup', array(
			'value' => true,
		) );
		$this->assertResponseStatus( $response, 200 );
	}

	/**
	 * @group rest
	 */
	function test_put_updates_key_value_from_value_param() {
		$value = $this->environment()
			->model( 'WP_Job_Manager_Models_Status' )
			->get_data_store()->get_entity( '' )
			->get( 'run_page_setup' );
		$response = $this->put( '/wpjm/v1/status/run_page_setup', array(
			'value' => ! $value,
		) );
		$this->assertResponseStatus( $response, 200 );
		$model = $this->environment()
			->model( 'WP_Job_Manager_Models_Status' )
			->get_data_store()->get_entity( '' );
		$this->assertNotEquals( $value, $model->get( 'run_page_setup' ) );
	}

	/**
	 * @group rest
	 */
	function test_post_response_status_requires_admin() {
		$this->login_as( $this->default_user_id );

		$response = $this->put( '/wpjm/v1/status/run_page_setup', array(
			'value' => false,
		) );

		$this->assertResponseStatus( $response, 403 );
	}
}
