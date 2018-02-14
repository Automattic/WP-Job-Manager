<?php
/**
 * Usage tracking data
 **/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supplies the usage tracking data for logging.
 *
 * @package Usage Tracking
 * @since 1.30.0
 */
class WP_Job_Manager_Usage_Tracking_Data {
	/**
	 * Get the usage tracking data to send.
	 *
	 * @since 1.30.0
	 *
	 * @return array Usage data.
	 **/
	public static function get_usage_data() {
		$count_posts = wp_count_posts( 'job_listing' );

		return array(
			'employers'            => self::get_employer_count(),
			'jobs_expired'         => isset( $count_posts->expired ) ? $count_posts->expired : 0,
			'jobs_pending'         => $count_posts->pending,
			'jobs_pending_payment' => isset( $count_posts->pending_payment ) ? $count_posts->pending_payment : 0,
			'jobs_preview'         => isset( $count_posts->preview ) ? $count_posts->preview : 0,
			'jobs_publish'         => $count_posts->publish,
		);
	}

	/**
	 * Get the total number of users with the "employer" role.
	 *
	 * @return int the number of "employers".
	 */
	private static function get_employer_count() {
		$employer_query = new WP_User_Query( array(
			'fields' => 'ID',
			'role' => 'employer',
		) );

		return $employer_query->total_users;
	}
}
