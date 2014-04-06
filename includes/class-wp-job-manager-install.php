<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WP_Job_Manager_Install
 */
class WP_Job_Manager_Install {

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$this->init_user_roles();
		$this->default_terms();
		$this->cron();
		delete_transient( 'wp_job_manager_addons_html' );
		update_option( 'wp_job_manager_version', JOB_MANAGER_VERSION );
	}

	/**
	 * Init user roles
	 *
	 * @access public
	 * @return void
	 */
	public function init_user_roles() {
		global $wp_roles;

		if ( class_exists( 'WP_Roles' ) && ! isset( $wp_roles ) )
			$wp_roles = new WP_Roles();

		if ( is_object( $wp_roles ) ) {
			$wp_roles->add_cap( 'administrator', 'manage_job_listings' );

			add_role( 'employer', __( 'Employer', 'wp-job-manager' ), array(
			    'read' 						=> true,
			    'edit_posts' 				=> false,
			    'delete_posts' 				=> false
			) );
		}
	}

	/**
	 * default_terms function.
	 *
	 * @access public
	 * @return void
	 */
	public function default_terms() {
		if ( get_option( 'job_manager_installed_terms' ) == 1 ) {
			return;
		}

		$taxonomies = array(
			'job_listing_type' => array(
				'Full Time',
				'Part Time',
				'Temporary',
				'Freelance',
				'Internship'
			)
		);

		foreach ( $taxonomies as $taxonomy => $terms ) {
			foreach ( $terms as $term ) {
				if ( ! get_term_by( 'slug', sanitize_title( $term ), $taxonomy ) ) {
					wp_insert_term( $term, $taxonomy );
				}
			}
		}

		update_option( 'job_manager_installed_terms', 1 );
	}

	/**
	 * Setup cron jobs
	 */
	public function cron() {
		wp_clear_scheduled_hook( 'job_manager_check_for_expired_jobs' );
		wp_clear_scheduled_hook( 'job_manager_delete_old_previews' );
		wp_schedule_event( time(), 'hourly', 'job_manager_check_for_expired_jobs' );
		wp_schedule_event( time(), 'daily', 'job_manager_delete_old_previews' );
	}
}

new WP_Job_Manager_Install();