<?php
/**
 * File containing the class WP_Job_Manager_Helper.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper functions used in WP Job Manager regarding addons and licenses.
 *
 * @package wp-job-manager
 * @since   1.29.0
 */
class WP_Job_Manager_Helper {
	/**
	 * License messages to display to the user.
	 *
	 * @var array Messages when updating licences.
	 */
	protected $licence_messages = [];

	/**
	 * API object.
	 *
	 * @var WP_Job_Manager_Helper_API
	 */
	protected $api;

	/**
	 * Language Pack helper.
	 *
	 * @var WP_Job_Manager_Helper_Language_Packs
	 */
	private $language_pack_helper;

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.29.0
	 */
	private static $instance = null;

	/**
	 * True if the plugin cache has already been cleared.
	 *
	 * @var bool
	 * @since 1.29.1
	 */
	private static $cleared_plugin_cache = false;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.29.0
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Loads the class, runs on init.
	 */
	public function init() {
		include_once dirname( __FILE__ ) . '/class-wp-job-manager-helper-options.php';
		include_once dirname( __FILE__ ) . '/class-wp-job-manager-helper-api.php';
		include_once dirname( __FILE__ ) . '/class-wp-job-manager-helper-language-packs.php';

		$this->api = WP_Job_Manager_Helper_API::instance();

		add_action( 'job_manager_helper_output', [ $this, 'licence_output' ] );

		add_filter( 'job_manager_addon_core_version_check', [ $this, 'addon_core_version_check' ], 10, 2 );
		add_filter( 'extra_plugin_headers', [ $this, 'extra_headers' ] );
		add_filter( 'plugins_api', [ $this, 'plugins_api' ], 20, 3 );
		add_action( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_updates' ] );

		add_action( 'activated_plugin', [ $this, 'plugin_activated' ] );
		add_action( 'deactivated_plugin', [ $this, 'plugin_deactivated' ] );
		add_action( 'admin_init', [ $this, 'admin_init' ] );
	}

	/**
	 * Initializes admin-only actions.
	 */
	public function admin_init() {
		$this->load_language_pack_helper();
		add_action( 'plugin_action_links', [ $this, 'plugin_links' ], 10, 2 );
		add_action( 'admin_notices', [ $this, 'licence_error_notices' ] );
		$this->handle_admin_request();
	}

	/**
	 * Load the language pack helper.
	 */
	private function load_language_pack_helper() {
		if ( $this->language_pack_helper ) {
			return;
		}

		$this->language_pack_helper = new WP_Job_Manager_Helper_Language_Packs( $this->get_plugin_versions(), $this->get_site_locales() );
	}

	/**
	 * Get the versions for the installed managed plugins, keyed with the plugin slug.
	 *
	 * @return string[]
	 */
	private function get_plugin_versions() {
		return array_filter(
			array_map(
				function ( $plugin ) {
					return $plugin['Version'];
				},
				$this->get_installed_plugins( false )
			)
		);
	}

	/**
	 * Get the locales used in the site.
	 *
	 * @return string[]
	 */
	private function get_site_locales() {
		$locales = array_values( get_available_languages() );

		/** This action is documented in WordPress core's wp-includes/update.php */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$locales = apply_filters( 'plugins_update_check_locales', $locales );
		$locales = array_unique( $locales );

		return $locales;
	}

	/**
	 * Handles special tasks on admin requests.
	 */
	private function handle_admin_request() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce should not be modified.
		if ( ! isset( $_GET['_wpjm_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpjm_nonce'] ), 'dismiss-wpjm-licence-notice' ) ) {
			return;
		}

		$product_slug = isset( $_GET['dismiss-wpjm-licence-notice'] ) ? sanitize_text_field( wp_unslash( $_GET['dismiss-wpjm-licence-notice'] ) ) : false;

		if ( ! empty( $product_slug ) ) {
			$product_plugins = $this->get_installed_plugins();
			if ( isset( $product_plugins[ $product_slug ] ) ) {
				WP_Job_Manager_Helper_Options::update( $product_slug, 'hide_key_notice', true );
			}
		}
	}

	/**
	 * Tell the add-on when to check for and display and core WPJM version notices.
	 *
	 * @param bool   $do_check                      True if the add-on should do a core version check.
	 * @param string $minimum_required_core_version Minimum version the plugin is reporting it requires.
	 * @return bool
	 */
	public function addon_core_version_check( $do_check, $minimum_required_core_version = null ) {
		if ( ! is_admin() || ! did_action( 'admin_init' ) ) {
			return false;
		}

		// We only want to show the notices on the plugins page and main job listing admin page.
		$screen = get_current_screen();
		if ( null === $screen || ! in_array( $screen->id, [ 'plugins', 'edit-job_listing' ], true ) ) {
			return false;
		}

		$dev_version_loc = strpos( JOB_MANAGER_VERSION, '-dev' );
		if (
			false !== $dev_version_loc
			&& substr( JOB_MANAGER_VERSION, 0, $dev_version_loc ) === $minimum_required_core_version
		) {
			return false;
		}

		return $do_check;
	}

	/**
	 * Check for licence managed WPJM addon plugin updates.
	 *
	 * @param array $check_for_updates_data
	 *
	 * @return array
	 */
	public function check_for_updates( $check_for_updates_data ) {
		$available_addon_updates = [];
		// Set version variables.
		foreach ( $this->get_installed_plugins() as $product_slug => $plugin_data ) {
			$response = $this->get_plugin_version( $plugin_data['_filename'] );
			// If there is a new version, modify the transient to reflect an update is available.
			if (
				$response
				&& isset( $response['new_version'] )
				&& ! empty( $response['new_version'] )
				&& version_compare( $response['new_version'], $plugin_data['Version'], '>' )
			) {
				$available_addon_updates[ $product_slug ]                      = $response;
				$check_for_updates_data->response[ $plugin_data['_filename'] ] = (object) $response;
			}
		}
		set_site_transient( 'wpjm_addon_updates_available', $available_addon_updates ); // No expiration set.

		return $check_for_updates_data;
	}

	/**
	 * Get plugin version info from API.
	 *
	 * @param string $plugin_filename
	 *
	 * @return array|bool
	 */
	private function get_plugin_version( $plugin_filename ) {
		$plugin_data = $this->get_licence_managed_plugin( $plugin_filename );
		if ( ! $plugin_data || empty( $plugin_data['_product_slug'] ) ) {
			return false;
		}

		$product_slug = $plugin_data['_product_slug'];
		$licence      = $this->get_plugin_licence( $product_slug );

		$response = $this->api->plugin_update_check(
			[
				'plugin_name'    => $plugin_data['Name'],
				'version'        => $plugin_data['Version'],
				'api_product_id' => $product_slug,
				'licence_key'    => $licence['licence_key'] ?? null,
				'email'          => $licence['email'] ?? null,
			]
		);

		$this->handle_api_errors( $product_slug, $response );

		// Set version variables.
		if ( ! empty( $response ) ) {
			return $response;
		}

		return false;
	}

	/**
	 * Cleanup old things when WPJM licence managed plugin is activated.
	 *
	 * @param string $plugin_filename
	 */
	public function plugin_activated( $plugin_filename ) {
		$plugins = $this->get_installed_plugins( false );
		foreach ( $plugins as $product_slug => $plugin_data ) {
			if ( $plugin_filename !== $plugin_data['_filename'] ) {
				continue;
			}

			WP_Job_Manager_Helper_Options::delete( $product_slug, 'hide_key_notice' );
			break;
		}
	}

	/**
	 * Deactivate licence when WPJM licence managed plugin is deactivated.
	 *
	 * @param string $plugin_filename
	 */
	public function plugin_deactivated( $plugin_filename ) {
		$plugins = $this->get_installed_plugins( false );
		foreach ( $plugins as $product_slug => $plugin_data ) {
			if ( $plugin_filename !== $plugin_data['_filename'] ) {
				continue;
			}
			$this->deactivate_licence( $product_slug );
			break;
		}
	}

	/**
	 * Fetches the plugin information for WPJM plugins.
	 *
	 * @param false|object|array $response The result object or array. Default false.
	 * @param string             $action   The type of information being requested from the Plugin Install API.
	 * @param object             $args     Plugin API arguments.
	 *
	 * @return false|object|array
	 */
	public function plugins_api( $response, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $response;
		}

		if ( empty( $args->slug ) ) {
			return $response;
		}

		$plugin_info = $this->get_plugin_info( $args->slug );
		if ( $plugin_info ) {
			$response = (object) $plugin_info;
		}

		return $response;
	}

	/**
	 * Appends links to manage plugin licence when managed.
	 *
	 * @param array  $actions
	 * @param string $plugin_filename
	 * @return array
	 */
	public function plugin_links( $actions, $plugin_filename ) {
		$plugin = $this->get_licence_managed_plugin( $plugin_filename );
		if ( ! $plugin || ! current_user_can( 'update_plugins' ) ) {
			return $actions;
		}
		$product_slug = $plugin['_product_slug'];
		$licence      = $this->get_plugin_licence( $product_slug );
		$css_class    = '';
		if ( $licence && ! empty( $licence['licence_key'] ) ) {
			if ( ! empty( $licence['errors'] ) ) {
				$manage_licence_label = __( 'Manage License (Requires Attention)', 'wp-job-manager' );
				$css_class            = 'wpjm-activate-licence-link';
			} else {
				$manage_licence_label = __( 'Manage License', 'wp-job-manager' );
			}
		} else {
			$manage_licence_label = __( 'Activate License', 'wp-job-manager' );
			$css_class            = 'wpjm-activate-licence-link';
		}
		$actions[] = '<a class="' . esc_attr( $css_class ) . '" href="' . esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-addons&section=helper' ) ) . '">' . esc_html( $manage_licence_label ) . '</a>';

		return $actions;
	}

	/**
	 * Returns the plugin info for a licensed WPJM plugin.
	 *
	 * @param string $product_slug
	 *
	 * @return bool|object
	 */
	protected function get_plugin_info( $product_slug ) {
		if ( ! $this->is_product_installed( $product_slug ) ) {
			return false;
		}
		$args                   = $this->get_plugin_licence( $product_slug );
		$args['api_product_id'] = $product_slug;

		$response = $this->api->plugin_information( $args );
		$this->handle_api_errors( $product_slug, $response );

		return $response;
	}

	/**
	 * Checks if a WPJM plugin is installed.
	 *
	 * @param string $product_slug
	 *
	 * @return bool
	 */
	public function is_product_installed( $product_slug ) {
		$product_plugins = $this->get_installed_plugins();

		return isset( $product_plugins[ $product_slug ] );
	}

	/**
	 * Returns true if there are licensed products being managed.
	 *
	 * @return bool
	 */
	public function has_licenced_products() {
		$product_plugins = $this->get_installed_plugins();

		return ! empty( $product_plugins );
	}

	/**
	 * Returns the plugin data for plugin with a `WPJM-Product` tag by plugin filename.
	 *
	 * @param string $plugin_filename
	 * @return bool|array
	 */
	private function get_licence_managed_plugin( $plugin_filename ) {
		foreach ( $this->get_installed_plugins() as $plugin ) {
			if ( $plugin_filename === $plugin['_filename'] ) {
				return $plugin;
			}
		}
		return false;
	}

	/**
	 * Gets the license key and email for a WPJM managed plugin.
	 *
	 * @param string $product_slug
	 * @return array|bool
	 */
	public function get_plugin_licence( $product_slug ) {
		$licence_key      = WP_Job_Manager_Helper_Options::get( $product_slug, 'licence_key' );
		$activation_email = WP_Job_Manager_Helper_Options::get( $product_slug, 'email' );
		$errors           = WP_Job_Manager_Helper_Options::get( $product_slug, 'errors' );

		return [
			'licence_key' => $licence_key,
			'email'       => $activation_email,
			'errors'      => $errors,
		];
	}

	/**
	 * Check if an official extension has an active license.
	 *
	 * @param string $product_slug
	 *
	 * @return bool
	 */
	public function has_plugin_licence( $product_slug ) {
		$licence = $this->get_plugin_licence( $product_slug );

		return ! empty( $licence['licence_key'] );
	}

	/**
	 * Adds newly recognized data header in WordPress plugin files.
	 *
	 * @param array $headers
	 * @return array
	 */
	public function extra_headers( $headers ) {
		$headers[] = 'WPJM-Product';
		return $headers;
	}

	/**
	 * Returns list of installed WPJM plugins with managed licenses indexed by product ID.
	 *
	 * @param bool $active_only Only return active plugins.
	 * @return array
	 */
	public function get_installed_plugins( $active_only = true ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		/**
		 * Clear the plugin cache on first request for installed WPJM add-on plugins.
		 *
		 * @since 1.29.1
		 *
		 * @param bool $clear_plugin_cache True if we should clear the plugin cache.
		 */
		if ( ! self::$cleared_plugin_cache && apply_filters( 'job_manager_clear_plugin_cache', true ) ) {
			// Reset the plugin cache on the first call. Some plugins prematurely hydrate the cache.
			wp_clean_plugins_cache( false );
			self::$cleared_plugin_cache = true;
		}

		$wpjm_plugins = [];
		$plugins      = get_plugins();

		foreach ( $plugins as $filename => $data ) {
			if ( empty( $data['WPJM-Product'] ) || ( true === $active_only && ! is_plugin_active( $filename ) ) ) {
				continue;
			}

			$data['_filename']                     = $filename;
			$data['_product_slug']                 = $data['WPJM-Product'];
			$data['_type']                         = 'plugin';
			$wpjm_plugins[ $data['WPJM-Product'] ] = $data;
		}

		return $wpjm_plugins;
	}

	/**
	 * Outputs the license management.
	 */
	public function licence_output() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Flow use only. Method does nonce check.
		if ( ! empty( $_POST ) ) {
			$this->handle_request();
		}
		$licenced_plugins = $this->get_installed_plugins();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No need for nonce here.
		$search_term        = sanitize_text_field( wp_unslash( $_REQUEST['s'] ?? '' ) );
		$licenced_plugins   = $this->search_licenced_plugins( $licenced_plugins, $search_term );
		$active_plugins     = array_filter(
			$licenced_plugins,
			function ( $product_slug ) {
				return $this->has_plugin_licence( $product_slug );
			},
			ARRAY_FILTER_USE_KEY
		);
		$inactive_plugins   = array_filter(
			$licenced_plugins,
			function ( $product_slug ) {
				return ! $this->has_plugin_licence( $product_slug );
			},
			ARRAY_FILTER_USE_KEY
		);
		$show_bulk_activate = $this->show_bulk_activation_form( $licenced_plugins );
		include_once dirname( __FILE__ ) . '/views/html-licences.php';
	}

	/**
	 * Search for the list of licenced plugins.
	 *
	 * @param array  $licenced_plugins The array of licenced plugins to filter.
	 * @param string $search_term      The search term to filter by.
	 * @return array The filtered list of licenced plugins.
	 */
	private function search_licenced_plugins( $licenced_plugins, $search_term ) {
		if ( ! empty( $search_term ) ) {
			$search_term      = strtolower( $search_term );
			$licenced_plugins = array_filter(
				$licenced_plugins,
				function ( $plugin ) use ( $search_term ) {
					return str_contains( strtolower( $plugin['Name'] ), $search_term )
						|| str_contains( strtolower( $plugin['Description'] ), $search_term )
						|| str_contains( strtolower( $plugin['Author'] ), $search_term );
				}
			);
		}
		return $licenced_plugins;
	}

	/**
	 * Return if we should show or not the bulk activation form.
	 *
	 * @param array $licenced_plugins The list of licensed plugins to handle.
	 *
	 * @return bool If we should show the bulk activation form or not.
	 */
	private function show_bulk_activation_form( $licenced_plugins ) {
		foreach ( array_keys( $licenced_plugins ) as $product_slug ) {
			$licence = self::get_plugin_licence( $product_slug );
			if ( empty( $licence['licence_key'] ) && apply_filters( 'wpjm_display_license_form_for_addon', true, $product_slug ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Outputs unset license key notices.
	 */
	public function licence_error_notices() {
		$screen = get_current_screen();
		if (
			null === $screen ||
			in_array( $screen->id, [ 'job_listing_page_job-manager-addons' ], true ) ||
			! current_user_can( 'update_plugins' )
		) {
			return;
		}
		foreach ( $this->get_installed_plugins() as $product_slug => $plugin_data ) {
			$licence = $this->get_plugin_licence( $product_slug );
			if ( ! WP_Job_Manager_Helper_Options::get( $product_slug, 'hide_key_notice' ) ) {
				if ( empty( $licence['licence_key'] ) ) {
					include 'views/html-licence-key-notice.php';
				} elseif ( ! empty( $licence['errors'] ) ) {
					include 'views/html-licence-key-error.php';
				}
			}
		}
	}

	/**
	 * Handles a request on the manage licence key screen.
	 *
	 * @return void
	 */
	private function handle_request() {
		if (
			empty( $_POST['action'] )
			|| empty( $_POST['_wpnonce'] )
			|| ! check_admin_referer( 'wpjm-manage-licence' )
		) {
			return;
		}
		if ( str_starts_with( sanitize_text_field( wp_unslash( $_POST['action'] ) ), 'bulk_' ) ) {
			$this->handle_bulk_request();
		} else {
			$this->handle_single_request();
		}
	}

	/**
	 * Handle a request for a single product on the manage licence key screen.
	 *
	 * @return void
	 */
	private function handle_single_request() {
		$licenced_plugins = $this->get_installed_plugins();
		if (
			empty( $_POST['action'] )
			|| empty( $_POST['_wpnonce'] )
			|| ! check_admin_referer( 'wpjm-manage-licence' )
			|| empty( $_POST['product_slug'] )
			|| ! isset( $licenced_plugins[ $_POST['product_slug'] ] )
		) {
			return;
		}
		$product_slug = sanitize_text_field( wp_unslash( $_POST['product_slug'] ) );
		switch ( $_POST['action'] ) {
			case 'activate':
				if ( empty( $_POST['licence_key'] ) ) {
					$this->add_error( $product_slug, __( 'Please enter a valid license key in order to activate this plugin\'s license.', 'wp-job-manager' ) );
					break;
				}
				$licence_key = sanitize_text_field( wp_unslash( $_POST['licence_key'] ) );
				$this->activate_licence( $product_slug, $licence_key, '' );
				break;
			case 'deactivate':
				$this->deactivate_licence( $product_slug );
				break;
		}
	}

	/**
	 * Handle a bulk request on the manage licence key screen.
	 *
	 * @return void
	 */
	private function handle_bulk_request() {
		if (
			empty( $_POST['action'] )
			|| 'bulk_activate' !== $_POST['action']
			|| empty( $_POST['_wpnonce'] )
			|| ! check_admin_referer( 'wpjm-manage-licence' )
			|| empty( $_POST['product_slugs'] )
			|| ! is_array( $_POST['product_slugs'] ) ) {
			return;
		}
		$product_slugs = array_map( 'sanitize_text_field', wp_unslash( $_POST['product_slugs'] ) );
		if ( empty( $_POST['licence_key'] ) ) {
			foreach ( $product_slugs as $product_slug ) {
				$this->add_error( $product_slug, __( 'Please enter a valid license key in order to activate the licenses of the plugins.', 'wp-job-manager' ) );
			}
			return;
		}
		$licence_key = sanitize_text_field( wp_unslash( $_POST['licence_key'] ) );
		$this->bulk_activate_licence( $licence_key, $product_slugs );
	}

	/**
	 * Activate multiple WPJM add-on plugins with a single licence key.
	 *
	 * @param string   $licence_key   The licence key to activate.
	 * @param string[] $product_slugs The product slugs to activate.
	 * @return void
	 */
	public function bulk_activate_licence( $licence_key, $product_slugs ) {
		$response = $this->api->bulk_activate(
			$licence_key,
			$product_slugs
		);
		if ( false === $response ) {
			// If response is false, the request failed, then we can consider that we returned the same error message
			// for every product slug.
			$response = array_fill_keys(
				$product_slugs,
				[
					'error_code'    => 'connection_error',
					'error_message' => __( 'There was an error activating your license key. Please try again later.', 'wp-job-manager' ),
				]
			);
		}
		$error_messages       = array_column( $response, 'error_message' );
		$skip_invalid_product = true;
		// We only handle bulk errors if there are multiple products to activate, and if all products returned the same
		// error.
		if ( 1 < count( $product_slugs ) && count( $product_slugs ) === count( $error_messages ) ) {
			// If there's an error for each product, then we want to show the errors for invalid products too.
			$skip_invalid_product  = false;
			$error_messages_unique = array_unique( $error_messages );
			// Now, if we ONLY HAVE one kind of error, then we just print it once, in the bulk form.
			if ( 1 === count( $error_messages_unique ) ) {
				$this->add_error( 'bulk-activate', $error_messages_unique[0] );
				return;
			}
		}
		foreach ( $product_slugs as $product_slug ) {
			$result = $response && isset( $response[ $product_slug ] ) ? $response[ $product_slug ] : false;
			if ( $skip_invalid_product && false !== $result && ! $result['success'] && 'invalid_product' === $result['error_code'] ) {
				continue;
			}
			$this->handle_product_activation_response( $result, $product_slug, $licence_key, false );
		}
	}

	/**
	 * Activate a licence key for a WPJM add-on plugin.
	 *
	 * @param string $product_slug The slug of the product to activate.
	 * @param string $licence_key  The licence key to activate.
	 * @param string $email        The e-mail associated with the license. Optional (and actually not used).
	 */
	public function activate_licence( $product_slug, $licence_key, $email = '' ) {
		$response = $this->api->activate(
			[
				'api_product_id' => $product_slug,
				'licence_key'    => $licence_key,
				'email'          => $email,
			]
		);

		$this->handle_product_activation_response( $response, $product_slug, $licence_key );
	}

	/**
	 * Deactivate a licence key for a WPJM add-on plugin.
	 *
	 * @param string $product_slug
	 */
	private function deactivate_licence( $product_slug ) {
		$licence = $this->get_plugin_licence( $product_slug );
		if ( empty( $licence['licence_key'] ) ) {
			$this->add_error( $product_slug, __( 'license is not active.', 'wp-job-manager' ) );
			return;
		}
		$this->api->deactivate(
			[
				'api_product_id' => $product_slug,
				'licence_key'    => $licence['licence_key'],
				'email'          => $licence['email'],
			]
		);

		WP_Job_Manager_Helper_Options::delete( $product_slug, 'licence_key' );
		WP_Job_Manager_Helper_Options::delete( $product_slug, 'email' );
		WP_Job_Manager_Helper_Options::delete( $product_slug, 'errors' );
		WP_Job_Manager_Helper_Options::delete( $product_slug, 'hide_key_notice' );
		delete_site_transient( 'update_plugins' );
		$this->add_success( $product_slug, __( 'Plugin license has been deactivated.', 'wp-job-manager' ) );

		self::log_event(
			'license_deactivated',
			[
				'slug' => $product_slug,
			]
		);
	}

	/**
	 * Handle errors from the API.
	 *
	 * @param string $product_slug
	 * @param array  $response
	 */
	private function handle_api_errors( $product_slug, $response ) {
		$plugin_products = $this->get_installed_plugins();
		if ( ! isset( $plugin_products[ $product_slug ] ) ) {
			return;
		}

		$errors         = ! empty( $response['errors'] ) ? $response['errors'] : [];
		$allowed_errors = [ 'no_activation', 'expired_key', 'expiring_soon', 'update_available' ];
		$ignored_errors = array_diff( array_keys( $errors ), $allowed_errors );

		foreach ( $ignored_errors as $key ) {
			unset( $errors[ $key ] );
		}

		if ( ! empty( $errors['no_activation'] ) ) {
			$this->deactivate_licence( $product_slug );
		}

		WP_Job_Manager_Helper_Options::update( $product_slug, 'errors', $errors );
	}

	/**
	 * Add an error message.
	 *
	 * @param string $product_slug The plugin slug.
	 * @param string $message      Your error message.
	 */
	private function add_error( $product_slug, $message ) {
		$this->add_message( 'error', $product_slug, $message );
	}

	/**
	 * Add a success message.
	 *
	 * @param string $product_slug The plugin slug.
	 * @param string $message      Your error message.
	 */
	private function add_success( $product_slug, $message ) {
		$this->add_message( 'success', $product_slug, $message );
	}

	/**
	 * Add a message.
	 *
	 * @param string $type         Message type.
	 * @param string $product_slug The plugin slug.
	 * @param string $message      Your error message.
	 */
	private function add_message( $type, $product_slug, $message ) {
		if ( ! isset( $this->licence_messages[ $product_slug ] ) ) {
			$this->licence_messages[ $product_slug ] = [];
		}
		$this->licence_messages[ $product_slug ][] = [
			'type'    => $type,
			'message' => $message,
		];
	}

	/**
	 * Get a plugin's licence messages.
	 *
	 * @param string $product_slug The plugin slug.
	 * @return array
	 */
	public function get_messages( $product_slug ) {
		if ( ! isset( $this->licence_messages[ $product_slug ] ) ) {
			$this->licence_messages[ $product_slug ] = [];
		}

		return $this->licence_messages[ $product_slug ];
	}

	/**
	 * Thin wrapper for WP_Job_Manager_Usage_Tracking::log_event().
	 *
	 * @param string $event_name The name of the event, without the `wpjm` prefix.
	 * @param array  $properties The event properties to be sent.
	 */
	private function log_event( $event_name, $properties = [] ) {
		if ( ! class_exists( 'WP_Job_Manager_Usage_Tracking' ) ) {
			return;
		}

		WP_Job_Manager_Usage_Tracking::log_event( $event_name, $properties );
	}

	/**
	 * Handle the response of the product activation API on WPJobManager.com.
	 *
	 * @param array|boolean $response             The response to handle.
	 * @param string        $product_slug         The slug of the product.
	 * @param string        $licence_key          The licence key being activated.
	 * @param boolean       $show_success_message Whether to show a success message or not.
	 * @return void
	 */
	private function handle_product_activation_response( $response, $product_slug, $licence_key, $show_success_message = true ) {
		$error = false;
		if ( ! isset( $response['error_message'] ) && isset( $response['error'] ) ) {
			$response['error_message'] = $response['error'];
		}
		if ( ! isset( $item['activated'] ) && isset( $response['success'] ) ) {
			$response['activated'] = $response['success'];
		}
		if ( false === $response ) {
			$error = 'connection_failed';
			$this->add_error( $product_slug, __( 'Connection failed to the License Key API server - possible server issue.', 'wp-job-manager' ) );
		} elseif ( isset( $response['error_code'] ) && isset( $response['error_message'] ) ) {
			$error = $response['error_code'];
			$this->add_error( $product_slug, $response['error_message'] );
		} elseif ( ! empty( $response['activated'] ) ) {
			WP_Job_Manager_Helper_Options::update( $product_slug, 'licence_key', $licence_key );
			WP_Job_Manager_Helper_Options::delete( $product_slug, 'errors' );
			WP_Job_Manager_Helper_Options::delete( $product_slug, 'hide_key_notice' );
			if ( $show_success_message ) {
				$this->add_success( $product_slug, __( 'Plugin license has been activated.', 'wp-job-manager' ) );
			}
		} else {
			$error = 'unknown';
			$this->add_error( $product_slug, __( 'An unknown error occurred while attempting to activate the license', 'wp-job-manager' ) );
		}

		$event_properties = [ 'slug' => $product_slug ];
		if ( false !== $error ) {
			$event_properties['error'] = $error;
			self::log_event( 'license_activation_error', $event_properties );
		} else {
			self::log_event( 'license_activated', $event_properties );
		}
	}
}

WP_Job_Manager_Helper::instance()->init();
