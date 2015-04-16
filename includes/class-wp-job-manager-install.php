<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Install
 */
class WP_Job_Manager_Install {

	/**
	 * Install WP Job Manager
	 */
	public static function install() {
		global $wpdb;

		self::init_user_roles();
		self::default_terms();
		self::schedule_cron();

		// Redirect to setup screen for new installs
		if ( ! get_option( 'wp_job_manager_version' ) ) {
			set_transient( '_job_manager_activation_redirect', 1, HOUR_IN_SECONDS );
		}

		// Update featured posts ordering
		if ( version_compare( get_option( 'wp_job_manager_version', JOB_MANAGER_VERSION ), '1.22.0', '<' ) ) {
			$wpdb->query( "UPDATE {$wpdb->posts} p SET p.menu_order = 0 WHERE p.post_type='job_listing';" );
			$wpdb->query( "UPDATE {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id SET p.menu_order = -1 WHERE pm.meta_key = '_featured' AND pm.meta_value='1' AND p.post_type='job_listing';" );
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

		delete_transient( 'wp_job_manager_addons_html' );
		update_option( 'wp_job_manager_version', JOB_MANAGER_VERSION );
	}

	/**
	 * Init user roles
	 */
	private static function init_user_roles() {
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

			$capabilities = self::get_core_capabilities();

			foreach ( $capabilities as $cap_group ) {
				foreach ( $cap_group as $cap ) {
					$wp_roles->add_cap( 'administrator', $cap );
				}
			}
		}
	}

	/**
	 * Get capabilities
	 * @return array
	 */
	private static function get_core_capabilities() {
		return array(
			'core' => array(
				'manage_job_listings'
			),
			'job_listing' => array(
				"edit_job_listing",
				"read_job_listing",
				"delete_job_listing",
				"edit_job_listings",
				"edit_others_job_listings",
				"publish_job_listings",
				"read_private_job_listings",
				"delete_job_listings",
				"delete_private_job_listings",
				"delete_published_job_listings",
				"delete_others_job_listings",
				"edit_private_job_listings",
				"edit_published_job_listings",
				"manage_job_listing_terms",
				"edit_job_listing_terms",
				"delete_job_listing_terms",
				"assign_job_listing_terms"
			)
		);
	}

	/**
	 * default_terms function.
	 */
	private static function default_terms() {
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
	private static function schedule_cron() {
		wp_clear_scheduled_hook( 'job_manager_check_for_expired_jobs' );
		wp_clear_scheduled_hook( 'job_manager_delete_old_previews' );
		wp_clear_scheduled_hook( 'job_manager_clear_expired_transients' );
		wp_schedule_event( time(), 'hourly', 'job_manager_check_for_expired_jobs' );
		wp_schedule_event( time(), 'daily', 'job_manager_delete_old_previews' );
		wp_schedule_event( time(), 'twicedaily', 'job_manager_clear_expired_transients' );
	}
}
