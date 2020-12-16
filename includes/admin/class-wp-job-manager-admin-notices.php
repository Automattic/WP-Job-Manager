<?php
/**
 * File containing the class WP_Job_Manager_Admin_Notices.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Admin_Notices class.
 *
 * @since 1.32.0
 */
class WP_Job_Manager_Admin_Notices {
	const STATE_OPTION      = 'job_manager_admin_notices';
	const NOTICE_CORE_SETUP = 'core_setup';

	/**
	 * Current notices for admin user.
	 *
	 * @var array
	 */
	private static $notice_state;

	/**
	 * Initialize admin notice handling.
	 */
	public static function init() {
		add_action( 'job_manager_init_admin_notices', [ __CLASS__, 'init_core_notices' ] );
		add_action( 'admin_notices', [ __CLASS__, 'display_notices' ] );
		add_action( 'wp_loaded', [ __CLASS__, 'dismiss_notices' ] );
	}

	/**
	 * Add a notice to be displayed in WP admin.
	 *
	 * @since 1.32.0
	 *
	 * @param string $notice Name of the notice.
	 */
	public static function add_notice( $notice ) {
		$notice = sanitize_key( $notice );

		if ( ! in_array( $notice, self::get_notice_state(), true ) ) {
			self::$notice_state[] = $notice;
			self::save_notice_state();
		}
	}

	/**
	 * Remove a notice from those displayed in WP admin.
	 *
	 * @since 1.32.0
	 *
	 * @param string $notice Name of the notice.
	 */
	public static function remove_notice( $notice ) {
		$notice_state = self::get_notice_state();
		$notice       = sanitize_key( $notice );

		$notice_key = array_search( $notice, $notice_state, true );
		if ( false !== $notice_key ) {
			unset( $notice_state[ $notice_key ] );
			self::$notice_state = array_values( $notice_state );
			self::save_notice_state();
		}
	}

	/**
	 * Clears all enqueued notices.
	 */
	public static function reset_notices() {
		self::$notice_state = [];
		self::save_notice_state();
	}

	/**
	 * Check for a notice to be displayed in WP admin.
	 *
	 * @since 1.32.0
	 *
	 * @param string $notice Name of the notice. Name is not sanitized for this method.
	 * @return bool
	 */
	public static function has_notice( $notice ) {
		$notice_state = self::get_notice_state();
		return in_array( $notice, $notice_state, true );
	}

	/**
	 * Set up filters for core admin notices.
	 *
	 * Note: For internal use only. Do not call manually.
	 */
	public static function init_core_notices() {
		// core_setup: Notice is used when first activating WP Job Manager.
		add_action( 'job_manager_admin_notice_' . self::NOTICE_CORE_SETUP, [ __CLASS__, 'display_core_setup' ] );
	}

	/**
	 * Dismiss notices as requested by user. Inspired by WooCommerce's approach.
	 */
	public static function dismiss_notices() {
		if ( isset( $_GET['wpjm_hide_notice'] ) && isset( $_GET['_wpjm_notice_nonce'] ) ) {
			if ( ! wp_verify_nonce( wp_unslash( $_GET['_wpjm_notice_nonce'] ), 'job_manager_hide_notices_nonce' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce should not be modified.
				wp_die( esc_html__( 'Action failed. Please refresh the page and retry.', 'wp-job-manager' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You don&#8217;t have permission to do this.', 'wp-job-manager' ) );
			}

			$hide_notice = sanitize_key( wp_unslash( $_GET['wpjm_hide_notice'] ) );

			self::remove_notice( $hide_notice );

			wp_safe_redirect( remove_query_arg( [ 'wpjm_hide_notice', '_wpjm_notice_nonce' ] ) );
			exit;
		}
	}


	/**
	 * Displays notices in WP admin.
	 *
	 * Note: For internal use only. Do not call manually.
	 */
	public static function display_notices() {
		/**
		 * Allows WPJM related plugins to set up their notice hooks.
		 *
		 * @since 1.32.0
		 */
		do_action( 'job_manager_init_admin_notices' );

		$notice_state = self::get_notice_state();
		foreach ( $notice_state as $notice ) {
			/**
			 * Allows suppression of individual admin notices.
			 *
			 * @since 1.32.0
			 *
			 * @param bool $do_show_notice Set to false to prevent an admin notice from showing up.
			 */

			if ( ! apply_filters( 'job_manager_show_admin_notice_' . $notice, true ) ) {
				continue;
			}

			/**
			 * Handle the display of the admin notice.
			 *
			 * @since 1.32.0
			 */
			do_action( 'job_manager_admin_notice_' . $notice );
		}
	}

	/**
	 * Helper for display functions to check if current request is for admin on a job manager screen.
	 *
	 * @param array $additional_screens Screen IDs to also show a notice on.
	 * @return bool
	 */
	public static function is_admin_on_standard_job_manager_screen( $additional_screens = [] ) {
		$screen          = get_current_screen();
		$screen_id       = $screen ? $screen->id : '';
		$show_on_screens = array_merge(
			[
				'edit-job_listing',
				'edit-job_listing_category',
				'edit-job_listing_type',
				'job_listing_page_job-manager-addons',
				'job_listing_page_job-manager-settings',
			],
			$additional_screens
		);

		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( ! in_array( $screen_id, $show_on_screens, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Displays the setup wizard notice when WPJM is first activated.
	 *
	 * Note: For internal use only. Do not call manually.
	 */
	public static function display_core_setup() {
		if ( ! self::is_admin_on_standard_job_manager_screen( [ 'plugins', 'dashboard' ] ) ) {
			return;
		}
		include dirname( __FILE__ ) . '/views/html-admin-notice-core-setup.php';
	}

	/**
	 * Gets the current admin notices to be displayed.
	 *
	 * @return array
	 */
	private static function get_notice_state() {
		if ( null === self::$notice_state ) {
			self::$notice_state = json_decode( get_option( self::STATE_OPTION, '[]' ), true );
			if ( ! is_array( self::$notice_state ) ) {
				self::$notice_state = [];
			}
		}
		return self::$notice_state;
	}

	/**
	 * Saves the notice state on shutdown.
	 */
	private static function save_notice_state() {
		if ( null === self::$notice_state ) {
			return;
		}

		update_option( self::STATE_OPTION, wp_json_encode( self::get_notice_state() ), false );
	}
}

WP_Job_Manager_Admin_Notices::init();
