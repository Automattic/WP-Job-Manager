<?php

class WPJM_BaseTest extends WP_UnitTestCase {
	/**
	 * @var WPJM_Factory
	 */
	protected $factory;

	function setUp() {
		parent::setUp();
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
	protected function disable_manage_job_listings_cap() {
		remove_filter( 'user_has_cap', array( $this, 'add_manage_job_listing_cap') );
	}

	/**
	 * Helper to enable manage job listings capability.
	 */
	protected function enable_manage_job_listings_cap() {
		add_filter( 'user_has_cap', array( $this, 'add_manage_job_listing_cap') );
	}

	/**
	 * Helper to add capability for `user_has_cap` filter.
	 */
	public function add_manage_job_listing_cap( $caps ) {
		$caps['manage_job_listings'] = 1;
		return $caps;
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
}
