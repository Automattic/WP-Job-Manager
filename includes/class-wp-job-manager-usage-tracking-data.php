<?php
/**
 * File containing the class WP_Job_Manager_Usage_Tracking_Data.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supplies the usage tracking data for logging.
 *
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
		$categories  = 0;
		$count_posts = wp_count_posts( 'job_listing' );

		if ( taxonomy_exists( 'job_listing_category' ) ) {
			$categories = wp_count_terms( 'job_listing_category', [ 'hide_empty' => false ] );
		}

		return [
			'employers'                   => self::get_employer_count(),
			'job_categories'              => $categories,
			'job_categories_desc'         => self::get_job_category_has_description_count(),
			'job_types'                   => wp_count_terms( 'job_listing_type', [ 'hide_empty' => false ] ),
			'job_types_desc'              => self::get_job_type_has_description_count(),
			'job_types_emp_type'          => self::get_job_type_has_employment_type_count(),
			'jobs_type'                   => self::get_job_type_count(),
			'jobs_logo'                   => self::get_company_logo_count(),
			'jobs_status_expired'         => isset( $count_posts->expired ) ? $count_posts->expired : 0,
			'jobs_status_pending'         => $count_posts->pending,
			'jobs_status_pending_payment' => isset( $count_posts->pending_payment ) ? $count_posts->pending_payment : 0,
			'jobs_status_preview'         => isset( $count_posts->preview ) ? $count_posts->preview : 0,
			'jobs_status_publish'         => $count_posts->publish,
			'jobs_location'               => self::get_jobs_count_with_meta( '_job_location' ),
			'jobs_app_contact'            => self::get_jobs_count_with_meta( '_application' ),
			'jobs_company_name'           => self::get_jobs_count_with_meta( '_company_name' ),
			'jobs_company_site'           => self::get_jobs_count_with_meta( '_company_website' ),
			'jobs_company_tagline'        => self::get_jobs_count_with_meta( '_company_tagline' ),
			'jobs_company_twitter'        => self::get_jobs_count_with_meta( '_company_twitter' ),
			'jobs_company_video'          => self::get_jobs_count_with_meta( '_company_video' ),
			'jobs_expiry'                 => self::get_jobs_count_with_meta( '_job_expires' ),
			'jobs_featured'               => self::get_jobs_count_with_checked_meta( '_featured' ),
			'jobs_filled'                 => self::get_jobs_count_with_checked_meta( '_filled' ),
			'jobs_freelance'              => self::get_jobs_by_type_count( 'freelance' ),
			'jobs_full_time'              => self::get_jobs_by_type_count( 'full-time' ),
			'jobs_intern'                 => self::get_jobs_by_type_count( 'internship' ),
			'jobs_part_time'              => self::get_jobs_by_type_count( 'part-time' ),
			'jobs_temp'                   => self::get_jobs_by_type_count( 'temporary' ),
			'jobs_by_guests'              => self::get_jobs_by_guests(),
			'official_extensions'         => self::get_official_extensions_count(),
			'licensed_extensions'         => self::get_licensed_extensions_count(),
		];
	}

	/**
	 * Get the total number of users with the "employer" role.
	 *
	 * @return int the number of "employers".
	 */
	private static function get_employer_count() {
		$employer_query = new WP_User_Query(
			[
				'fields' => 'ID',
				'role'   => 'employer',
			]
		);

		return $employer_query->total_users;
	}

	/**
	 * Get the number of job categories that have a description.
	 *
	 * @since 1.30.0
	 *
	 * @return int Number of job categories with a description.
	 **/
	private static function get_job_category_has_description_count() {
		if ( ! taxonomy_exists( 'job_listing_category' ) ) {
			return 0;
		}

		$count = 0;
		$terms = get_terms(
			[
				'taxonomy'   => 'job_listing_category',
				'hide_empty' => false,
			]
		);

		foreach ( $terms as $term ) {
			$description = isset( $term->description ) ? trim( $term->description ) : '';

			if ( ! empty( $description ) ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Get the number of job types that have a description.
	 *
	 * @since 1.30.0
	 *
	 * @return int Number of job types with a description.
	 **/
	private static function get_job_type_has_description_count() {
		$count = 0;
		$terms = get_terms(
			[
				'taxonomy'   => 'job_listing_type',
				'hide_empty' => false,
			]
		);

		foreach ( $terms as $term ) {
			$description = isset( $term->description ) ? trim( $term->description ) : '';

			if ( ! empty( $description ) ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Get the number of job types that have Employment Type set.
	 *
	 * @since 1.30.0
	 *
	 * @return int Number of job types with an employment type.
	 **/
	private static function get_job_type_has_employment_type_count() {
		$count = 0;
		$terms = get_terms(
			[
				'taxonomy'   => 'job_listing_type',
				'hide_empty' => false,
			]
		);

		foreach ( $terms as $term ) {
			$employment_type = get_term_meta( $term->term_id, 'employment_type', true );

			if ( ! empty( $employment_type ) ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Get the total number of published or expired jobs for a particular job type.
	 *
	 * @since 1.30.0
	 *
	 * @param string $job_type Job type to search for.
	 *
	 * @return int Number of published or expired jobs for a particular job type.
	 **/
	private static function get_jobs_by_type_count( $job_type ) {
		$query = new WP_Query(
			[
				'post_type'   => 'job_listing',
				'post_status' => [ 'expired', 'publish' ],
				'fields'      => 'ids',
				'tax_query'   => [
					[
						'field'    => 'slug',
						'taxonomy' => 'job_listing_type',
						'terms'    => $job_type,
					],
				],
			]
		);

		return $query->found_posts;
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
			[
				'post_type'   => 'job_listing',
				'post_status' => [ 'expired', 'publish' ],
				'fields'      => 'ids',
				'meta_query'  => [
					[
						'key'     => '_thumbnail_id',
						'compare' => 'EXISTS',
					],
				],
			]
		);

		return $query->found_posts;
	}

	/**
	 * Get the total number of job listings that have one or more job types selected.
	 *
	 * @since 1.30.0
	 *
	 * @return int Number of job listings associated with at least one job type.
	 **/
	private static function get_job_type_count() {
		$query = new WP_Query(
			[
				'post_type'   => 'job_listing',
				'post_status' => [ 'expired', 'publish' ],
				'fields'      => 'ids',
				'tax_query'   => [
					[
						'taxonomy' => 'job_listing_type',
						'operator' => 'EXISTS',
					],
				],
			]
		);

		return $query->found_posts;
	}

	/**
	 * Get the number of job listings where the given meta value is non-empty.
	 *
	 * @param string $meta_key the key for the meta value to check.
	 *
	 * @return int the number of job listings.
	 */
	private static function get_jobs_count_with_meta( $meta_key ) {
		$query = new WP_Query(
			[
				'post_type'   => 'job_listing',
				'post_status' => [ 'publish', 'expired' ],
				'fields'      => 'ids',
				'meta_query'  => [
					[
						'key'     => $meta_key,
						'value'   => '[^[:space:]]',
						'compare' => 'REGEXP',
					],
				],
			]
		);

		return $query->found_posts;
	}

	/**
	 * Get the number of job listings where the given checkbox meta value is
	 * checked.
	 *
	 * @param string $meta_key the key for the meta value to check.
	 *
	 * @return int the number of job listings.
	 */
	private static function get_jobs_count_with_checked_meta( $meta_key ) {
		$query = new WP_Query(
			[
				'post_type'   => 'job_listing',
				'post_status' => [ 'publish', 'expired' ],
				'fields'      => 'ids',
				'meta_query'  => [
					[
						'key'   => $meta_key,
						'value' => '1',
					],
				],
			]
		);

		return $query->found_posts;
	}

	/**
	 * Get the number of job listings posted by guests.
	 *
	 * @return int the number of job listings.
	 */
	private static function get_jobs_by_guests() {
		$query = new WP_Query(
			[
				'post_type'   => 'job_listing',
				'post_status' => [ 'publish', 'expired' ],
				'fields'      => 'ids',
				'author__in'  => [ 0 ],
			]
		);

		return $query->found_posts;
	}

	/**
	 * Get the official extensions that are installed.
	 *
	 * @param bool $licensed_only Return only official extensions with an active license.
	 *
	 * @return array
	 */
	private static function get_official_extensions( $licensed_only ) {
		if ( ! class_exists( 'WP_Job_Manager_Helper' ) ) {
			include_once JOB_MANAGER_PLUGIN_DIR . '/includes/helper/class-wp-job-manager-helper.php';
		}

		$helper         = WP_Job_Manager_Helper::instance();
		$active_plugins = $helper->get_installed_plugins( true );

		if ( $licensed_only ) {
			foreach ( $active_plugins as $plugin_slug => $data ) {
				if ( ! $helper->has_plugin_licence( $plugin_slug ) ) {
					unset( $active_plugins[ $plugin_slug ] );
				}
			}
		}

		return $active_plugins;
	}

	/**
	 * Gets the count of all official extensions that are installed and activated.
	 */
	private static function get_official_extensions_count() {
		return count( self::get_official_extensions( false ) );
	}

	/**
	 * Gets the count of all official extensions that are installed, activated, and have active license.
	 */
	private static function get_licensed_extensions_count() {
		return count( self::get_official_extensions( true ) );
	}

	/**
	 * Checks if we have paid extensions installed and activated. Right now, all of our official extensions are paid.
	 *
	 * @return bool
	 */
	private static function has_paid_extensions() {
		return self::get_official_extensions_count() > 0;
	}

	/**
	 * Get the base fields to be sent for event logging.
	 *
	 * @since 1.33.0
	 *
	 * @return array
	 */
	public static function get_event_logging_base_fields() {
		$base_fields = [
			'job_listings' => wp_count_posts( 'job_listing' )->publish,
			'paid'         => self::has_paid_extensions() ? 1 : 0,
		];

		/**
		 * Filter the fields that should be sent with every event that is logged.
		 *
		 * @param array $base_fields The default base fields.
		 */
		return apply_filters( 'job_manager_event_logging_base_fields', $base_fields );
	}
}
