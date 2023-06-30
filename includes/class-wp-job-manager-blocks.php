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

use WP_Job_Manager\Blocks\JobListing;

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

		add_theme_support( 'job-manager-templates' );
		add_theme_support( 'job-manager-blocks' );

		/**
		 * TODO document new theme support flags
		 */
		if ( get_theme_support( 'job-manager-blocks' ) ) {
			\WP_Job_Manager_Post_Types::instance()->job_content_filter( false );
		}

		\WP_Job_Manager::register_script( 'wp-job-manager-blocks', 'blocks.js' );

		JobListing\JobTitle::instance();
		JobListing\JobSalary::instance();

	}

	/**
	 * Register all Gutenblocks
	 *
	 * @deprecated
	 */
	public function register_blocks() {
	}
}

WP_Job_Manager_Blocks::get_instance();
