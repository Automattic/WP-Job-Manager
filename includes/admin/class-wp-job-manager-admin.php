<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

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

		include_once dirname( __FILE__ ) . '/class-wp-job-manager-admin-notices.php';
		include_once dirname( __FILE__ ) . '/class-wp-job-manager-cpt.php';
		if ( version_compare( $wp_version, '4.7.0', '<' ) ) {
			include_once dirname( __FILE__ ) . '/class-wp-job-manager-cpt-legacy.php';
			WP_Job_Manager_CPT_Legacy::instance();
		} else {
			WP_Job_Manager_CPT::instance();
		}
		include_once dirname( __FILE__ ) . '/class-wp-job-manager-settings.php';
		include_once dirname( __FILE__ ) . '/class-wp-job-manager-writepanels.php';
		include_once dirname( __FILE__ ) . '/class-wp-job-manager-setup.php';

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
		global $wp_version;

		include_once dirname( __FILE__ ) . '/class-wp-job-manager-taxonomy-meta.php';

		if ( version_compare( $wp_version, JOB_MANAGER_MINIMUM_WP_VERSION, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'wp_version_admin_notice' ) );
			add_filter( 'plugin_action_links_' . JOB_MANAGER_PLUGIN_BASENAME, array( $this, 'wp_version_plugin_action_notice' ) );
		}
	}

	/**
	 * Display notice if WordPress core is out-of-date in admin notice section.
	 */
	public function wp_version_admin_notice() {
		// We only want to show the notices on the plugins page and WPJM admin pages.
		$screen        = get_current_screen();
		$valid_screens = array( 'plugins', 'edit-job_listing', 'job_listing_page_job-manager-settings', 'edit-job_listing_type', 'edit-job_listing_category', 'job_listing' );
		if ( null === $screen || ! in_array( $screen->id, $valid_screens, true ) ) {
			return;
		}

		echo '<div class="error">';
		// translators: %s is the URL for the page where users can go to update WordPress.
		echo '<p>' . wp_kses_post( sprintf( __( '<strong>WP Job Manager</strong> requires a more recent version of WordPress. <a href="%s">Please update WordPress</a> to avoid issues.', 'wp-job-manager' ), esc_url( self_admin_url( 'update-core.php' ) ) ) ) . '</p>';
		echo '</div>';
	}

	/**
	 * Add admin notice when WP upgrade is required.
	 *
	 * @param array $actions
	 * @return array
	 */
	public function wp_version_plugin_action_notice( $actions ) {
		// translators: Placeholder (%s) is the URL where users can go to update WordPress.
		$actions[] = wp_kses_post( sprintf( __( '<a href="%s" style="color: red">WordPress Update Required</a>', 'wp-job-manager' ), esc_url( self_admin_url( 'update-core.php' ) ) ) );
		return $actions;
	}

	/**
	 * Include admin files conditionally.
	 */
	public function conditional_includes() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		switch ( $screen->id ) {
			case 'options-permalink':
				include 'class-wp-job-manager-permalink-settings.php';
				break;
		}
	}

	/**
	 * Enqueues CSS and JS assets.
	 */
	public function admin_enqueue_scripts() {
		WP_Job_Manager::register_select2_assets();

		$screen = get_current_screen();
		if ( in_array( $screen->id, apply_filters( 'job_manager_admin_screen_ids', array( 'edit-job_listing', 'plugins', 'job_listing', 'job_listing_page_job-manager-settings', 'job_listing_page_job-manager-addons' ) ), true ) ) {
			wp_enqueue_style( 'jquery-ui' );
			wp_enqueue_style( 'select2' );
			wp_enqueue_style( 'job_manager_admin_css', JOB_MANAGER_PLUGIN_URL . '/assets/css/admin.css', array(), JOB_MANAGER_VERSION );
			wp_register_script( 'jquery-tiptip', JOB_MANAGER_PLUGIN_URL . '/assets/js/jquery-tiptip/jquery.tipTip.min.js', array( 'jquery' ), JOB_MANAGER_VERSION, true );
			wp_enqueue_script( 'job_manager_datepicker_js', JOB_MANAGER_PLUGIN_URL . '/assets/js/datepicker.min.js', array( 'jquery', 'jquery-ui-datepicker' ), JOB_MANAGER_VERSION, true );
			wp_enqueue_script( 'job_manager_admin_js', JOB_MANAGER_PLUGIN_URL . '/assets/js/admin.min.js', array( 'jquery', 'jquery-tiptip', 'select2' ), JOB_MANAGER_VERSION, true );

			wp_localize_script(
				'job_manager_admin_js',
				'job_manager_admin_params',
				array(
					'user_selection_strings'     => array(
						'no_matches'           => _x( 'No matches found', 'user selection', 'wp-job-manager' ),
						'ajax_error'           => _x( 'Loading failed', 'user selection', 'wp-job-manager' ),
						'input_too_short_1'    => _x( 'Please enter 1 or more characters', 'user selection', 'wp-job-manager' ),
						'input_too_short_n'    => _x( 'Please enter %qty% or more characters', 'user selection', 'wp-job-manager' ),
						'load_more'            => _x( 'Loading more results&hellip;', 'user selection', 'wp-job-manager' ),
						'searching'            => _x( 'Searching&hellip;', 'user selection', 'wp-job-manager' ),
					),
					'ajax_url'                  => admin_url( 'admin-ajax.php' ),
					'search_users_nonce'        => wp_create_nonce( 'search-users' ),
				)
			);

			if ( ! function_exists( 'wp_localize_jquery_ui_datepicker' ) || ! has_action( 'admin_enqueue_scripts', 'wp_localize_jquery_ui_datepicker' ) ) {
				wp_localize_script(
					'job_manager_datepicker_js',
					'job_manager_datepicker',
					array(
						/* translators: jQuery date format, see http://api.jqueryui.com/datepicker/#utility-formatDate */
						'date_format' => _x( 'yy-mm-dd', 'Date format for jQuery datepicker.', 'wp-job-manager' ),
					)
				);
			}
		}

		wp_enqueue_style( 'job_manager_admin_menu_css', JOB_MANAGER_PLUGIN_URL . '/assets/css/menu.css', array(), JOB_MANAGER_VERSION );
	}

	/**
	 * Adds pages to admin menu.
	 */
	public function admin_menu() {
		add_submenu_page( 'edit.php?post_type=job_listing', __( 'Settings', 'wp-job-manager' ), __( 'Settings', 'wp-job-manager' ), 'manage_options', 'job-manager-settings', array( $this->settings_page, 'output' ) );

		if ( WP_Job_Manager_Helper::instance()->has_licenced_products() || apply_filters( 'job_manager_show_addons_page', true ) ) {
			add_submenu_page( 'edit.php?post_type=job_listing', __( 'WP Job Manager Add-ons', 'wp-job-manager' ), __( 'Add-ons', 'wp-job-manager' ), 'manage_options', 'job-manager-addons', array( $this, 'addons_page' ) );
		}
	}

	/**
	 * Displays addons page.
	 */
	public function addons_page() {
		$addons = include 'class-wp-job-manager-addons.php';
		$addons->output();
	}
}

WP_Job_Manager_Admin::instance();
