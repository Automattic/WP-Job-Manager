<?php
/**
 * File containing the class WP_Job_Manager_Helper_Options.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Helper_Options
 */
class WP_Job_Manager_Helper_Options {
	const OPTION_NAME = 'job_manager_helper';

	/**
	 * Update a WPJM plugin's licence data.
	 *
	 * @param string $product_slug
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return bool
	 */
	public static function update( $product_slug, $key, $value ) {
		$options = self::get_master_option();
		if ( ! isset( $options[ $product_slug ] ) ) {
			$options[ $product_slug ] = [];
		}
		$options[ $product_slug ][ $key ] = $value;
		return self::update_master_option( $options );
	}

	/**
	 * Retrieve a WPJM plugin's licence data.
	 *
	 * @param string $product_slug
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public static function get( $product_slug, $key, $default = false ) {
		$options = self::get_master_option();
		if ( ! isset( $options[ $product_slug ] ) ) {
			$options[ $product_slug ] = self::attempt_legacy_restore( $product_slug );
		}
		if ( isset( $options[ $product_slug ][ $key ] ) ) {
			return $options[ $product_slug ][ $key ];
		}
		return $default;
	}

	/**
	 * Delete a WPJM plugin's licence data.
	 *
	 * @param string $product_slug
	 * @param string $key
	 *
	 * @return bool
	 */
	public static function delete( $product_slug, $key ) {
		$options = self::get_master_option();
		if ( ! isset( $options[ $product_slug ] ) ) {
			$options[ $product_slug ] = [];
		}
		unset( $options[ $product_slug ][ $key ] );
		return self::update_master_option( $options );
	}

	/**
	 * Attempt to retrieve licence data from legacy storage.
	 *
	 * @param string $product_slug
	 *
	 * @return array
	 */
	private static function attempt_legacy_restore( $product_slug ) {
		$options = self::get_master_option();
		if ( ! isset( $options[ $product_slug ] ) ) {
			$options[ $product_slug ] = [];
		}
		foreach ( [ 'licence_key', 'email', 'errors', 'hide_key_notice' ] as $key ) {
			$option_value = get_option( $product_slug . '_' . $key, false );
			if ( ! empty( $option_value ) ) {
				$options[ $product_slug ][ $key ] = $option_value;
				delete_option( $product_slug . '_' . $key );
			}
		}
		self::update_master_option( $options );
		return $options[ $product_slug ];
	}

	/**
	 * Retrieve the master option.
	 *
	 * @return array
	 */
	private static function get_master_option() {
		if ( is_multisite() || is_network_admin() ) {
			return get_site_option( self::OPTION_NAME, [] );
		}
		return get_option( self::OPTION_NAME, [] );
	}

	/**
	 * Update the master option.
	 *
	 * @param array $value Master license container array.
	 * @return bool
	 */
	private static function update_master_option( $value ) {
		if ( is_multisite() || is_network_admin() ) {
			return update_site_option( self::OPTION_NAME, $value );
		}
		return update_option( self::OPTION_NAME, $value );
	}
}
