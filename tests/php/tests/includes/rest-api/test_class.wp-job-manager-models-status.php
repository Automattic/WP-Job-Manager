<?php

class WP_Test_WP_Job_Manager_Models_Status extends WPJM_REST_TestCase {

	/**
	 * @group rest
	 */
	function test_exists() {
		$this->assertClassExists( 'WP_Job_Manager_Models_Status' );
	}
}
