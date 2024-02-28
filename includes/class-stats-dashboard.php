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

		add_action( 'job_manager_job_overlay_content', [ $this, 'output_job_stats' ], 12 );
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

	/**
	 * Output job analytics section.
	 *
	 * @param \WP_Post $job
	 */
	public function output_job_stats( $job ) {

		$stat_summaries = $this->get_stat_summaries( $job );

		$chart = $this->get_daily_stats_chart( $job );

		get_job_manager_template(
			'job-stats.php',
			[
				'stats' => $stat_summaries,
				'chart' => $chart ?? null,
			]
		);
	}

	/**
	 * Get data for the daily stats chart for a job.
	 *
	 * @param \WP_Post $job
	 *
	 * @return array
	 */
	private function get_daily_stats_chart( \WP_Post $job ): array {

		$job_stats = new Job_Listing_Stats( $job->ID );

		$daily_views       = $job_stats->get_event_daily( Job_Listing_Stats::VIEW );
		$daily_uniques     = $job_stats->get_event_daily( Job_Listing_Stats::VIEW_UNIQUE );
		$daily_impressions = $job_stats->get_event_daily( Job_Listing_Stats::SEARCH_IMPRESSION );

		$resolution = max( $daily_views ) < 1000 ? 100 : 1000;
		$max        = ceil( max( $daily_views ) / $resolution ) * $resolution;

		foreach ( $daily_views as $date => $views ) {
			$by_day[ $date ] = [
				'date'        => $date,
				'views'       => $views,
				'uniques'     => $daily_uniques[ $date ] ?? 0,
				'impressions' => $daily_impressions[ $date ] ?? 0,
				'class'       => '',
			];
		}

		$publish_date = get_post_datetime( $job );

		$job_expires = \WP_Job_Manager_Post_Types::instance()->get_job_expiration( $job );

		if ( ! $job_expires ) {
			$job_expires = $publish_date->modify( '+30 days' );
		}

		$all_days = $publish_date->diff( $job_expires )->days + 1;

		$today = ( new \DateTime() )->format( 'Y-m-d' );

		for ( $i = 0; $i < $all_days; $i++ ) {
			$date = $publish_date->modify( '+' . $i . ' day' )->format( 'Y-m-d' );
			if ( empty( $by_day[ $date ] ) ) {
				$by_day[ $date ] = [
					'date'        => $date,
					'views'       => 0,
					'uniques'     => 0,
					'impressions' => 0,
					'class'       => 'future-day',
				];
			}
		}

		$by_day[ $today ]['class'] = 'today';

		ksort( $by_day );

		$date_format = apply_filters( 'job_manager_get_dashboard_date_format', 'M d, Y' );

		$by_day_formatted = [];
		foreach ( $by_day as $date => $data ) {
			$date_fmt                              = wp_date( $date_format, strtotime( $date ) );
			$by_day_formatted[ $date_fmt ]         = $by_day[ $date ];
			$by_day_formatted[ $date_fmt ]['date'] = $date_fmt;
		}

		$chart = [
			'values'   => $by_day_formatted,
			'max'      => $max,
			'y-labels' => [ $max / 2, $max ],
		];

		/**
		 * Filter the job daily stat data, displayed as a chart in the job overlay.
		 *
		 * @param array    $stats Stat definition.
		 * @param \WP_Post $job Job post object.
		 */
		return apply_filters( 'job_manager_job_stats_chart', $chart, $job );
	}

	/**
	 * Get summaries grouped into sections for various stats.
	 *
	 * @param \WP_Post $job
	 *
	 * @return mixed
	 */
	private function get_stat_summaries( \WP_Post $job ) {
		$job_stats = new Job_Listing_Stats( $job->ID );

		/**
		 * Filter the job stat summaries, displayed in the job overlay.
		 *
		 * @param array    $stats Stat definition.
		 * @param \WP_Post $job Job post object.
		 */
		$stats = apply_filters(
			'job_manager_job_stats_summary',
			[
				'views'       => [
					'title'  => __( 'Total Views', 'wp-job-manager' ),
					'stats'  => [
						[
							'icon'  => 'color-page-view',
							'label' => __( 'Page Views', 'wp-job-manager' ),
							'value' => $job_stats->get_event_total( Job_Listing_Stats::VIEW ),
						],
						[
							'icon'  => 'color-unique-view',
							'label' => __( 'Unique Visitors', 'wp-job-manager' ),
							'value' => $job_stats->get_event_total( Job_Listing_Stats::VIEW_UNIQUE ),
						],
					],
					'column' => 1,
				],
				'impressions' => [
					'title'  => __( 'Impressions', 'wp-job-manager' ),
					'help'   => __( 'How many times the listing was seen in search results.', 'wp-job-manager' ),
					'stats'  => [
						[
							'icon'  => 'search',
							'label' => __( 'Search Impressions', 'wp-job-manager' ),
							'value' => $job_stats->get_event_total( Job_Listing_Stats::SEARCH_IMPRESSION ),
						],
					],
					'column' => 1,
				],
				'interest'    => [
					'title'  => __( 'Interest', 'wp-job-manager' ),
					'stats'  => [
						[
							'icon'  => 'search',
							'label' => __( 'Search clicks', 'wp-job-manager' ),
							'value' => 'TODO',
						],
						[
							'icon'  => 'cursor',
							'label' => __( 'Apply clicks', 'wp-job-manager' ),
							'value' => $job_stats->get_event_total( Job_Listing_Stats::APPLY_CLICK ),
						],
						[
							'icon'  => 'history',
							'label' => __( 'Repeat viewers', 'wp-job-manager' ),
							'value' => 'TODO',
						],
					],
					'column' => 2,
				],
			],
			$job
		);

		$stat_columns = array_reduce(
			$stats,
			fn( $columns, $section ) => array_merge_recursive(
				$columns,
				[ 'column-' . $section['column'] => [ $section ] ]
			),
			[]
		);

		return $stat_columns;
	}
}
