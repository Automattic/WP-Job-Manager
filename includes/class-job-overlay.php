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


}
