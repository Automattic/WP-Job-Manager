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

	/**
	 * Default User ID
	 *
	 * @var int
	 */
	protected $default_user_id;

	function setUp() {
		parent::setUp();
		include_once WPJM_Unit_Tests_Bootstrap::instance()->includes_dir . '/class-requests-transport-faker.php';
		$this->_transport = null;

		$this->factory = self::factory();

		$this->default_user_id = get_current_user_id();
	}

	public function tearDown() {
		parent::tearDown();
		$this->disable_manage_job_listings_cap();
		$this->logout();
	}

	protected static function factory() {
		static $factory = null;
		if ( ! $factory ) {
			$factory = new WPJM_Factory();
		}
		return $factory;
	}

	/**
	 * When needed, this allows you to re-register post type.
	 */
	protected function reregister_post_type() {
		unregister_post_type( 'job_listing' );
		WP_Job_Manager_Post_Types::instance()->register_post_types();
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
		return [ $this, 'do_not_die' ];
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
		remove_filter( 'user_has_cap', [ $this, 'add_manage_update_plugins_cap' ] );
	}

	/**
	 * Helper to enable manage job listings capability.
	 */
	protected function enable_update_plugins_cap() {
		add_filter( 'user_has_cap', [ $this, 'add_manage_update_plugins_cap' ] );
	}

	/**
	 * Helper to disable update plugins capability.
	 */
	protected function disable_manage_job_listings_cap() {
		remove_filter( 'user_has_cap', [ $this, 'add_manage_job_listing_cap' ] );
	}

	/**
	 * Helper to enable update plugins capability.
	 */
	protected function enable_manage_job_listings_cap() {
		add_filter( 'user_has_cap', [ $this, 'add_manage_job_listing_cap' ] );
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
		remove_action( 'requests-requests.before_request', [ $this, 'overload_request_transport' ], 10 );
		remove_filter( 'job_manager_geolocation_api_key', '__return_empty_string', 10 );
		add_filter( 'job_manager_geolocation_api_key', [ $this, 'get_google_maps_api_key' ], 10 );
	}

	protected function enable_transport_faker() {
		add_action( 'requests-requests.before_request', [ $this, 'overload_request_transport' ], 10, 5 );
		remove_filter( 'job_manager_geolocation_api_key', [ $this, 'get_google_maps_api_key' ], 10 );
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

	protected function get_user_by_role( $role, $variant = '' ) {
		if ( ! wp_roles()->is_role( 'employer' ) ) {
			// Ensure the role gets created.
			WP_Job_Manager_Install::install();
			wp_roles()->init_roles();
			wp_cache_flush();
		}

		$slug = $role . $variant;
		$user = get_user_by( 'email', 'wpjm_' . $slug . '_user@example.com' );
		if ( empty( $user ) ) {
			$user_id = wp_create_user(
				'wpjm_' . $slug . '_user',
				'wpjm_' . $slug . '_user',
				'wpjm_' . $slug . '_user@example.com'
			);
			$user    = get_user_by( 'ID', $user_id );
			$user->set_role( $role );
		}
		return $user->ID;
	}

	protected function login_as_admin() {
		return $this->login_as( $this->get_user_by_role( 'administrator' ) );
	}

	protected function login_as_employer() {
		return $this->login_as( $this->get_user_by_role( 'employer' ) );
	}

	protected function login_as_employer_b() {
		return $this->login_as( $this->get_user_by_role( 'employer', '_b' ) );
	}

	protected function login_as_default_user() {
		return $this->login_as( $this->default_user_id );
	}

	protected function login_as( $user_id ) {
		wp_set_current_user( $user_id );
		return $this;
	}

	protected function logout() {
		$this->login_as( 0 );
		wp_logout();
		return $this;
	}
}
