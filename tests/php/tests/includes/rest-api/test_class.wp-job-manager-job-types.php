<?php
/**
 * Routes:
 * OPTIONS /wp-json/wp/v2/job-types
 * GET /wp-json/wp/v2/job-types
 * POST /wp-json/wp/v2/job-types
 *
 * OPTIONS /wp-json/wp/v2/job-types/{id}
 * GET /wp-json/wp/v2/job-types/{id}
 * POST /wp-json/wp/v2/job-types/{id}
 * PATCH /wp-json/wp/v2/job-types/{id} (Alias for `POST /wp-json/wp/v2/job-types/{id}`)
 * PUT /wp-json/wp/v2/job-types/{id} (Alias for `POST /wp-json/wp/v2/job-types/{id}`)
 * DELETE /wp-json/wp/v2/job-types/{id}?force=1
 *
 * @see https://developer.wordpress.org/rest-api/reference/categories/
 * @group rest
 */
class WP_Test_WP_Job_Manager_Job_Types_Test extends WPJM_REST_TestCase {

	public function test_wp_v2_has_job_types_route() {
		$this->login_as_default_user();
		$response = $this->get( '/wp/v2' );
		$this->assertResponseStatus( $response, 200 );
		$data = $response->get_data();

		$routes = array_keys( $data['routes'] );
		$this->assertTrue( in_array( '/wp/v2/job-types', $routes ) );
	}

	public function test_get_job_types_success() {
		$this->login_as_default_user();
		$response = $this->get( '/wp/v2/job-types' );
		$this->assertResponseStatus( $response, 200 );
	}

	public function test_post_job_types_fail_if_invalid_employment_type() {
		$this->login_as_admin();
		$response = $this->post(
			'/wp/v2/job-types', array(
				'name'   => 'Software Engineer',
				'slug'   => 'software-engineer',
				'fields' => array(
					'employment_type' => 'invalid',
				),
			)
		);
		$this->assertResponseStatus( $response, 400 );
	}

	public function test_delete_fail_as_default_user() {
		$this->login_as_default_user();
		$term_id  = $this->get_job_type();
		$response = $this->delete( sprintf( '/wp/v2/job-types/%d', $term_id ), array( 'force' => 1 ) );
		$this->assertResponseStatus( $response, 401 );
	}

	public function test_delete_succeed_as_admin_user() {
		$this->login_as_admin();
		$term_id  = $this->get_job_type();
		$response = $this->delete( sprintf( '/wp/v2/job-types/%d', $term_id ), array( 'force' => 1 ) );
		$this->assertResponseStatus( $response, 200 );
	}

	public function test_post_job_types_succeed_if_valid_employment_type() {
		$this->login_as_admin();
		$response = $this->post(
			'/wp/v2/job-types', array(
				'name'   => 'Software Engineer',
				'slug'   => 'software-engineer',
				'fields' => array(
					'employment_type' => 'FULL_TIME',
				),
			)
		);

		$this->assertResponseStatus( $response, 201 );
	}

	public function test_post_job_types_save_employment_type() {
		$this->login_as_admin();
		$response = $this->post(
			'/wp/v2/job-types', array(
				'name'   => 'Software Engineer',
				'slug'   => 'software-engineer',
				'fields' => array(
					'employment_type' => 'FULL_TIME',
				),
			)
		);

		$this->assertResponseStatus( $response, 201 );
		$data = $response->get_data();
		$this->assertTrue( array_key_exists( 'fields', $data ) );
		$fields = $data['fields'];
		$this->assertTrue( array_key_exists( 'employment_type', $fields ) );
		$job_type_employment_type = $fields['employment_type'];
		$this->assertSame( 'FULL_TIME', $job_type_employment_type );
	}

	protected function get_job_type() {
		return $this->factory->term->create( array( 'taxonomy' => 'job_listing_type' ) );
	}
}
