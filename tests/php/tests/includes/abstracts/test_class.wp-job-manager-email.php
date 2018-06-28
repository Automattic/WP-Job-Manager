<?php
require_once WPJM_Unit_Tests_Bootstrap::instance()->includes_dir . '/stubs/class-wp-job-manager-email-valid.php';

/**
 * Tests for WP_Job_Manager_Email.
 *
 * @group email
 */
class WP_Test_WP_Job_Manager_Email extends WPJM_BaseTest {
	/**
	 * @covers WP_Job_Manager_Email::get_attachments()
	 */
	public function test_get_attachments() {
		$test = new WP_Job_Manager_Email_Valid( array(), $this->get_base_settings() );
		$this->assertEquals( array(), $test->get_attachments() );
	}

	/**
	 * @covers WP_Job_Manager_Email::get_cc()
	 */
	public function test_get_cc() {
		$test = new WP_Job_Manager_Email_Valid( array(), $this->get_base_settings() );
		$this->assertNull( $test->get_cc() );
	}

	/**
	 * @covers WP_Job_Manager_Email::get_headers()
	 */
	public function test_get_headers() {
		$test = new WP_Job_Manager_Email_Valid( array(), $this->get_base_settings() );
		$this->assertEquals( array(), $test->get_headers() );
	}

	/**
	 * @covers WP_Job_Manager_Email::get_plain_content()
	 */
	public function test_get_plain_contents() {
		$args = array( 'test' => md5( microtime( true ) ) );
		$test = new WP_Job_Manager_Email_Valid( $args, $this->get_base_settings() );
		$this->assertEquals( $args['test'], $test->get_plain_content() );
	}

	protected function get_base_settings() {
		return array(
			'enabled'    => '1',
			'plain_text' => '0',
		);
	}
}
