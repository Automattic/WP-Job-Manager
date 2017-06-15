<?php

class WP_Test_WP_Job_Manager_Models_Configuration extends WPJM_REST_TestCase {
	function test_exists() {
		$this->assertClassExists( 'WP_Job_Manager_Models_Configuration' );
	}
}
