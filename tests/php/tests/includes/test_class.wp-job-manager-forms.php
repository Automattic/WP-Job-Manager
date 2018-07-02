<?php

class WP_Test_WP_Job_Manager_Forms extends WPJM_BaseTest {

	public function setUp() {
		parent::setUp();
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-form.php';
		include_once WPJM_Unit_Tests_Bootstrap::instance()->includes_dir . '/stubs/class-wp-job-manager-form-test.php';
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Forms::load_posted_form
	 */
	public function test_load_posted_form_too_legit_to_quit() {
		WP_Job_manager_Form_Test::reset();
		$this->assertFalse( WP_Job_manager_Form_Test::has_instance() );
		$_POST['job_manager_form'] = 'Test';
		$instance                  = WP_Job_Manager_Forms::instance();
		$instance->load_posted_form();
		$this->assertTrue( WP_Job_manager_Form_Test::has_instance() );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Forms::load_posted_form
	 */
	public function test_load_posted_form_not_legit_so_quit() {
		WP_Job_manager_Form_Test::reset();
		$this->assertFalse( WP_Job_manager_Form_Test::has_instance() );
		unset( $_POST['job_manager_form'] );
		$instance = WP_Job_Manager_Forms::instance();
		$instance->load_posted_form();
		$this->assertFalse( WP_Job_manager_Form_Test::has_instance() );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Forms::get_form
	 */
	public function test_get_form_good_form() {
		WP_Job_manager_Form_Test::reset();
		$this->assertFalse( WP_Job_manager_Form_Test::has_instance() );
		$instance = WP_Job_Manager_Forms::instance();
		$result   = $instance->get_form( 'Test' );
		$this->assertTrue( WP_Job_manager_Form_Test::has_instance() );
		$this->assertEquals( 'success', $result );
	}

	/**
	 * @since 1.27.0
	 * @covers WP_Job_Manager_Forms::get_form
	 */
	public function test_get_form_bad_form() {
		WP_Job_manager_Form_Test::reset();
		$this->assertFalse( WP_Job_manager_Form_Test::has_instance() );
		$instance = WP_Job_Manager_Forms::instance();
		$result   = $instance->get_form( 'Boop' );
		$this->assertFalse( WP_Job_manager_Form_Test::has_instance() );
		$this->assertEmpty( $result );
	}
}
