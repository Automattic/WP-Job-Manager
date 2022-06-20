<?php

require JOB_MANAGER_PLUGIN_DIR . '/includes/admin/class-wp-job-manager-admin-notices.php';

class WP_Test_WP_Job_Manager_Admin_Notices extends WPJM_BaseTest {

	public function setUp() {
		parent::setUp();
	}

	public function tearDown() {
		parent::tearDown();
		WP_Job_Manager_Admin_Notices::reset_notices();
	}

	public function test_reset_notices() {
		$this->assertEmpty( $this->get_raw_state() );
		WP_Job_Manager_Admin_Notices::add_notice( 'test_action' );
		WP_Job_Manager_Admin_Notices::add_notice( 'test_action_2' );
		$this->assertEquals( 2, count( $this->get_raw_state() ) );

		WP_Job_Manager_Admin_Notices::reset_notices();

		$this->assertEquals( [], $this->get_raw_state() );
	}

	public function test_add_notice_simple() {
		$this->assertEmpty( $this->get_raw_state() );

		WP_Job_Manager_Admin_Notices::add_notice( 'test_action' );

		$this->assertTrue( in_array( 'test_action', $this->get_raw_state() ) );
	}

	public function test_add_notice_no_dups() {
		$this->assertEmpty( $this->get_raw_state() );

		WP_Job_Manager_Admin_Notices::add_notice( 'test_action' );
		WP_Job_Manager_Admin_Notices::add_notice( 'test_action' );

		$this->assertTrue( in_array( 'test_action', $this->get_raw_state() ) );
		$this->assertEquals( 1, count( $this->get_raw_state() ) );
	}

	public function test_add_notice_non_standard_chars_removed() {
		$this->assertEmpty( $this->get_raw_state() );

		WP_Job_Manager_Admin_Notices::add_notice( 'test_action' );
		WP_Job_Manager_Admin_Notices::add_notice( 'test_action$$$' );

		$this->assertTrue( in_array( 'test_action', $this->get_raw_state() ) );
		$this->assertFalse( in_array( 'test_action$$$', $this->get_raw_state() ) );
		$this->assertEquals( 1, count( $this->get_raw_state() ) );
	}

	public function test_remove_notice_simple() {
		$this->assertEmpty( $this->get_raw_state() );
		WP_Job_Manager_Admin_Notices::add_notice( 'test_action' );
		WP_Job_Manager_Admin_Notices::add_notice( 'other-test-action-222' );
		$this->assertTrue( in_array( 'test_action', $this->get_raw_state() ) );

		WP_Job_Manager_Admin_Notices::remove_notice( 'test_action' );

		$this->assertFalse( in_array( 'test_action', $this->get_raw_state() ) );
		$this->assertTrue( in_array( 'other-test-action-222', $this->get_raw_state() ) );
	}

	public function test_display_notices_does_actions() {
		WP_Job_Manager_Admin_Notices::add_notice( 'test_action' );
		$this->assertEquals( 0, did_action( 'job_manager_init_admin_notices' ) );
		$this->assertEquals( 0, did_action( 'job_manager_admin_notice_test_action' ) );
		$this->assertTrue( in_array( 'test_action', $this->get_raw_state() ) );

		WP_Job_Manager_Admin_Notices::display_notices();

		$this->assertEquals( 1, did_action( 'job_manager_init_admin_notices' ) );
		$this->assertEquals( 1, did_action( 'job_manager_admin_notice_test_action' ) );
	}

	public function test_is_admin_on_standard_job_manager_screen_non_admin() {
		$this->login_as_admin();
		$this->assertTrue( WP_Job_Manager_Admin_Notices::is_admin_on_standard_job_manager_screen( [ '' ] ) );

		$this->login_as_employer();
		$this->assertFalse( WP_Job_Manager_Admin_Notices::is_admin_on_standard_job_manager_screen( [ '' ] ) );
	}

	public function test_is_admin_on_standard_job_manager_screen_uncommon_screen_test() {
		$this->login_as_admin();
		$this->assertFalse( WP_Job_Manager_Admin_Notices::is_admin_on_standard_job_manager_screen( [ 'dinosaur' ] ) );
		set_current_screen( 'dinosaur' );

		$this->assertTrue( WP_Job_Manager_Admin_Notices::is_admin_on_standard_job_manager_screen( [ 'dinosaur' ] ) );
		$this->assertFalse( WP_Job_Manager_Admin_Notices::is_admin_on_standard_job_manager_screen( [ 'dogs' ] ) );
	}

	public function test_is_admin_on_standard_job_manager_screen_common_screen_test() {
		$this->login_as_admin();
		$this->assertFalse( WP_Job_Manager_Admin_Notices::is_admin_on_standard_job_manager_screen( [] ) );
		set_current_screen( 'edit-job_listing' );

		$this->assertTrue( WP_Job_Manager_Admin_Notices::is_admin_on_standard_job_manager_screen( [] ) );
	}

	private function get_raw_state() {
		return json_decode( get_option( WP_Job_Manager_Admin_Notices::STATE_OPTION, '[]' ), true );
	}
}
