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

	/**
	 * Constructor.
	 */
	private function __construct() {

		if ( ! Stats::is_enabled() ) {
			return;
		}

		add_filter( 'job_manager_job_dashboard_columns', [ $this, 'add_stats_column' ] );
		add_action( 'job_manager_job_dashboard_column_stats', [ $this, 'render_stats_column' ] );

		add_filter( 'manage_edit-job_listing_columns', [ $this, 'add_stats_column' ], 20 );
		add_action( 'manage_job_listing_posts_custom_column', [ $this, 'maybe_render_job_listing_posts_custom_column' ], 2 );
	}

	/**
	 * Add a new column to the job dashboards.
	 *
	 * @param array $columns
	 * @return array
	 */
	public function add_stats_column( $columns ) {
		$columns['stats'] = __( 'Views', 'wp-job-manager' );
		return $columns;

	}

	/**
	 * Output the stats column content.
	 *
	 * @param \WP_Post $job
	 */
	public function render_stats_column( $job ) {
		$stats = new Job_Listing_Stats( $job->ID );
		$total = $stats->get_total_stats();
		$daily = $stats->get_daily_stats();

		$views = $total['view'];

		// translators: %1d is the number of views.
		$views_str = '<span>' . sprintf( _n( '%1d view', '%1d views', $views, 'wp-job-manager' ), $views ) . '</span>';

		echo wp_kses_post( $views_str );
	}

	/**
	 * Output stats column for the job listing post type admin screen.
	 *
	 * @param  string $column
	 */
	public function maybe_render_job_listing_posts_custom_column( $column ) {
		global $post;

		if ( 'stats' === $column ) {
			$this->render_stats_column( $post );
		}
	}
}
