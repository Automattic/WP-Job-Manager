<?php

require_once JOB_MANAGER_PLUGIN_DIR . '/includes/admin/class-wp-job-manager-settings.php';
require_once WPJM_Unit_Tests_Bootstrap::instance()->includes_dir . '/stubs/class-wp-job-manager-admin-settings-stub.php';

class WP_Test_WP_Job_Manager_Settings extends WPJM_BaseTest {

	public function test_input_capabilities_should_not_fail_on_invalid_capabilities_provided() {
		$stub = WP_Job_Manager_Admin_Settings_Stub::instance();

		$values_to_test = array(
			null,
			0,
			'',
			'invalid',
			array(),
			new stdClass(),
		);

		$this->setOutputCallback( function() {} );
		$this->expectNotToPerformAssertions();

		foreach ( $values_to_test as $value ) {
			$stub->test_input_capabilities( [ 'name' => 'test' ], [], $value );
		}
	}

}
