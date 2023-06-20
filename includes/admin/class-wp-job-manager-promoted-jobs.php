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
					<a href="#">Deactivate</a>
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
		<dialog class="promote-jobs-dialog" id="promoteDialog">
			<form class="dialog" method="dialog">
				<button class="dialog-close" type="submit" autofocus>X</button>
			</form>
			<promote-job-modal>
				<div slot="column-left" class="promote-job-modal-column-left">
					<h2 class="promote-jobs-heading" slot="promote-heading">
						Promote your job on our partner network.
					</h2>

					<div slot="price" class="promote-job-modal-price">
						<div class="price-text">Starting From</div>
						<span>$83.00</span>
					</div>

					<div slot="promote-list">
						<ul class="promote-list">
							<li class="promote-list-item">Your ad will get shared on our Partner Network</li>
							<li class="promote-list-item">Featured on jobs.blog for 7 days</li>
							<li class="promote-list-item">Featured on our weekly email blast</li>
						</ul>
					</div>

					<div slot="buttons" class="promote-buttons-group">
						<button class="promote-button button button-primary" type="submit">Promote your job</button>
						<button class="promote-button button button-secondary" type="submit">Learn More</button>
					</div>
				</div>
				<div slot="column-right" class="promote-job-modal-column-right">
					<img class="promote-jobs-image" src="https://d.pr/i/4PgTqN+">
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
		?>
			<template id="promote-job-template">
				<slot name="column-left" class="promote-job-modal-column-left">
					<slot class="promote-jobs-heading" name="promote-heading">
						<?php esc_html_e( 'Promote Your Job on our Partner Network', 'wp-job-manager' ); ?>
					</slot>

					<slot name="price" class="promote-job-modal-price">
						<div class="price-text"><?php esc_html_e( 'Starting From', 'wp-job-manager' ); ?></div>
						<span>$--</span>
					</slot>

					<slot name="promote-list">
						<ul class="promote-list">
							<li class="promote-list-item"><?php esc_html_e( 'Your ad will get shared on our Partner Network', 'wp-job-manager' ); ?></li>
							<li class="promote-list-item"><?php esc_html_e( 'Promote your job on external job boards', 'wp-job-manager' ); ?></li>
							<li class="promote-list-item"><?php esc_html_e( 'Featured on our weekly email blast', 'wp-job-manager' ); ?></li>
						</ul>
					</slot>

					<slot name="buttons" class="promote-buttons-group">
						<button class="promote-button button btn-primary" type="submit">
							<?php esc_html_e( 'Promote your job', 'wp-job-manager' ); ?>
						</button>
						<button class="promote-button button btn-secondary" type="submit">
							<?php esc_html_e( 'Learn more', 'wp-job-manager' ); ?>
						</button>
					</slot>

				</slot>
			<slot name="promote-job-modal-column-right">
				<img class="promote-jobs-image" src="#">
			</slot>
			</template>
		<?php

		echo $this->get_promote_jobs_template(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

}

WP_Job_Manager_Promoted_Jobs::instance();
