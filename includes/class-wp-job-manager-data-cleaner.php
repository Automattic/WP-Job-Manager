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
	 * Cleanup all data.
	 *
	 * @access public
	 */
	public static function cleanup_all() {
		self::cleanup_custom_post_types();
		self::cleanup_taxonomies();
		self::cleanup_pages();
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
}
