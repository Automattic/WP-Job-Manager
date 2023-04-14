<?php

require JOB_MANAGER_PLUGIN_DIR . '/includes/admin/class-wp-job-manager-admin-notices-conditions.php';

/**
 * @covers WP_Job_Manager_Admin_Notices_Conditions
 */
class WP_Test_WP_Job_Manager_Admin_Notices_Conditions extends WPJM_BaseTest {

	public function setUp() {
		parent::setUp();
	}

	public function tearDown() {
		parent::tearDown();
	}

	public function test_check_passes_only_for_wpjm_screens_by_default() {
		$this->login_as_admin();
		$result = WP_Job_Manager_Admin_Notices_Conditions::check( [] );
		$this->assertFalse( $result, 'Must not pass for non-WPJM screens' );
		foreach ( WP_Job_Manager_Admin_Notices_Conditions::ALL_WPJM_SCREEN_IDS as $wpjm_screen_id ) {
			set_current_screen( $wpjm_screen_id );
			$result = WP_Job_Manager_Admin_Notices_Conditions::check( [] );
			$this->assertTrue( $result, "Must pass for WPJM screen: $wpjm_screen_id" );
		}
	}

	public function test_check_passes_for_explicit_screen() {
		$this->login_as_admin();
		set_current_screen( 'some-random-screen' );
		$result = WP_Job_Manager_Admin_Notices_Conditions::check( [
			[
				'type'    => 'screens',
				'screens' => [ 'some-random-screen' ],
			],
		] );
		$this->assertTrue( $result, 'Must pass for explicit screen' );
	}

	public function test_check_explicit_screen_overrides_default_screens() {
		$this->login_as_admin();
		foreach ( WP_Job_Manager_Admin_Notices_Conditions::ALL_WPJM_SCREEN_IDS as $wpjm_screen_id ) {
			set_current_screen( $wpjm_screen_id );
			$result = WP_Job_Manager_Admin_Notices_Conditions::check( [
				[
					'type'    => 'screens',
					'screens' => [ 'some-random-screen' ],
				],
			] );
			$this->assertFalse( $result, "Must not pass for WPJM screen: $wpjm_screen_id" );
		}
	}
}
