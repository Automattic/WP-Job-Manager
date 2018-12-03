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
}
