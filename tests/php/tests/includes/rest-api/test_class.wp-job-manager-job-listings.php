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
		$post_id  = $this->get_job_listing( [ 'post_status' => 'draft' ] );
		$response = $this->get( sprintf( '/wp/v2/job-listings/%d', $post_id ) );
		$this->assertResponseStatus( $response, 401 );
	}

	public function test_guest_delete_job_listings_fail() {
		$this->logout();
		$post_id  = $this->get_job_listing();
		$response = $this->delete( sprintf( '/wp/v2/job-listings/%d', $post_id ), [ 'force' => 1 ] );
		$this->assertResponseStatus( $response, 401 );
	}

	public function test_guest_post_job_listings_fail() {
		$this->logout();
		$response = $this->post(
			'/wp/v2/job-listings',
			[
				'post_title' => 'Software Engineer',
				'post_name'  => 'software-engineer',
			]
		);

		$this->assertResponseStatus( $response, 401 );
	}

	public function test_guest_put_job_listings_fail() {
		$post_id = $this->get_job_listing();
		$this->logout();
		$response = $this->put(
			sprintf( '/wp/v2/job-listings/%d', $post_id ),
			[
				'post_title' => 'Software Engineer 2',
			]
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
		$post_id = $this->get_job_listing( [ 'post_status' => 'draft' ] );
		$this->login_as_employer();
		$response = $this->get( sprintf( '/wp/v2/job-listings/%d', $post_id ) );
		$this->assertResponseStatus( $response, 403 );
	}

	public function test_employer_get_their_own_unpublished_job_listing_success() {
		$this->login_as_employer();
		$post_id  = $this->get_job_listing( [ 'post_status' => 'draft' ] );
		$response = $this->get( sprintf( '/wp/v2/job-listings/%d', $post_id ) );
		$this->assertResponseStatus( $response, 200 );
	}

	public function test_employer_publish_their_own_unpublished_job_listing_success() {
		$this->login_as_employer();
		$post_id      = $this->get_job_listing( [ 'post_status' => 'draft' ] );
		$response_get = $this->get( sprintf( '/wp/v2/job-listings/%d', $post_id ) );
		$this->assertResponseStatus( $response_get, 200 );

		$response_post = $this->put(
			sprintf( '/wp/v2/job-listings/%d', $post_id ),
			[
				'post_status' => 'publish',
			]
		);

		$this->assertResponseStatus( $response_post, 403 );
	}

	public function test_employer_delete_job_listings_fail() {
		$this->login_as_employer();
		$post_id  = $this->get_job_listing();
		$response = $this->delete( sprintf( '/wp/v2/job-listings/%d', $post_id ), [ 'force' => 1 ] );
		$this->assertResponseStatus( $response, 403 );
	}

	public function test_employer_post_job_listings_fail() {
		$this->login_as_employer();
		$response = $this->post(
			'/wp/v2/job-listings',
			[
				'post_title' => 'Software Engineer',
				'post_name'  => 'software-engineer',
			]
		);

		$this->assertResponseStatus( $response, 403 );
	}

	public function test_employer_put_job_listings_fail() {
		$post_id = $this->get_job_listing();
		$this->login_as_employer();
		$response = $this->put(
			sprintf( '/wp/v2/job-listings/%d', $post_id ),
			[
				'post_title' => 'Software Engineer 2',
			]
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
	public function test_guest_can_read_only_public_fields() {
		$public_fields  = [ '_job_location', '_application', '_company_name', '_company_website', '_company_tagline', '_company_twitter', '_company_video', '_filled', '_featured' ];
		$private_fields = [ '_job_expires' ];
		$this->logout();
		$post_id = $this->get_job_listing();

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
		$available_fields = [ '_job_location', '_application', '_company_name', '_company_website', '_company_tagline', '_company_twitter', '_company_video', '_filled', '_featured',  '_job_expires' ];
		$this->login_as_employer();
		$post_id = $this->get_job_listing();

		$response = $this->get( sprintf( '/wp/v2/job-listings/%d', $post_id ) );

		$this->assertResponseStatus( $response, 200 );
		$this->assertFalse( empty( $response->data['meta'] ) );
		$this->assertEquals( count( $available_fields ), count( $response->data['meta'] ) );
		foreach ( $available_fields as $field ) {
			$this->assertArrayHasKey( $field, $response->data['meta'], sprintf( '%s should be provided in the response meta fields', $field ) );
		}
	}

	public function test_different_employer_read_access_to_private_meta_fields() {
		$public_fields  = [ '_job_location', '_application', '_company_name', '_company_website', '_company_tagline', '_company_twitter', '_company_video', '_filled', '_featured' ];
		$private_fields = [ '_job_expires' ];
		$this->login_as_employer();
		$post_id = $this->get_job_listing();
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
		add_filter( 'job_manager_job_listing_data_fields', [ $this, 'add_legacy_field' ] );
		$this->reset_meta_keys();
		$this->login_as_admin();
		$post_id  = $this->get_job_listing();
		$response = $this->get( sprintf( '/wp/v2/job-listings/%d', $post_id ) );
		remove_filter( 'job_manager_job_listing_data_fields', [ $this, 'add_legacy_field' ] );

		$this->assertResponseStatus( $response, 200 );
		$this->assertFalse( empty( $response->data['meta'] ) );
		$this->assertArrayNotHasKey( '_favorite_dog', $response->data['meta'], 'Legacy custom fields should not be included in REST API responses.' );
	}

	public function test_admin_can_set_meta_fields() {
		$this->login_as_admin();
		$test_meta = [
			'_job_location'    => 'Location A',
			'_application'     => 'example@example.com',
			'_company_name'    => 'Test Company',
			'_company_website' => 'https://www.example.com/awesome#nice',
			'_company_tagline' => 'Best Example Money Can Buy',
			'_company_twitter' => '@exampledotcom',
			'_company_video'   => 'https://youtube.com/example',
			'_filled'          => 0,
			'_featured'        => 0,
			'_job_expires'     => date( 'Y-m-d', strtotime( '+45 days' ) ),
		];

		$response = $this->post(
			'/wp/v2/job-listings',
			[
				'post_title' => 'Software Engineer',
				'post_name'  => 'software-engineer',
				'meta'       => $test_meta,
			]
		);

		$this->assertResponseStatus( $response, 201 );
		$response_data = $response->get_data();

		// Confirm data sent back matches.
		foreach ( $test_meta as $meta_key => $expected_value ) {
			$this->assertArrayHasKey( $meta_key, $response_data['meta'] );
			$this->assertEquals( $expected_value, $response_data['meta'][ $meta_key ] );
		}

		// Confirm what is in database matches.
		$this->assertArrayHasKey( 'id', $response_data );
		foreach ( $test_meta as $meta_key => $expected_value ) {
			$this->assertEquals( $expected_value, get_post_meta( $response_data['id'], $meta_key, true ) );
		}
	}

	public function test_admin_can_delete_meta_fields() {
		$this->login_as_admin();
		$post_id = $this->get_job_listing(
			[
			'meta_input' => [
				'_company_name' => 'Test Company',
			 ]
			]
		);

		$this->assertEquals( 'Test Company', get_post_meta( $post_id, '_company_name', true ) );

		$response = $this->put(
			'/wp/v2/job-listings/' . $post_id,
			[
				'meta'       => [
					'_company_name' => null,
				],
			]
		);

		$this->assertResponseStatus( $response, 200 );
		$this->assertFalse( metadata_exists( 'post', $post_id, '_company_name' ) );
	}

	public function test_meta_input_sterilized() {
		$this->login_as_admin();
		$test_meta = [
			'_job_location'    => [
				'sent'     => '<a href="http://example.com">Location A</a>',
				'expected' => 'Location A',
			],
			'_application'     => [
				'sent'     => 'chrome://net=internals',
				'expected' => '',
			],
			'_company_name'    => [
				'sent'     => 'Test Company 🐵 🙈 🙉 🙊',
				'expected' => 'Test Company 🐵 🙈 🙉 🙊',
			],
			'_company_website' => [
				'sent'     => 'https://www.example.com/awesome#nice',
				'expected' => 'https://www.example.com/awesome#nice',
			],
			'_company_tagline' => [
				'sent'     => 'Best Example Money Can Buy<script\\x20type=\"text/javascript\">javascript:alert(1);</script>',
				'expected' => 'Best Example Money Can Buy',
			],
			'_company_twitter' => [
				'sent'     => '    @exampledotcom ',
				'expected' => '@exampledotcom',
			],
			'_company_video'   => [
				'sent'     => 'http://example.com/index.php?search="><script>alert(0)</script>',
				'expected' => 'http://example.com/index.php?search=scriptalert(0)/script',
			],
			'_filled'          => [
				'sent'     => 11,
				'expected' => 1,
			],
			'_featured'        => [
				'sent'     => 0x0,
				'expected' => 0,
			],
			'_job_expires'     => [
				'sent'     => '01-01-2018',
				'expected' => '',
			],
		];

		$test_meta_values = [];
		foreach ( $test_meta as $meta_key => $values ) {
			$test_meta_values[ $meta_key ] = $values['sent'];
		}

		$response = $this->post(
			'/wp/v2/job-listings',
			[
				'post_title' => 'Software Engineer',
				'post_name'  => 'software-engineer',
				'meta'       => $test_meta_values,
			]
		);

		$this->assertResponseStatus( $response, 201 );
		$response_data = $response->get_data();

		// Confirm what is in database matches.
		$this->assertArrayHasKey( 'id', $response_data );
		foreach ( $test_meta as $meta_key => $values ) {
			$this->assertEquals( $values['expected'], get_post_meta( $response_data['id'], $meta_key, true ) );
		}
	}

	/**
	 * Data provider for the `\WP_Test_WP_Job_Manager_Job_Listings_Test::test_meta_input_bad_data_type` test.
	 *
	 * @return array
	 */
	public function data_provider_test_meta_input_bad_data_type() {
		return [
			[
				[
					'_filled' => 'yes',
				],
			],
			[
				[
					'_featured' => 'true',
				],
			],
			[
				[
					'_job_location' => [ 'Seattle', 'WA' ],
				],
			],
		];
	}

	/**
	 * @dataProvider data_provider_test_meta_input_bad_data_type
	 */
	public function test_meta_input_bad_data_type( $test_meta ) {
		$this->login_as_admin();

		$response = $this->post(
			'/wp/v2/job-listings',
			[
				'post_title' => 'Software Engineer',
				'post_name'  => 'software-engineer',
				'meta'       => $test_meta,
			]
		);

		$this->assertResponseStatus( $response, 400 );
	}

	public function add_legacy_field( $fields ) {
		$fields['_favorite_dog'] = [
			'label'         => 'Favorite Dog',
			'placeholder'   => 'Layla',
			'priority'      => 6,
			'data_type'     => 'string',
		];

		return $fields;
	}

	private function reset_meta_keys() {
		global $wp_meta_keys;

		unset( $wp_meta_keys['post']['job_listing'] );

		WP_Job_Manager_Post_Types::instance()->register_meta_fields();
	}

	private function get_job_listing( $args = [] ) {
		return $this->factory()->job_listing->create_and_get( $args )->ID;
	}
}
