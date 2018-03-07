<?php
/**
 * Job Data Block
 */

class WP_Job_Manager_Block_Job_Data {
	private static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	public function register_block() {
		if ( function_exists( 'register_block_type' ) ) {
			wp_register_script(
				'wpjm-job-data',
				plugins_url( 'build/index.js', __FILE__ ),
				array( 'wp-blocks', 'wp-i18n' ),
				filemtime( plugin_dir_path( __FILE__ ) . 'build/index.js' )
			);

			wp_register_style(
				'wpjm-job-data-editor',
				plugins_url( 'build/editor.css', __FILE__ ),
				array( 'wp-edit-blocks' ),
				filemtime( plugin_dir_path( __FILE__ ) . 'build/editor.css' )
			);

			wp_register_style(
				'wpjm-job-data',
				plugins_url( 'build/style.css', __FILE__ ),
				array( 'wp-edit-blocks' ),
				filemtime( plugin_dir_path( __FILE__ ) . 'build/style.css' )
			);

			register_block_type( 'wpjm/job-data', array(
				'editor_script' => 'wpjm-job-data',
				'editor_style'	=> 'wpjm-job-data-editor',
				'style'			=> 'wpjm-job-data',
			) );
		}
	}
}

WP_Job_Manager_Block_Job_Data::instance();
