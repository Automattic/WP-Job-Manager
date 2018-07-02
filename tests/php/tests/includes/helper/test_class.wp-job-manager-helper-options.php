<?php
/**
 * @group helper
 * @group helper-options
 */
class WP_Test_WP_Job_Manager_Helper_Options extends WPJM_Helper_Base_Test {
	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper_Options::update
	 */
	public function test_update_simple() {
		$this->setup_master_option();
		WP_Job_Manager_Helper_Options::update( 'test', 'licence_key', 'new-value' );
		$new_option = $this->get_master_option();
		$this->assertTrue( isset( $new_option['test']['licence_key'] ) );
		$this->assertEquals( 'new-value', $new_option['test']['licence_key'] );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper_Options::get
	 */
	public function test_get_return_default() {
		$result_expected = 'simple';
		$result          = WP_Job_Manager_Helper_Options::get( 'test', 'licence_key', $result_expected );
		$this->assertEquals( $result_expected, $result );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper_Options::get
	 */
	public function test_get_return_value() {
		$this->setup_master_option();
		$result = WP_Job_Manager_Helper_Options::get( 'test', 'licence_key', 'simple' );
		$this->assertEquals( 'abcd', $result );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper_Options::get
	 * @covers WP_Job_Manager_Helper_Options::attempt_legacy_restore
	 */
	public function test_get_return_legacy() {
		$this->setup_legacy_options();
		$licence_key_result = WP_Job_Manager_Helper_Options::get( 'legacy', 'licence_key', 'simple' );
		$this->assertEquals( 'legacy-abcd', $licence_key_result );

		$email_result = WP_Job_Manager_Helper_Options::get( 'legacy', 'email', 'simple' );
		$this->assertEquals( 'legacy@test.dev', $email_result );

		$errors_result = WP_Job_Manager_Helper_Options::get( 'legacy', 'errors', 'simple' );
		$this->assertEquals( 'legacy-errors', $errors_result );

		$errors_hide_key_notice = WP_Job_Manager_Helper_Options::get( 'legacy', 'hide_key_notice', 'simple' );
		$this->assertEquals( 'legacy-hide', $errors_hide_key_notice );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper_Options::delete
	 */
	public function test_delete_simple() {
		$this->setup_master_option();
		$result = WP_Job_Manager_Helper_Options::delete( 'test', 'licence_key' );

		$new_option = $this->get_master_option();
		$this->assertFalse( isset( $new_option['test']['licence_key'] ) );
	}

	private function setup_legacy_options() {
		update_option( 'legacy_licence_key', 'legacy-abcd' );
		update_option( 'legacy_email', 'legacy@test.dev' );
		update_option( 'legacy_errors', 'legacy-errors' );
		update_option( 'legacy_hide_key_notice', 'legacy-hide' );
	}

	private function setup_master_option( $value = null ) {
		if ( null === $value ) {
			$value = array(
				'test' => array(
					'licence_key'     => 'abcd',
					'email'           => 'local@local.dev',
					'errors'          => null,
					'hide_key_notice' => false,
				),
			);
		}
		update_option( 'job_manager_helper', $value );
	}

	private function get_master_option() {
		return get_option( 'job_manager_helper', array() );
	}
}
