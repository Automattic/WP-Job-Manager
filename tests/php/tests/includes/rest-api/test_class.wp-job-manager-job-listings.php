<?php
/**
 * Routes:
 * OPTIONS /wp-json/wp/v2/job-listings
 * GET /wp-json/wp/v2/job-listings
 * POST /wp-json/wp/v2/job-listings
 *
 * OPTIONS /wp-json/wp/v2/job-listings/{id}
 * GET /wp-json/wp/v2/job-listings/{id}
 * POST /wp-json/wp/v2/job-listings/{id}
 * PATCH /wp-json/wp/v2/job-listings/{id} (Alias for `POST /wp-json/wp/v2/job-listings/{id}`)
 * PUT /wp-json/wp/v2/job-listings/{id} (Alias for `POST /wp-json/wp/v2/job-listings/{id}`)
 * DELETE /wp-json/wp/v2/job-listings/{id}
 *
 * @see https://developer.wordpress.org/rest-api/reference/posts/
 * @group rest
 */
class WP_Test_WP_Job_Manager_Job_Listings_Test extends WPJM_REST_TestCase {

	public function test_guest_get_job_listings_success() {
		$this->logout();
		$response = $this->get( '/wp/v2/job-listings' );
		$this->assertResponseStatus( $response, 200 );
	}

	public function test_guest_get_job_listing_success() {
		$this->logout();
		$post_id  = $this->get_job_listing();
		$response = $this->get( sprintf( '/wp/v2/job-listings/%d', $post_id ) );
		$this->assertResponseStatus( $response, 200 );
	}

	public function test_guest_delete_job_listings_fail() {
		$this->logout();
		$post_id  = $this->get_job_listing();
		$response = $this->delete( sprintf( '/wp/v2/job-listings/%d', $post_id ), array( 'force' => 1 ) );
		$this->assertResponseStatus( $response, 401 );
	}

	public function test_guest_post_job_listings_fail() {
		$this->logout();
		$response = $this->post(
			'/wp/v2/job-listings', array(
				'post_title' => 'Software Engineer',
				'post_name'  => 'software-engineer',
			)
		);

		$this->assertResponseStatus( $response, 401 );
	}

	public function test_guest_put_job_listings_fail() {
		$post_id  = $this->get_job_listing();
		$this->logout();
		$response = $this->put(
			sprintf( '/wp/v2/job-listings/%d', $post_id ), array(
				'post_title' => 'Software Engineer 2',
			)
		);

		$this->assertResponseStatus( $response, 401 );
	}

	public function test_employer_get_job_listings_success() {
		$this->login_as_employer();
		$response = $this->get( '/wp/v2/job-listings' );
		$this->assertResponseStatus( $response, 200 );
	}

	public function test_employer_get_job_listing_success() {
		$this->login_as_employer();
		$post_id  = $this->get_job_listing();
		$response = $this->get( sprintf( '/wp/v2/job-listings/%d', $post_id ) );
		$this->assertResponseStatus( $response, 200 );
	}

	public function test_employer_delete_job_listings_fail() {
		$this->login_as_employer();
		$post_id  = $this->get_job_listing();
		$response = $this->delete( sprintf( '/wp/v2/job-listings/%d', $post_id ), array( 'force' => 1 ) );
		$this->assertResponseStatus( $response, 403 );
	}

	public function test_employer_post_job_listings_fail() {
		$this->login_as_employer();
		$response = $this->post(
			'/wp/v2/job-listings', array(
				'post_title' => 'Software Engineer',
				'post_name'  => 'software-engineer',
			)
		);

		$this->assertResponseStatus( $response, 403 );
	}

	public function test_employer_put_job_listings_fail() {
		$term_id  = $this->get_job_listing();
		$this->login_as_employer();
		$response = $this->put(
			sprintf( '/wp/v2/job-listings/%d', $term_id ), array(
				'name'   => 'Software Engineer 2',
			)
		);

		$this->assertResponseStatus( $response, 403 );
	}
	
	/**
	 * @covers WP_Job_Manager_Registrable_Job_Listings::get_fields
	 */
	public function test_get_job_listings_success() {
		$this->login_as_default_user();
		$response = $this->get( '/wp/v2/job-listings' );
		$this->assertResponseStatus( $response, 200 );
	}

	public function test_get_job_listings_success_guest() {
		$this->logout();
		$response = $this->get( '/wp/v2/job-listings' );
		$this->assertResponseStatus( $response, 200 );
	}

	private function get_job_listing() {
		return $this->factory()->job_listing->create_and_get()->ID;
	}
}
