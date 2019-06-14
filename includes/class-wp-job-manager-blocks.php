<?php
/**
 * Handles Job Manager's Gutenberg Blocks.
 *
 * @package wp-job-manager
 * @since 1.32.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class WP_Job_Manager_Blocks.
 */
class WP_Job_Manager_Blocks {
	/**
	 * The static instance of the WP_Job_Manager_Blocks
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Singleton instance getter
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new WP_Job_Manager_Blocks();
		}

		return self::$instance;
	}

	/**
	 * Instance constructor
	 */
	private function __construct() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Register all Gutenblocks
	 */
	public function register_blocks() {
		// Add script includes for gutenblocks.
	}
}

WP_Job_Manager_Blocks::get_instance();
