<?php

class WP_Job_Manager_Rest_Api_Endpoint_Version extends Mixtape_Rest_Api_Controller {
    protected $base = '/version';
    public function register() {
        register_rest_route( $this->controller_bundle->get_api_prefix(),  $this->base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_items' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args'                => array()
            )
        ) );
    }

    public function get_items( $request ) {
        return new WP_REST_Response( array( 'job_manager_version' => JOB_MANAGER_VERSION ), 200 );
    }

    public function get_items_permissions_check( $request ) {
        return true;
    }
}