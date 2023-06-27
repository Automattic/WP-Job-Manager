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
class WP_Job_Manager_Promoted_Jobs {

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
	}

	/**
	 * Add a column to the job listings admin page.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function promoted_jobs_columns( $columns ) {
		if ( ! $this->promote_jobs_template_exists() ) {
			return $columns;
		}
		$columns['promoted_jobs'] = __( 'Promote', 'wp-job-manager' );
		return $columns;
	}

	/**
	 * Check if a job is promoted.
	 *
	 * @param int $post_id
	 * @return boolean
	 */
	public function is_promoted( $post_id ) {
		$promoted = get_post_meta( $post_id, '_promoted', true );
		return $promoted ? true : false;
	}

	/**
	 * Handle display of new column
	 *
	 * @param  string $column
	 */
	public function promoted_jobs_custom_columns( $column ) {
		global $post;
		if ( 'promoted_jobs' === $column ) {
			if ( $this->is_promoted( $post->ID ) ) {
				echo '
					Live
					<br />
					<a href="#">Edit promotion</a>
					<br />
					<a class="deactivate-job"href="#" data-post=' . esc_attr( $post->ID ) . '>Deactivate</a>
				';
			} else {
				echo '<button class="promote_job button button-primary" data-post=' . esc_attr( $post->ID ) . '>Promote</button>';
			}
		}
	}

	/**
	 * Check if promote jobs template exists.
	 *
	 * @return bool
	 */
	public function promote_jobs_template_exists() {
		return $this->get_promote_jobs_template() ? true : false;
	}

	/**
	 * Store the promoted jobs template from wpjobmanager.com
	 *
	 * @return string
	 */
	public function get_promote_jobs_template() {

		$promote_template = wp_cache_get( 'promote-jobs-template', 'promote-jobs', false, $found );

		if ( ! $found ) {
			$response         = wp_remote_get( 'http://wpjobmanager.com/wp-json/promoted-jobs/v1/assets/promote-dialog/?lang=' . get_locale() );
			$promote_template = json_decode( $response['body'], true );
			wp_cache_set( 'promote-jobs-template', $promote_template, 'promote-jobs', DAY_IN_SECONDS );
		}

		if ( is_array( $promote_template ) && ! is_wp_error( $promote_template ) ) {
			return $promote_template['assets'][0]['content'];
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
				<div class="deactivate-action promote-buttons-group">
					<button class="dialog-close button button-secondary" type="submit">
						<?php esc_html_e( 'Cancel', 'wp-job-manager' ); ?>
					</button>
					<button class="deactivate-promotion button button-primary" type="submit">
						<?php esc_html_e( 'Deactivate', 'wp-job-manager' ); ?>
					</button>
				</div>
			</dialog>
		<?php
	}

}

WP_Job_Manager_Promoted_Jobs::instance();
