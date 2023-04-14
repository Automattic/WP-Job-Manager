<?php
/**
 * File containing the class WP_Job_Manager_Admin_Notices_Conditions.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Admin_Notices_Conditions class.
 *
 * @since $$next-version$$
 * @internal
 */
class WP_Job_Manager_Admin_Notices_Conditions {

	const ALL_WPJM_SCREENS_PLACEHOLDER = 'wpjm*';

	const ALL_WPJM_SCREEN_IDS = [
		'edit-job_listing',
		'edit-job_listing_category',
		'edit-job_listing_type',
		'job_listing_page_job-manager-addons',
		'job_listing_page_job-manager-settings',
	];

	/**
	 * Check notice conditions.
	 *
	 * @param array $conditions The notice conditions.
	 *
	 * @return bool
	 */
	public static function check( $conditions ) {
		$has_screen_condition = false;
		$can_see_notice       = true;

		foreach ( $conditions as $condition ) {
			if ( ! isset( $condition['type'] ) ) {
				continue;
			}

			switch ( $condition['type'] ) {
				case 'min_php':
					if ( ! isset( $condition['version'] ) ) {
						break;
					}

					if ( ! self::condition_check_min_php( $condition['version'] ) ) {
						$can_see_notice = false;
						break 2;
					}

					break;
				case 'min_wp':
					if ( ! isset( $condition['version'] ) ) {
						break;
					}

					if ( ! self::condition_check_min_wp( $condition['version'] ) ) {
						$can_see_notice = false;
						break 2;
					}

					break;
				case 'user_cap':
					if ( ! isset( $condition['capabilities'] ) || ! is_array( $condition['capabilities'] ) ) {
						break;
					}

					if ( ! self::condition_check_capabilities( $condition['capabilities'] ) ) {
						$can_see_notice = false;
						break 2;
					}

					break;
				case 'screens':
					if ( ! isset( $condition['screens'] ) || ! is_array( $condition['screens'] ) ) {
						break;
					}
					$has_screen_condition = true;
					if ( ! self::condition_check_screen( $condition['screens'] ) ) {
						$can_see_notice = false;
						break 2;
					}

					break;
				case 'plugins':
					// if ( ! isset( $condition['plugins'] ) || ! is_array( $condition['plugins'] ) ) {
					// break;
					// }
					//
					// if ( ! $this->condition_check_plugin( $condition['plugins'] ) ) {
					// $can_see_notice = false;
					// break 2;
					// }
					// break;
			}
		}

		// If no screens condition was set, only show this message on WPJM screens.
		if ( $can_see_notice && ! $has_screen_condition && ! self::condition_check_screen( [ self::ALL_WPJM_SCREENS_PLACEHOLDER ] ) ) {
			$can_see_notice = false;
		}

		return $can_see_notice;
	}

	/**
	 * Check a screen condition.
	 *
	 * @param array $allowed_screens Array of allowed screen IDs. `wpjm*` is a special screen ID for any WPJM screen.
	 *
	 * @return bool
	 */
	private static function condition_check_screen( array $allowed_screens ): bool {
		$condition_pass = true;

		if ( in_array( self::ALL_WPJM_SCREENS_PLACEHOLDER, $allowed_screens, true ) ) {
			$allowed_screens = array_merge( $allowed_screens, self::ALL_WPJM_SCREEN_IDS );
		}

		$screen_id = self::get_screen_id();

		if ( ! $screen_id || ! in_array( $screen_id, $allowed_screens, true ) ) {
			$condition_pass = false;
		}

		return $condition_pass;
	}

	/**
	 * Get the screen ID.
	 *
	 * @return string|null
	 */
	private static function get_screen_id() {
		// Not available before the `admin_init` hook.
		if ( ! function_exists( 'get_current_screen' ) ) {
			return null;
		}

		$screen = get_current_screen();

		return $screen ? $screen->id : null;
	}

	/**
	 * Check a PHP version condition.
	 *
	 * @param string $min_version Minimum PHP version.
	 * @return bool
	 */
	private static function condition_check_min_php( string $min_version ): bool {
		return version_compare( phpversion(), $min_version, '>=' );
	}

	/**
	 * Check a WP version condition.
	 *
	 * @param string $min_version Minimum WP version.
	 * @return bool
	 */
	private static function condition_check_min_wp( string $min_version ): bool {
		return version_compare( get_bloginfo( 'version' ), $min_version, '>=' );
	}


	/**
	 * Check a capability condition.
	 *
	 * @param array $allowed_caps Array of capabilities that the user must have.
	 * @return bool
	 */
	private static function condition_check_capabilities( array $allowed_caps ): bool {
		$condition_pass = true;

		foreach ( $allowed_caps as $cap ) {
			if ( ! current_user_can( $cap ) ) {
				$condition_pass = false;
				break;
			}
		}

		return $condition_pass;
	}
}
