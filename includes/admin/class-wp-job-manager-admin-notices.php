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
	const STATE_OPTION                  = 'job_manager_admin_notices';
	const NOTICE_CORE_SETUP             = 'core_setup';
	const NOTICE_ADDON_UPDATE_AVAILABLE = 'addon_update_available';
	const DISMISS_NOTICE_NONCE_ACTION   = 'wpjm-dismiss-notice';
	const DISMISSED_NOTICES_OPTION      = 'wpjm-dismissed-notices';
	const DISMISSED_NOTICES_USER_META   = 'wpjm-dismissed-notices';
	const ALLOWED_HTML                  = [
		'div' => [
			'class' => [],
		],
		'a'   => [
			'target' => [],
			'href'   => [],
			'rel'    => [],
		],
	];

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
		add_action( 'wp_ajax_wpjm_dismiss_notice', [ __CLASS__, 'handle_notice_dismiss' ] );
	}

	/**
	 * Add a notice to be displayed in WP admin.
	 *
	 * @param string $notice Name of the notice.
	 *
	 * @since 1.32.0
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
	 * @param string $notice Name of the notice.
	 *
	 * @since 1.32.0
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
	 * @param string $notice Name of the notice. Name is not sanitized for this method.
	 *
	 * @return bool
	 * @since 1.32.0
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
		add_action(
			'job_manager_admin_notice_' . self::NOTICE_ADDON_UPDATE_AVAILABLE,
			[
				__CLASS__,
				'display_addon_update_available',
			]
		);
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
			 * @param bool $do_show_notice Set to false to prevent an admin notice from showing up.
			 *
			 * @since 1.32.0
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
	 *
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
	 * Displays the notice that informs about WPJM addon updates available.
	 *
	 * Note: For internal use only. Do not call manually.
	 */
	public static function display_addon_update_available() {
		if ( ! self::is_admin_on_standard_job_manager_screen( [ 'dashboard' ] ) ) {
			return;
		}

		$updates = get_transient( 'wpjm_addon_updates_available', [] );

		if ( ! empty( $updates ) ) {
			$notice    = self::generate_notice_from_updates( $updates );
			$notice_id = self::NOTICE_ADDON_UPDATE_AVAILABLE;
			self::render_notice( $notice_id, $notice );
		}
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

	/**
	 * Given an array of updates, generate a notice.
	 *
	 * @param array $updates The contents of the addon updates transient.
	 *
	 * @return array|null
	 */
	private static function generate_notice_from_updates( $updates ) {
		if ( empty( $updates ) ) {
			return null;
		}

		// Default: Single update available.
		$extra_info          = null;
		$update_action_label = __( 'Update', 'wp-job-manager' );
		$message             = __( 'Good news, reminder to update to the latest version of WP Job Manager.', 'wp-job-manager' );
		$actions             = [
			[
				'url'     => 'https://wpjobmanager.com/release-notes/',
				'label'   => __( 'View release notes', 'wp-job-manager' ),
				'target'  => '_blank',
				'primary' => false,
			],
		];

		// Multiple updates: Change message, update action label, remove release notes secondary action and add extra info.
		if ( count( $updates ) > 1 ) {
			$message             = __( 'Good news, reminder to update these plugins to their latest versions.', 'wp-job-manager' );
			$update_action_label = __( 'Update All', 'wp-job-manager' );
			$extra_info          = '';
			$actions             = []; // Remove more_info link.
			foreach ( $updates as $update ) {
				$extra_info .= '<div class="wpjm-addon-update-notice-info">';
				$extra_info .= '<div class="wpjm-addon-update-notice-info__name">' . esc_html( $update['plugin'] ) . '</div>';
				$extra_info .= '<div class="wpjm-addon-update-notice-info__version">';
				$extra_info .= '<a href="https://wpjobmanager.com/release-notes/" target="_blank">';
				// translators: %s is the new version number for the addon.
				$extra_info .= sprintf( esc_html__( 'New Version: %s', 'wp-job-manager' ), $update['new_version'] );
				$extra_info .= '</a>';
				$extra_info .= '</div>';
				$extra_info .= '</div>';
			}
		}

		$actions[] = [
			'label' => $update_action_label,
			'url'   => admin_url( 'plugins.php' ),
		];

		return [
			'message'    => $message,
			'actions'    => $actions,
			'extra_info' => $extra_info,
		];
	}

	/**
	 * Renders a notice.
	 *
	 * @param string $notice_id  Unique identifier for the notice.
	 * @param array  $notice See `generate_notice_from_updates` for format.
	 */
	private static function render_notice( $notice_id, $notice ) {
		if ( empty( $notice['actions'] ) || ! is_array( $notice['actions'] ) ) {
			$notice['actions'] = [];
		}

		$notice_class          = [];
		$notice_class['style'] = 'wpjm-admin-notice--' . ( $notice['style'] ?? 'info' );
		$is_dismissible        = $notice['dismissible'] ?? true;
		$notice_wrapper_extra  = '';
		if ( $is_dismissible ) {
			wp_enqueue_script( 'job_manager_notice_dismiss' );
			$notice_class[]       = 'is-dismissible';
			$notice_wrapper_extra = sprintf( ' data-dismiss-action="wpjm_dismiss_notice" data-dismiss-notice="%1$s" data-dismiss-nonce="%2$s"', esc_attr( $notice_id ), esc_attr( wp_create_nonce( self::DISMISS_NOTICE_NONCE_ACTION ) ) );
		}

		// TODO Remove hard-coded 'is-dismissable' CSS class once all notices are converted to use this class.
		echo '<div class="notice wpjm-admin-notice ' . esc_attr( implode( ' ', $notice_class ) ) . '"' . esc_html( $notice_wrapper_extra ) . '>';

		echo '<div class="wpjm-admin-notice__top">';

		// TODO Implement get_icon method.
		if ( ! empty( $notice['icon'] ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Dynamic parts escaped in the function.
			echo get_icon( $notice['icon'], 'wpjm-notice__icon' );
		}

		echo '<div class="wpjm-admin-notice__message">';
		echo '<strong>' . wp_kses( $notice['message'], self::ALLOWED_HTML ) . '</strong>';
		echo '</div>';
		if ( ! empty( $notice['actions'] ) ) {
			echo '<div class="wpjm-admin-notice__actions">';
			foreach ( $notice['actions'] as $action ) {
				if ( ! isset( $action['label'], $action['url'] ) ) {
					continue;
				}

				$button_class = ! isset( $action['primary'] ) || $action['primary'] ? 'button-primary' : 'button-secondary';
				echo '<a href="' . esc_url( $action['url'] ) . '" target="' . esc_attr( $action['target'] ?? '_self' ) . '" rel="noopener noreferrer" class="button ' . esc_attr( $button_class ) . '">';
				echo esc_html( $action['label'] );
				echo '</a>';
			}
			echo '</div>';
		}
		echo '</div>';

		if ( ! empty( $notice['extra_info'] ) ) {
			echo '<div class="wpjm-admin-notice__extra_info">';
			echo wp_kses( $notice['extra_info'], self::ALLOWED_HTML );
			echo '</div>';
		}
		echo '</div>';

	}
}

WP_Job_Manager_Admin_Notices::init();
