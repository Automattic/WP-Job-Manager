<?php

class WP_Test_WP_Job_Manager_Models_Job_Types_Custom_Fields extends WPJM_REST_TestCase {

    /**
     * @group rest
     */
    function test_exists() {
        $this->assertClassExists( 'WP_Job_Manager_Models_Job_Types_Custom_Fields' );
    }

    /**
     * @group rest
     */
    function test_can_set_employment_type() {
        $model = $this
            ->environment()
            ->model( 'WP_Job_Manager_Models_Job_Types_Custom_Fields' )
            ->create( array() );

        $model->set( 'employment_type', 'FULL_TIME' );
        $this->assertEquals( $model->get( 'employment_type' ), 'FULL_TIME' );
    }
}