<?php
/**
 * Tests for the Promoted Jobs enable/disable admin setting.
 *
 * @package wp-job-manager
 */
class Tests_Promoted_Jobs_Setting extends WPJM_BaseTest {

	/**
	 * Clean up option and filters after each test.
	 */
	public function tearDown(): void {
		delete_option( 'job_manager_enable_promoted_jobs' );
		remove_filter( 'job_manager_enable_promoted_jobs', '__return_false', 10 );
		remove_filter( 'job_manager_enable_promoted_jobs', '__return_true', 10 );
		remove_filter( 'job_manager_enable_promoted_jobs', '__return_true', 1 );
		parent::tearDown();
	}

	/**
	 * Test: Option ON + no code filter = enabled.
	 */
	public function test_option_on_no_filter_returns_true() {
		update_option( 'job_manager_enable_promoted_jobs', '1' );

		$this->assertTrue( WP_Job_Manager_Promoted_Jobs_Helper::is_enabled() );
	}

	/**
	 * Test: Option ON + code filter false = disabled.
	 */
	public function test_option_on_with_false_filter_returns_false() {
		update_option( 'job_manager_enable_promoted_jobs', '1' );
		add_filter( 'job_manager_enable_promoted_jobs', '__return_false' );

		$this->assertFalse( WP_Job_Manager_Promoted_Jobs_Helper::is_enabled() );
	}

	/**
	 * Test: Option OFF + no code filter = disabled.
	 */
	public function test_option_off_no_filter_returns_false() {
		update_option( 'job_manager_enable_promoted_jobs', '0' );

		$this->assertFalse( WP_Job_Manager_Promoted_Jobs_Helper::is_enabled() );
	}

	/**
	 * Test: Option OFF + code filter true = enabled (code wins).
	 */
	public function test_option_off_with_true_filter_returns_true() {
		update_option( 'job_manager_enable_promoted_jobs', '0' );
		add_filter( 'job_manager_enable_promoted_jobs', '__return_true', 10 );

		$this->assertTrue( WP_Job_Manager_Promoted_Jobs_Helper::is_enabled() );
	}

	/**
	 * Test: Option OFF + low-priority code filter true = enabled (code wins at any priority).
	 */
	public function test_option_off_with_low_priority_filter_returns_true() {
		update_option( 'job_manager_enable_promoted_jobs', '0' );
		add_filter( 'job_manager_enable_promoted_jobs', '__return_true', 1 );

		$this->assertTrue( WP_Job_Manager_Promoted_Jobs_Helper::is_enabled() );
	}

	/**
	 * Test: Option not set defaults to enabled.
	 */
	public function test_option_not_set_defaults_to_true() {
		delete_option( 'job_manager_enable_promoted_jobs' );

		$this->assertTrue( WP_Job_Manager_Promoted_Jobs_Helper::is_enabled() );
	}

	/**
	 * Test: Setting is registered in the settings array.
	 */
	public function test_setting_exists_in_settings() {
		$settings = new WP_Job_Manager_Settings();
		$all      = $settings->get_settings();

		$found = false;
		foreach ( $all as $section ) {
			if ( ! isset( $section[1] ) || ! is_array( $section[1] ) ) {
				continue;
			}
			foreach ( $section[1] as $field ) {
				if ( isset( $field['name'] ) && 'job_manager_enable_promoted_jobs' === $field['name'] ) {
					$found = true;
					$this->assertSame( 'checkbox', $field['type'] );
					$this->assertSame( '1', $field['std'] );
					break 2;
				}
			}
		}
		$this->assertTrue( $found, 'Setting job_manager_enable_promoted_jobs not found in settings array.' );
	}

	/**
	 * Test: Data cleaner includes the option key.
	 */
	public function test_data_cleaner_includes_option() {
		$options = ( new ReflectionClass( 'WP_Job_Manager_Data_Cleaner' ) )->getConstant( 'OPTIONS' );

		$this->assertContains(
			'job_manager_enable_promoted_jobs',
			$options,
			'Data cleaner OPTIONS should include job_manager_enable_promoted_jobs.'
		);
	}
}
