<?php

class WPJM_BaseTest extends WP_UnitTestCase {
	/**
	 * @var WPJM_Factory
	 */
	protected $factory;

	function setUp() {
		parent::setUp();
		$this->factory = self::factory();
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
	 * @since 1.26
	 * @return array
	 */
	public function return_do_not_die() {
		return array( $this, 'do_not_die' );
	}

	/**
	 * Does nothing.
	 *
	 * @since 1.26
	 */
	public function do_not_die() {
		return;
	}
}
