<?php
/**
 * @group helper
 * @group helper-base
 */
class WP_Test_WP_Job_Manager_Helper extends WPJM_Helper_Base_Test {
	/**
	 * Tests the WP_Job_Manager_Helper::instance() always returns the same `WP_Job_Manager_Helper` instance.
	 *
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::instance
	 */
	public function test_wp_job_manager_instance() {
		$instance = WP_Job_Manager_Helper::instance();
		// check the class.
		$this->assertInstanceOf( 'WP_Job_Manager_Helper', $instance, 'Job Manager Helper object is instance of WP_Job_Manager_Helper class' );

		// check it always returns the same object.
		$this->assertSame( WP_Job_Manager_Helper::instance(), $instance, 'WP_Job_Manager_Helper::instance() must always return the same object' );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::admin_init
	 * @requires PHP 5.3.0
	 */
	public function test_admin_init_no_dismiss() {
		$instance     = $this->getMockHelper();
		$product_slug = 'test';
		$default      = WP_Job_Manager_Helper_Options::get( $product_slug, 'hide_key_notice', null );
		$this->assertNull( $default );
		unset( $_GET['dismiss-wpjm-licence-notice'] );
		$instance->admin_init();
		$key_notice_status = WP_Job_Manager_Helper_Options::get( $product_slug, 'hide_key_notice', null );
		$this->assertNull( $key_notice_status );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::admin_init
	 * @requires PHP 5.3.0
	 */
	public function test_admin_init_with_dismiss() {
		$instance     = $this->getMockHelper();
		$product_slug = 'test';
		$default      = WP_Job_Manager_Helper_Options::get( $product_slug, 'hide_key_notice', null );
		$this->assertNull( $default );
		$_GET['dismiss-wpjm-licence-notice'] = $product_slug;
		$instance->admin_init();
		$key_notice_status = WP_Job_Manager_Helper_Options::get( $product_slug, 'hide_key_notice', null );
		$this->assertTrue( $key_notice_status );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::has_licenced_products
	 * @covers WP_Job_Manager_Helper::get_plugin_version
	 * @covers WP_Job_Manager_Helper::get_plugin_licence
	 * @requires PHP 5.3.0
	 */
	public function test_check_for_updates_has_update() {
		$instance       = $this->getMockHelper( $this->plugin_data_with_update() );
		$data           = new stdClass();
		$data->response = array();
		$instance->check_for_updates( $data );
		$this->assertTrue( isset( $data->response['test/test.php'] ) );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::has_licenced_products
	 * @covers WP_Job_Manager_Helper::get_plugin_version
	 * @covers WP_Job_Manager_Helper::get_plugin_licence
	 * @requires PHP 5.3.0
	 */
	public function test_check_for_updates_no_update() {
		$instance       = $this->getMockHelper( $this->plugin_data_without_update() );
		$data           = new stdClass();
		$data->response = array();
		$instance->check_for_updates( $data );
		$this->assertEmpty( $data->response );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::has_licenced_products
	 * @covers WP_Job_Manager_Helper::get_plugin_version
	 * @covers WP_Job_Manager_Helper::get_plugin_licence
	 * @requires PHP 5.3.0
	 */
	public function test_check_for_updates_no_license() {
		$instance = $this->getMockHelper( $this->plugin_data_with_update() );

		WP_Job_Manager_Helper_Options::delete( 'test', 'licence_key' );
		WP_Job_Manager_Helper_Options::delete( 'test', 'email' );

		$data           = new stdClass();
		$data->response = array();
		$instance->check_for_updates( $data );
		$this->assertEmpty( $data->response );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::plugin_activated
	 * @requires PHP 5.3.0
	 */
	public function test_plugin_activated_actual_plugin() {
		$instance     = $this->getMockHelper();
		$product_slug = 'test';
		WP_Job_Manager_Helper_Options::update( $product_slug, 'hide_key_notice', true );
		$this->assertTrue( WP_Job_Manager_Helper_Options::get( $product_slug, 'hide_key_notice', null ) );
		$instance->plugin_activated( 'test/test.php' );
		$this->assertNull( WP_Job_Manager_Helper_Options::get( $product_slug, 'hide_key_notice', null ) );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::plugin_activated
	 * @requires PHP 5.3.0
	 */
	public function test_plugin_activated_untracked_plugin() {
		$instance     = $this->getMockHelper();
		$product_slug = 'rhino';
		WP_Job_Manager_Helper_Options::update( $product_slug, 'hide_key_notice', true );
		$this->assertTrue( WP_Job_Manager_Helper_Options::get( $product_slug, 'hide_key_notice', null ) );
		$instance->plugin_activated( 'rhino/rhino.php' );
		$this->assertTrue( WP_Job_Manager_Helper_Options::get( $product_slug, 'hide_key_notice', null ) );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::plugin_deactivated
	 * @covers WP_Job_Manager_Helper::deactivate_licence
	 * @requires PHP 5.3.0
	 */
	public function test_plugin_deactivated_actual_plugin() {
		$instance = $this->getMockHelper();
		$api      = $instance->_get_api();
		$api->expects( $this->once() )->method( 'deactivate' );

		$product_slug = 'test';
		WP_Job_Manager_Helper_Options::update( $product_slug, 'hide_key_notice', true );
		$this->assertNotEmpty( WP_Job_Manager_Helper_Options::get( $product_slug, 'licence_key', null ) );
		$this->assertNotEmpty( WP_Job_Manager_Helper_Options::get( $product_slug, 'email', null ) );
		$instance->plugin_deactivated( 'test/test.php' );
		$this->assertEmpty( WP_Job_Manager_Helper_Options::get( $product_slug, 'licence_key', null ) );
		$this->assertEmpty( WP_Job_Manager_Helper_Options::get( $product_slug, 'email', null ) );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::plugins_api
	 * @requires PHP 5.3.0
	 */
	public function test_plugins_api_unknown_plugin() {
		$instance = $this->getMockHelper();

		$plugin_slug = 'rhino';
		$response    = new stdClass();
		$args        = new stdClass();
		$args->slug  = $plugin_slug;
		$result      = $instance->plugins_api( $response, 'plugin_information', $args );
		$this->assertSame( $response, $result );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::plugins_api
	 * @requires PHP 5.3.0
	 */
	public function test_plugins_api_empty_plugin() {
		$instance = $this->getMockHelper();

		$response = new stdClass();
		$args     = new stdClass();
		$result   = $instance->plugins_api( $response, 'plugin_information', $args );
		$this->assertSame( $response, $result );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::plugins_api
	 * @requires PHP 5.3.0
	 */
	public function test_plugins_api_bad_action() {
		$instance = $this->getMockHelper();

		$plugin_slug = 'rhino';
		$response    = new stdClass();
		$args        = new stdClass();
		$args->slug  = $plugin_slug;
		$args        = new stdClass( array( 'slug' => $plugin_slug ) );
		$result      = $instance->plugins_api( $response, 'what_the_what', $args );
		$this->assertSame( $response, $result );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::plugins_api
	 * @requires PHP 5.3.0
	 */
	public function test_plugins_api_valid_plugin() {
		$instance = $this->getMockHelper();

		$plugin_slug = 'test';
		$response    = new stdClass();
		$args        = new stdClass();
		$args->slug  = $plugin_slug;
		$result      = $instance->plugins_api( $response, 'plugin_information', $args );
		$expected    = (object) $this->result_plugin_information();
		$this->assertEquals( $expected, $result );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::plugin_links
	 * @requires PHP 5.3.0
	 */
	public function test_plugin_links_valid_plugin_valid_license() {
		$instance = $this->getMockHelper();
		$this->enable_update_plugins_cap();
		$actions = $instance->plugin_links( array(), 'test/test.php' );
		$this->disable_update_plugins_cap();
		$this->assertCount( 1, $actions );
		$this->assertContains( __( 'Manage License', 'wp-job-manager' ), $actions[0] );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::plugin_links
	 * @requires PHP 5.3.0
	 */
	public function test_plugin_links_valid_plugin_invalid_license() {
		$instance = $this->getMockHelper();
		$this->enable_update_plugins_cap();
		WP_Job_Manager_Helper_Options::delete( 'test', 'licence_key' );
		WP_Job_Manager_Helper_Options::delete( 'test', 'email' );
		$actions = $instance->plugin_links( array(), 'test/test.php' );
		$this->disable_update_plugins_cap();
		$this->assertCount( 1, $actions );
		$this->assertContains( __( 'Activate License', 'wp-job-manager' ), $actions[0] );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::plugin_links
	 * @requires PHP 5.3.0
	 */
	public function test_plugin_links_invalid_plugin() {
		$instance = $this->getMockHelper();
		$this->enable_update_plugins_cap();
		$actions = $instance->plugin_links( array(), 'rhino/rhino.php' );
		$this->disable_update_plugins_cap();
		$this->assertCount( 0, $actions );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::plugin_links
	 * @requires PHP 5.3.0
	 */
	public function test_plugin_links_invalid_cap() {
		$instance = $this->getMockHelper();
		$actions  = $instance->plugin_links( array(), 'test/test.php' );
		$this->assertCount( 0, $actions );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::is_product_installed
	 * @requires PHP 5.3.0
	 */
	public function test_is_product_installed_valid() {
		$instance = $this->getMockHelper();
		$result   = $instance->is_product_installed( 'test' );
		$this->assertTrue( $result );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::is_product_installed
	 * @requires PHP 5.3.0
	 */
	public function test_is_product_installed_invalid() {
		$instance = $this->getMockHelper();
		$result   = $instance->is_product_installed( 'rhino' );
		$this->assertFalse( $result );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::has_licenced_products
	 * @covers WP_Job_Manager_Helper::get_plugin_info
	 * @requires PHP 5.3.0
	 */
	public function test_has_licenced_products_true() {
		// Simulate no installed plugins.
		$instance = $this->getMockHelper( array() );
		$this->assertFalse( $instance->has_licenced_products() );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::has_licenced_products
	 * @covers WP_Job_Manager_Helper::get_plugin_info
	 * @requires PHP 5.3.0
	 */
	public function test_has_licenced_products_false() {
		// Simulate a installed plugin.
		$instance = $this->getMockHelper( array( 'test' => array() ) );
		$this->assertTrue( $instance->has_licenced_products() );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::get_plugin_licence
	 */
	public function test_get_plugin_license_valid() {
		WP_Job_Manager_Helper_Options::update( 'rhino', 'licence_key', '1234' );
		WP_Job_Manager_Helper_Options::update( 'rhino', 'email', 'test@local.dev' );
		WP_Job_Manager_Helper_Options::update( 'rhino', 'errors', null );
		$instance = new WP_Job_Manager_Helper();
		$result   = $instance->get_plugin_licence( 'rhino' );
		WP_Job_Manager_Helper_Options::delete( 'rhino', 'licence_key' );
		WP_Job_Manager_Helper_Options::delete( 'rhino', 'email' );
		WP_Job_Manager_Helper_Options::delete( 'rhino', 'errors' );
		$expected = array(
			'licence_key' => '1234',
			'email'       => 'test@local.dev',
			'errors'      => null,
		);
		$this->assertEquals( $expected, $result );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::get_plugin_info
	 */
	public function test_get_plugin_license_invalid() {
		$instance = new WP_Job_Manager_Helper();
		$result   = $instance->get_plugin_licence( 'rhino' );
		$expected = array(
			'licence_key' => null,
			'email'       => null,
			'errors'      => null,
		);
		$this->assertEquals( $expected, $result );
	}

	/**
	 * @since 1.29.0
	 * @covers WP_Job_Manager_Helper::extra_headers
	 */
	public function test_extra_headers() {
		$instance = new WP_Job_Manager_Helper();
		$result   = $instance->extra_headers( array() );
		$expected = array( 'WPJM-Product' );
		$this->assertEquals( $expected, $result );
	}
}
