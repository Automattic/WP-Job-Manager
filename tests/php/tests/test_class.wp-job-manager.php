<?php

class WP_Test_WP_Job_Manager extends WPJM_BaseTest {
	/**
	 * Tests the global $job_manager object.
	 *
	 * @since 1.26
	 */
	public function test_wp_job_manager_global_object() {
		// setup the test
		global $job_manager;

		// test if the global job manager object is loaded
		$this->assertTrue( isset( $job_manager ), 'Job Manager global object loaded' );

		// check the class
		$this->assertInstanceOf( 'WP_Job_Manager', $job_manager, 'Job Manager object is instance of WP_Job_Manager class' );

		// check it matches result of global function
		$this->assertSame( WPJM(), $job_manager, 'Job Manager global must be equal to result of WPJM()' );
	}

	/**
	 * Tests the WPJM() always returns the same `WP_Job_Manager` instance.
	 *
	 * @since 1.26
	 * @covers ::WPJM
	 */
	public function test_wp_job_manager_global_function() {
		$job_manager_instance = WPJM();
		$this->assertSame( WPJM(), $job_manager_instance, 'WPJM() must always provide the same instance of WP_Job_Manager' );
		$this->assertTrue( $job_manager_instance instanceof WP_Job_Manager, 'Job Manager object is instance of WP_Job_Manager class' );
	}

	/**
	 * Tests the WP_Job_Manager::instance() always returns the same `WP_Job_Manager` instance.
	 *
	 * @since 1.26
	 * @covers WP_Job_Manager::instance
	 */
	public function test_wp_job_manager_instance() {
		$job_manager_instance = WP_Job_Manager::instance();
		$this->assertSame( WP_Job_Manager::instance(), $job_manager_instance, 'WP_Job_Manager::instance() must always provide the same instance of WP_Job_Manager' );
		$this->assertInstanceOf( 'WP_Job_Manager', $job_manager_instance, 'WP_Job_Manager::instance() must always provide the same instance of WP_Job_Manager' );
	}

	/**
	 * Tests classes of object properties.
	 *
	 * @since 1.26
	 */
	public function test_classes_of_object_properties() {
		$this->assertInstanceOf( 'WP_Job_Manager_Forms', WPJM()->forms );
		$this->assertInstanceOf( 'WP_Job_Manager_Post_Types', WPJM()->post_types );
	}

	/**
	 * Checks constants are defined when constructing
	 *
	 * @since 1.26
	 */
	public function test_class_defined_constants() {
		WPJM();
		$this->assertTrue( defined( 'JOB_MANAGER_VERSION' ) );
		$this->assertTrue( defined( 'JOB_MANAGER_PLUGIN_DIR' ) );
		$this->assertTrue( defined( 'JOB_MANAGER_PLUGIN_URL' ) );
	}
}
