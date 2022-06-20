<?php
/**
 * File containing the class WP_Job_Manager_Cache_Helper.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Assists in cache management for WP Job Management posts and terms.
 *
 * @package wp-job-manager
 * @since 1.0.0
 */
class WP_Job_Manager_Cache_Helper {

	/**
	 * Initializes cache hooks.
	 */
	public static function init() {
		add_action( 'save_post', [ __CLASS__, 'flush_get_job_listings_cache' ] );
		add_action( 'delete_post', [ __CLASS__, 'flush_get_job_listings_cache' ] );
		add_action( 'trash_post', [ __CLASS__, 'flush_get_job_listings_cache' ] );
		add_action( 'job_manager_my_job_do_action', [ __CLASS__, 'job_manager_my_job_do_action' ] );
		add_action( 'set_object_terms', [ __CLASS__, 'set_term' ], 10, 4 );
		add_action( 'edited_term', [ __CLASS__, 'edited_term' ], 10, 3 );
		add_action( 'create_term', [ __CLASS__, 'edited_term' ], 10, 3 );
		add_action( 'delete_term', [ __CLASS__, 'edited_term' ], 10, 3 );
		add_action( 'transition_post_status', [ __CLASS__, 'maybe_clear_count_transients' ], 10, 3 );
	}

	/**
	 * Flushes the cache.
	 *
	 * @param int|WP_Post $post_id
	 */
	public static function flush_get_job_listings_cache( $post_id ) {
		if ( 'job_listing' === get_post_type( $post_id ) ) {
			self::get_transient_version( 'get_job_listings', true );
		}
	}

	/**
	 * Refreshes the Job Listing cache when performing actions on it.
	 *
	 * @param string $action
	 */
	public static function job_manager_my_job_do_action( $action ) {
		if ( 'mark_filled' === $action || 'mark_not_filled' === $action ) {
			self::get_transient_version( 'get_job_listings', true );
		}
	}

	/**
	 * Refreshes the Job Listing cache when terms are updated.
	 *
	 * @param string|int $object_id
	 * @param string     $terms
	 * @param string     $tt_ids
	 * @param string     $taxonomy
	 */
	public static function set_term( $object_id = '', $terms = '', $tt_ids = '', $taxonomy = '' ) {
		self::get_transient_version( 'jm_get_' . sanitize_text_field( $taxonomy ), true );
	}

	/**
	 * Refreshes the Job Listing cache when terms are updated.
	 *
	 * @param string|int $term_id
	 * @param string|int $tt_id
	 * @param string     $taxonomy
	 */
	public static function edited_term( $term_id = '', $tt_id = '', $taxonomy = '' ) {
		self::get_transient_version( 'jm_get_' . sanitize_text_field( $taxonomy ), true );
	}

	/**
	 * Gets transient version.
	 *
	 * When using transients with unpredictable names, e.g. those containing an md5
	 * hash in the name, we need a way to invalidate them all at once.
	 *
	 * When using default WP transients we're able to do this with a DB query to
	 * delete transients manually.
	 *
	 * With external cache however, this isn't possible. Instead, this function is used
	 * to append a unique string (based on time()) to each transient. When transients
	 * are invalidated, the transient version will increment and data will be regenerated.
	 *
	 * @param  string  $group   Name for the group of transients we need to invalidate.
	 * @param  boolean $refresh True to force a new version (Default: false).
	 * @return string Transient version based on time(), 10 digits.
	 */
	public static function get_transient_version( $group, $refresh = false ) {
		$transient_name  = $group . '-transient-version';
		$transient_value = get_transient( $transient_name );

		if ( false === $transient_value || true === $refresh ) {
			self::delete_version_transients( $transient_value );
			set_transient( $transient_name, $transient_value = time() );
		}
		return $transient_value;
	}

	/**
	 * When the transient version increases, this is used to remove all past transients to avoid filling the DB.
	 *
	 * Note: this only works on transients appended with the transient version, and when object caching is not being used.
	 *
	 * @param string $version
	 */
	private static function delete_version_transients( $version ) {
		global $wpdb;

		if ( ! wp_using_ext_object_cache() && ! empty( $version ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Only used when object caching is disabled.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s;", '\_transient\_%' . $version ) );
		}
	}

	/**
	 * Clear expired transients.
	 *
	 * @deprecated 1.33.4 Handled by WordPress since 4.9.
	 */
	public static function clear_expired_transients() {
		_deprecated_function( __METHOD__, '1.33.4', 'handled by WordPress core since 4.9' );
	}

	/**
	 * Maybe remove pending count transients
	 *
	 * When a supported post type status is updated, check if any cached count transients
	 * need to be removed, and remove the
	 *
	 * @since 1.27.0
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function maybe_clear_count_transients( $new_status, $old_status, $post ) {
		global $wpdb;

		/**
		 * Get supported post types for count caching
		 *
		 * @since 1.27.0
		 *
		 * @param array   $post_types Post types that should be cached.
		 * @param string  $new_status New post status.
		 * @param string  $old_status Old post status.
		 * @param WP_Post $post       Post object.
		 */
		$post_types = apply_filters( 'wpjm_count_cache_supported_post_types', [ 'job_listing' ], $new_status, $old_status, $post );

		// Only proceed when statuses do not match, and post type is supported post type.
		if ( $new_status === $old_status || ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		/**
		 * Get supported post statuses for count caching
		 *
		 * @since 1.27.0
		 *
		 * @param array   $post_statuses Post statuses that should be cached.
		 * @param string  $new_status    New post status.
		 * @param string  $old_status    Old post status.
		 * @param WP_Post $post          Post object.
		 */
		$valid_statuses = apply_filters( 'wpjm_count_cache_supported_statuses', [ 'pending' ], $new_status, $old_status, $post );

		$rlike = [];
		// New status transient option name.
		if ( in_array( $new_status, $valid_statuses, true ) ) {
			$rlike[] = "^_transient_jm_{$new_status}_{$post->post_type}_count_user_";
		}
		// Old status transient option name.
		if ( in_array( $old_status, $valid_statuses, true ) ) {
			$rlike[] = "^_transient_jm_{$old_status}_{$post->post_type}_count_user_";
		}

		if ( empty( $rlike ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fetches dynamic list of cached counts.
		$transients = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM $wpdb->options WHERE option_name RLIKE %s",
				implode( '|', $rlike )
			)
		);

		// For each transient...
		foreach ( $transients as $transient ) {
			// Strip away the WordPress prefix in order to arrive at the transient key.
			$key = str_replace( '_transient_', '', $transient );
			// Now that we have the key, use WordPress core to the delete the transient.
			delete_transient( $key );
		}

		// Sometimes transients are not in the DB, so we have to do this too:.
		wp_cache_flush();
	}

	/**
	 * Get Listings Count from Cache
	 *
	 * @since 1.27.0
	 *
	 * @param string $post_type
	 * @param string $status
	 * @param bool   $force Force update cache.
	 *
	 * @return int
	 */
	public static function get_listings_count( $post_type = 'job_listing', $status = 'pending', $force = false ) {

		// Get user based cache transient.
		$user_id   = get_current_user_id();
		$transient = "jm_{$status}_{$post_type}_count_user_{$user_id}";

		// Set listings_count value from cache if exists, otherwise set to 0 as default.
		$cached_count = get_transient( $transient );
		$status_count = $cached_count ? $cached_count : 0;

		// $cached_count will be false if transient does not exist.
		if ( false === $cached_count || $force ) {
			$count_posts = wp_count_posts( $post_type, 'readable' );
			// Default to 0 $status if object does not have a value.
			$status_count = isset( $count_posts->$status ) ? $count_posts->$status : 0;
			set_transient( $transient, $status_count, DAY_IN_SECONDS * 7 );
		}

		return $status_count;
	}
}

WP_Job_Manager_Cache_Helper::init();
