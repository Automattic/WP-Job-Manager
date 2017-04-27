<?php

class WP_Test_WP_Job_Manager extends WP_UnitTestCase {
	/**
	 * Test the global $job_manager object
	 *
	 * @author jom
	 * @since 1.26
	 */
	function testJobManagerGlobalObject() {
		// setup the test
		global $job_manager;

		// test if the global job manager object is loaded
		$this->assertTrue( isset( $job_manager ), 'Job Manager global object loaded ' );

		// check the class
		$this->assertTrue( $job_manager instanceof WP_Job_Manager, 'Job Manager object is instance of WP_Job_Manager class' );
	}
}
