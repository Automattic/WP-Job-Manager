<?php
include_once WPJM_Unit_Tests_Bootstrap::instance()->includes_dir . '/stubs/class-wp-job-manager-email-valid.php';
include_once WPJM_Unit_Tests_Bootstrap::instance()->includes_dir . '/stubs/class-wp-job-manager-email-invalid.php';

/**
 * Tests for WP_Job_Manager_Email_Notifications.
 *
 * @group email
 */
class WP_Test_WP_Job_Manager_Email_Notifications extends WPJM_BaseTest {
	public function setUp() {
		defined( 'PHPUNIT_WPJM_TESTSUITE' ) || define( 'PHPUNIT_WPJM_TESTSUITE', true );
		parent::setUp();
		reset_phpmailer_instance();
		update_option( 'job_manager_enable_categories', 1 );
		update_option( 'job_manager_enable_types', 1 );
		add_theme_support( 'job-manager-templates' );
		unregister_post_type( 'job_listing' );
		$post_type_instance = WP_Job_Manager_Post_Types::instance();
		$post_type_instance->register_post_types();
		WP_Job_Manager_Email_Notifications::_clear_deferred_notifications();
	}

	public function tearDown() {
		reset_phpmailer_instance();
		WP_Job_Manager_Email_Notifications::_clear_deferred_notifications();
		remove_action( 'shutdown', array( 'WP_Job_Manager_Email_Notifications', '_send_deferred_notifications' ) );
		parent::tearDown();
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::_schedule_notification()
	 * @covers WP_Job_Manager_Email_Notifications::_get_deferred_notification_count()
	 */
	public function test_schedule_notification() {
		$this->assertEquals( 0, WP_Job_Manager_Email_Notifications::_get_deferred_notification_count() );

		WP_Job_Manager_Email_Notifications::_schedule_notification( 'test-notification' );
		$this->assertEquals( 1, WP_Job_Manager_Email_Notifications::_get_deferred_notification_count() );

		WP_Job_Manager_Email_Notifications::_schedule_notification( 'test-notification', array( 'test' => 'test' ) );
		$this->assertEquals( 2, WP_Job_Manager_Email_Notifications::_get_deferred_notification_count() );

		do_action( 'job_manager_send_notification', 'test-notification-action', array( 'test' => 'test' ) );
		$this->assertEquals( 3, WP_Job_Manager_Email_Notifications::_get_deferred_notification_count() );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::_send_deferred_notifications()
	 * @covers WP_Job_Manager_Email_Notifications::send_email()
	 */
	public function test_send_deferred_notifications_valid_email() {
		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertFalse( $mailer->get_sent() );
		add_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_valid_email' ) );
		do_action( 'job_manager_send_notification', 'valid-email', array( 'test' => 'test' ) );
		WP_Job_Manager_Email_Notifications::_send_deferred_notifications();
		remove_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_valid_email' ) );

		$sent_email = $mailer->get_sent();
		$this->assertNotFalse( $sent_email );
		$this->assertInternalType( 'array', $sent_email->to );
		$this->assertTrue( isset( $sent_email->to[0][0] ) );
		$this->assertEquals( 'to@example.com', $sent_email->to[0][0] );
		$this->assertEmpty( $sent_email->cc );
		$this->assertEmpty( $sent_email->bcc );
		$this->assertEquals( 'Test Subject', $sent_email->subject );
		$this->assertContains( "<p><strong>test</strong></p>\n", $sent_email->body );
		$this->assertContains( 'From: From Name <from@example.com>', $sent_email->header );
		$this->assertContains( 'Content-Type: text/html;', $sent_email->header );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::_send_deferred_notifications()
	 */
	public function test_send_deferred_notifications_unknown_email() {
		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertFalse( $mailer->get_sent() );
		add_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_invalid_class_ordinary' ) );
		do_action( 'job_manager_send_notification', 'invalid-email', array( 'test' => 'test' ) );
		WP_Job_Manager_Email_Notifications::_send_deferred_notifications();
		remove_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_invalid_class_ordinary' ) );
		$this->assertFalse( $mailer->get_sent() );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::_send_deferred_notifications()
	 * @covers WP_Job_Manager_Email_Notifications::send_email()
	 */
	public function test_send_deferred_notifications_invalid_args() {
		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertFalse( $mailer->get_sent() );
		add_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_valid_email' ) );
		do_action( 'job_manager_send_notification', 'valid-email', array( 'nope' => 'test' ) );
		WP_Job_Manager_Email_Notifications::_send_deferred_notifications();
		remove_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_valid_email' ) );
		$this->assertFalse( $mailer->get_sent() );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::get_email_notifications()
	 * @covers WP_Job_Manager_Email_Notifications::is_email_notification_valid()
	 */
	public function test_get_email_notifications() {
		$emails = WP_Job_Manager_Email_Notifications::get_email_notifications( false );
		$core_email_notifications = WP_Job_Manager_Email_Notifications::core_email_notifications();
		$this->assertEquals( count( $core_email_notifications ), count( $emails ) );

		foreach ( $core_email_notifications as $email_notification_class ) {
			$email_notification_key = call_user_func( array( $email_notification_class, 'get_key' ) );
			$this->assertArrayHasKey( $email_notification_key, $emails );
			$this->assertValidEmailNotificationConfig( $emails[ $email_notification_key ] );
		}
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::get_email_notifications()
	 * @covers WP_Job_Manager_Email_Notifications::is_email_notification_valid()
	 */
	public function test_get_email_notifications_inject_bad_ordinary_class() {
		add_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_invalid_class_ordinary' ) );
		$emails = WP_Job_Manager_Email_Notifications::get_email_notifications( false );
		remove_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_invalid_class_ordinary' ) );
		$this->assertArrayNotHasKey( 'invalid-email', $emails );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::get_email_notifications()
	 * @covers WP_Job_Manager_Email_Notifications::is_email_notification_valid()
	 */
	public function test_get_email_notifications_inject_bad_class_unknown() {
		add_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_invalid_class_unknown' ) );
		$emails = WP_Job_Manager_Email_Notifications::get_email_notifications( false );
		remove_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_invalid_class_unknown' ) );
		$this->assertArrayNotHasKey( 'invalid-email', $emails );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::get_email_notifications()
	 * @covers WP_Job_Manager_Email_Notifications::is_email_notification_valid()
	 */
	public function test_get_email_notifications_inject_malformed_class() {
		add_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_invalid_class_setup' ) );
		$emails = WP_Job_Manager_Email_Notifications::get_email_notifications( false );
		remove_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_invalid_class_setup' ) );
		$this->assertArrayNotHasKey( 'invalid-email', $emails );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::get_email_notifications()
	 * @covers WP_Job_Manager_Email_Notifications::is_email_notification_valid()
	 */
	public function test_get_email_notifications_inject_valid_email() {
		add_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_valid_email' ) );
		$emails = WP_Job_Manager_Email_Notifications::get_email_notifications( false );
		remove_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_valid_email' ) );
		$this->assertArrayHasKey( 'valid-email', $emails );
		$this->assertValidEmailNotificationConfig( $emails['valid-email'] );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::get_template_file_name()
	 */
	public function test_get_template_file_name_plain() {
		$template_name = md5( microtime( true ) );
		$this->assertEquals( "emails/plain/{$template_name}.php", WP_Job_Manager_Email_Notifications::get_template_file_name( $template_name, true ) );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::get_template_file_name()
	 */
	public function test_get_template_file_name_rich() {
		$template_name = md5( microtime( true ) );
		$this->assertEquals( "emails/{$template_name}.php", WP_Job_Manager_Email_Notifications::get_template_file_name( $template_name, false ) );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::output_job_details()
	 * @covers WP_Job_Manager_Email_Notifications::get_job_detail_fields()
	 */
	public function test_output_job_details() {
		add_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_valid_email' ) );
		$emails = WP_Job_Manager_Email_Notifications::get_email_notifications( false );
		remove_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_valid_email' ) );
		$email = $emails['valid-email'];
		$job = $this->get_valid_job();

		ob_start();
		WP_Job_Manager_Email_Notifications::output_job_details( $job, $email, true, true );
		$content = ob_get_clean();
		$this->assertContains( 'Job title: ' . $job->post_title, $content );
		$this->assertContains( 'Location: ' . $job->_job_location, $content );
		$this->assertContains( 'Job type: Full Time', $content );
		$this->assertContains( 'Job category: Weird', $content );
		$this->assertContains( 'Company name: ' . $job->_company_name, $content );
		$this->assertContains( 'Company website: ' . $job->_company_website, $content );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::output_header()
	 */
	public function test_output_header() {
		add_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_valid_email' ) );
		$emails = WP_Job_Manager_Email_Notifications::get_email_notifications( false );
		remove_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_valid_email' ) );
		$email = $emails['valid-email'];
		ob_start();
		WP_Job_Manager_Email_Notifications::output_header( $email, true, false );
		$content = ob_get_clean();
		$this->assertContains( '<!DOCTYPE html>', $content );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::output_footer()
	 */
	public function test_output_footer() {
		add_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_valid_email' ) );
		$emails = WP_Job_Manager_Email_Notifications::get_email_notifications( false );
		remove_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_valid_email' ) );
		$email = $emails['valid-email'];
		ob_start();
		WP_Job_Manager_Email_Notifications::output_footer( $email, true, false );
		$content = ob_get_clean();
		$this->assertContains( '</html>', $content );
	}

	/**
	 * Helper Methods
	 */
	public function inject_email_config_invalid_class_unknown( $emails ) {
		$emails[] = 'WP_Job_Manager_BoopBeepBoop';
		return $emails;
	}

	public function inject_email_config_invalid_class_ordinary( $emails ) {
		$emails[] = 'WP_Job_Manager';
		return $emails;
	}

	public function inject_email_config_invalid_class_setup( $emails ) {
		$emails[] = 'WP_Job_Manager_Email_Invalid';
		return $emails;
	}

	public function inject_email_config_valid_email( $emails ) {
		$emails[] = 'WP_Job_Manager_Email_Valid';
		return $emails;
	}

	protected function get_valid_job() {
		$full_time_term = wp_create_term( 'Full Time', 'job_listing_type' );
		$weird_cat_term = wp_create_term( 'Weird', 'job_listing_category' );
		$job_args = array(
			'post_title'   => 'Job Post-' . md5( microtime( true ) ),
			'post_content' => 'Job Description-' . md5( microtime( true ) ),
			'meta_input'   => array(
				'_job_location'    => 'Job Location-' . md5( microtime( true ) ),
				'_company_name'    => 'Company-' . md5( microtime( true ) ),
				'_company_website' => 'http://' . md5( microtime( true ) ) .'.com',
			),
			'tax_input' => array(
				'job_listing_type'     => $full_time_term['term_id'],
				'job_listing_category' => $weird_cat_term['term_id'],
			),
		);
		return get_post( $this->factory->job_listing->create( $job_args ) );
	}

	/**
	 * @param array $core_email_class
	 */
	protected function assertValidEmailNotificationConfig( $core_email_class ) {
		$this->assertTrue( is_string( $core_email_class ) );
		$this->assertTrue( class_exists( $core_email_class ) );
		$this->assertTrue( is_subclass_of( $core_email_class, 'WP_Job_Manager_Email' ) );

		// // PHP 5.2: Using `call_user_func()` but `$core_email_class::get_key()` preferred.
		$this->assertTrue( is_string( call_user_func( array( $core_email_class, 'get_key') ) ) );
		$this->assertTrue( is_string( call_user_func( array( $core_email_class, 'get_name') ) ) );
	}
}
