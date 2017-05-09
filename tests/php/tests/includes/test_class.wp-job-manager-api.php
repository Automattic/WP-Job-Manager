<?php

class WP_Test_WP_Job_Manager_API extends WPJM_BaseTest {
	/**
	 * Tests the WP_Job_Manager_API::instance() always returns the same `WP_Job_Manager_API` instance.
	 *
	 * @since 1.26
	 * @covers WP_Job_Manager_API::instance
	 */
	public function test_wp_job_manager_api_instance() {
		$instance = WP_Job_Manager_API::instance();
		// check the class
		$this->assertInstanceOf( 'WP_Job_Manager_API', $instance, 'Job Manager API object is instance of WP_Job_Manager_API class' );

		// check it always returns the same object
		$this->assertSame( WP_Job_Manager_API::instance(), $instance, 'WP_Job_Manager_API::instance() must always return the same object' );
	}

	/**
	 * @since 1.26
	 * @covers WP_Job_Manager_API::add_query_vars
	 */
	public function test_add_query_vars() {
		$instance = WP_Job_Manager_API::instance();
		$vars = array( 'existing-var' );
		$new_vars = $instance->add_query_vars( $vars );
		$this->assertCount( 2, $new_vars );
		$this->assertContains( 'job-manager-api', $new_vars );
		$this->assertContains( 'existing-var', $new_vars );
	}

	/**
	 * @since 1.26
	 * @covers WP_Job_Manager_API::api_requests
	 */
	public function test_valid_api_requests() {
		global $wp;
		$instance = WP_Job_Manager_API::instance();
		$bootstrap = WPJM_Unit_Tests_Bootstrap::instance();
		include_once( $bootstrap->includes_dir . '/stubs/class-wpjm-api-handler-stub.php' );
		$this->assertTrue( class_exists( 'WPJM_Api_Handler_Stub' ) );
		$handler = new WPJM_Api_Handler_Stub();
		$handler_tag = strtolower( get_class( $handler ) );
		add_filter( 'wp_die_ajax_handler', array( $this, 'return_do_not_die' ) );
		$wp->query_vars['job-manager-api'] = $handler_tag;
		$this->assertFalse( $handler->fired );
		$instance->api_requests();
		$this->assertTrue( $handler->fired );
		remove_filter( 'wp_die_ajax_handler', array( $this, 'return_do_not_die' ) );
	}
}
