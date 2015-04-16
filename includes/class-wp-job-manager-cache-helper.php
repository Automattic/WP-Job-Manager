<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Cache_Helper class.
 */
class WP_Job_Manager_Cache_Helper {

	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'flush_get_job_listings_cache' ) );
		add_action( 'job_manager_my_job_do_action', array( __CLASS__, 'job_manager_my_job_do_action' ) );
		add_action( 'set_object_terms', array( __CLASS__, 'set_term' ), 10, 4 );
		add_action( 'edited_term', array( __CLASS__, 'edited_term' ), 10, 3 );
		add_action( 'create_term', array( __CLASS__, 'edited_term' ), 10, 3 );
		add_action( 'delete_term', array( __CLASS__, 'edited_term' ), 10, 3 );
		add_action( 'job_manager_clear_expired_transients', array( __CLASS__, 'clear_expired_transients' ), 10 );
	}

	/**
	 * Flush the cache
	 */
	public static function flush_get_job_listings_cache( $post_id ) {
		if ( 'job_listing' === get_post_type( $post_id ) ) {
			self::get_transient_version( 'get_job_listings', true );
		}
	}

	/**
	 * Flush the cache
	 */
	public static function job_manager_my_job_do_action( $action ) {
		if ( 'mark_filled' === $action || 'mark_not_filled' === $action ) {
			self::get_transient_version( 'get_job_listings', true );
		}
	}

	/**
	 * When any post has a term set
	 */
	public static function set_term( $object_id = '', $terms = '', $tt_ids = '', $taxonomy = '' ) {
		self::get_transient_version( 'jm_get_' . sanitize_text_field( $taxonomy ), true );
	}

	/**
	 * When any term is edited
	 */
	public static function edited_term( $term_id = '', $tt_id = '', $taxonomy = '' ) {
		self::get_transient_version( 'jm_get_' . sanitize_text_field( $taxonomy ), true );
	}

	/**
	 * Get transient version
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
	 * @param  string  $group   Name for the group of transients we need to invalidate
	 * @param  boolean $refresh true to force a new version
	 * @return string transient version based on time(), 10 digits
	 */
	public static function get_transient_version( $group, $refresh = false ) {
		$transient_name  = $group . '-transient-version';
		$transient_value = get_transient( $transient_name );

		if ( false === $transient_value || true === $refresh ) {
			$transient_value = time();
			set_transient( $transient_name, $transient_value );
		}
		return $transient_value;
	}

    /**
	 * Clear expired transients
	 */
	public static function clear_expired_transients() {
		global $wpdb;

		if ( ! defined( 'WP_SETUP_CONFIG' ) && ! defined( 'WP_INSTALLING' ) ) {
			$rows = $wpdb->query( "
				DELETE
					a, b
				FROM
					{$wpdb->options} a, {$wpdb->options} b
				WHERE
					a.option_name LIKE '\_transient\_jm\_%' AND
					a.option_name NOT LIKE '\_transient\_timeout\_jm\_%' AND
					b.option_name = CONCAT(
						'_transient_timeout_jm_',
						SUBSTRING(
							a.option_name,
							CHAR_LENGTH('_transient_jm_') + 1
						)
					)
					AND b.option_value < UNIX_TIMESTAMP()
			" );
			$rows2 = $wpdb->query( "
				DELETE
					a, b
				FROM
					{$wpdb->options} a, {$wpdb->options} b
				WHERE
					a.option_name LIKE '\_site\_transient\_jm\_%' AND
					a.option_name NOT LIKE '\_site\_transient\_timeout\_jm\_%' AND
					b.option_name = CONCAT(
						'_site_transient_timeout_jm_',
						SUBSTRING(
							a.option_name,
							CHAR_LENGTH('_site_transient_jm_') + 1
						)
					)
					AND b.option_value < UNIX_TIMESTAMP()
			" );
		}
	}
}

WP_Job_Manager_Cache_Helper::init();
