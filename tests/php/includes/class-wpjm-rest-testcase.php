<?php

class WPJM_REST_TestCase extends WPJM_BaseTest {

    /**
     * @var WP_REST_Server
     */
    private $rest_server;

    /**
     * @var WPJM_REST_Environment
     */
    private $environment;

    /**
     * @return WPJM_REST_Environment
     */
    protected function environment() {
        return $this->environment;
    }

    protected function rest_server() {
        return $this->rest_server;
    }

    function setUp() {
        parent::setUp();
        $this->environment = WPJM()->rest_api()->get_bootstrap()->environment();
        /** @var WP_REST_Server $wp_rest_server */
        global $wp_rest_server;
        $this->rest_server = $wp_rest_server = new WP_REST_Server;
        do_action( 'rest_api_init' );
    }

    function assertClassExists( $cls) {
        $this->assertNotFalse( class_exists( $cls ), $cls . ': should exist' );
    }

    function assertModelValid( $model ) {
        $this->assertTrue( $model->validate() );
    }

    function assertResponseStatus($response, $status_code) {
        $this->assertInstanceOf( WP_REST_Response::class, $response );
        $this->assertEquals( $status_code, $response->get_status() );
    }

    function request( $endpoint, $method, $args = array() ) {
        $request = new WP_REST_Request( $method, $endpoint );
        foreach ($args as $key => $value ) {
            $request->set_param( $key, $value );
        }
        return $this->rest_server()->dispatch( $request );
    }

    function get( $endpoint, $args = array() ) {
        return $this->request( $endpoint, 'GET', $args );
    }

    function post( $endpoint, $args = array() ) {
        return $this->request( $endpoint, 'POST', $args );
    }

    function put( $endpoint, $args = array() ) {
        return $this->request( $endpoint, 'PUT', $args );
    }

    function delete( $endpoint, $args = array() ) {
        return $this->request( $endpoint, 'DELETE', $args );
    }
}