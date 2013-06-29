<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WP_Job_Manager_Admin class.
 */
class WP_Job_Manager_Admin {

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		include_once( 'class-wp-job-manager-cpt.php' );
		include_once( 'class-wp-job-manager-settings.php' );
		include_once( 'class-wp-job-manager-writepanels.php' );

		$this->settings_page = new WP_Job_Manager_Settings();

		add_action( 'admin_menu', array( $this, 'admin_menu' ), 12 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * admin_enqueue_scripts function.
	 *
	 * @access public
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		global $job_manager;

		wp_enqueue_style( 'job_manager_admin_menu_css', JOB_MANAGER_PLUGIN_URL . '/assets/css/menu.css' );
		wp_enqueue_style( 'job_manager_admin_css', JOB_MANAGER_PLUGIN_URL . '/assets/css/admin.css' );
		wp_register_script( 'jquery-tiptip', JOB_MANAGER_PLUGIN_URL. '/assets/js/jquery-tiptip/jquery.tipTip.min.js', array( 'jquery' ), JOB_MANAGER_VERSION, true );
		wp_enqueue_script( 'job_manager_admin_js', JOB_MANAGER_PLUGIN_URL. '/assets/js/admin.min.js', array( 'jquery', 'jquery-tiptip' ), JOB_MANAGER_VERSION, true );
	}

	/**
	 * admin_menu function.
	 *
	 * @access public
	 * @return void
	 */
	public function admin_menu() {
		add_submenu_page( 'edit.php?post_type=job_listing', __( 'Settings', 'job_manager' ), __( 'Settings', 'job_manager' ), 'manage_options', 'job-manager-settings', array( $this->settings_page, 'output' ) );
	}

	/**
	 * admin_dashboard function.
	 *
	 * @access public
	 * @return void
	 */
	public function admin_dashboard() {
		include_once( 'class-dlm-admin-dashboard.php' );
	}
}

new WP_Job_Manager_Admin();