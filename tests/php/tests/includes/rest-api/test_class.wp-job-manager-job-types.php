<?php

class WP_Test_WP_Job_Manager_Job_Types_Test extends WPJM_REST_TestCase {

	/**
	 * @group rest
	 */
	function test_wp_v2_has_job_types_route() {
		$this->login_as( $this->default_user_id );
        $response = $this->get( '/wp/v2' );
        $this->assertResponseStatus( $response, 200 );
        $data = $response->get_data();

        $routes =  array_keys( $data['routes'] );
        $this->assertTrue( in_array( '/wp/v2/job-types', $routes ) );
	}

    /**
     * @group rest
     */
	function test_get_job_types_success() {
        $this->login_as( $this->default_user_id );
        $response = $this->get( '/wp/v2/job-types' );
        $this->assertResponseStatus( $response, 200 );
    }

    /**
     * @group rest
     */
    function test_post_job_types_fail_if_invalid_employment_type() {
        $this->login_as( $this->admin_id );
        $response = $this->post( '/wp/v2/job-types', array(
            'name' => 'Software Engineer',
            'slug' => 'software-engineer',
            'fields' => array(
                'employment_type' => 'invalid',
            ),
        ) );
        $this->assertResponseStatus( $response, 400 );
    }

    /**
     * @group rest
     */
    function test_post_job_types_succeed_if_valid_employment_type() {
        $this->login_as( $this->admin_id );
        $response = $this->post( '/wp/v2/job-types', array(
            'name' => 'Software Engineer',
            'slug' => 'software-engineer',
            'fields' => array(
                'employment_type' => 'FULL_TIME',
            ),
        ) );

        $this->assertResponseStatus( $response, 201 );
    }

    /**
     * @group rest
     */
    function test_post_job_types_save_employment_type() {
        $this->login_as( $this->admin_id );
        $response = $this->post( '/wp/v2/job-types', array(
            'name' => 'Software Engineer',
            'slug' => 'software-engineer',
            'fields' => array(
                'employment_type' => 'FULL_TIME',
            ),
        ) );

        $this->assertResponseStatus( $response, 201 );
        $data = $response->get_data();
        $this->assertTrue( array_key_exists( 'fields', $data ) );
        $fields = $data['fields'];
        $this->assertTrue( array_key_exists( 'employment_type', $fields ) );
        $job_type_employment_type = $fields['employment_type'];
        $this->assertSame( 'FULL_TIME', $job_type_employment_type );
    }
}