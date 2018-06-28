<?php
/**
 * Routes:
 * OPTIONS /wp-json/wpjm/v1/settings
 * GET /wp-json/wpjm/v1/settings
 *
 * OPTIONS /wp-json/wpjm/v1/settings/{setting_key}
 * GET /wp-json/wpjm/v1/settings/{setting_key}
 * POST /wp-json/wpjm/v1/settings/{setting_key}
 *
 * @group rest
 */
class WP_Test_WP_Job_Manager_REST_API_Settings extends WPJM_REST_TestCase {

	public function test_responds_when_no_sufficient_permissions() {
		$this->login_as_default_user();
		$response = $this->get( '/wpjm/v1/settings' );

		$this->assertResponseStatus( $response, 401 );
	}

	public function test_delete_not_found() {
		$response = $this->delete( '/wpjm/v1/settings' );
		$this->assertResponseStatus( $response, 404 );
	}

	public function test_get_response_status_success() {
		$response = $this->get( '/wpjm/v1/settings' );
		$this->assertResponseStatus( $response, 200 );
	}

	public function test_post_response_status_created() {
		$settings = $this->get_settings();

		$previous_setting = $settings->get( 'job_manager_per_page' );

		$new_settings = array(
			'job_manager_per_page' => $previous_setting + 1,
		);

		$response = $this->post( '/wpjm/v1/settings', $new_settings );
		$this->assertResponseStatus( $response, 201 );
	}

	public function test_post_response_contain_settings() {
		$settings = $this->get_settings();

		$previous_setting = $settings->get( 'job_manager_per_page' );

		$new_settings = array(
			'job_manager_per_page' => $previous_setting + 1,
		);

		$response = $this->post( '/wpjm/v1/settings', $new_settings );
		$this->assertResponseStatus( $response, 201 );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'job_manager_per_page', $data );
		$this->assertEquals( $previous_setting + 1, $data['job_manager_per_page'] );
	}

	public function test_put_response_status_success() {
		$settings = $this->get_settings();

		$previous_setting = $settings->get( 'job_manager_per_page' );

		$new_settings = array(
			'job_manager_per_page' => $previous_setting + 1,
		);

		$response = $this->put( '/wpjm/v1/settings', $new_settings );
		$this->assertResponseStatus( $response, 200 );
	}

	public function test_post_updates_settings() {
		$settings = $this->get_settings();

		$previous_setting = $settings->get( 'job_manager_per_page' );

		$new_settings = array(
			'job_manager_per_page' => $previous_setting + 1,
		);

		$this->post( '/wpjm/v1/settings', $new_settings );

		$response = $this->get( '/wpjm/v1/settings' );
		$data     = $response->get_data();
		$this->assertArrayHasKey( 'job_manager_per_page', $data );
		$this->assertEquals( $previous_setting + 1, $data['job_manager_per_page'] );
	}

	public function test_put_updates_settings() {
		$settings = $this->get_settings();

		$previous_setting = $settings->get( 'job_manager_per_page' );

		$new_settings = array(
			'job_manager_per_page' => $previous_setting + 1,
		);

		$this->put( '/wpjm/v1/settings', $new_settings );

		$response = $this->get( '/wpjm/v1/settings' );
		$data     = $response->get_data();
		$this->assertArrayHasKey( 'job_manager_per_page', $data );
		$this->assertEquals( $previous_setting + 1, $data['job_manager_per_page'] );
	}

	public function test_put_validation_error_bad_request_no_setting_change() {
		$settings = $this->get_settings();

		$previous_setting = $settings->get( 'job_manager_job_dashboard_page_id' );

		$new_settings = array(
			'job_manager_job_dashboard_page_id' => -1,
		);

		$response = $this->put( '/wpjm/v1/settings', $new_settings );
		$this->assertResponseStatus( $response, 400 );

		$response = $this->get( '/wpjm/v1/settings' );
		$data     = $response->get_data();
		$this->assertArrayHasKey( 'job_manager_job_dashboard_page_id', $data );
		$this->assertEquals( $previous_setting, $data['job_manager_job_dashboard_page_id'] );
	}

	private function get_settings() {
		return $this->environment()->model( 'WP_Job_Manager_Models_Settings' )
			->get_data_store()->get_entity( -1 );
	}
}
