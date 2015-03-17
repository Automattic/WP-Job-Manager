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
		global $wpdb;

		$this->init_user_roles();
		$this->default_terms();
		$this->cron();
		delete_transient( 'wp_job_manager_addons_html' );

		// Redirect to setup screen for new insalls
		if ( ! get_option( 'wp_job_manager_version' ) ) {
			set_transient( '_job_manager_activation_redirect', 1, HOUR_IN_SECONDS );
		}

		// Update featured posts ordering
		if ( version_compare( get_option( 'wp_job_manager_version', JOB_MANAGER_VERSION ), '1.22.3', '<' ) ) {
			$wpdb->query( "UPDATE {$wpdb->posts} SET menu_order = 1 WHERE post_type='job_listing';" );

			$featured_ids = array_map( 'absint', $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_featured' AND meta_value='1';" ) );

			if ( $featured_ids ) {
				$wpdb->query( "UPDATE {$wpdb->posts} SET menu_order = 0 WHERE ID IN (" . implode( ',', $featured_ids ) . ") AND post_type='job_listing';" );
			}
		}

		// Update legacy options
		if ( false === get_option( 'job_manager_submit_job_form_page_id', false ) && get_option( 'job_manager_submit_page_slug' ) ) {
			$page_id = get_page_by_path( get_option( 'job_manager_submit_page_slug' ) )->ID;
			update_option( 'job_manager_submit_job_form_page_id', $page_id );
		}
		if ( false === get_option( 'job_manager_job_dashboard_page_id', false ) && get_option( 'job_manager_job_dashboard_page_slug' ) ) {
			$page_id = get_page_by_path( get_option( 'job_manager_job_dashboard_page_slug' ) )->ID;
			update_option( 'job_manager_job_dashboard_page_id', $page_id );
		}

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

		if ( class_exists( 'WP_Roles' ) && ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}

		if ( is_object( $wp_roles ) ) {
			add_role( 'employer', __( 'Employer', 'wp-job-manager' ), array(
				'read'         => true,
				'edit_posts'   => false,
				'delete_posts' => false
			) );

			$capabilities = $this->get_core_capabilities();

			foreach ( $capabilities as $cap_group ) {
				foreach ( $cap_group as $cap ) {
					$wp_roles->add_cap( 'administrator', $cap );
				}
			}
		}
	}

	/**
	 * Get capabilities
	 *
	 * @return array
	 */
	public function get_core_capabilities() {
		$capabilities = array();

		$capabilities['core'] = array(
			'manage_job_listings'
		);

		$capability_types = array( 'job_listing' );

		foreach ( $capability_types as $capability_type ) {

			$capabilities[ $capability_type ] = array(
				// Post type
				"edit_{$capability_type}",
				"read_{$capability_type}",
				"delete_{$capability_type}",
				"edit_{$capability_type}s",
				"edit_others_{$capability_type}s",
				"publish_{$capability_type}s",
				"read_private_{$capability_type}s",
				"delete_{$capability_type}s",
				"delete_private_{$capability_type}s",
				"delete_published_{$capability_type}s",
				"delete_others_{$capability_type}s",
				"edit_private_{$capability_type}s",
				"edit_published_{$capability_type}s",

				// Terms
				"manage_{$capability_type}_terms",
				"edit_{$capability_type}_terms",
				"delete_{$capability_type}_terms",
				"assign_{$capability_type}_terms"
			);
		}

		return $capabilities;
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