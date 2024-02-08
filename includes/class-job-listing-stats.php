<?php
/**
 * File containing the class WP_Job_Manager_Stats
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is responsible for initializing all aspects of stats for wpjm.
 */
class Job_Listing_Stats {

	const JOB_LISTING_VIEW        = 'job_listing_view';
	const JOB_LISTING_VIEW_UNIQUE = 'job_listing_view_unique';

	/**
	 * Job listing post ID.
	 *
	 * @var mixed
	 */
	private $job_id;

	/**
	 * Publish date of the job listing.
	 *
	 * @var string
	 */
	private $start_date;

	/**
	 * Stats for a single job listing.
	 *
	 * @param int $job_id
	 */
	public function __construct( $job_id ) {

		$this->job_id     = $job_id;
		$this->start_date = get_post_datetime( $job_id )->format( 'Y-m-d' );

	}

	/**
	 * Get total stats for a job listing.
	 *
	 * @return array
	 */
	public function get_total_stats() {
		return [
			'view'        => $this->get_event_total( self::JOB_LISTING_VIEW ),
			'view_unique' => $this->get_event_total( self::JOB_LISTING_VIEW_UNIQUE ),
		];
	}

	/**
	 * Get daily stats for a job listing.
	 *
	 * @return array
	 */
	public function get_daily_stats() {
		return [
			'view'        => $this->get_event_daily( self::JOB_LISTING_VIEW ),
			'view_unique' => $this->get_event_daily( self::JOB_LISTING_VIEW_UNIQUE ),
		];
	}

	/**
	 * Get totals for an event.
	 *
	 * @param string $event
	 *
	 * @return int
	 */
	public function get_event_total( $event ) {

		global $wpdb;

		$cache_key = 'wpjm_stats_sum_' . $event . '_' . $this->job_id;
		$sum       = wp_cache_get( $cache_key, Stats::CACHE_GROUP );

		if ( false !== $sum ) {
			return $sum;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$sum = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(count) FROM {$wpdb->wpjm_stats}
              WHERE post_id = %d AND name = %s AND date >= %s",
				$this->job_id,
				$event,
				$this->start_date
			)
		);

		$sum = is_numeric( $sum ) ? (int) $sum : 0;

		wp_cache_set( $cache_key, $sum, Stats::CACHE_GROUP, HOUR_IN_SECONDS );

		return $sum;
	}

	/**
	 * Get daily breakdown of stats for an event.
	 *
	 * @param string $event
	 *
	 * @return array
	 */
	public function get_event_daily( $event ) {
		global $wpdb;

		$cache_key = 'wpjm_stats_daily_' . $event . '_' . $this->job_id;
		$views     = wp_cache_get( $cache_key, Stats::CACHE_GROUP );

		if ( false !== $views ) {
			return $views;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$views = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT date, count FROM {$wpdb->wpjm_stats}
		  WHERE post_id = %d AND name = %s AND date BETWEEN %s AND %s
		  ORDER BY date DESC",
				$this->job_id,
				$event,
				$this->start_date,
				( new \DateTime( 'yesterday' ) )->format( 'Y-m-d' )
			),
			OBJECT_K
		);

		wp_cache_set( $cache_key, $views, Stats::CACHE_GROUP, strtotime( 'tomorrow' ) - time() );

		return $views;

	}


}
