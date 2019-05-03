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
 * @group rest-api
 */
class WP_Test_WP_Job_Manager_Job_Listings_Test extends WPJM_REST_TestCase {

	public function setUp() {
		parent::setUp();

		$this->reset_meta_keys();
	}

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

	public function test_guest_get_unpublished_job_listing_fail() {
		$this->logout();
		$post_id = $this->get_job_listing( array( 'post_status' => 'draft' ) );
		$response = $this->get( sprintf( '/wp/v2/job-listings/%d', $post_id ) );
		$this->assertResponseStatus( $response, 401 );
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

	public function test_employer_get_unpublished_job_listing_fail() {
		$post_id = $this->get_job_listing( array( 'post_status' => 'draft' ) );
		$this->login_as_employer();
		$response = $this->get( sprintf( '/wp/v2/job-listings/%d', $post_id ) );
		$this->assertResponseStatus( $response, 403 );
	}

	public function test_employer_get_their_own_unpublished_job_listing_success() {
		$this->login_as_employer();
		$post_id = $this->get_job_listing( array( 'post_status' => 'draft' ) );
		$response = $this->get( sprintf( '/wp/v2/job-listings/%d', $post_id ) );
		$this->assertResponseStatus( $response, 200 );
	}

	public function test_employer_publish_their_own_unpublished_job_listing_success() {
		$this->login_as_employer();
		$post_id = $this->get_job_listing( array( 'post_status' => 'draft' ) );
		$response_get = $this->get( sprintf( '/wp/v2/job-listings/%d', $post_id ) );
		$this->assertResponseStatus( $response_get, 200 );

		$response_post = $this->put(
			sprintf( '/wp/v2/job-listings/%d', $post_id ), array(
				'post_status' => 'publish',
			)
		);

		$this->assertResponseStatus( $response_post, 403 );
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
		$post_id  = $this->get_job_listing();
		$this->login_as_employer();
		$response = $this->put(
			sprintf( '/wp/v2/job-listings/%d', $post_id ), array(
				'post_title' => 'Software Engineer 2',
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

	/**
	 * Tests to make sure public meta fields are exposed to guest users and private meta fields are hidden.
	 */
	public function test_guest_read_access_to_private_meta_fields() {
		$public_fields  = array( '_job_location', '_application', '_company_name', '_company_website', '_company_tagline', '_company_twitter', '_company_video', '_filled', '_featured' );
		$private_fields = array( '_job_expires' );
		$this->logout();
		$post_id  = $this->get_job_listing();

		$response = $this->get( sprintf( '/wp/v2/job-listings/%d', $post_id ) );

		$this->assertResponseStatus( $response, 200 );
		$this->assertFalse( empty( $response->data['meta'] ) );
		$this->assertEquals( count( $public_fields ), count( $response->data['meta'] ) );

		foreach ( $public_fields as $field ) {
			$this->assertArrayHasKey( $field, $response->data['meta'], sprintf( '%s should be provided in the response meta fields', $field ) );
		}

		foreach ( $private_fields as $field ) {
			$this->assertArrayNotHasKey( $field, $response->data['meta'], sprintf( '%s should NOT be provided in the response meta fields', $field ) );
		}
	}

	public function test_same_employer_read_access_to_private_meta_fields() {
		$available_fields  = array( '_job_location', '_application', '_company_name', '_company_website', '_company_tagline', '_company_twitter', '_company_video', '_filled', '_featured',  '_job_expires' );
		$this->login_as_employer();
		$post_id  = $this->get_job_listing();

		$response = $this->get( sprintf( '/wp/v2/job-listings/%d', $post_id ) );

		$this->assertResponseStatus( $response, 200 );
		$this->assertFalse( empty( $response->data['meta'] ) );
		$this->assertEquals( count( $available_fields ), count( $response->data['meta'] ) );
		foreach ( $available_fields as $field ) {
			$this->assertArrayHasKey( $field, $response->data['meta'], sprintf( '%s should be provided in the response meta fields', $field ) );
		}
	}

	public function test_different_employer_read_access_to_private_meta_fields() {
		$public_fields  = array( '_job_location', '_application', '_company_name', '_company_website', '_company_tagline', '_company_twitter', '_company_video', '_filled', '_featured' );
		$private_fields = array( '_job_expires' );
		$this->login_as_employer();
		$post_id  = $this->get_job_listing();
		$this->login_as_employer_b();

		$response = $this->get( sprintf( '/wp/v2/job-listings/%d', $post_id ) );

		$this->assertResponseStatus( $response, 200 );
		$this->assertFalse( empty( $response->data['meta'] ) );
		$this->assertEquals( count( $public_fields ), count( $response->data['meta'] ) );
		foreach ( $public_fields as $field ) {
			$this->assertArrayHasKey( $field, $response->data['meta'], sprintf( '%s should be provided in the response meta fields', $field ) );
		}

		foreach ( $private_fields as $field ) {
			$this->assertArrayNotHasKey( $field, $response->data['meta'], sprintf( '%s should NOT be provided in the response meta fields', $field ) );
		}
	}

	public function test_legacy_custom_fields_do_not_show_up_in_rest() {
		add_filter( 'job_manager_job_listing_data_fields', array( $this, 'add_legacy_field' ) );
		$this->reset_meta_keys();
		$this->login_as_admin();
		$post_id = $this->get_job_listing();
		$response = $this->get( sprintf( '/wp/v2/job-listings/%d', $post_id ) );
		remove_filter( 'job_manager_job_listing_data_fields', array( $this, 'add_legacy_field' ) );

		$this->assertResponseStatus( $response, 200 );
		$this->assertFalse( empty( $response->data['meta'] ) );
		$this->assertArrayNotHasKey( '_favorite_dog', $response->data['meta'], 'Legacy custom fields should not be included in REST API responses.' );
	}

	public function add_legacy_field( $fields ) {
		$fields['_favorite_dog'] = array(
			'label'         => 'Favorite Dog',
			'placeholder'   => 'Layla',
			'priority'      => 6,
			'data_type'     => 'string',
		);

		return $fields;
	}

	private function reset_meta_keys() {
		global $wp_meta_keys;

		unset( $wp_meta_keys['post']['job_listing'] );

		WP_Job_Manager_Post_Types::instance()->register_meta_fields();
	}

	private function get_job_listing( $args = array() ) {
		return $this->factory()->job_listing->create_and_get( $args )->ID;
	}
}
