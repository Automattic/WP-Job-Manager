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

	/**
	 * Options to be deleted.
	 *
	 * @var $options
	 */
	private static $options = array(
		'wp_job_manager_version',
		'job_manager_installed_terms',
		'wpjm_permalinks',
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
	 * Cleanup all data.
	 *
	 * @access public
	 */
	public static function cleanup_all() {
		self::cleanup_custom_post_types();
		self::cleanup_taxonomies();
		self::cleanup_pages();
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
			$items = get_posts( array(
				'post_type'   => $post_type,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			) );

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
}
