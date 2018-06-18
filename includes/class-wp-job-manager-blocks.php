<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Handles Job Manager's Gutenberg Blocks.
 *
 * @package wp-job-manager
 * @since 1.32.0
 */
class WP_Job_Manager_Blocks {
	private static $_instance = null;

	public static function get_instance() {
		self::$_instance = new WP_Job_Manager_Blocks();
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	public function register_blocks() {
		// Jobs block
		wp_register_script(
			'wp-job-manager-block-jobs',
			JOB_MANAGER_PLUGIN_URL . '/assets/blocks/build/jobs.js',
			array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-hooks' ),
			'1.0.0'
		);
		register_block_type( 'wp-job-manager/jobs', array(
			'editor_script' => 'wp-job-manager-block-jobs',
		) );
	}
}

WP_Job_Manager_Blocks::get_instance();
