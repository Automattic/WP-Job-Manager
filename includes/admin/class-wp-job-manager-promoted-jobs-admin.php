<?php
/**
 * File containing the class WP_Job_Manager_Promoted_Jobs.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles promoted jobs functionality.
 *
 * @since $$next-version$$
 */
class WP_Job_Manager_Promoted_Jobs_Admin {

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  $$next-version$$
	 */
	private static $instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  $$next-version$$
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'manage_edit-job_listing_columns', [ $this, 'promoted_jobs_columns' ] );
		add_action( 'manage_job_listing_posts_custom_column', [ $this, 'promoted_jobs_custom_columns' ], 2 );
		add_action( 'admin_footer', [ $this, 'promoted_jobs_admin_footer' ] );
		add_action( 'load-edit.php', [ $this, 'handle_deactivate_promotion' ] );
		add_action( 'wpjm_job_listing_bulk_actions', [ $this, 'add_action_notice' ] );

	}

	/**
	 * Add a column to the job listings admin page.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function promoted_jobs_columns( $columns ) {
		$columns['promoted_jobs'] = __( 'Promote', 'wp-job-manager' );

		return $columns;
	}

	/**
	 * Handle request to deactivate promotion for a job.
	 */
	public function handle_deactivate_promotion() {
		if ( ! isset( $_GET['action'] ) || 'deactivate_promotion' !== $_GET['action'] ) {
			return;
		}

		$post_id = absint( $_GET['post_id'] ?? 0 );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce should not be modified.
		if ( ! $post_id || empty( $_GET['nonce'] ) || ! wp_verify_nonce( $_GET['nonce'], 'deactivate_promotion_' . $_GET['post_id'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_job_listings', $post_id ) || 'job_listing' !== get_post_type( $post_id ) ) {
			return;
		}

		$this->deactivate_promotion( $post_id );

		wp_safe_redirect(
			add_query_arg(
				[
					'action_performed' => 'promotion_deactivated',
					'handled_jobs'     => [ $post_id ],
				],
				remove_query_arg( [ 'action', 'post_id', 'nonce' ] )
			)
		);
		exit;
	}

	/**
	 * Add feedback notice after successful deactivation.
	 *
	 * @param array $actions_handled
	 *
	 * @return array
	 */
	public function add_action_notice( $actions_handled ) {

		$actions_handled['promotion_deactivated'] = [
			// translators: Placeholder (%s) is the name of the job listing affected.
			'notice' => __( 'Promotion for %s deactivated', 'wp-job-manager' ),
		];

		return $actions_handled;
	}

	/**
	 * Check if a job is promoted.
	 *
	 * @param int $post_id
	 *
	 * @return boolean
	 */
	public function is_promoted( $post_id ) {
		$promoted = get_post_meta( $post_id, '_promoted', true );

		return (bool) $promoted;
	}

	/**
	 * Deactivate promotion for a job.
	 *
	 * @param int $post_id
	 *
	 * @return boolean
	 */
	public function deactivate_promotion( $post_id ) {
		return update_post_meta( $post_id, '_promoted', 0 );
	}

	/**
	 * Handle display of new column
	 *
	 * @param string $column
	 */
	public function promoted_jobs_custom_columns( $column ) {
		global $post;

		if ( 'promoted_jobs' !== $column ) {
			return;
		}

		if ( $this->is_promoted( $post->ID ) ) {
			$nonce                  = wp_create_nonce( 'deactivate_promotion_' . $post->ID );
			$deactivate_action_link = add_query_arg(
				[
					'action'  => 'deactivate_promotion',
					'post_id' => $post->ID,
					'nonce'   => $nonce,
				]
			);
			echo '
			<span class="jm-promoted__status-promoted">' . esc_html__( 'Promoted', 'wp-job-manager' ) . '</span>
			<div class="row-actions">
				<a href="#" class="jm-promoted__edit">' . esc_html__( 'Edit', 'wp-job-manager' ) . '</a>
				|
				<a class="jm-promoted__deactivate delete" href="#" data-href="' . esc_url( $deactivate_action_link ) . '">' . esc_html__( 'Deactivate', 'wp-job-manager' ) . '</a>
			</div>
			';
		} else {
			$promote_url = add_query_arg(
				[
					'job_id' => $post->ID,
				],
				'https://wpjobmanager.com/promote-job/'
			);
			echo '<button class="promote_job button button-primary" data-href=' . esc_url( $promote_url ) . '>' . esc_html__( 'Promote', 'wp-job-manager' ) . '</button>';
		}
	}

	/**
	 * Store the promoted jobs template from wpjobmanager.com.
	 *
	 * @return string
	 */
	public function get_promote_jobs_template() {
		$promote_template                 = get_option( 'promote-jobs-template', false );
		$promote_jobs_template_next_check = get_option( '_promote-jobs-template_next_check' );

		if ( ! $promote_jobs_template_next_check || $promote_jobs_template_next_check < time() ) {
			$check_for_updated_template = true;
		}

		if ( $check_for_updated_template ) {
			$response = wp_safe_remote_get( 'https://wpjobmanager.com/wp-json/promoted-jobs/v1/assets/promote-dialog/?lang=' . get_locale() );
			if (
				is_wp_error( $response )
				|| 200 !== wp_remote_retrieve_response_code( $response )
				|| empty( wp_remote_retrieve_body( $response ) )
			) {
				update_option( '_promote-jobs-template_next_check', time() + MINUTE_IN_SECONDS * 5, false );
				return $promote_template;
			} else {
				$assets           = json_decode( wp_remote_retrieve_body( $response ), true );
				$promote_template = $assets['assets'][0]['content'];
				update_option( 'promote-jobs-template', $promote_template, false );
				update_option( '_promote-jobs-template_next_check', time() + HOUR_IN_SECONDS, false );
			}
		}

		if ( ! is_wp_error( $promote_template ) ) {
			return $promote_template;
		}
	}

	/**
	 * Output the promote jobs template
	 *
	 * @return void
	 */
	public function promoted_jobs_admin_footer() {
		$screen = get_current_screen();
		if ( 'edit-job_listing' !== $screen->id ) {
			return;
		}
		?>
		<template id="promote-job-template">
			<?php echo $this->get_promote_jobs_template(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</template>
		<dialog class="wpjm-dialog" id="promote-dialog"></dialog>

		<dialog class="wpjm-dialog deactivate-dialog" id="deactivate-dialog">
			<form class="dialog deactivate-button" method="dialog">
				<button class="dialog-close" type="submit">X</button>
			</form>
			<h2 class="deactivate-modal-heading">
				<?php esc_html_e( 'Are you sure you want to deactivate promotion for this job?', 'wp-job-manager' ); ?>
			</h2>
			<p>
				<?php esc_html_e( 'If you still have time until the promotion expires, this time will be lost and the promotion of the job will be canceled.', 'wp-job-manager' ); ?>
			</p>
			<form method="dialog">
				<div class="deactivate-action promote-buttons-group">
					<button class="dialog-close button button-secondary" type="submit">
						<?php esc_html_e( 'Cancel', 'wp-job-manager' ); ?>
					</button>
					<a class="deactivate-promotion button button-primary">
					<?php esc_html_e( 'Deactivate', 'wp-job-manager' ); ?>
					</a>
				</div>
			</form>
		</dialog>
		<?php
	}

}

WP_Job_Manager_Promoted_Jobs_Admin::instance();
