<?php
/**
 * Plugin Name: WP Job Manager
 * Plugin URI: https://wpjobmanager.com/
 * Description: Manage job listings from the WordPress admin panel, and allow users to post jobs directly to your site.
 * Version: 1.27.0
 * Author: Automattic
 * Author URI: https://wpjobmanager.com/
 * Requires at least: 4.1
 * Tested up to: 4.7
 * Text Domain: wp-job-manager
 * Domain Path: /languages/
 * License: GPL2+
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles core plugin hooks and action setup.
 *
 * @package wp-job-manager
 * @since 1.0.0
 */
class WP_Job_Manager {
	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.26.0
	 */
	private static $_instance = null;

	/**
	 * @var WP_Job_Manager_REST_API
	 */
	private $rest_api;

	/**
	 * Main WP Job Manager Instance.
	 *
	 * Ensures only one instance of WP Job Manager is loaded or can be loaded.
	 *
	 * @since  1.26.0
	 * @static
	 * @see WPJM()
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
		// Define constants
		define( 'JOB_MANAGER_VERSION', '1.27.0' );
		define( 'JOB_MANAGER_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
		define( 'JOB_MANAGER_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );

		// Includes
		include_once( 'includes/class-wp-job-manager-install.php' );
		include_once( 'includes/class-wp-job-manager-post-types.php' );
		include_once( 'includes/class-wp-job-manager-ajax.php' );
		include_once( 'includes/class-wp-job-manager-shortcodes.php' );
		include_once( 'includes/class-wp-job-manager-api.php' );
		include_once( 'includes/class-wp-job-manager-forms.php' );
		include_once( 'includes/class-wp-job-manager-geocode.php' );
		include_once( 'includes/class-wp-job-manager-cache-helper.php' );
		include( 'includes/rest-api/class-wp-job-manager-rest-api.php' );

		add_action( 'init', array( $this, 'rest_api' ) );

		if ( is_admin() ) {
			include_once( 'includes/admin/class-wp-job-manager-admin.php' );
		}

		// Load 3rd party customizations
		include_once( 'includes/3rd-party/3rd-party.php' );

		// Init classes
		$this->forms      = WP_Job_Manager_Forms::instance();
		$this->post_types = WP_Job_Manager_Post_Types::instance();

		// Schedule cron jobs
		self::maybe_schedule_cron_jobs();

		// Activation - works with symlinks
		register_activation_hook( basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ), array( $this, 'activate' ) );

		// Switch theme
		add_action( 'after_switch_theme', array( 'WP_Job_Manager_Ajax', 'add_endpoint' ), 10 );
		add_action( 'after_switch_theme', array( $this->post_types, 'register_post_types' ), 11 );
		add_action( 'after_switch_theme', 'flush_rewrite_rules', 15 );

		// Actions
		add_action( 'after_setup_theme', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'after_setup_theme', array( $this, 'include_template_functions' ), 11 );
		add_action( 'widgets_init', array( $this, 'widgets_init' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
		add_action( 'admin_init', array( $this, 'updater' ) );
		add_action( 'wp_logout', array( $this, 'cleanup_job_posting_cookies' ) );
	}

	/**
	 * Performs plugin activation steps.
	 */
	public function activate() {
		WP_Job_Manager_Ajax::add_endpoint();
		$this->post_types->register_post_types();
		WP_Job_Manager_Install::install();
		flush_rewrite_rules();
	}

	/**
	 * Handles tasks after plugin is updated.
	 */
	public function updater() {
		if ( version_compare( JOB_MANAGER_VERSION, get_option( 'wp_job_manager_version' ), '>' ) ) {
			WP_Job_Manager_Install::install();
			flush_rewrite_rules();
		}
	}

	/**
	 * Loads textdomain for plugin.
	 */
	public function load_plugin_textdomain() {
		load_textdomain( 'wp-job-manager', WP_LANG_DIR . '/wp-job-manager/wp-job-manager-' . apply_filters( 'plugin_locale', get_locale(), 'wp-job-manager' ) . '.mo' );
		load_plugin_textdomain( 'wp-job-manager', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	public function rest_api() {
		if ( null === $this->rest_api ) {
			$this->rest_api = new WP_Job_Manager_REST_API( dirname( __FILE__ ) );
			$this->rest_api->init();
		}
		return $this->rest_api;
	}

	/**
	 * Loads plugin's core helper template functions.
	 */
	public function include_template_functions() {
		include_once( 'wp-job-manager-deprecated.php' );
		include_once( 'wp-job-manager-functions.php' );
		include_once( 'wp-job-manager-template.php' );
	}

	/**
	 * Loads plugin's widgets.
	 */
	public function widgets_init() {
		include_once( 'includes/class-wp-job-manager-widget.php' );
		include_once( 'includes/widgets/class-wp-job-manager-widget-recent-jobs.php' );
		include_once( 'includes/widgets/class-wp-job-manager-widget-featured-jobs.php' );
	}

	/**
	 * Schedule cron jobs for WPJM events.
	 */
	public static function maybe_schedule_cron_jobs() {
		if ( ! wp_next_scheduled( 'job_manager_check_for_expired_jobs' ) ) {
			wp_schedule_event( time(), 'hourly', 'job_manager_check_for_expired_jobs' );
		}
		if ( ! wp_next_scheduled( 'job_manager_delete_old_previews' ) ) {
			wp_schedule_event( time(), 'daily', 'job_manager_delete_old_previews' );
		}
		if ( ! wp_next_scheduled( 'job_manager_clear_expired_transients' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'job_manager_clear_expired_transients' );
		}
	}

	/**
	 * Cleanup job posting cookies.
	 */
	public function cleanup_job_posting_cookies() {
		if ( isset( $_COOKIE['wp-job-manager-submitting-job-id'] ) ) {
			setcookie( 'wp-job-manager-submitting-job-id', '', 0, COOKIEPATH, COOKIE_DOMAIN, false );
		}
		if ( isset( $_COOKIE['wp-job-manager-submitting-job-key'] ) ) {
			setcookie( 'wp-job-manager-submitting-job-key', '', 0, COOKIEPATH, COOKIE_DOMAIN, false );
		}
	}

	/**
	 * Registers and enqueues scripts and CSS.
	 */
	public function frontend_scripts() {
		global $post;

		$ajax_url         = WP_Job_Manager_Ajax::get_endpoint();
		$ajax_filter_deps = array( 'jquery', 'jquery-deserialize' );
		$ajax_data 		  = array(
			'ajax_url'                => $ajax_url,
			'is_rtl'                  => is_rtl() ? 1 : 0,
			'i18n_load_prev_listings' => __( 'Load previous listings', 'wp-job-manager' ),
		);

		/**
		 * Retrieves the current language for use when caching requests.
		 *
		 * @since 1.26.0
		 *
		 * @param string|null $lang
		 */
		$ajax_data['lang'] = apply_filters( 'wpjm_lang', null );

		if ( apply_filters( 'job_manager_chosen_enabled', true ) ) {
			wp_register_script( 'chosen', JOB_MANAGER_PLUGIN_URL . '/assets/js/jquery-chosen/chosen.jquery.min.js', array( 'jquery' ), '1.1.0', true );
			wp_register_script( 'wp-job-manager-term-multiselect', JOB_MANAGER_PLUGIN_URL . '/assets/js/term-multiselect.min.js', array( 'jquery', 'chosen' ), JOB_MANAGER_VERSION, true );
			wp_register_script( 'wp-job-manager-multiselect', JOB_MANAGER_PLUGIN_URL . '/assets/js/multiselect.min.js', array( 'jquery', 'chosen' ), JOB_MANAGER_VERSION, true );
			wp_enqueue_style( 'chosen', JOB_MANAGER_PLUGIN_URL . '/assets/css/chosen.css', array(), '1.1.0' );
			$ajax_filter_deps[] = 'chosen';

			wp_localize_script( 'chosen', 'job_manager_chosen_multiselect_args',
				apply_filters( 'job_manager_chosen_multiselect_args', array(
					'search_contains' => true,
				) )
			);
		}

		if ( apply_filters( 'job_manager_ajax_file_upload_enabled', true ) ) {
			wp_register_script( 'jquery-iframe-transport', JOB_MANAGER_PLUGIN_URL . '/assets/js/jquery-fileupload/jquery.iframe-transport.js', array( 'jquery' ), '1.8.3', true );
			wp_register_script( 'jquery-fileupload', JOB_MANAGER_PLUGIN_URL . '/assets/js/jquery-fileupload/jquery.fileupload.js', array( 'jquery', 'jquery-iframe-transport', 'jquery-ui-widget' ), '9.11.2', true );
			wp_register_script( 'wp-job-manager-ajax-file-upload', JOB_MANAGER_PLUGIN_URL . '/assets/js/ajax-file-upload.min.js', array( 'jquery', 'jquery-fileupload' ), JOB_MANAGER_VERSION, true );

			ob_start();
			get_job_manager_template( 'form-fields/uploaded-file-html.php', array(
				'name' => '',
				'value' => '',
				'extension' => 'jpg',
			) );
			$js_field_html_img = ob_get_clean();

			ob_start();
			get_job_manager_template( 'form-fields/uploaded-file-html.php', array(
				'name' => '',
				'value' => '',
				'extension' => 'zip',
			) );
			$js_field_html = ob_get_clean();

			wp_localize_script( 'wp-job-manager-ajax-file-upload', 'job_manager_ajax_file_upload', array(
				'ajax_url'               => $ajax_url,
				'js_field_html_img'      => esc_js( str_replace( "\n", '', $js_field_html_img ) ),
				'js_field_html'          => esc_js( str_replace( "\n", '', $js_field_html ) ),
				'i18n_invalid_file_type' => __( 'Invalid file type. Accepted types:', 'wp-job-manager' ),
			) );
		}

		wp_register_script( 'jquery-deserialize', JOB_MANAGER_PLUGIN_URL . '/assets/js/jquery-deserialize/jquery.deserialize.js', array( 'jquery' ), '1.2.1', true );
		wp_register_script( 'wp-job-manager-ajax-filters', JOB_MANAGER_PLUGIN_URL . '/assets/js/ajax-filters.min.js', $ajax_filter_deps, JOB_MANAGER_VERSION, true );
		wp_register_script( 'wp-job-manager-job-dashboard', JOB_MANAGER_PLUGIN_URL . '/assets/js/job-dashboard.min.js', array( 'jquery' ), JOB_MANAGER_VERSION, true );
		wp_register_script( 'wp-job-manager-job-application', JOB_MANAGER_PLUGIN_URL . '/assets/js/job-application.min.js', array( 'jquery' ), JOB_MANAGER_VERSION, true );
		wp_register_script( 'wp-job-manager-job-submission', JOB_MANAGER_PLUGIN_URL . '/assets/js/job-submission.min.js', array( 'jquery' ), JOB_MANAGER_VERSION, true );
		wp_localize_script( 'wp-job-manager-ajax-filters', 'job_manager_ajax_filters', $ajax_data );
		wp_localize_script( 'wp-job-manager-job-dashboard', 'job_manager_job_dashboard', array(
			'i18n_confirm_delete' => __( 'Are you sure you want to delete this listing?', 'wp-job-manager' ),
		) );

		wp_enqueue_style( 'wp-job-manager-frontend', JOB_MANAGER_PLUGIN_URL . '/assets/css/frontend.css', array(), JOB_MANAGER_VERSION );
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'submit_job_form' ) ) {
			wp_enqueue_style( 'wp-job-manager-job-submission', JOB_MANAGER_PLUGIN_URL . '/assets/css/job-submission.css', array(), JOB_MANAGER_VERSION );
		}
	}
}

/**
 * Add post type for Job Manager.
 *
 * @param array $types
 * @return array
 */
function job_manager_add_post_types( $types ) {
	$types[] = 'job_listing';
	return $types;
}
add_filter( 'post_types_to_delete_with_user', 'job_manager_add_post_types', 10 );

/**
 * Main instance of WP Job Manager.
 *
 * Returns the main instance of WP Job Manager to prevent the need to use globals.
 *
 * @since  1.26
 * @return WP_Job_Manager
 */
function WPJM() {
	return WP_Job_Manager::instance();
}

$GLOBALS['job_manager'] = WPJM();
