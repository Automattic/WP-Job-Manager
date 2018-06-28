<?php

/**
 * Routes:
 * OPTIONS /wp-json/wpjm/v1/status
 * GET /wp-json/wpjm/v1/status
 *
 * OPTIONS /wp-json/wpjm/v1/status/{status_key}
 * GET /wp-json/wpjm/v1/status/{status_key}
 * POST /wp-json/wpjm/v1/status/{status_key}
 *
 * @group rest
 */
class WP_Test_WP_Job_Manager_Controllers_Status extends WPJM_REST_TestCase {
	public function test_get_fail_when_guest() {
		$this->logout();
		$response = $this->get( '/wpjm/v1/status' );
		$this->assertResponseStatus( $response, 401 );
	}

	public function test_post_fail_when_guest() {
		$this->logout();
		$response = $this->post( '/wpjm/v1/status/run_page_setup', 'true' );
		$this->assertResponseStatus( $response, 401 );
	}

	public function test_put_fail_when_guest() {
		$this->logout();
		$response = $this->put(
			'/wpjm/v1/status/run_page_setup', array(
				'value' => true,
			)
		);
		$this->assertResponseStatus( $response, 401 );
	}

	public function test_delete_fail() {
		$this->login_as_admin();
		$response = $this->delete( '/wpjm/v1/status' );
		$this->assertResponseStatus( $response, 404 );
	}

	public function test_delete_fail_run_page_setup() {
		$this->login_as_admin();
		$response = $this->delete( '/wpjm/v1/status/run_page_setup' );
		$this->assertResponseStatus( $response, 404 );
	}

	public function test_get_fail_when_user_not_admin() {
		$this->login_as_default_user();
		$response = $this->get( '/wpjm/v1/status' );
		$this->assertResponseStatus( $response, 401 );
	}

	public function test_post_fail_when_not_admin() {
		$this->login_as_default_user();
		$response = $this->post( '/wpjm/v1/status/run_page_setup', 'true' );
		$this->assertResponseStatus( $response, 401 );
	}

	public function test_get_succeed_when_user_admin() {
		$this->login_as_admin();
		$response = $this->get( '/wpjm/v1/status' );
		$this->assertResponseStatus( $response, 200 );
	}

	public function test_get_index_response() {
		$this->login_as_admin();
		$response = $this->get( '/wpjm/v1/status' );
		$this->assertResponseStatus( $response, 200 );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'run_page_setup', $data );
		$this->assertInternalType( 'bool', $data['run_page_setup'] );
	}

	public function test_get_show_response_succeed_when_valid_key() {
		$this->login_as_admin();
		$response = $this->get( '/wpjm/v1/status/run_page_setup' );
		$this->assertResponseStatus( $response, 200 );
		$data = $response->get_data();
		$this->assertInternalType( 'bool', $data );
	}

	public function test_get_show_response_not_found_when_valid_key() {
		$this->login_as_admin();
		$response = $this->get( '/wpjm/v1/status/invalid' );
		$this->assertResponseStatus( $response, 404 );
	}

	public function test_delete_not_found() {
		$this->login_as_admin();
		$response = $this->delete( '/wpjm/v1/status/run_page_setup' );
		$this->assertResponseStatus( $response, 404 );
	}

	public function test_post_created_key_value_from_request_body() {
		$this->login_as_admin();
		$response = $this->post( '/wpjm/v1/status/run_page_setup', 'true' );
		$this->assertResponseStatus( $response, 201 );
	}

	public function test_post_created_key_value_from_value_param() {
		$this->login_as_admin();
		$response = $this->post(
			'/wpjm/v1/status/run_page_setup', array(
				'value' => true,
			)
		);
		$this->assertResponseStatus( $response, 201 );
	}

	public function test_put_ok_key_value_from_value_param() {
		$this->login_as_admin();
		$response = $this->put(
			'/wpjm/v1/status/run_page_setup', array(
				'value' => true,
			)
		);
		$this->assertResponseStatus( $response, 200 );
	}

	public function test_put_updates_key_value_from_value_param() {
		$this->login_as_admin();
		$value    = $this->environment()
			->model( 'WP_Job_Manager_Models_Status' )
			->get_data_store()->get_entity( '' )
			->get( 'run_page_setup' );
		$response = $this->put(
			'/wpjm/v1/status/run_page_setup', array(
				'value' => ! $value ? 1 : 0,
			)
		);

		$this->assertResponseStatus( $response, 200 );
		$model = $this->environment()
			->model( 'WP_Job_Manager_Models_Status' )
			->get_data_store()->get_entity( '' );
		$this->assertNotEquals( $value, $model->get( 'run_page_setup' ) );
	}

	public function test_post_response_status_requires_admin() {
		global $wp_version;

		$this->login_as_default_user();

		$response = $this->put(
			'/wpjm/v1/status/run_page_setup', array(
				'value' => false,
			)
		);
		// We have a logged in user so post-4.9.1 versions of WordPress will correctly return 401.
		// See https://core.trac.wordpress.org/changeset/42421.
		if ( version_compare( $wp_version, '4.9.1', '>' ) ) {
			$this->assertResponseStatus( $response, 401 );
		} else {
			$this->assertResponseStatus( $response, 403 );
		}
	}
}
