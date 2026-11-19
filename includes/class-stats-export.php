<?php
/**
 * File containing the class WP_Job_Manager\Stats_Export
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Export job listing stats as a CSV file.
 *
 * @since $$next-version$$
 */
class Stats_Export {

	use Singleton;

	/**
	 * The action and nonce base used for the export request.
	 */
	const NONCE_ACTION = 'wpjm_export_job_stats';

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_post_' . self::NONCE_ACTION, [ $this, 'handle_export' ] );
	}

	/**
	 * Get the export URL for a job listing.
	 *
	 * @param int $job_id Job listing post ID.
	 *
	 * @return string
	 */
	public static function get_export_url( $job_id ) {
		return wp_nonce_url(
			add_query_arg(
				[
					'action' => self::NONCE_ACTION,
					'job_id' => absint( $job_id ),
				],
				admin_url( 'admin-post.php' )
			),
			self::NONCE_ACTION . '_' . absint( $job_id )
		);
	}

	/**
	 * Handle the stats export request.
	 */
	public function handle_export() {
		$job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0;

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION . '_' . $job_id ) ) {
			wp_die( esc_html__( 'The link you followed has expired.', 'wp-job-manager' ), 403 );
		}

		if ( ! Stats::is_enabled() ) {
			wp_die( esc_html__( 'Job statistics are disabled.', 'wp-job-manager' ), 403 );
		}

		$job = get_post( $job_id );

		if ( ! $job || \WP_Job_Manager_Post_Types::PT_LISTING !== $job->post_type || ! job_manager_user_can_edit_job( $job_id ) ) {
			wp_die( esc_html__( 'You cannot export stats for this job listing.', 'wp-job-manager' ), 403 );
		}

		if ( ! $this->send_csv( $job ) ) {
			wp_die( esc_html__( 'Unable to export stats for this job listing.', 'wp-job-manager' ), 404 );
		}
		exit;
	}

	/**
	 * Send the daily stats of a job listing as a CSV file. One row per day from the publishing date to today.
	 *
	 * @param \WP_Post $job Job listing post.
	 *
	 * @return bool True when the file was sent.
	 */
	public function send_csv( \WP_Post $job ) {
		$rows = $this->get_csv_rows( $job );

		if ( empty( $rows ) ) {
			return false;
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=job-stats-' . absint( $job->ID ) . '.csv' );

		$handle = fopen( 'php://output', 'w' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite
		fwrite( $handle, "\xEF\xBB\xBF" );
		foreach ( $rows as $row ) {
			fputcsv( $handle, $row );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		fclose( $handle );

		return true;
	}

	/**
	 * Get the CSV rows for a job listing. The first row holds the column titles.
	 *
	 * @param \WP_Post $job Job listing post.
	 *
	 * @return array
	 */
	public function get_csv_rows( \WP_Post $job ): array {
		$start_date = get_post_datetime( $job );

		if ( ! $start_date ) {
			return [];
		}

		$job_stats = new Job_Listing_Stats( $job->ID, [ $start_date ] );

		$daily_views        = $job_stats->get_event_daily( Job_Listing_Stats::VIEW );
		$daily_uniques      = $job_stats->get_event_daily( Job_Listing_Stats::VIEW_UNIQUE );
		$daily_impressions  = $job_stats->get_event_daily( Job_Listing_Stats::SEARCH_IMPRESSION );
		$daily_apply_clicks = $job_stats->get_event_daily( Job_Listing_Stats::APPLY_CLICK );

		$rows = [
			[
				__( 'Date', 'wp-job-manager' ),
				__( 'Page views', 'wp-job-manager' ),
				__( 'Unique visitors', 'wp-job-manager' ),
				__( 'Search impressions', 'wp-job-manager' ),
				__( 'Apply clicks', 'wp-job-manager' ),
			],
		];

		$past_days = $start_date->diff( new \DateTime() )->days + 1;
		$cursor    = clone $start_date;

		for ( $i = 0; $i < $past_days; $i++ ) {
			$date   = $cursor->format( 'Y-m-d' );
			$rows[] = [
				$date,
				$daily_views[ $date ] ?? 0,
				$daily_uniques[ $date ] ?? 0,
				$daily_impressions[ $date ] ?? 0,
				$daily_apply_clicks[ $date ] ?? 0,
			];
			$cursor = $cursor->modify( '+1 day' );
		}

		return $rows;
	}
}
