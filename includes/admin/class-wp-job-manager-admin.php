<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Handles front admin page for WP Job Manager.
 *
 * @package wp-job-manager
 * @since 1.0.0
 */
class WP_Job_Manager_Admin {

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.26.0
	 */
	private static $_instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.26.0
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wp_version;

		include_once( 'class-wp-job-manager-cpt.php' );
		if ( version_compare( $wp_version, '4.7.0', '<' ) ) {
			include_once( 'class-wp-job-manager-cpt-legacy.php' );
			WP_Job_Manager_CPT_Legacy::instance();
		} else {
			WP_Job_Manager_CPT::instance();
		}
		include_once( 'class-wp-job-manager-settings.php' );
		include_once( 'class-wp-job-manager-writepanels.php' );
		include_once( 'class-wp-job-manager-setup.php' );

		$this->settings_page = WP_Job_Manager_Settings::instance();

		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'current_screen', array( $this, 'conditional_includes' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 12 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * Set up actions during admin initialization.
	 */
	public function admin_init() {
		include_once( 'class-wp-job-manager-taxonomy-meta.php' );
	}

	/**
	 * Include admin files conditionally.
	 */
	public function conditional_includes() {
		if ( ! $screen = get_current_screen() ) {
			return;
		}
		switch ( $screen->id ) {
			case 'options-permalink' :
				include( 'class-wp-job-manager-permalink-settings.php' );
				break;
		}
	}

	/**
	 * Enqueues CSS and JS assets.
	 */
	public function admin_enqueue_scripts() {
		global $wp_scripts;

		$screen = get_current_screen();

		if ( in_array( $screen->id, apply_filters( 'job_manager_admin_screen_ids', array( 'edit-job_listing', 'job_listing', 'job_listing_page_job-manager-settings', 'job_listing_page_job-manager-addons' ) ) ) ) {
			$jquery_version = isset( $wp_scripts->registered['jquery-ui-core']->ver ) ? $wp_scripts->registered['jquery-ui-core']->ver : '1.9.2';

			wp_enqueue_style( 'jquery-ui-style', '//code.jquery.com/ui/' . $jquery_version . '/themes/smoothness/jquery-ui.css', array(), $jquery_version );
			wp_enqueue_style( 'job_manager_admin_css', JOB_MANAGER_PLUGIN_URL . '/assets/css/admin.css', array(), JOB_MANAGER_VERSION );
			wp_register_script( 'jquery-tiptip', JOB_MANAGER_PLUGIN_URL. '/assets/js/jquery-tiptip/jquery.tipTip.min.js', array( 'jquery' ), JOB_MANAGER_VERSION, true );
			wp_enqueue_script( 'job_manager_admin_js', JOB_MANAGER_PLUGIN_URL. '/assets/js/admin.min.js', array( 'jquery', 'jquery-tiptip', 'jquery-ui-datepicker' ), JOB_MANAGER_VERSION, true );

			wp_localize_script( 'job_manager_admin_js', 'job_manager_admin', array(
				/* translators: jQuery date format, see http://api.jqueryui.com/datepicker/#utility-formatDate */
				'date_format' => _x( 'yy-mm-dd', 'Date format for jQuery datepicker.', 'wp-job-manager' )
			) );
		}

		wp_enqueue_style( 'job_manager_admin_menu_css', JOB_MANAGER_PLUGIN_URL . '/assets/css/menu.css', array(), JOB_MANAGER_VERSION );
	}

	/**
	 * Adds pages to admin menu.
	 */
	public function admin_menu() {
		add_submenu_page( 'edit.php?post_type=job_listing', __( 'Settings', 'wp-job-manager' ), __( 'Settings', 'wp-job-manager' ), 'manage_options', 'job-manager-settings', array( $this->settings_page, 'output' ) );

		if ( apply_filters( 'job_manager_show_addons_page', true ) )
			add_submenu_page(  'edit.php?post_type=job_listing', __( 'WP Job Manager Add-ons', 'wp-job-manager' ),  __( 'Add-ons', 'wp-job-manager' ) , 'manage_options', 'job-manager-addons', array( $this, 'addons_page' ) );
	}

	/**
	 * Displays addons page.
	 */
	public function addons_page() {
		$addons = include( 'class-wp-job-manager-addons.php' );
		$addons->output();
	}
}

WP_Job_Manager_Admin::instance();
