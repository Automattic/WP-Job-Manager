<?php
/**
 * File containing the class Job_Overlay.
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

use WP_Job_Manager\UI\Modal_Dialog;
use WP_Job_Manager\UI\Notice;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Job details overlay.
 *
 * @since $$next-version$$
 */
class Job_Overlay {

	use Singleton;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'job_manager_ajax_job_dashboard_overlay', [ $this, 'ajax_job_overlay' ] );
		add_action( 'job_manager_job_overlay_content', [ $this, 'output_job_stats' ], 12 );

	}

	/**
	 * Render the job dashboard overlay content for an AJAX request.
	 */
	public function ajax_job_overlay() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$job_id = isset( $_REQUEST['job_id'] ) ? absint( $_REQUEST['job_id'] ) : null;

		$job = $job_id ? get_post( $job_id ) : null;

		$shortcode = Job_Dashboard_Shortcode::instance();

		if ( empty( $job ) || ! $shortcode->is_job_available_on_dashboard( $job ) ) {
			wp_send_json_error(
				Notice::error(
					[
						'message' => __( 'Invalid Job ID.', 'wp-job-manager' ),
						'classes' => [ 'type-dialog' ],
					]
				)
			);

			return;
		}

		$content = $this->get_job_overlay( $job );

		wp_send_json_success( $content );

	}

	/**
	 * Output the modal element.
	 */
	public function output_modal_element() {
		$overlay = new Modal_Dialog(
			[
				'id'    => 'jmDashboardOverlay',
				'class' => 'jm-dashboard__overlay',
			]
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Modal_Dialog class.
		echo $overlay->render( '' );
	}

	/**
	 * Get the job overlay content.
	 *
	 * @param \WP_Post $job
	 *
	 * @return string
	 */
	private function get_job_overlay( $job ) {

		$job_actions = Job_Dashboard_Shortcode::instance()->get_job_actions( $job );

		ob_start();

		get_job_manager_template(
			'job-dashboard-overlay.php',
			[
				'job'         => $job,
				'job_actions' => $job_actions,
			]
		);

		$content = ob_get_clean();

		return $content;
	}

	/**
	 * Output job analytics section.
	 *
	 * @param \WP_Post $job
	 */
	public function output_job_stats( $job ) {

		$job_stats = new Job_Listing_Stats( $job->ID );

		$totals = $job_stats->get_total_stats();

		/**
		 * Filter the job stats displayed in the job overlay.
		 *
		 * @param array   $stats Stat definition.
		 * @param \WP_Post $job   Job post object.
		 */
		$stats = apply_filters(
			'job_manager_job_overlay_stats',
			[
				'views'       => [
					'title'  => __( 'Total Views', 'wp-job-manager' ),
					'stats'  => [
						[
							'icon'  => 'color-page-view',
							'label' => __( 'Page Views', 'wp-job-manager' ),
							'value' => $totals['view'],
						],
						[
							'icon'  => 'color-unique-view',
							'label' => __( 'Unique Visitors', 'wp-job-manager' ),
							'value' => $totals['view_unique'],
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
							'label' => __( 'In Search', 'wp-job-manager' ),
							'value' => 0,
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
							'value' => 0,
						],
						[
							'icon'  => 'cursor',
							'label' => __( 'Apply clicks', 'wp-job-manager' ),
							'value' => 0,
						],
						[
							'icon'  => 'history',
							'label' => __( 'Repeat viewers', 'wp-job-manager' ),
							'value' => 0,
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

		get_job_manager_template(
			'job-stats.php',
			[
				'stats' => $stat_columns,
			]
		);
	}
}
