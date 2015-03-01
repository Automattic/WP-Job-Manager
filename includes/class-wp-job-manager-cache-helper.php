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
}

WP_Job_Manager_Cache_Helper::init();
