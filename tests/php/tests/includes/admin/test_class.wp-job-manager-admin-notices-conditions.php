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

	public function test_check_without_conditions_passes_only_for_wpjm_screens_by_default() {
		$this->login_as_admin();
		$result = $this->get_instance()->check( [] );
		$this->assertFalse( $result, 'Must not pass for non-WPJM screens' );
		foreach ( WP_Job_Manager_Admin_Notices_Conditions::ALL_WPJM_SCREEN_IDS as $wpjm_screen_id ) {
			set_current_screen( $wpjm_screen_id );
			$result = $this->get_instance()->check( [] );
			$this->assertTrue( $result, "Must pass for WPJM screen: $wpjm_screen_id" );
		}
	}

	public function test_check_screens_condition_passes_for_explicit_screen_id() {
		$this->login_as_admin();
		set_current_screen( 'some-random-screen' );
		$result = $this->get_instance()->check( [
			[
				'type'    => 'screens',
				'screens' => [ 'some-random-screen' ],
			],
		] );
		$this->assertTrue( $result, 'Must pass for explicit screen' );
	}

	public function test_check_screens_condition_overrides_default_screens_with_explicit_screen_id() {
		$this->login_as_admin();
		foreach ( WP_Job_Manager_Admin_Notices_Conditions::ALL_WPJM_SCREEN_IDS as $wpjm_screen_id ) {
			set_current_screen( $wpjm_screen_id );
			$result = $this->get_instance()->check( [
				[
					'type'    => 'screens',
					'screens' => [ 'some-random-screen' ],
				],
			] );
			$this->assertFalse( $result, "Must not pass for WPJM screen: $wpjm_screen_id" );
		}
	}

	public function test_check_min_php_condition_fails_for_unexpectedly_high_php_version() {
		$this->login_as_admin();
		set_current_screen( 'edit-job_listing' );
		$result = $this->get_instance()->check( [
			[
				'type'    => 'min_php',
				'version' => '9999',
			],
		] );
		$this->assertFalse( $result, 'Must not pass for unexpectedly high PHP version' );
	}

	public function test_check_min_php_condition_passes_for_unexpectedly_low_php_version() {
		$this->login_as_admin();
		set_current_screen( 'edit-job_listing' );
		$result = $this->get_instance()->check( [
			[
				'type'    => 'min_php',
				'version' => '1',
			],
		] );
		$this->assertTrue( $result, 'Must pass for expectedly low PHP version' );
	}

	public function test_check_min_wp_condition_fails_for_unexpectedly_high_wp_version() {
		$this->login_as_admin();
		set_current_screen( 'edit-job_listing' );
		$result = $this->get_instance()->check( [
			[
				'type'    => 'min_wp',
				'version' => '9999',
			],
		] );
		$this->assertFalse( $result, 'Must not pass for unexpectedly high WP version' );
	}

	public function test_check_min_wp_condition_passes_for_unexpectedly_low_wp_version() {
		$this->login_as_admin();
		set_current_screen( 'edit-job_listing' );
		$result = $this->get_instance()->check( [
			[
				'type'    => 'min_wp',
				'version' => '1',
			],
		] );
		$this->assertTrue( $result, 'Must pass for expectedly low WP version' );
	}

	public function test_check_user_cap_condition_passes_for_admin() {
		$this->login_as_admin();
		set_current_screen( 'edit-job_listing' );
		$result = $this->get_instance()->check( [
			[
				'type'         => 'user_cap',
				'capabilities' => [ 'manage_options' ],
			],
		] );
		$this->assertTrue( $result, 'Must pass for admin' );
	}

	public function test_check_user_cap_condition_fails_for_employer() {
		$this->login_as_employer();
		set_current_screen( 'edit-job_listing' );
		$result = $this->get_instance()->check( [
			[
				'type'         => 'user_cap',
				'capabilities' => [ 'manage_options' ],
			],
		] );
		$this->assertFalse( $result, 'Must not pass for employer' );
	}

	public function test_check_plugins_condition_passes_when_installed_plugin_expected_to_be_installed() {
		$this->login_as_admin();
		set_current_screen( 'edit-job_listing' );
		$result = $this->get_instance( [
			'some-random-plugin/some-random-plugin.php' => [ 'Version' => '5.0.0' ],
		] )->check( [
			[
				'type'    => 'plugins',
				'plugins' => [
					'some-random-plugin/some-random-plugin.php' => true,
				],
			],
		] );
		$this->assertTrue( $result, 'Must pass when expected plugin is installed' );
	}

	public function test_check_plugins_condition_fails_when_installed_plugin_expected_not_to_be_installed() {
		$this->login_as_admin();
		set_current_screen( 'edit-job_listing' );
		$result = $this->get_instance( [
			'some-random-plugin/some-random-plugin.php' => [ 'Version' => '5.0.0' ],
		] )->check( [
			[
				'type'    => 'plugins',
				'plugins' => [
					'some-random-plugin/some-random-plugin.php' => false,
				],
			],
		] );
		$this->assertFalse( $result, 'Must not pass when expected plugin is not installed' );
	}

	public function test_check_plugins_condition_honors_min_version() {
		$this->login_as_admin();
		set_current_screen( 'edit-job_listing' );
		$instance = $this->get_instance( [
			'some-random-plugin/some-random-plugin.php' => [ 'Version' => '5.0.0' ],
		] );
		$result   = $instance->check( [
			[
				'type'    => 'plugins',
				'plugins' => [
					'some-random-plugin/some-random-plugin.php' => [ 'min' => '1.0.0' ],
				],
			],
		] );
		$this->assertTrue( $result, 'Must pass when plugin is over minimum version' );
		$result = $instance->check( [
			[
				'type'    => 'plugins',
				'plugins' => [
					'some-random-plugin/some-random-plugin.php' => [ 'min' => '6.0.0' ],
				],
			],
		] );
		$this->assertFalse( $result, 'Must pass not when plugin is not over minimum version' );
	}

	public function test_check_plugins_condition_honors_max_version() {
		$this->login_as_admin();
		set_current_screen( 'edit-job_listing' );
		$instance = $this->get_instance( [
			'some-random-plugin/some-random-plugin.php' => [ 'Version' => '5.0.0' ],
		] );
		$result   = $instance->check( [
			[
				'type'    => 'plugins',
				'plugins' => [
					'some-random-plugin/some-random-plugin.php' => [ 'max' => '1.0.0' ],
				],
			],
		] );
		$this->assertFalse( $result, 'Must not pass when plugin is over maximum version' );
		$result = $instance->check( [
			[
				'type'    => 'plugins',
				'plugins' => [
					'some-random-plugin/some-random-plugin.php' => [ 'max' => '6.0.0' ],
				],
			],
		] );
		$this->assertTrue( $result, 'Must pass when plugin is not over maximum version' );
	}


	/**
	 * Get the mock instance.
	 *
	 * @return WP_Job_Manager_Admin_Notices_Conditions
	 */
	public function get_instance( $active_plugins = [] ) {

		$mock = $this->getMockBuilder( 'WP_Job_Manager_Admin_Notices_Conditions' )
			->disableOriginalConstructor()
			->setMethods( [ 'get_active_plugins' ] )
			->getMock();

		$mock->expects( $this->any() )
			->method( 'get_active_plugins' )
			->willReturn( $active_plugins );

		return $mock;
	}
}
