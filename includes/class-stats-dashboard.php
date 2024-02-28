<?php
/**
 * File containing the class Stats_Dashboard.
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

/**
 * Job listing stats for the jobs dashboard.
 *
 * @since $$next-version$$
 */
class Stats_Dashboard {

	use Singleton;

	private const COLUMN_NAME = 'stats';

	/**
	 * Constructor.
	 */
	private function __construct() {

		if ( ! Stats::is_enabled() ) {
			return;
		}

		add_filter( 'job_manager_job_dashboard_columns', [ $this, 'add_stats_column' ] );
		add_action( 'job_manager_job_dashboard_column_' . self::COLUMN_NAME, [ $this, 'render_stats_column' ] );

		add_filter( 'manage_edit-job_listing_columns', [ $this, 'add_stats_column' ], 20 );
		add_action( 'manage_job_listing_posts_custom_column', [ $this, 'maybe_render_job_listing_posts_custom_column' ], 2 );
	}

	/**
	 * Add a new column to the job dashboards.
	 *
	 * @param array $columns
	 *
	 * @return array
	 */
	public function add_stats_column( $columns ) {
		$columns[ self::COLUMN_NAME ] = __( 'Views', 'wp-job-manager' );
		return $columns;
	}

	/**
	 * Output the stats column content.
	 *
	 * @param \WP_Post $job
	 */
	public function render_stats_column( $job ) {
		$stats       = new Job_Listing_Stats( $job->ID );
		$views       = $stats->get_event_total( Job_Listing_Stats::VIEW );
		$impressions = $stats->get_event_total( Job_Listing_Stats::SEARCH_IMPRESSION );

		// translators: %1d is the number of page views.
		$views_str = '<div>' . sprintf( _n( '%1d view', '%1d views', $views, 'wp-job-manager' ), $views ) . '</div>';
		// translators: %1d is the number of impressions.
		$views_str .= '<small>' . sprintf( _n( '%1d impression', '%1d impressions', $impressions, 'wp-job-manager' ), $impressions ) . '</small>';

		echo wp_kses_post( $views_str );
	}

	/**
	 * Output stats column for the job listing post type admin screen.
	 *
	 * @param string $column
	 */
	public function maybe_render_job_listing_posts_custom_column( $column ) {
		global $post;

		if ( self::COLUMN_NAME === $column ) {
			$this->render_stats_column( $post );
		}
	}
}
