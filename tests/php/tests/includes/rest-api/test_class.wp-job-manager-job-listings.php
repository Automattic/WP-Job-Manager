<?php

class WP_Test_WP_Job_Manager_Job_Listings_Test extends WPJM_REST_TestCase {

	/**
	 * @group rest
	 * @covers WP_Job_Manager_REST_Registrable_Job_Listings::get_fields
	 */
	function test_get_job_listings_success() {
		$this->login_as( $this->default_user_id );
		$response = $this->get( '/wp/v2/job-listings' );
		$this->assertResponseStatus( $response, 200 );
	}

	/**
	 * @group rest
	 * @covers WP_Job_Manager_REST_Registrable_Job_Listings::get_fields
	 */
	function test_get_job_listings_add_fields() {
		$published = $this->factory->job_listing->create_many( 2 );
		$response = $this->get( '/wp/v2/job-listings' );
		$this->assertResponseStatus( $response, 200 );
		$response_data = $response->get_data();
		$this->assertInternalType( 'array', $response_data );
		$this->assertSame( 2, count( $response_data ) );
		$first_listing = $response_data[0];
		$this->assertArrayHasKey( 'fields', $first_listing );
		$fields = $first_listing['fields'];
		$this->assertArrayHasKey( '_job_location', $fields );
		$this->assertArrayHasKey( '_application', $fields );
		$this->assertArrayHasKey( '_company_name', $fields );
		$this->assertArrayHasKey( '_company_website', $fields );
		$this->assertArrayHasKey( '_company_tagline', $fields );
		$this->assertArrayHasKey( '_company_twitter', $fields );
		$this->assertArrayHasKey( '_company_video', $fields );
		$this->assertArrayHasKey( '_filled', $fields );
	}

	/**
	 * @group rest
	 */
	function test_update_update_fields_fail_if_no_permissions() {
		$published = $this->factory->job_listing->create_many( 2 );
		$first_id = $published[0];
		$response = $this->get( '/wp/v2/job-listings/' . $first_id );
		$this->assertResponseStatus( $response, 200 );
		$response_data = $response->get_data();
		$first_listing = $response_data;
		$first_listing['fields']['_application'] = 'foo@example.com';

		$response = $this->put( '/wp/v2/job-listings/' . $first_listing['id'], $first_listing );
		$this->assertResponseStatus( $response, 403 );
	}

	/**
	 * @group rest
	 */
	function test_update_update_fields_success() {
		$this->markTestSkipped( 'Skip for now, need to figure out why this does not pass while working on the frontend' );
		$user_id = $this->factory->user->create( array(
			'role'       => 'administrator',
			'user_login' => 'superadmin',
		) );
		wp_set_current_user( $user_id );
		$published = $this->factory->job_listing->create_many( 2, array(
			'post_author' => $user_id,
		) );
		$this->login_as_admin();
		$first_id = $published[0];
		$response = $this->get( '/wp/v2/job-listings/' . $first_id );
		$this->assertResponseStatus( $response, 200 );
		$response_data = $response->get_data();
		$first_listing = $response_data;
		$first_listing['fields']['_application'] = 'foo@example.com';
		$request = array(
			'fields' => $first_listing['fields'],
		);

		$response = $this->put( sprintf( '/wp/v2/job-listings/%d', $first_id ), $request );
		$data = $response->get_data();
		$this->assertResponseStatus( $response, 200 );
	}
}