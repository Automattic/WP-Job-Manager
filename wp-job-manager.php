<?php
/*
Plugin Name: WP Job Manager
Plugin URI: http://mikejolley.com
Description: Manage job listings from the WordPress admin panel, and allow users to post jobs directly to your site.
Version: 1.0 Beta 1
Author: Mike Jolley
Author URI: http://mikejolley.com
Requires at least: 3.5
Tested up to: 3.5

	Copyright: 2013 Mike Jolley
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * WP_Job_Manager class.
 */
class WP_Job_Manager {

	/**
	 * __construct function.
	 */
	public function __construct() {
		// Define constants
		define( 'JOB_MANAGER_VERSION', '1.0' );
		define( 'JOB_MANAGER_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
		define( 'JOB_MANAGER_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );

		// Includes
		include( 'wp-job-manager-functions.php' );
		include( 'wp-job-manager-template.php' );
		include( 'includes/class-wp-job-manager-post-types.php' );
		include( 'includes/class-wp-job-manager-ajax.php' );
		include( 'includes/class-wp-job-manager-shortcodes.php' );
		include( 'includes/class-wp-job-manager-api.php' );
		include( 'includes/class-wp-job-manager-forms.php' );

		if ( is_admin() )
			include( 'includes/admin/class-wp-job-manager-admin.php' );

		// Init classes
		$this->forms      = new WP_Job_Manager_Forms();
		$this->post_types = new WP_Job_Manager_Post_Types();

		// Activation - works with symlinks
		register_activation_hook( basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ), array( $this->post_types, 'register_post_types' ), 10 );
		register_activation_hook( basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ), create_function( "", "include( 'includes/class-wp-job-manager-install.php' );" ), 10 );
		register_activation_hook( basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ), 'flush_rewrite_rules', 15 );

		// Actions
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'switch_theme', array( $this->post_types, 'register_post_types' ), 10 );
		add_action( 'switch_theme', 'flush_rewrite_rules', 15 );
		add_action( 'widgets_init', create_function( "", "include_once( 'includes/class-wp-job-manager-widgets.php' );" ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
	}

	/**
	 * Localisation
	 *
	 * @access private
	 * @return void
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'job_manager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * frontend_scripts function.
	 *
	 * @access public
	 * @return void
	 */
	public function frontend_scripts() {
		wp_register_script( 'wp-job-manager-ajax-filters', JOB_MANAGER_PLUGIN_URL . '/assets/js/ajax-filters.min.js', array( 'jquery' ), JOB_MANAGER_VERSION, true );
		wp_register_script( 'wp-job-manager-job-dashboard', JOB_MANAGER_PLUGIN_URL . '/assets/js/job-dashboard.min.js', array( 'jquery' ), JOB_MANAGER_VERSION, true );
		wp_register_script( 'wp-job-manager-job-application', JOB_MANAGER_PLUGIN_URL . '/assets/js/job-application.min.js', array( 'jquery' ), JOB_MANAGER_VERSION, true );

		wp_localize_script( 'wp-job-manager-ajax-filters', 'job_manager_ajax_filters', array(
			'ajax_url' => admin_url('admin-ajax.php')
		) );
		wp_localize_script( 'wp-job-manager-job-dashboard', 'job_manager_job_dashboard', array(
			'i18n_confirm_delete' => __( 'Are you sure you want to delete this job?', 'job_manager' )
		) );

		wp_enqueue_style( 'wp-job-manager-frontend', JOB_MANAGER_PLUGIN_URL . '/assets/css/frontend.css' );
	}
}

$GLOBALS['job_manager'] = new WP_Job_Manager();