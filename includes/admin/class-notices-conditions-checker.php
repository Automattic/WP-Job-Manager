<?php
/**
 * File containing the class Notices_Conditions_Checker.
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notices_Conditions_Checker class.
 *
 * @since 1.40.0
 * @internal
 */
class Notices_Conditions_Checker {

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
	public function check( $conditions ) {
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

					if ( ! $this->condition_check_min_php( $condition['version'] ) ) {
						$can_see_notice = false;
						break 2;
					}

					break;
				case 'min_wp':
					if ( ! isset( $condition['version'] ) ) {
						break;
					}

					if ( ! $this->condition_check_min_wp( $condition['version'] ) ) {
						$can_see_notice = false;
						break 2;
					}

					break;
				case 'user_cap':
					if ( ! isset( $condition['capabilities'] ) || ! is_array( $condition['capabilities'] ) ) {
						break;
					}

					if ( ! $this->condition_check_capabilities( $condition['capabilities'] ) ) {
						$can_see_notice = false;
						break 2;
					}

					break;
				case 'screens':
					if ( ! isset( $condition['screens'] ) || ! is_array( $condition['screens'] ) ) {
						break;
					}
					$has_screen_condition = true;
					if ( ! $this->condition_check_screen( $condition['screens'] ) ) {
						$can_see_notice = false;
						break 2;
					}

					break;
				case 'plugins':
					if ( ! isset( $condition['plugins'] ) || ! is_array( $condition['plugins'] ) ) {
						break;
					}

					if ( ! $this->condition_check_plugin( $condition['plugins'] ) ) {
						$can_see_notice = false;
						break 2;
					}
					break;
				case 'date_range':
					if ( ! isset( $condition['start_date'] ) && ! isset( $condition['end_date'] ) ) {
						break;
					}

					if ( ! $this->condition_check_date_range( $condition['start_date'] ?? null, $condition['end_date'] ?? null ) ) {
						$can_see_notice = false;
						break 2;
					}

					break;
			}
		}

		// If no screens condition was set, only show this message on WPJM screens.
		if ( $can_see_notice && ! $has_screen_condition && ! $this->condition_check_screen( [ self::ALL_WPJM_SCREENS_PLACEHOLDER ] ) ) {
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
	private function condition_check_screen( array $allowed_screens ): bool {
		$condition_pass = true;

		if ( in_array( self::ALL_WPJM_SCREENS_PLACEHOLDER, $allowed_screens, true ) ) {
			$allowed_screens = array_merge( $allowed_screens, self::ALL_WPJM_SCREEN_IDS );
		}

		$screen_id = $this->get_screen_id();

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
	private function get_screen_id() {
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
	private function condition_check_min_php( string $min_version ): bool {
		return version_compare( phpversion(), $min_version, '>=' );
	}

	/**
	 * Check a WP version condition.
	 *
	 * @param string $min_version Minimum WP version.
	 * @return bool
	 */
	private function condition_check_min_wp( string $min_version ): bool {
		return version_compare( get_bloginfo( 'version' ), $min_version, '>=' );
	}


	/**
	 * Check a capability condition.
	 *
	 * @param array $allowed_caps Array of capabilities that the user must have.
	 * @return bool
	 */
	private function condition_check_capabilities( array $allowed_caps ): bool {
		$condition_pass = true;

		foreach ( $allowed_caps as $cap ) {
			if ( ! current_user_can( $cap ) ) {
				$condition_pass = false;
				break;
			}
		}

		return $condition_pass;
	}


	/**
	 * Check a plugin condition.
	 *
	 * @param array $allowed_plugins Array of the plugins to check for.
	 *
	 * @return bool
	 */
	private function condition_check_plugin( array $allowed_plugins ): bool {
		$condition_pass = true;
		$active_plugins = $this->get_active_plugins();

		foreach ( $allowed_plugins as $plugin_basename => $plugin_condition ) {
			$plugin_active  = isset( $active_plugins[ $plugin_basename ] );
			$plugin_version = isset( $active_plugins[ $plugin_basename ]['Version'] ) ? $active_plugins[ $plugin_basename ]['Version'] : false;

			if ( false === $plugin_condition ) {
				// The plugin should not be active.
				if ( $plugin_active ) {
					$condition_pass = false;
					break;
				}
			} elseif ( true === $plugin_condition ) {
				// The plugin just needs to be active.
				if ( ! $plugin_active ) {
					$condition_pass = false;
					break;
				}
			} elseif ( isset( $plugin_condition['min'] ) || isset( $plugin_condition['max'] ) ) {
				// There is a plugin version condition, but we expect the plugin to be activated.
				if ( ! $plugin_active ) {
					$condition_pass = false;
					break;
				}

				if ( isset( $plugin_condition['min'] ) && version_compare( $plugin_version, $plugin_condition['min'], '<' ) ) {
					// If the activated plugin version is older than the minimum required, do not show the notice.
					$condition_pass = false;
					break;
				}

				if ( isset( $plugin_condition['max'] ) && version_compare( $plugin_version, $plugin_condition['max'], '>' ) ) {
					// If the activated plugin version is newer than the maximum required, do not show the notice.
					$condition_pass = false;
					break;
				}
			}
		}

		return $condition_pass;
	}

	/**
	 * Partial wrapper for `get_plugins()` function. Filters out non-active plugins.
	 *
	 * @return array Key is basename of active plugins and value is version.
	 */
	protected function get_active_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		foreach ( $plugins as $plugin_basename => $plugin_data ) {
			if ( ! is_plugin_active( $plugin_basename ) ) {
				unset( $plugins[ $plugin_basename ] );
			}
		}

		return $plugins;
	}

	/**
	 * Check a date range condition.
	 *
	 * @param ?string $start_date_str Start date.
	 * @param ?string $end_date_str   End date.
	 *
	 * @return bool
	 */
	private function condition_check_date_range( ?string $start_date_str, ?string $end_date_str ): bool {
		$now = new \DateTime();

		// Defaults to WP timezone, but can be overridden by passing string that includes timezone.
		$start_date = $start_date_str ? date_create( $start_date_str, wp_timezone() ) : null;
		$end_date   = $end_date_str ? date_create( $end_date_str, wp_timezone() ) : null;

		// If the passed date strings are invalid, don't show the notice.
		if ( false === $start_date || false === $end_date ) {
			return false;
		}

		if ( $start_date && $now < $start_date ) {
			return false;
		}

		if ( $end_date && $now > $end_date ) {
			return false;
		}

		return true;
	}
}
