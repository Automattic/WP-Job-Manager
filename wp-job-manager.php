<?php
/**
 * Plugin Name: WP Job Manager
 * Plugin URI: https://wpjobmanager.com/
 * Description: Manage job listings from the WordPress admin panel, and allow users to post jobs directly to your site.
 * Version: 1.32.2
 * Author: Automattic
 * Author URI: https://wpjobmanager.com/
 * Requires at least: 4.7.0
 * Tested up to: 5.1
 * Text Domain: wp-job-manager
 * Domain Path: /languages/
 * License: GPL2+
 *
 * @package wp-job-manager
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
	 * REST API instance.
	 *
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
		// Define constants.
		define( 'JOB_MANAGER_VERSION', '1.32.2' );
		define( 'JOB_MANAGER_MINIMUM_WP_VERSION', '4.7.0' );
		define( 'JOB_MANAGER_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
		define( 'JOB_MANAGER_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
		define( 'JOB_MANAGER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

		// Includes.
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-install.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-post-types.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-ajax.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-shortcodes.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-api.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-forms.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-geocode.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-blocks.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-cache-helper.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/helper/class-wp-job-manager-helper.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-email.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-email-template.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-email-notifications.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-data-exporter.php';

		/**
		 * This custom REST API implementation is deprecated and will be removed in 1.33.0.
		 *
		 * @see WP_Job_Manager::rest_api()
		 * @see https://github.com/Automattic/WP-Job-Manager/issues/1625
		 */
		if ( defined( 'WPJM_REST_API_ENABLED' ) && WPJM_REST_API_ENABLED ) {
			trigger_error( esc_html__( 'Constant `WPJM_REST_API_ENABLED` and custom REST API implementation is deprecated and will be removed in 1.33.0. Please use standard WP Core\'s implementation.', 'wp-job-manager' ) );
			add_action( 'rest_api_init', array( $this, 'rest_api' ) );
		}

		if ( is_admin() ) {
			include_once JOB_MANAGER_PLUGIN_DIR . '/includes/admin/class-wp-job-manager-admin.php';
		}

		// Load 3rd party customizations.
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/3rd-party/3rd-party.php';

		// Init classes.
		$this->forms      = WP_Job_Manager_Forms::instance();
		$this->post_types = WP_Job_Manager_Post_Types::instance();

		// Schedule cron jobs.
		self::maybe_schedule_cron_jobs();

		// Activation - works with symlinks.
		register_activation_hook( basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ), array( $this, 'activate' ) );

		// Switch theme.
		add_action( 'after_switch_theme', array( 'WP_Job_Manager_Ajax', 'add_endpoint' ), 10 );
		add_action( 'after_switch_theme', array( $this->post_types, 'register_post_types' ), 11 );
		add_action( 'after_switch_theme', 'flush_rewrite_rules', 15 );

		// Actions.
		add_action( 'after_setup_theme', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'after_setup_theme', array( $this, 'include_template_functions' ), 11 );
		add_action( 'widgets_init', array( $this, 'widgets_init' ) );
		add_action( 'wp_loaded', array( $this, 'register_shared_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_scripts' ) );
		add_action( 'admin_init', array( $this, 'updater' ) );
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		add_action( 'wp_logout', array( $this, 'cleanup_job_posting_cookies' ) );
		add_action( 'init', array( 'WP_Job_Manager_Email_Notifications', 'init' ) );

		// Filters.
		add_filter( 'wp_privacy_personal_data_exporters', array( 'WP_Job_Manager_Data_Exporter', 'register_wpjm_user_data_exporter' ) );

		add_action( 'init', array( $this, 'usage_tracking_init' ) );
		register_deactivation_hook( __FILE__, array( $this, 'usage_tracking_cleanup' ) );

		// Other cleanup.
		register_deactivation_hook( __FILE__, array( $this, 'unschedule_cron_jobs' ) );

		// Defaults for WPJM core actions.
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
	 * Adds Privacy Policy suggested content.
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			// translators: Placeholders %1$s and %2$s are the names of the two cookies used in WP Job Manager.
			__( 'This site adds the following cookies to help users resume job submissions that they 
				have started but have not completed: %1$s and %2$s', 'wp-job-manager'
			),
			'<code>wp-job-manager-submitting-job-id</code>', '<code>wp-job-manager-submitting-job-key</code>'
		);

		wp_add_privacy_policy_content(
			'WP Job Manager',
			wp_kses_post( wpautop( $content, false ) )
		);
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
	 * NOTE: This custom, unsupported REST API implementation be removed in 1.33.0 and the constant `WPJM_REST_API_ENABLED`
	 * will have no effect.
	 *
	 * @see https://developer.wordpress.org/rest-api/
	 * @see https://github.com/Automattic/WP-Job-Manager/issues/1625
	 *
	 * @deprecated 1.32.0 Please use standard WP core REST API.
	 * @return WP_Job_Manager_REST_API|WP_Error
	 */
	public function rest_api() {
		_deprecated_function(
			__CLASS__ . ':' . __FUNCTION__ . '()',
			'1.32.0',
			esc_html__( 'Standard REST API implementation from WP core', 'wp-job-manager' )
		);
		if ( null === $this->rest_api ) {
			include_once JOB_MANAGER_PLUGIN_DIR . '/includes/rest-api/class-wp-job-manager-rest-api.php';
			$this->rest_api = new WP_Job_Manager_REST_API( dirname( __FILE__ ) );
		}
		return $this->rest_api;
	}

	/**
	 * Loads plugin's core helper template functions.
	 */
	public function include_template_functions() {
		include_once JOB_MANAGER_PLUGIN_DIR . '/wp-job-manager-deprecated.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/wp-job-manager-functions.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/wp-job-manager-template.php';
	}

	/**
	 * Loads plugin's widgets.
	 */
	public function widgets_init() {
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-widget.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/widgets/class-wp-job-manager-widget-recent-jobs.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/widgets/class-wp-job-manager-widget-featured-jobs.php';
	}

	/**
	 * Initialize the Usage Tracking system.
	 */
	public function usage_tracking_init() {
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-usage-tracking.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-usage-tracking-data.php';

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
	 * Registers select2 assets when needed.
	 */
	public static function register_select2_assets() {
		wp_register_script( 'select2', JOB_MANAGER_PLUGIN_URL . '/assets/js/select2/select2.full.min.js', array( 'jquery' ), '4.0.5' );
		wp_register_style( 'select2', JOB_MANAGER_PLUGIN_URL . '/assets/js/select2/select2.min.css', array(), '4.0.5' );
	}

	/**
	 * Registers and enqueues scripts and CSS.
	 *
	 * Note: For enhanced select, 1.32.0 moved to Select2. Chosen is currently packaged but will be removed in an
	 * upcoming release.
	 */
	public function frontend_scripts() {
		$ajax_url         = WP_Job_Manager_Ajax::get_endpoint();
		$ajax_filter_deps = array( 'jquery', 'jquery-deserialize' );
		$ajax_data        = array(
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

		$enhanced_select_shortcodes   = array( 'submit_job_form', 'job_dashboard', 'jobs' );
		$enhanced_select_used_on_page = has_wpjm_shortcode( null, $enhanced_select_shortcodes );

		/**
		 * Set the constant `JOB_MANAGER_DISABLE_CHOSEN_LEGACY_COMPAT` to true to test for future behavior once
		 * this legacy code is removed and `chosen` is no longer packaged with the plugin.
		 */
		if ( ! defined( 'JOB_MANAGER_DISABLE_CHOSEN_LEGACY_COMPAT' ) || ! JOB_MANAGER_DISABLE_CHOSEN_LEGACY_COMPAT ) {
			if ( is_wpjm_taxonomy() || is_wpjm_job_listing() || is_wpjm_page() ) {
				$enhanced_select_used_on_page = true;
			}

			// Register the script for dependencies that still require it.
			if ( ! wp_script_is( 'chosen', 'registered' ) ) {
				wp_register_script( 'chosen', JOB_MANAGER_PLUGIN_URL . '/assets/js/jquery-chosen/chosen.jquery.min.js', array( 'jquery' ), '1.1.0', true );
				wp_register_style( 'chosen', JOB_MANAGER_PLUGIN_URL . '/assets/css/chosen.css', array(), '1.1.0' );
			}

			// Backwards compatibility for third-party themes/plugins while they transition to Select2.
			wp_localize_script(
				'chosen', 'job_manager_chosen_multiselect_args',
				apply_filters(
					'job_manager_chosen_multiselect_args', array(
						'search_contains' => true,
					)
				)
			);

			/**
			 * Filter the use of the deprecated chosen library. Themes and plugins should migrate to Select2. This will be
			 * removed in an upcoming major release.
			 *
			 * @since 1.19.0
			 * @deprecated 1.32.0 Migrate to job_manager_select2_enabled and enable only on pages that need it.
			 *
			 * @param bool $chosen_used_on_page
			 */
			if ( apply_filters( 'job_manager_chosen_enabled', false ) ) {
				_deprecated_hook( 'job_manager_chosen_enabled', '1.32.0', 'job_manager_select2_enabled' );

				// Assume if this filter returns true that the current page should have the multi-select scripts.
				$enhanced_select_used_on_page = true;

				wp_enqueue_script( 'chosen' );
				wp_enqueue_style( 'chosen' );
			}
		}

		/**
		 * Filter the use of the enhanced select.
		 *
		 * Note: Don't depend on `select2` being registered/enqueued in customizations.
		 *
		 * @since 1.32.0
		 *
		 * @param bool $enhanced_select_used_on_page Defaults to only when there are known shortcodes on the page.
		 */
		if ( apply_filters( 'job_manager_enhanced_select_enabled', $enhanced_select_used_on_page ) ) {
			self::register_select2_assets();
			wp_register_script( 'wp-job-manager-term-multiselect', JOB_MANAGER_PLUGIN_URL . '/assets/js/term-multiselect.min.js', array( 'jquery', 'select2' ), JOB_MANAGER_VERSION, true );
			wp_register_script( 'wp-job-manager-multiselect', JOB_MANAGER_PLUGIN_URL . '/assets/js/multiselect.min.js', array( 'jquery', 'select2' ), JOB_MANAGER_VERSION, true );
			wp_enqueue_style( 'select2' );

			$ajax_filter_deps[] = 'select2';

			$select2_args = array();
			if ( is_rtl() ) {
				$select2_args['dir'] = 'rtl';
			}

			$select2_args['width'] = '100%';

			wp_localize_script(
				'select2', 'job_manager_select2_args',
				apply_filters( 'job_manager_select2_args', $select2_args )
			);
		}

		if ( job_manager_user_can_upload_file_via_ajax() ) {
			wp_register_script( 'jquery-iframe-transport', JOB_MANAGER_PLUGIN_URL . '/assets/js/jquery-fileupload/jquery.iframe-transport.js', array( 'jquery' ), '1.8.3', true );
			wp_register_script( 'jquery-fileupload', JOB_MANAGER_PLUGIN_URL . '/assets/js/jquery-fileupload/jquery.fileupload.js', array( 'jquery', 'jquery-iframe-transport', 'jquery-ui-widget' ), '9.11.2', true );
			wp_register_script( 'wp-job-manager-ajax-file-upload', JOB_MANAGER_PLUGIN_URL . '/assets/js/ajax-file-upload.min.js', array( 'jquery', 'jquery-fileupload' ), JOB_MANAGER_VERSION, true );

			ob_start();
			get_job_manager_template(
				'form-fields/uploaded-file-html.php',
				array(
					'name'      => '',
					'value'     => '',
					'extension' => 'jpg',
				)
			);
			$js_field_html_img = ob_get_clean();

			ob_start();
			get_job_manager_template(
				'form-fields/uploaded-file-html.php',
				array(
					'name'      => '',
					'value'     => '',
					'extension' => 'zip',
				)
			);
			$js_field_html = ob_get_clean();

			wp_localize_script(
				'wp-job-manager-ajax-file-upload',
				'job_manager_ajax_file_upload',
				array(
					'ajax_url'               => $ajax_url,
					'js_field_html_img'      => esc_js( str_replace( "\n", '', $js_field_html_img ) ),
					'js_field_html'          => esc_js( str_replace( "\n", '', $js_field_html ) ),
					'i18n_invalid_file_type' => __( 'Invalid file type. Accepted types:', 'wp-job-manager' ),
				)
			);
		}

		wp_register_script( 'jquery-deserialize', JOB_MANAGER_PLUGIN_URL . '/assets/js/jquery-deserialize/jquery.deserialize.js', array( 'jquery' ), '1.2.1', true );
		wp_register_script( 'wp-job-manager-ajax-filters', JOB_MANAGER_PLUGIN_URL . '/assets/js/ajax-filters.min.js', $ajax_filter_deps, JOB_MANAGER_VERSION, true );
		wp_register_script( 'wp-job-manager-job-dashboard', JOB_MANAGER_PLUGIN_URL . '/assets/js/job-dashboard.min.js', array( 'jquery' ), JOB_MANAGER_VERSION, true );
		wp_register_script( 'wp-job-manager-job-application', JOB_MANAGER_PLUGIN_URL . '/assets/js/job-application.min.js', array( 'jquery' ), JOB_MANAGER_VERSION, true );
		wp_register_script( 'wp-job-manager-job-submission', JOB_MANAGER_PLUGIN_URL . '/assets/js/job-submission.min.js', array( 'jquery' ), JOB_MANAGER_VERSION, true );
		wp_localize_script( 'wp-job-manager-ajax-filters', 'job_manager_ajax_filters', $ajax_data );
		wp_localize_script(
			'wp-job-manager-job-dashboard',
			'job_manager_job_dashboard',
			array(
				'i18n_confirm_delete' => __( 'Are you sure you want to delete this listing?', 'wp-job-manager' ),
			)
		);

		/**
		 * Filter whether to enqueue WPJM core's frontend scripts. By default, they will only be enqueued on WPJM related
		 * pages.
		 *
		 * If your theme or plugin depend on `frontend.css` from WPJM core, you can use the
		 * `job_manager_enqueue_frontend_style` filter.
		 *
		 * Example code for a custom shortcode that depends on the frontend style:
		 *
		 * add_filter( 'job_manager_enqueue_frontend_style', function( $frontend_used_on_page ) {
		 *   global $post;
		 *   if ( is_singular()
		 *        && is_a( $post, 'WP_Post' )
		 *        && has_shortcode( $post->post_content, 'resumes' )
		 *   ) {
		 *     $frontend_used_on_page = true;
		 *   }
		 *   return $frontend_used_on_page;
		 * } );
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
function WPJM() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
	return WP_Job_Manager::instance();
}

$GLOBALS['job_manager'] = WPJM();
