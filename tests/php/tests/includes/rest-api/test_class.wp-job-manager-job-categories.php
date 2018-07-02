<?php
/**
 * Routes:
 * OPTIONS /wp-json/wp/v2/job-categories
 * GET /wp-json/wp/v2/job-categories
 * POST /wp-json/wp/v2/job-categories
 *
 * OPTIONS /wp-json/wp/v2/job-categories/{id}
 * GET /wp-json/wp/v2/job-categories/{id}
 * POST /wp-json/wp/v2/job-categories/{id}
 * PATCH /wp-json/wp/v2/job-categories/{id} (Alias for `POST /wp-json/wp/v2/job-categories/{id}`)
 * PUT /wp-json/wp/v2/job-categories/{id} (Alias for `POST /wp-json/wp/v2/job-categories/{id}`)
 * DELETE /wp-json/wp/v2/job-categories/{id}?force=1
 *
 * @see https://developer.wordpress.org/rest-api/reference/categories/
 * @group rest
 */
class WP_Test_WP_Job_Manager_Job_Categories_Test extends WPJM_REST_TestCase {

	public function test_get_success_when_guest() {
		$this->logout();
		$response = $this->get( '/wp/v2/job-categories' );
		$this->assertResponseStatus( $response, 200 );
	}

	public function test_post_fail_when_guest() {
		$this->logout();
		$response = $this->post(
			'/wp/v2/job-categories', array(
				'name' => 'REST Test' . microtime( true ),
			)
		);
		$this->assertResponseStatus( $response, 401 );
	}

	public function test_post_success_when_admin() {
		$this->login_as_admin();
		$response = $this->post(
			'/wp/v2/job-categories', array(
				'name' => 'REST Test' . microtime( true ),
			)
		);
		$this->assertResponseStatus( $response, 201 );
	}

	public function test_post_fail_when_default_user() {
		$this->login_as_default_user();
		$response = $this->post(
			'/wp/v2/job-categories', array(
				'name' => 'REST Test' . microtime( true ),
			)
		);
		$this->assertResponseStatus( $response, 401 );
	}

	public function test_delete_fail_not_implemented() {
		$this->login_as_admin();
		$term_id  = $this->get_job_category();
		$response = $this->delete( '/wp/v2/job-categories/' . $term_id );
		$this->assertResponseStatus( $response, 501 );
	}

	public function test_delete_fail_as_default_user() {
		$this->login_as_default_user();
		$term_id  = $this->get_job_category();
		$response = $this->delete( sprintf( '/wp/v2/job-categories/%d', $term_id ), array( 'force' => 1 ) );
		$this->assertResponseStatus( $response, 401 );
	}

	public function test_delete_succeed_as_admin_user() {
		$this->login_as_admin();
		$term_id  = $this->get_job_category();
		$response = $this->delete( sprintf( '/wp/v2/job-categories/%d', $term_id ), array( 'force' => 1 ) );
		$this->assertResponseStatus( $response, 200 );
	}

	public function test_wp_v2_has_job_categories_route() {
		$this->login_as_default_user();
		$response = $this->get( '/wp/v2' );
		$this->assertResponseStatus( $response, 200 );
		$data = $response->get_data();

		$routes = array_keys( $data['routes'] );
		$this->assertTrue( in_array( '/wp/v2/job-categories', $routes ) );
	}

	public function test_get_job_categories_success() {
		$this->login_as_default_user();
		$response = $this->get( '/wp/v2/job-categories' );
		$this->assertResponseStatus( $response, 200 );
	}

	protected function get_job_category() {
		return $this->factory->term->create( array( 'taxonomy' => 'job_listing_category' ) );
	}
}
