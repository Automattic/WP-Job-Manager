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
	const LICENSE_STORAGE_VERSION = 2;
	const OPTION_NAME             = 'job_manager_helper';

	/**
	 * Update a WPJM plugin's license data.
	 *
	 * @param string $product_slug
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return bool
	 */
	public static function update( $product_slug, $key, $value ) {
		if ( '_' === substr( $product_slug, 0, 1 ) ) {
			return false;
		}

		$options = self::get_license_option();
		if ( ! isset( $options[ $product_slug ] ) ) {
			$options[ $product_slug ] = [];
		}
		$options[ $product_slug ][ $key ] = $value;
		return self::update_license_option( $options );
	}

	/**
	 * Retrieve a WPJM plugin's license data.
	 *
	 * @param string $product_slug
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public static function get( $product_slug, $key, $default = false ) {
		if ( '_' === substr( $product_slug, 0, 1 ) ) {
			return $default;
		}

		$options = self::get_license_option();
		if ( ! isset( $options[ $product_slug ] ) ) {
			$options[ $product_slug ] = self::attempt_legacy_restore( $product_slug );
		}
		if ( isset( $options[ $product_slug ][ $key ] ) ) {
			return $options[ $product_slug ][ $key ];
		}

		return $default;
	}

	/**
	 * Delete a WPJM plugin's license data.
	 *
	 * @param string $product_slug
	 * @param string $key
	 *
	 * @return bool
	 */
	public static function delete( $product_slug, $key ) {
		if ( '_' === substr( $product_slug, 0, 1 ) ) {
			return false;
		}

		$options = self::get_license_option();
		if ( ! isset( $options[ $product_slug ] ) ) {
			$options[ $product_slug ] = [];
		}
		unset( $options[ $product_slug ][ $key ] );
		return self::update_license_option( $options );
	}

	/**
	 * Save the previous license key for a product.
	 *
	 * @param string $product_slug
	 * @param string $key
	 *
	 * @return void
	 */
	public static function set_previous_license_key( $product_slug, $key ) {
		update_option( 'job_manager_previous_license_' . $product_slug, $key );
	}

	/**
	 * Attempt to retrieve license data from legacy storage.
	 *
	 * @param string $product_slug
	 *
	 * @return array
	 */
	private static function attempt_legacy_restore( $product_slug ) {
		$options = self::get_license_option();
		if ( ! isset( $options[ $product_slug ] ) ) {
			$options[ $product_slug ] = [];
		}
		foreach ( [ 'licence_key', 'email', 'errors', 'hide_key_notice' ] as $key ) {
			$option_value = get_option( $product_slug . '_' . $key, false );
			if ( ! empty( $option_value ) ) {
				// If we have any more legacy licenses, migrate the licence_key => license_key.
				if ( 'licence_key' === $key ) {
					$key = 'license_key';
				}
				$options[ $product_slug ][ $key ] = $option_value;
				delete_option( $product_slug . '_' . $key );
			}
		}

		// Save if we migrated from the very legacy storage.
		if ( ! empty( $options[ $product_slug ] ) ) {
			self::update_license_option( $options );
		}

		return $options[ $product_slug ];
	}

	/**
	 * Retrieve the license option.
	 *
	 * @return array
	 */
	private static function get_license_option() {
		$default = [
			'_version' => self::LICENSE_STORAGE_VERSION,
		];

		if ( is_multisite() || is_network_admin() ) {
			$licenses = get_site_option( self::OPTION_NAME, $default );
		} else {
			$licenses = get_option( self::OPTION_NAME, $default );
		}

		$current_version = $licenses['_version'] ?? false;
		if ( self::LICENSE_STORAGE_VERSION !== $current_version ) {
			$licenses = self::migrate_license_option( $licenses );
		}

		return $licenses;
	}

	/**
	 * Update the license option.
	 *
	 * @param array $value Master license container array.
	 * @return bool
	 */
	private static function update_license_option( $value ) {
		if ( is_multisite() || is_network_admin() ) {
			return update_site_option( self::OPTION_NAME, $value );
		}
		return update_option( self::OPTION_NAME, $value );
	}

	/**
	 * Migrate license data to the latest version.
	 *
	 * @param array $licenses
	 */
	private static function migrate_license_option( $licenses ) {
		foreach ( $licenses as $key => $license_data ) {
			if ( '_version' === $key || ! is_array( $license_data ) ) {
				continue;
			}

			// v1 -> v2: Migrate `licence_key` to `license_key`.
			if ( isset( $license_data['licence_key'] ) && empty( $license_data['license_key'] ) ) {
				$licenses[ $key ]['license_key'] = $license_data['licence_key'];
				unset( $licenses[ $key ]['licence_key'] );
			}
		}

		$licenses['_version'] = self::LICENSE_STORAGE_VERSION;
		self::update_license_option( $licenses );

		return $licenses;
	}
}
