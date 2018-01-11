<?php
/**
 * Job Listing Block
 */

class WP_Job_Manager_Block_Job_Listing {
	private static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		add_action( 'init', array( $this, 'enqueue_assets' ) );
	}

	public function enqueue_assets() {
		wp_enqueue_script(
			'wpjm-job-listing',
			plugins_url( 'build/index.js', __FILE__ ),
			array( 'wp-blocks' )
		);

		wp_enqueue_style(
			'wpjm-job-listing-editor',
			plugins_url( 'build/editor.css', __FILE__ ),
			array( 'wp-edit-blocks' ),
			filemtime( plugin_dir_path( __FILE__ ) . 'build/editor.css' )
		);

		wp_enqueue_style(
			'wpjm-job-listing',
			plugins_url( 'build/style.css', __FILE__ ),
			array( 'wp-edit-blocks' ),
			filemtime( plugin_dir_path( __FILE__ ) . 'build/style.css' )
		);
	}
}

WP_Job_Manager_Block_Job_Listing::instance();
