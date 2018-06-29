<?php
require_once WPJM_Unit_Tests_Bootstrap::instance()->includes_dir . '/stubs/class-wp-job-manager-email-valid.php';
require_once WPJM_Unit_Tests_Bootstrap::instance()->includes_dir . '/stubs/class-wp-job-manager-email-invalid.php';

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
		WP_Job_Manager_Email_Notifications::clear_deferred_notifications();
		WP_Job_Manager_Email_Notifications::maybe_init();
	}

	public function tearDown() {
		reset_phpmailer_instance();
		WP_Job_Manager_Email_Notifications::clear_deferred_notifications();
		remove_action( 'shutdown', array( 'WP_Job_Manager_Email_Notifications', 'send_deferred_notifications' ) );
		parent::tearDown();
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::schedule_notification()
	 * @covers WP_Job_Manager_Email_Notifications::get_deferred_notification_count()
	 */
	public function test_schedule_notification() {
		$this->assertEquals( 0, WP_Job_Manager_Email_Notifications::get_deferred_notification_count() );

		WP_Job_Manager_Email_Notifications::schedule_notification( 'test-notification' );
		$this->assertEquals( 1, WP_Job_Manager_Email_Notifications::get_deferred_notification_count() );

		WP_Job_Manager_Email_Notifications::schedule_notification( 'test-notification', array( 'test' => 'test' ) );
		$this->assertEquals( 2, WP_Job_Manager_Email_Notifications::get_deferred_notification_count() );

		do_action( 'job_manager_send_notification', 'test-notification-action', array( 'test' => 'test' ) );
		$this->assertEquals( 3, WP_Job_Manager_Email_Notifications::get_deferred_notification_count() );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::send_deferred_notifications()
	 * @covers WP_Job_Manager_Email_Notifications::send_email()
	 */
	public function test_send_deferred_notifications_valid_email() {
		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertFalse( $mailer->get_sent() );
		add_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_valid_email' ) );
		do_action( 'job_manager_send_notification', 'valid_email', array( 'test' => 'test' ) );
		WP_Job_Manager_Email_Notifications::send_deferred_notifications();
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
	 * @covers WP_Job_Manager_Email_Notifications::send_deferred_notifications()
	 */
	public function test_send_deferred_notifications_unknown_email() {
		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertFalse( $mailer->get_sent() );
		add_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_invalid_class_ordinary' ) );
		do_action( 'job_manager_send_notification', 'invalid_email', array( 'test' => 'test' ) );
		WP_Job_Manager_Email_Notifications::send_deferred_notifications();
		remove_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_invalid_class_ordinary' ) );
		$this->assertFalse( $mailer->get_sent() );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::send_deferred_notifications()
	 * @covers WP_Job_Manager_Email_Notifications::send_email()
	 */
	public function test_send_deferred_notifications_invalid_args() {
		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertFalse( $mailer->get_sent() );
		add_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_valid_email' ) );
		do_action( 'job_manager_send_notification', 'valid_email', array( 'nope' => 'test' ) );
		WP_Job_Manager_Email_Notifications::send_deferred_notifications();
		remove_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_valid_email' ) );
		$this->assertFalse( $mailer->get_sent() );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::get_email_notifications()
	 * @covers WP_Job_Manager_Email_Notifications::is_email_notification_valid()
	 */
	public function test_get_email_notifications() {
		$emails                   = WP_Job_Manager_Email_Notifications::get_email_notifications( false );
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
		$this->assertArrayNotHasKey( 'invalid_email', $emails );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::get_email_notifications()
	 * @covers WP_Job_Manager_Email_Notifications::is_email_notification_valid()
	 */
	public function test_get_email_notifications_inject_bad_class_unknown() {
		add_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_invalid_class_unknown' ) );
		$emails = WP_Job_Manager_Email_Notifications::get_email_notifications( false );
		remove_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_invalid_class_unknown' ) );
		$this->assertArrayNotHasKey( 'invalid_email', $emails );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::get_email_notifications()
	 * @covers WP_Job_Manager_Email_Notifications::is_email_notification_valid()
	 */
	public function test_get_email_notifications_inject_malformed_class() {
		add_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_invalid_class_setup' ) );
		$emails = WP_Job_Manager_Email_Notifications::get_email_notifications( false );
		remove_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_invalid_class_setup' ) );
		$this->assertArrayNotHasKey( 'invalid_email', $emails );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::get_email_notifications()
	 * @covers WP_Job_Manager_Email_Notifications::is_email_notification_valid()
	 */
	public function test_get_email_notifications_inject_valid_email() {
		$emails = $this->get_valid_emails();
		$this->assertArrayHasKey( 'valid_email', $emails );
		$this->assertValidEmailNotificationConfig( $emails['valid_email'] );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::output_job_details()
	 * @covers WP_Job_Manager_Email_Notifications::get_job_detail_fields()
	 */
	public function test_output_job_details() {
		$email = $this->get_valid_email();
		$job   = $this->get_valid_job();

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
		$email = $this->get_valid_email();
		ob_start();
		WP_Job_Manager_Email_Notifications::output_header( $email, true, false );
		$content = ob_get_clean();
		$this->assertContains( '<!DOCTYPE html>', $content );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::output_footer()
	 */
	public function test_output_footer() {
		$email = $this->get_valid_email();
		ob_start();
		WP_Job_Manager_Email_Notifications::output_footer( $email, true, false );
		$content = ob_get_clean();
		$this->assertContains( '</html>', $content );
	}

	/**
	 * @covers WP_Job_Manager_Email_Notifications::add_email_settings()
	 * @covers WP_Job_Manager_Email_Notifications::get_email_setting_fields()
	 * @covers WP_Job_Manager_Email_Notifications::get_email_setting_defaults()
	 */
	public function test_add_email_settings() {

		add_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_valid_email' ) );
		$emails   = WP_Job_Manager_Email_Notifications::get_email_notifications( false );
		$settings = WP_Job_Manager_Email_Notifications::add_email_settings( array(), WP_Job_Manager_Email::get_context() );
		remove_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_valid_email' ) );

		$this->assertArrayHasKey( 'email_notifications', $settings );
		$email_notifications_settings = $settings['email_notifications'];

		$this->assertTrue( isset( $email_notifications_settings[0] ) );
		$this->assertInternalType( 'string', $email_notifications_settings[0] );
		$this->assertTrue( isset( $email_notifications_settings[1] ) );
		$this->assertInternalType( 'array', $email_notifications_settings[1] );

		$settings      = $email_notifications_settings[1];
		$email_keys    = array_keys( $emails );
		$email_classes = array_values( $emails );
		$this->assertEquals( count( $emails ), count( $settings ) );

		foreach ( $settings as $key => $setting ) {
			$email_class              = $email_classes[ $key ];
			$email_key                = $email_keys[ $key ];
			$email_settings           = call_user_func( array( $email_class, 'get_setting_fields' ) );
			$email_is_default_enabled = call_user_func( array( $email_class, 'is_default_enabled' ) );
			$defaults                 = array(
				'enabled'    => $email_is_default_enabled ? '1' : '0',
				'plain_text' => '0',
			);
			foreach ( $email_settings as $email_setting ) {
				$defaults[ $email_setting['name'] ] = $email_setting['std'];
			}

			$this->assertArrayHasKey( 'type', $setting );
			$this->assertEquals( 'multi_enable_expand', $setting['type'] );
			$this->assertArrayHasKey( 'class', $setting );
			$this->assertArrayHasKey( 'name', $setting );
			$this->assertEquals( WP_Job_Manager_Email_Notifications::EMAIL_SETTING_PREFIX . $email_key, $setting['name'] );
			$this->assertArrayHasKey( 'enable_field', $setting );
			$this->assertInternalType( 'array', $setting['enable_field'] );
			$this->assertArrayHasKey( 'label', $setting );
			$this->assertArrayHasKey( 'std', $setting );
			$this->assertEquals( $setting['std'], $defaults );
			$this->assertArrayHasKey( 'settings', $setting );
			$this->assertEquals( count( $setting['settings'] ), count( $email_settings ) + 1 );
		}
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

	protected function get_valid_email() {
		$emails = $this->get_valid_emails();
		return $emails['valid_email'];
	}

	protected function get_valid_emails() {
		add_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_valid_email' ) );
		$emails = WP_Job_Manager_Email_Notifications::get_email_notifications( false );
		remove_filter( 'job_manager_email_notifications', array( $this, 'inject_email_config_valid_email' ) );
		return $emails;
	}

	protected function get_valid_job() {
		$full_time_term = wp_create_term( 'Full Time', 'job_listing_type' );
		$weird_cat_term = wp_create_term( 'Weird', 'job_listing_category' );
		$job_args       = array(
			'post_title'   => 'Job Post-' . md5( microtime( true ) ),
			'post_content' => 'Job Description-' . md5( microtime( true ) ),
			'meta_input'   => array(
				'_job_location'    => 'Job Location-' . md5( microtime( true ) ),
				'_company_name'    => 'Company-' . md5( microtime( true ) ),
				'_company_website' => 'http://' . md5( microtime( true ) ) . '.com',
			),
			'tax_input'    => array(
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
		$this->assertTrue( is_string( call_user_func( array( $core_email_class, 'get_key' ) ) ) );
		$this->assertTrue( is_string( call_user_func( array( $core_email_class, 'get_name' ) ) ) );
	}
}
