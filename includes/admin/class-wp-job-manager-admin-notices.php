<?php
/**
 * File containing the class WP_Job_Manager_Admin_Notices.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Job_Manager\Admin\Notices_Conditions_Checker;
use WP_Job_Manager\WP_Job_Manager_Com_API;

/**
 * WP_Job_Manager_Admin_Notices class.
 *
 * @since 1.32.0
 */
class WP_Job_Manager_Admin_Notices {
	/**
	 * Notice states.
	 *
	 * @deprecated 1.40.0 This option should not be used anymore.
	 */
	const STATE_OPTION                  = 'job_manager_admin_notices';
	const NOTICE_CORE_SETUP             = 'core_setup';
	const NOTICE_ADDON_UPDATE_AVAILABLE = 'addon_update_available';
	const DISMISS_NOTICE_ACTION         = 'wp_job_manager_dismiss_notice';
	const DISMISSED_NOTICES_OPTION      = 'wp_job_manager_dismissed_notices';
	const DISMISSED_NOTICES_USER_META   = 'wp_job_manager_dismissed_notices';

	const ALLOWED_HTML = [
		'div'    => [
			'class' => [],
		],
		'a'      => [
			'target' => [],
			'href'   => [],
			'rel'    => [],
		],
		'em'     => [],
		'strong' => [],
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
		add_action( 'wp_ajax_wp_job_manager_dismiss_notice', [ __CLASS__, 'handle_notice_dismiss' ] );
		add_filter( 'wpjm_admin_notices', [ __CLASS__, 'maybe_add_addon_update_available_notice' ], 10, 1 );
		add_filter( 'wpjm_admin_notices', [ __CLASS__, 'paid_listings_renewal_notice' ], 10, 1 );
	}

	/**
	 * Get and show our icon in WPJM notices.
	 *
	 * @param string $icon_name Icon ID passed from API.
	 *
	 * @return string path to the image
	 */
	private static function get_icon( $icon_name ) {
		switch ( $icon_name ) {
			case 'wpjm':
				return JOB_MANAGER_PLUGIN_URL . '/assets/images/wpjm-logo.png';
		}
		return JOB_MANAGER_PLUGIN_URL . '/assets/images/wpjm-logo.png';
	}

	/**
	 * Add a notice to be displayed in WP admin.
	 *
	 * @since      1.32.0
	 * @deprecated 1.40.0 Use the `job_manager_admin_notices` filter instead to add your own notices. You might need to persist an option/flag by yourself.
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
	 * @since      1.32.0
	 * @deprecated 1.40.0 Use the `job_manager_admin_notices` filter instead to add your own notices. You might need to persist an option/flag by yourself.
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
	 *
	 * @deprecated 1.40.0 Use the `job_manager_admin_notices` filter instead.
	 */
	public static function reset_notices() {
		self::$notice_state = [];
		self::save_notice_state();
	}

	/**
	 * Check for a notice to be displayed in WP admin.
	 *
	 * @since      1.32.0
	 * @deprecated 1.40.0 Use the `job_manager_admin_notices` filter instead.
	 *
	 * @param string $notice Name of the notice. Name is not sanitized for this method.
	 *
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
	 * Returns all the registered notices.
	 * This includes notices coming from wpjobmanager.com and notices added by other plugins using the `job_manager_admin_notices` filter.
	 *
	 * The notices will be later filtered when displayed with `display_notices()` method.
	 *
	 * @return mixed
	 */
	public static function get_notices() {

		$remote_notices = WP_Job_Manager_Com_API::instance()->get_notices();

		/**
		 * Filters the admin notices. Allows to add or remove notices.
		 *
		 * @since 1.40.0
		 *
		 * @param array $notices The admin notices.
		 *
		 * @return array The admin notices.
		 */
		$all_notices = apply_filters( 'wpjm_admin_notices', $remote_notices );

		return $all_notices;
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
		 * @since      1.32.0
		 * @deprecated 1.40.0 Use the `job_manager_admin_notices` filter instead to add your own notices.
		 */
		do_action( 'job_manager_init_admin_notices' );

		$notice_state = self::get_notice_state();
		foreach ( $notice_state as $notice ) {
			/**
			 * Allows suppression of individual admin notices.
			 *
			 * @since      1.32.0
			 * @deprecated 1.40.0 Use the `job_manager_admin_notices` filter instead to remove notices.
			 *
			 * @param bool $do_show_notice Set to false to prevent an admin notice from showing up.
			 */

			if ( ! apply_filters( 'job_manager_show_admin_notice_' . $notice, true ) ) {
				continue;
			}

			/**
			 * Handle the display of the admin notice.
			 *
			 * @since      1.32.0
			 * @deprecated 1.40.0 Use the `job_manager_admin_notices` to add your own notices with the normalised format.
			 */
			do_action( 'job_manager_admin_notice_' . $notice );
		}

		$notices = self::get_notices();

		$condition_checker = new Notices_Conditions_Checker();
		foreach ( $notices as $notice_id => $notice ) {
			$notice               = self::normalize_notice( $notice );
			$is_user_notification = 'user' === $notice['type'];
			if ( in_array( $notice_id, self::get_dismissed_notices( $is_user_notification ), true ) ) {
				continue;
			}
			if ( $condition_checker->check( $notice['conditions'] ?? [] ) ) {
				self::render_notice( $notice_id, $notice );
			}
		}
	}

	/**
	 * Normalize notices.
	 *
	 * @param array $notice The notice configuration.
	 *
	 * @return array
	 */
	private static function normalize_notice( $notice ) {
		if ( ! isset( $notice['conditions'] ) || ! is_array( $notice['conditions'] ) ) {
			$notice['conditions'] = [];
		}

		if ( ! isset( $notice['type'] ) ) {
			$notice['type'] = 'site-wide';
		}

		if ( 'site-wide' === $notice['type'] ) {
			// Only admins can see and manage site-wide notifications.
			$notice['conditions'][] = [
				'type'         => 'user_cap',
				'capabilities' => [ 'manage_options' ],
			];
		}

		if ( ! isset( $notice['dismissible'] ) ) {
			$notice['dismissible'] = true;
		}

		return $notice;
	}

	/**
	 * Get the dismissed notifications (either for the user or site-wide).
	 *
	 * @param bool $is_user_notification True if this is for a user notification (vs site-wide notification).
	 *
	 * @return array
	 */
	private static function get_dismissed_notices( $is_user_notification ) {
		if ( $is_user_notification ) {
			$dismissed_notices = get_user_meta( get_current_user_id(), self::DISMISSED_NOTICES_USER_META, true );
			if ( ! $dismissed_notices ) {
				$dismissed_notices = [];
			}
		} else {
			$dismissed_notices = get_option( self::DISMISSED_NOTICES_OPTION, [] );
		}

		return $dismissed_notices;
	}

	/**
	 * Save dismissed notices.
	 *
	 * @param array $dismissed_notices    Array of dismissed notices.
	 * @param bool  $is_user_notification True if we are setting user notifications (vs site-wide notifications).
	 */
	private static function save_dismissed_notices( $dismissed_notices, $is_user_notification ) {
		$dismissed_notices = array_unique( $dismissed_notices );
		if ( $is_user_notification ) {
			update_user_meta( get_current_user_id(), self::DISMISSED_NOTICES_USER_META, $dismissed_notices );
		} else {
			update_option( self::DISMISSED_NOTICES_OPTION, $dismissed_notices );
		}
	}

	/**
	 * Handle the dismissal of the notice.
	 *
	 * @access private
	 */
	public static function handle_notice_dismiss() {
		check_ajax_referer( self::DISMISS_NOTICE_ACTION, 'nonce' );

		$notices   = self::get_notices();
		$notice_id = isset( $_POST['notice'] ) ? sanitize_text_field( wp_unslash( $_POST['notice'] ) ) : false;
		if ( ! $notice_id || ! isset( $notices[ $notice_id ] ) ) {
			return;
		}

		$notice = self::normalize_notice( $notices[ $notice_id ] );

		$is_dismissible       = $notice['dismissible'];
		$is_user_notification = 'user' === $notice['type'];
		if (
			! $is_dismissible
			|| ( ! $is_user_notification && ! current_user_can( 'manage_options' ) )
		) {
			wp_die( '', '', 403 );
		}

		$dismissed_notices   = self::get_dismissed_notices( $is_user_notification );
		$dismissed_notices[] = $notice_id;

		self::save_dismissed_notices( $dismissed_notices, $is_user_notification );

		wp_die();
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
	 * Adds notice that informs about WPJM addon updates available if any.
	 *
	 * @param array $notices Existing notices.
	 *
	 * @internal
	 *
	 * @return mixed
	 */
	public static function maybe_add_addon_update_available_notice( $notices ) {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return $notices;
		}

		$updates = get_site_transient( 'wpjm_addon_updates_available', [] );
		if ( ! empty( $updates ) ) {
			$notice = self::generate_notice_from_updates( $updates );
			if ( ! is_null( $notice ) ) {
				$notice_id             = self::generate_notice_id_from_updates( $updates );
				$notices[ $notice_id ] = $notice;
			}
		}
		return $notices;
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
		$extra_details       = null;
		$update_action_label = __( 'Update', 'wp-job-manager' );
		$first_update        = current( $updates );
		// translators: %s is the name of the wpjm addon to be updated.
		$message = sprintf( esc_html__( 'Good news, reminder to update to the latest version of %s.', 'wp-job-manager' ), $first_update['plugin_name'] );
		$actions = [
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
			$extra_details       = '';
			$actions             = []; // Remove more_info link.
			foreach ( $updates as $update ) {
				$extra_details .= '<div class="wpjm-addon-update-notice-info">';
				$extra_details .= '<div class="wpjm-addon-update-notice-info__name">' . esc_html( $update['plugin_name'] ) . '</div>';
				$extra_details .= '<div class="wpjm-addon-update-notice-info__version">';
				$extra_details .= '<a href="https://wpjobmanager.com/release-notes/" target="_blank">';
				// translators: %s is the new version number for the addon.
				$extra_details .= sprintf( esc_html__( 'New Version: %s', 'wp-job-manager' ), $update['new_version'] );
				$extra_details .= '</a>';
				$extra_details .= '</div>';
				$extra_details .= '</div>';
			}
		}

		$actions[] = [
			'label' => $update_action_label,
			'url'   => admin_url( 'plugins.php' ),
		];

		return [
			'type'          => 'site-wide',
			'message'       => $message,
			'actions'       => $actions,
			'extra_details' => $extra_details,
			'conditions'    => [
				[
					'type'    => 'screens',
					'screens' => [ 'wpjm*', 'dashboard' ],
				],
			],
		];
	}

	/**
	 * Renders a notice.
	 *
	 * @param string $notice_id Unique identifier for the notice.
	 * @param array  $notice    See `generate_notice_from_updates` for format.
	 */
	private static function render_notice( $notice_id, $notice ) {
		if ( empty( $notice['actions'] ) || ! is_array( $notice['actions'] ) ) {
			$notice['actions'] = [];
		}

		$notice_class  = [];
		$notice_levels = [ 'error', 'warning', 'success', 'info' ];
		if ( isset( $notice['level'] ) && in_array( $notice['level'], $notice_levels, true ) ) {
			$notice_class[] = 'wpjm-admin-notice--' . $notice['level'];
		} else {
			$notice_class[] = 'wpjm-admin-notice--info';
		}

		$is_dismissible       = $notice['dismissible'] ?? true;
		$notice_wrapper_extra = '';
		if ( $is_dismissible ) {
			wp_enqueue_script( 'job_manager_notice_dismiss' );
			$notice_class[]       = 'is-dismissible';
			$notice_wrapper_extra = sprintf( ' data-dismiss-action="%1$s" data-dismiss-notice="%2$s" data-dismiss-nonce="%3$s"', esc_attr( self::DISMISS_NOTICE_ACTION ), esc_attr( $notice_id ), esc_attr( wp_create_nonce( self::DISMISS_NOTICE_ACTION ) ) );
		}

		echo '<div class="notice wpjm-admin-notice ' . esc_attr( implode( ' ', $notice_class ) ) . '"';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		echo $notice_wrapper_extra . '>';

		echo '<div class="wpjm-admin-notice__top">';

		$notice['icon'] = 'wpjm';
		if ( ! empty( $notice['icon'] ) ) {
			echo '<img src="' . esc_url( self::get_icon( $notice['icon'] ) ) . '" class="wpjm-admin-notice__icon" alt="WP Job Manager Icon" />';
		}
		echo '<div class="wpjm-admin-notice__message">';
		if ( ! empty( $notice['heading'] ) ) {
			echo '<div class="wpjm-admin-notice__heading">';
			echo wp_kses( $notice['heading'], self::ALLOWED_HTML );
			echo '</div>';
		}
		echo wp_kses( $notice['message'], self::ALLOWED_HTML );
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

		if ( ! empty( $notice['extra_details'] ) ) {
			echo '<div class="wpjm-admin-notice__extra_details">';
			echo wp_kses( $notice['extra_details'], self::ALLOWED_HTML );
			echo '</div>';
		}
		echo '</div>';

	}

	/**
	 * Generate unique notice ID based on the updates available.
	 *
	 * @param array $updates The updates available.
	 * @return string The notice ID.
	 */
	private static function generate_notice_id_from_updates( $updates ) {
		$updates_info = '';
		foreach ( $updates as $update ) {
			$updates_info .= $update['plugin'] . '@' . $update['new_version'];
		}
		return self::NOTICE_ADDON_UPDATE_AVAILABLE . '-' . md5( $updates_info );
	}

	/**
	 * Adds notice to update Simple or WC paid listings plugin to use listing renewal feature.
	 *
	 * @since 1.41.0
	 * @param array $notices Existing notices.
	 *
	 * @return array Notices.
	 */
	public static function paid_listings_renewal_notice( $notices ) {
		if ( ! WP_Job_Manager_Helper_Renewals::is_wcpl_renew_compatible() ) {
			$notices['wcpl_listing_renewal'] = [
				'level'       => 'info',
				'dismissible' => true,
				'message'     => wp_kses_post(
					__( 'Listing renewals require the latest version of WC Paid Listings. Please update the plugin to enable the feature.', 'wp-job-manager' )
				),
			];
		}
		if ( ! WP_Job_Manager_Helper_Renewals::is_spl_renew_compatible() ) {
			$notices['spl_listing_renewal'] = [
				'level'       => 'info',
				'dismissible' => true,
				'message'     => wp_kses_post(
					__( 'Listing renewals require the latest version of Simple Paid Listings. Please update the plugin to enable the feature.', 'wp-job-manager' )
				),
			];
		}
		return $notices;
	}
}

WP_Job_Manager_Admin_Notices::init();
