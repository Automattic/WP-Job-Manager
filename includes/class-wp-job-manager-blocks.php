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
 * WP_Job_Manager_Blocks
 */
class WP_Job_Manager_Blocks {
	/**
	 * The static instance of the WP_Job_Manager_Blocks
	 *
	 * @var self
	 */
	private static $_instance = null;

	/**
	 * Singleton instance getter
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( ! self::$_instance ) {
			self::$_instance = new WP_Job_Manager_Blocks();
		}

		return self::$_instance;
	}

	/**
	 * Instance constructor
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Register all Gutenblocks
	 */
	public function register_blocks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// Jobs block
		wp_register_script(
			'wp-job-manager-block-jobs',
			JOB_MANAGER_PLUGIN_URL . '/assets/build/blocks/jobs.js',
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-hooks' ),
			'1.0.0'
		);
		register_block_type( 'wp-job-manager/jobs', array(
			'editor_script' => 'wp-job-manager-block-jobs',
		) );
	}
}

WP_Job_Manager_Blocks::get_instance();
