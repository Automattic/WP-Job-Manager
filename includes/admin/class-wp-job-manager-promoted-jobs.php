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
		add_filter( 'manage_edit-job_listing_columns', [ $this, 'columns' ] );
		add_action( 'manage_job_listing_posts_custom_column', [ $this, 'custom_columns' ], 2 );
		add_action( 'admin_notices', [ $this, 'my_admin_notice' ] );
	}

	/**
	 * Add a column to the job listings admin page.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$columns['promoted_jobs'] = __( 'Promote', 'wp-job-manager' );
		return $columns;
	}

	/**
	 * Check if a job is promoted.
	 *
	 * @param [string] $post_id
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
	public function custom_columns( $column ) {
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
	 *
	 * @return string
	 */
	public function get_promote_jobs_template() {
		return '
		<dialog id="promoteDialog">
			<form method="dialog">
				<button type="submit" autofocus>X</button>
			</form>
			<promote-job-modal>
				<div slot="column-left" class="column-left">
					<h2 slot="promote-heading">
					Promote yoor job on our partner network.
					</h2>

					<div slot="price" class="price">
						Starting From
						<span>$83.00</span>
					</div>

					<div slot="promote-list">
						<ul>
							<li>Your ad will get shared on our Partner Network</li>
							<li>Featured on jobs.blog for 7 days</li>
							<li>Featured on our weekly email blast</li>
						</ul>
					</div>

					<div slot="buttons" class="promote-buttons-group">
						<button class="button button-primary" type="submit">Promote your job</button>
						<button class="button button-secondary" type="submit">Learn More</button>
					</div>
				</div>
				<div slot="column-right" class="column-right">
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
	public function my_admin_notice() {
		echo '
			<template id="promote-job-template">
			<style>

			</style>
				<slot name="column-left" class="column-left">
					<slot name="promote-heading">
					Promote Your Job on our Partner Network
					</slot>

					<slot name="price">
						Starting from
						<span>$80.00</span>
					</slot>

					<slot name="promote-list">
						<ul>
							<li>Your ad will get shared on our Partner Network</li>
							<li>Featured on jobs.blog for 7 days</li>
							<li>Featured on our weekly email blast</li>
						</ul>
					</slot>

					<slot name="buttons" class="button-group">
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
