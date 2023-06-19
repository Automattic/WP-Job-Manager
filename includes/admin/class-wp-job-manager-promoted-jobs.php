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
					<a class="deactivateJob"href="#" data-post=' . esc_attr( $post->ID ) . '>Deactivate</a>
				';
			} else {
				echo '<button class="promote_job button button-primary" data-post=' . esc_attr( $post->ID ) . '>Promote</button>';
			}
		}
	}
	/**
	 * Store the promoted jobs template from wpjobmanager.com
	 *
	 * TODO: we need to fetch this from wpjobmanager.com and store in cache for X amount of time
	 * We should also have a fallback in case the API call fails.
	 * We need to have a fallback here because we can't use a `dialog` element inside the template
	 *
	 * @return string
	 */
	public function get_promote_jobs_template() {
		return '
		<dialog class="promoteJobsDialog" id="promoteDialog">
			<form method="dialog">
				<button type="submit" autofocus>X</button>
			</form>
			<promote-job-modal>
				<div slot="column-left" class="promote-job-modal-column-left">
					<h2 slot="promote-heading">
						Promote your job on our partner network.
					</h2>

					<div slot="price" class="promote-job-modal-price">
						<div class="price-text">Starting From</div>
						<span>$83.00</span>
					</div>

					<div slot="promote-list">
						<ul>
							<li>Your ad will get shared on our Partner Network</li>
							<li>Featured on jobs.blog for 7 days</li>
							<li>Featured on our weekly email blast</li>
						</ul>
					</div>
					<div slot="buttons" class="dialog-button-group">
						<button class="button button-primary" type="submit">Promote your job</button>
						<button class="button button-secondary" type="submit">Learn More</button>
					</div>
				</div>
				<div slot="column-right" class="promote-job-modal-column-right">
					<img src="https://d.pr/i/4PgTqN+">
				</div>
			</promote-job-modal>
		</dialog>';
	}

	/**
	 * Output the promote jobs template
	 *
	 * @return void
	 */
	public function promoted_jobs_admin_footer() {
		echo '
		<dialog class="promoteJobsDialog" id="deactivateDialog">
			<form method="dialog">
				<button type="submit">X</button>
			</form>
			<h2>Are you sure you want to deactivate promotion for this job?</h2>
			<p>If you still have time until the promotion expires, this time will be lost and the promotion of the job will be canceled.</p>
			<div class="deactivate-action dialog-button-group">
				<button class="cancel-promotion button button-secondary" type="submit">Cancel</button>
				<button class="button button-primary" type="submit">Deactivate</button>
			</div>
		</dialog>

			<template id="promote-job-template">
				<slot name="column-left" class="column-left">
					<slot name="promote-heading">
						Promote Your Job on our Partner Network
					</slot>

					<slot name="price">
						<div class="price-text">Starting From</div>
						<span>$--</span>
					</slot>

					<slot name="promote-list">
						<ul>
							<li>Your ad will get shared on our Partner Network</li>
							<li>Promote your job on external job boards</li>
							<li>Featured on our weekly email blast</li>
						</ul>
					</slot>

					<slot name="buttons" class="dialog-button-group">
						<button class="button btn-primary" type="submit">Promote your job</button>
						<button class="button btn-secondary" type="submit">Learn More</button>
					</slot>

				</slot>
			<slot name="column-right">
				<img src="#">
			</slot>
			</template>
		';
		echo $this->get_promote_jobs_template(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

}

WP_Job_Manager_Promoted_Jobs::instance();
