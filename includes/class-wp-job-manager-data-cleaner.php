<?php
/**
 * Defines a class with methods for cleaning up plugin data. To be used when
 * the plugin is deleted.
 *
 * @package Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Exit if accessed directly.
	exit;
}

/**
 * Methods for cleaning up all plugin data.
 *
 * @author Automattic
 * @since 1.31.0
 */
class WP_Job_Manager_Data_Cleaner {

	/**
	 * Custom post types to be deleted.
	 *
	 * @var $custom_post_types
	 */
	private static $custom_post_types = array(
		'job_listing',
	);

	/**
	 * Taxonomies to be deleted.
	 *
	 * @var $taxonomies
	 */
	private static $taxonomies = array(
		'job_listing_category',
		'job_listing_type',
	);

	/** Cron jobs to be unscheduled.
	 *
	 * @var $cron_jobs
	 */
	private static $cron_jobs = array(
		'job_manager_check_for_expired_jobs',
		'job_manager_delete_old_previews',
		'job_manager_clear_expired_transients',
		'job_manager_email_daily_notices',
		'job_manager_usage_tracking_send_usage_data',
	);

	/**
	 * Options to be deleted.
	 *
	 * @var $options
	 */
	private static $options = array(
		'wp_job_manager_version',
		'job_manager_installed_terms',
		'wpjm_permalinks',
		'job_manager_permalinks',
		'job_manager_helper',
		'job_manager_date_format',
		'job_manager_google_maps_api_key',
		'job_manager_usage_tracking_enabled',
		'job_manager_usage_tracking_opt_in_hide',
		'job_manager_per_page',
		'job_manager_hide_filled_positions',
		'job_manager_hide_expired',
		'job_manager_hide_expired_content',
		'job_manager_enable_categories',
		'job_manager_enable_default_category_multiselect',
		'job_manager_category_filter_type',
		'job_manager_enable_types',
		'job_manager_multi_job_type',
		'job_manager_user_requires_account',
		'job_manager_enable_registration',
		'job_manager_generate_username_from_email',
		'job_manager_use_standard_password_setup_email',
		'job_manager_registration_role',
		'job_manager_submission_requires_approval',
		'job_manager_user_can_edit_pending_submissions',
		'job_manager_user_edit_published_submissions',
		'job_manager_submission_duration',
		'job_manager_allowed_application_method',
		'job_manager_recaptcha_label',
		'job_manager_recaptcha_site_key',
		'job_manager_recaptcha_secret_key',
		'job_manager_enable_recaptcha_job_submission',
		'job_manager_submit_job_form_page_id',
		'job_manager_job_dashboard_page_id',
		'job_manager_jobs_page_id',
		'job_manager_submit_page_slug',
		'job_manager_job_dashboard_page_slug',
		'job_manager_delete_data_on_uninstall',
		'job_manager_email_admin_updated_job',
		'job_manager_email_admin_new_job',
		'job_manager_email_admin_expiring_job',
		'job_manager_email_employer_expiring_job',
		'job_manager_admin_notices',
	);

	/**
	 * Site options to be deleted.
	 *
	 * @var $site_options
	 */
	private static $site_options = array(
		'job_manager_helper',
	);

	/**
	 * Transient names (as MySQL regexes) to be deleted. The prefixes
	 * "_transient_" and "_transient_timeout_" will be prepended.
	 *
	 * @var $transients
	 */
	private static $transients = array(
		'_job_manager_activation_redirect',
		'get_job_listings-transient-version',
		'jm_.*',
	);

	/**
	 * Role to be removed.
	 *
	 * @var $role
	 */
	private static $role = 'employer';

	/**
	 * Capabilities to be deleted.
	 *
	 * @var $caps
	 */
	private static $caps = array(
		'manage_job_listings',
		'edit_job_listing',
		'read_job_listing',
		'delete_job_listing',
		'edit_job_listings',
		'edit_others_job_listings',
		'publish_job_listings',
		'read_private_job_listings',
		'delete_job_listings',
		'delete_private_job_listings',
		'delete_published_job_listings',
		'delete_others_job_listings',
		'edit_private_job_listings',
		'edit_published_job_listings',
		'manage_job_listing_terms',
		'edit_job_listing_terms',
		'delete_job_listing_terms',
		'assign_job_listing_terms',
	);

	/**
	 * User meta key names to be deleted.
	 *
	 * @var array $user_meta_keys
	 */
	private static $user_meta_keys = array(
		'_company_logo',
		'_company_name',
		'_company_website',
		'_company_tagline',
		'_company_twitter',
		'_company_video',
	);

	/**
	 * Cleanup all data.
	 *
	 * @access public
	 */
	public static function cleanup_all() {
		self::cleanup_custom_post_types();
		self::cleanup_taxonomies();
		self::cleanup_pages();
		self::cleanup_cron_jobs();
		self::cleanup_roles_and_caps();
		self::cleanup_transients();
		self::cleanup_user_meta();
		self::cleanup_options();
		self::cleanup_site_options();
	}

	/**
	 * Cleanup data for custom post types.
	 *
	 * @access private
	 */
	private static function cleanup_custom_post_types() {
		foreach ( self::$custom_post_types as $post_type ) {
			$items = get_posts(
				array(
					'post_type'   => $post_type,
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			);

			foreach ( $items as $item ) {
				wp_trash_post( $item );
			}
		}
	}

	/**
	 * Cleanup data for taxonomies.
	 *
	 * @access private
	 */
	private static function cleanup_taxonomies() {
		global $wpdb;

		foreach ( self::$taxonomies as $taxonomy ) {
			$terms = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT term_id, term_taxonomy_id FROM $wpdb->term_taxonomy WHERE taxonomy = %s",
					$taxonomy
				)
			);

			// Delete all data for each term.
			foreach ( $terms as $term ) {
				$wpdb->delete( $wpdb->term_relationships, array( 'term_taxonomy_id' => $term->term_taxonomy_id ) );
				$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $term->term_taxonomy_id ) );
				$wpdb->delete( $wpdb->terms, array( 'term_id' => $term->term_id ) );
				$wpdb->delete( $wpdb->termmeta, array( 'term_id' => $term->term_id ) );
			}

			if ( function_exists( 'clean_taxonomy_cache' ) ) {
				clean_taxonomy_cache( $taxonomy );
			}
		}
	}

	/**
	 * Cleanup data for pages.
	 *
	 * @access private
	 */
	private static function cleanup_pages() {
		// Trash the Submit Job page.
		$submit_job_form_page_id = get_option( 'job_manager_submit_job_form_page_id' );
		if ( $submit_job_form_page_id ) {
			wp_trash_post( $submit_job_form_page_id );
		}

		// Trash the Job Dashboard page.
		$job_dashboard_page_id = get_option( 'job_manager_job_dashboard_page_id' );
		if ( $job_dashboard_page_id ) {
			wp_trash_post( $job_dashboard_page_id );
		}

		// Trash the Jobs page.
		$jobs_page_id = get_option( 'job_manager_jobs_page_id' );
		if ( $jobs_page_id ) {
			wp_trash_post( $jobs_page_id );
		}
	}

	/**
	 * Cleanup data for options.
	 *
	 * @access private
	 */
	private static function cleanup_options() {
		foreach ( self::$options as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Cleanup data for site options.
	 *
	 * @access private
	 */
	private static function cleanup_site_options() {
		foreach ( self::$site_options as $option ) {
			delete_site_option( $option );
		}
	}

	/**
	 * Cleanup transients from the database.
	 *
	 * @access private
	 */
	private static function cleanup_transients() {
		global $wpdb;

		foreach ( array( '_transient_', '_transient_timeout_' ) as $prefix ) {
			foreach ( self::$transients as $transient ) {
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM $wpdb->options WHERE option_name RLIKE %s",
						$prefix . $transient
					)
				);
			}
		}
	}

	/**
	 * Cleanup data for roles and caps.
	 *
	 * @access private
	 */
	private static function cleanup_roles_and_caps() {
		global $wp_roles;

		// Remove caps from roles.
		$role_names = array_keys( $wp_roles->roles );
		foreach ( $role_names as $role_name ) {
			$role = get_role( $role_name );
			self::remove_all_job_manager_caps( $role );
		}

		// Remove caps and role from users.
		$users = get_users( array() );
		foreach ( $users as $user ) {
			self::remove_all_job_manager_caps( $user );
			$user->remove_role( self::$role );
		}

		// Remove role.
		remove_role( self::$role );
	}

	/**
	 * Helper method to remove WPJM caps from a user or role object.
	 *
	 * @param (WP_User|WP_Role) $object the user or role object.
	 */
	private static function remove_all_job_manager_caps( $object ) {
		foreach ( self::$caps as $cap ) {
			$object->remove_cap( $cap );
		}
	}

	/**
	 * Cleanup user meta from the database.
	 *
	 * @access private
	 */
	private static function cleanup_user_meta() {
		global $wpdb;

		foreach ( self::$user_meta_keys as $meta_key ) {
			$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => $meta_key ) );
		}
	}

	/**
	 * Cleanup cron jobs. Note that this should be done on deactivation, but
	 * doing it here as well for safety.
	 *
	 * @access private
	 */
	private static function cleanup_cron_jobs() {
		foreach ( self::$cron_jobs as $job ) {
			wp_clear_scheduled_hook( $job );
		}
	}
}
