<?php

class WPJM_BaseTest extends WP_UnitTestCase {
	/**
	 * @var Requests_Transport
	 */
	protected $_transport;

	/**
	 * @var WPJM_Factory
	 */
	protected $factory;

	function setUp() {
		parent::setUp();
		include_once WPJM_Unit_Tests_Bootstrap::instance()->includes_dir . '/class-requests-transport-faker.php';
		$this->_transport = null;

		$this->factory = self::factory();
		$this->enable_manage_job_listings_cap();
	}

	public function tearDown() {
		parent::tearDown();
		$this->disable_manage_job_listings_cap();
	}

	protected static function factory() {
		static $factory = null;
		if ( ! $factory ) {
			$factory = new WPJM_Factory();
		}
		return $factory;
	}

	/**
	 * Helps to prevent `wp_die()` from ending execution during API call.
	 *
	 * Example setting of hook: add_action( 'wp_die_handler', array( $this, 'return_do_not_die' ) )
	 *
	 * @since 1.26.0
	 * @return array
	 */
	public function return_do_not_die() {
		return array( $this, 'do_not_die' );
	}

	/**
	 * Does nothing.
	 *
	 * @since 1.26.0
	 */
	public function do_not_die() {
		return;
	}

	/**
	 * Helper to disable manage job listings capability.
	 */
	protected function disable_update_plugins_cap() {
		remove_filter( 'user_has_cap', array( $this, 'add_manage_update_plugins_cap' ) );
	}

	/**
	 * Helper to enable manage job listings capability.
	 */
	protected function enable_update_plugins_cap() {
		add_filter( 'user_has_cap', array( $this, 'add_manage_update_plugins_cap' ) );
	}

	/**
	 * Helper to disable update plugins capability.
	 */
	protected function disable_manage_job_listings_cap() {
		remove_filter( 'user_has_cap', array( $this, 'add_manage_job_listing_cap' ) );
	}

	/**
	 * Helper to enable update plugins capability.
	 */
	protected function enable_manage_job_listings_cap() {
		add_filter( 'user_has_cap', array( $this, 'add_manage_job_listing_cap' ) );
	}

	/**
	 * Helper to add capability for `user_has_cap` filter.
	 */
	public function add_manage_update_plugins_cap( $caps ) {
		$caps['update_plugins'] = 1;
		return $caps;
	}

	/**
	 * Helper to add capability for `user_has_cap` filter.
	 */
	public function add_manage_job_listing_cap( $caps ) {
		$caps['manage_job_listings'] = 1;
		return $caps;
	}

	protected function disable_transport_faker() {
		remove_action( 'requests-requests.before_request', array( $this, 'overload_request_transport' ), 10 );
		remove_filter( 'job_manager_geolocation_api_key', '__return_empty_string', 10 );
		add_filter( 'job_manager_geolocation_api_key', array( $this, 'get_google_maps_api_key' ), 10 );
	}

	protected function enable_transport_faker() {
		add_action( 'requests-requests.before_request', array( $this, 'overload_request_transport' ), 10, 5 );
		remove_filter( 'job_manager_geolocation_api_key', array( $this, 'get_google_maps_api_key' ), 10 );
		add_filter( 'job_manager_geolocation_api_key', '__return_empty_string', 10 );
	}

	public function overload_request_transport( &$url, &$headers, &$data, &$type, &$options ) {
		$options['transport'] = $this->get_request_transport();
	}

	protected function get_request_transport() {
		if ( ! isset( $this->_transport ) ) {
			$this->_transport = new Requests_Transport_Faker();
		}
		return $this->_transport;
	}

	protected function assertTrashed( $post ) {
		$this->assertPostStatus( 'trash', $post );
	}

	protected function assertNotTrashed( $post ) {
		$this->assertNotPostStatus( 'trash', $post );
	}

	protected function assertExpired( $post ) {
		$this->assertPostStatus( 'expired', $post );
	}

	protected function assertNotExpired( $post ) {
		$this->assertNotPostStatus( 'expired', $post );
	}

	protected function assertPostStatus( $expected_post_type, $post ) {
		$post = get_post( $post );
		$this->assertNotEmpty( $post );
		$this->assertEquals( $expected_post_type, $post->post_status );
	}

	protected function assertNotPostStatus( $expected_post_type, $post ) {
		$post = get_post( $post );
		$this->assertNotEmpty( $post );
		$this->assertNotEquals( $expected_post_type, $post->post_status );
	}

	protected function login_as_admin() {
		$admin = get_user_by( 'email', 'wpjm_admin_user@example.com' );
		if ( false === $admin ) {
			$admin_id = wp_create_user(
				'wpjm_admin_user',
				'wpjm_admin_user',
				'wpjm_admin_user@example.com'
			);
			$admin    = get_user_by( 'ID', $admin_id );
			$admin->set_role( 'administrator' );
		}
		wp_set_current_user( $admin->ID );
	}
}
