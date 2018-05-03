<?php
/**
 * Plugin Name: WP Job Manager
 * Plugin URI: https://wpjobmanager.com/
 * Description: Manage job listings from the WordPress admin panel, and allow users to post jobs directly to your site.
 * Version: 1.31.0
 * Author: Automattic
 * Author URI: https://wpjobmanager.com/
 * Requires at least: 4.7.0
 * Tested up to: 4.9
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
	private $rest_api = null;

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
		define( 'JOB_MANAGER_VERSION', '1.31.0' );
		define( 'JOB_MANAGER_MINIMUM_WP_VERSION', '4.7.0' );
		define( 'JOB_MANAGER_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
		define( 'JOB_MANAGER_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
		define( 'JOB_MANAGER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

		// Includes
		include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-install.php' );
		include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-post-types.php' );
		include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-ajax.php' );
		include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-shortcodes.php' );
		include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-api.php' );
		include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-forms.php' );
		include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-geocode.php' );
		include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-cache-helper.php' );
		include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/helper/class-wp-job-manager-helper.php' );
		include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-email.php' );
		include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-email-template.php' );
		include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-email-notifications.php' );

		add_action( 'rest_api_init', array( $this, 'rest_api' ) );

		if ( is_admin() ) {
			include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/admin/class-wp-job-manager-admin.php' );
		}

		// Load 3rd party customizations
		include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/3rd-party/3rd-party.php' );

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
		add_action( 'wp_loaded', array( $this, 'register_shared_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
		add_action( 'admin_init', array( $this, 'updater' ) );
		add_action( 'wp_logout', array( $this, 'cleanup_job_posting_cookies' ) );
		add_action( 'init', array( 'WP_Job_Manager_Email_Notifications', 'init' ) );

		add_action( 'init', array( $this, 'usage_tracking_init' ) );
		register_deactivation_hook( __FILE__, array( $this, 'usage_tracking_cleanup' ) );

		// Other cleanup
		register_deactivation_hook( __FILE__, array( $this, 'unschedule_cron_jobs' ) );

		// Defaults for WPJM core actions
		add_action( 'wpjm_notify_new_user', 'wp_job_manager_notify_new_user', 10, 2 );
	}

	/**
	 * Performs plugin activation steps.
	 */
	public function activate() {
		WP_Job_Manager_Ajax::add_endpoint();
		unregister_post_type( 'job_listing' );
		add_filter( 'pre_option_job_manager_enable_types', '__return_true' );
		$this->post_types->register_post_types();
		remove_filter( 'pre_option_job_manager_enable_types', '__return_true' );
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

	/**
	 * Initialize our REST API.
	 *
	 * @return WP_Job_Manager_REST_API|WP_Error
	 */
	public function rest_api() {
		if ( null === $this->rest_api ) {
			include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/rest-api/class-wp-job-manager-rest-api.php' );
			$this->rest_api = new WP_Job_Manager_REST_API( dirname( __FILE__ ) );
		}
		return $this->rest_api;
	}

	/**
	 * Loads plugin's core helper template functions.
	 */
	public function include_template_functions() {
		include_once( JOB_MANAGER_PLUGIN_DIR . '/wp-job-manager-deprecated.php' );
		include_once( JOB_MANAGER_PLUGIN_DIR . '/wp-job-manager-functions.php' );
		include_once( JOB_MANAGER_PLUGIN_DIR . '/wp-job-manager-template.php' );
	}

	/**
	 * Loads plugin's widgets.
	 */
	public function widgets_init() {
		include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-widget.php' );
		include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/widgets/class-wp-job-manager-widget-recent-jobs.php' );
		include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/widgets/class-wp-job-manager-widget-featured-jobs.php' );
	}

	/**
	 * Initialize the Usage Tracking system.
	 */
	public function usage_tracking_init() {
		include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-usage-tracking.php' );
		include_once( JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-usage-tracking-data.php' );

		WP_Job_Manager_Usage_Tracking::get_instance()->set_callback(
			array( 'WP_Job_Manager_Usage_Tracking_Data', 'get_usage_data' )
		);
		WP_Job_Manager_Usage_Tracking::get_instance()->schedule_tracking_task();
	}

	/**
	 * Cleanup the Usage Tracking system for plugin deactivation.
	 */
	public function usage_tracking_cleanup() {
		WP_Job_Manager_Usage_Tracking::get_instance()->unschedule_tracking_task();
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
		if ( ! wp_next_scheduled( 'job_manager_email_daily_notices' ) ) {
			wp_schedule_event( time(), 'daily', 'job_manager_email_daily_notices' );
		}
	}

	/**
	 * Unschedule cron jobs. This is run on plugin deactivation.
	 */
	public static function unschedule_cron_jobs() {
		wp_clear_scheduled_hook( 'job_manager_check_for_expired_jobs' );
		wp_clear_scheduled_hook( 'job_manager_delete_old_previews' );
		wp_clear_scheduled_hook( 'job_manager_clear_expired_transients' );
		wp_clear_scheduled_hook( 'job_manager_email_daily_notices' );
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
	 * Registers assets used in both the frontend and WP admin.
	 */
	public function register_shared_assets() {
		global $wp_scripts;

		$jquery_version = isset( $wp_scripts->registered['jquery-ui-core']->ver ) ? $wp_scripts->registered['jquery-ui-core']->ver : '1.9.2';
		wp_register_style( 'jquery-ui', '//code.jquery.com/ui/' . $jquery_version . '/themes/smoothness/jquery-ui.css', array(), $jquery_version );
	}

	/**
	 * Registers and enqueues scripts and CSS.
	 */
	public function frontend_scripts() {
		global $post;

		/**
		 * Starting in WP Job Manager 1.32.0, the chosen JS library and core frontend WPJM CSS will only be enqueued
		 * when used on a particular page. Theme and plugin authors as well as people who have overloaded WPJM's default
		 * template files should test this upcoming behavior.
		 *
		 * To test this behavior before 1.32.0, add this to your `wp-config.php`:
		 * define( 'JOB_MANAGER_TEST_NEW_ASSET_BEHAVIOR', true );
		 *
		 * Unless this constant is defined, WP Job Manager will default to its old behavior: chosen JS library and
		 * frontend styles are always enqueued.
		 *
		 * If your theme or plugin depend on the `frontend.css` or chosen JS library from WPJM core, you can use the
		 * `job_manager_chosen_enabled` and `job_manager_enqueue_frontend_style` filters.
		 *
		 * Example code for a custom shortcode that depends on the chosen library:
		 *
		 * add_filter( 'job_manager_chosen_enabled', function( $chosen_used_on_page ) {
		 *   global $post;
		 *   if ( is_singular()
		 *        && is_a( $post, 'WP_Post' )
		 *        && has_shortcode( $post->post_content, 'resumes' )
		 *   ) {
		 *     $chosen_used_on_page = true;
		 *   }
		 *   return $chosen_used_on_page;
		 * } );
		 *
		 */
		if ( ! defined( 'JOB_MANAGER_TEST_NEW_ASSET_BEHAVIOR' ) || true !== JOB_MANAGER_TEST_NEW_ASSET_BEHAVIOR ) {
			add_filter( 'job_manager_chosen_enabled', '__return_true' );
			add_filter( 'job_manager_enqueue_frontend_style', '__return_true' );
		}

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

		$chosen_shortcodes = array( 'submit_job_form', 'job_dashboard', 'jobs' );
		$chosen_used_on_page = has_wpjm_shortcode( null, $chosen_shortcodes );

		/**
		 * Filter the use of the chosen library.
		 *
		 * NOTE: See above. Before WP Job Manager 1.32.0 is released, `job_manager_enqueue_frontend_style` will be filtered to `true` by default.
		 *
		 * @since 1.19.0
		 *
		 * @param bool $chosen_used_on_page Defaults to only when there are known shortcodes on the page.
		 */
		if ( apply_filters( 'job_manager_chosen_enabled', $chosen_used_on_page ) ) {
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

		if ( job_manager_user_can_upload_file_via_ajax() ) {
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


		/**
		 * Filter whether to enqueue WPJM core's frontend scripts. By default, they will only be enqueued on WPJM related
		 * pages.
		 *
		 * NOTE: See above. Before WP Job Manager 1.32.0 is released, `job_manager_enqueue_frontend_style` will be filtered to `true` by default.
		 *
		 * @since 1.30.0
		 *
		 * @param bool $is_frontend_style_enabled
		 */
		if ( apply_filters( 'job_manager_enqueue_frontend_style', is_wpjm() ) ) {
			wp_enqueue_style( 'wp-job-manager-frontend', JOB_MANAGER_PLUGIN_URL . '/assets/css/frontend.css', array(), JOB_MANAGER_VERSION );
		} else {
			wp_register_style( 'wp-job-manager-job-listings', JOB_MANAGER_PLUGIN_URL . '/assets/css/job-listings.css', array(), JOB_MANAGER_VERSION );
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
