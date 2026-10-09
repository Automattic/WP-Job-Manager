<?php
/**
 * Helper for the Promoted Jobs feature toggle.
 *
 * Bridges the `job_manager_enable_promoted_jobs` admin option to the
 * `job_manager_enable_promoted_jobs` filter. The option value is the
 * initial value passed to `apply_filters()`, so any developer filter
 * (regardless of priority) overrides the admin setting.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_Promoted_Jobs_Helper
 */
class WP_Job_Manager_Promoted_Jobs_Helper {

	/**
	 * The option key for the admin toggle.
	 */
	const OPTION = 'job_manager_enable_promoted_jobs';

	/**
	 * Determine whether Promoted Jobs is enabled.
	 *
	 * Reads the admin option (default: enabled) and exposes it through the
	 * `job_manager_enable_promoted_jobs` filter. A developer filter wins
	 * over the option at any priority because the option value is the
	 * initial value passed to `apply_filters()`.
	 *
	 * @since $$next-version$$
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$option = get_option( self::OPTION, '1' );

		/**
		 * Filters whether Promoted Jobs is enabled.
		 *
		 * Initial value is the admin option (`job_manager_enable_promoted_jobs`).
		 * A developer filter overrides the option at any priority.
		 *
		 * @since 2.3.0
		 *
		 * @param bool $enabled Whether Promoted Jobs is enabled.
		 */
		return (bool) apply_filters( 'job_manager_enable_promoted_jobs', ! empty( $option ) && '0' !== (string) $option );
	}
}
