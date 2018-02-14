<?php
/**
 * Usage tracking data
 *
 * @package Usage Tracking
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
			'employers'                   => self::get_employer_count(),
			'jobs_type'                   => self::get_job_type_count(),
			'jobs_logo'                   => self::get_company_logo_count(),
			'jobs_status_expired'         => isset( $count_posts->expired ) ? $count_posts->expired : 0,
			'jobs_status_pending'         => $count_posts->pending,
			'jobs_status_pending_payment' => isset( $count_posts->pending_payment ) ? $count_posts->pending_payment : 0,
			'jobs_status_preview'         => isset( $count_posts->preview ) ? $count_posts->preview : 0,
			'jobs_status_publish'         => $count_posts->publish,
			'jobs_location'               => self::get_jobs_with_location_count(),
		);
	}

	/**
	 * Get the total number of users with the "employer" role.
	 *
	 * @return int the number of "employers".
	 */
	private static function get_employer_count() {
		$employer_query = new WP_User_Query(
			array(
				'fields' => 'ID',
				'role'   => 'employer',
			)
		);

		return $employer_query->total_users;
	}

	/**
	 * Get the number of job listings that have a company logo.
	 *
	 * @since 1.30.0
	 *
	 * @return int Number of job listings with a company logo.
	 */
	private static function get_company_logo_count() {
		$query = new WP_Query(
			array(
				'post_type'   => 'job_listing',
				'post_status' => array( 'expired', 'publish' ),
				'fields'      => 'ids',
				'meta_query'  => array(
					array(
						'key'     => '_thumbnail_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		return $query->found_posts;
	}

	/**
	 * Get the total number of job listings that have one or more job types selected.
	 *
	 * @since 1.30.0
	 *
	 * @return array Number of job listings associated with at least one job type.
	 **/
	private static function get_job_type_count() {
		$query = new WP_Query(
			array(
				'post_type'   => 'job_listing',
				'post_status' => array( 'expired', 'publish' ),
				'fields'      => 'ids',
				'tax_query'   => array(
					array(
						'taxonomy' => 'job_listing_type',
						'operator' => 'EXISTS',
					),
				),
			)
		);

		return $query->found_posts;
	}

	/**
	 * Get the number of job listings with a non-empty location.
	 *
	 * @return int the number of job listings.
	 */
	private static function get_jobs_with_location_count() {
		$query = new WP_Query( array(
			'post_type'  => 'job_listing',
			'fields'     => 'ids',
			'meta_query' => array(
				array(
					'key'     => '_job_location',
					'value'   => '[^[:space:]]',
					'compare' => 'REGEXP',
				),
			),
		) );

		return $query->found_posts;
	}
}
