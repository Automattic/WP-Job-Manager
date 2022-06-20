<?php
/**
 * File containing the class WP_Job_Manager_Geocode.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Obtains Geolocation data for posted jobs from Google.
 *
 * @since 1.6.1
 */
class WP_Job_Manager_Geocode {

	const GOOGLE_MAPS_GEOCODE_API_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.26.0
	 */
	private static $instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.26.0
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'job_manager_geolocation_endpoint', [ $this, 'add_geolocation_endpoint_query_args' ], 0, 2 );
		add_filter( 'job_manager_geolocation_api_key', [ $this, 'get_google_maps_api_key' ], 0 );
		add_action( 'job_manager_update_job_data', [ $this, 'update_location_data' ], 20, 2 );
		add_action( 'job_manager_job_location_edited', [ $this, 'change_location_data' ], 20, 2 );
	}

	/**
	 * Updates location data when submitting a job.
	 *
	 * @param int   $job_id
	 * @param array $values
	 */
	public function update_location_data( $job_id, $values ) {
		if ( apply_filters( 'job_manager_geolocation_enabled', true ) && isset( $values['job']['job_location'] ) ) {
			$address_data = self::get_location_data( $values['job']['job_location'] );
			self::save_location_data( $job_id, $address_data );
		}
	}

	/**
	 * Changes a jobs location data upon editing.
	 *
	 * @param  int    $job_id
	 * @param  string $new_location
	 */
	public function change_location_data( $job_id, $new_location ) {
		if ( apply_filters( 'job_manager_geolocation_enabled', true ) ) {
			$address_data = self::get_location_data( $new_location );
			self::clear_location_data( $job_id );
			self::save_location_data( $job_id, $address_data );
		}
	}

	/**
	 * Checks if a job has location data or not.
	 *
	 * @param  int $job_id
	 * @return boolean
	 */
	public static function has_location_data( $job_id ) {
		return 1 === intval( get_post_meta( $job_id, 'geolocated', true ) );
	}

	/**
	 * Generates location data and saves to a post.
	 *
	 * @param  int    $job_id
	 * @param  string $location
	 */
	public static function generate_location_data( $job_id, $location ) {
		$address_data = self::get_location_data( $location );
		self::save_location_data( $job_id, $address_data );
	}

	/**
	 * Deletes a job's location data.
	 *
	 * @param  int $job_id
	 */
	public static function clear_location_data( $job_id ) {
		delete_post_meta( $job_id, 'geolocated' );
		delete_post_meta( $job_id, 'geolocation_city' );
		delete_post_meta( $job_id, 'geolocation_country_long' );
		delete_post_meta( $job_id, 'geolocation_country_short' );
		delete_post_meta( $job_id, 'geolocation_formatted_address' );
		delete_post_meta( $job_id, 'geolocation_lat' );
		delete_post_meta( $job_id, 'geolocation_long' );
		delete_post_meta( $job_id, 'geolocation_state_long' );
		delete_post_meta( $job_id, 'geolocation_state_short' );
		delete_post_meta( $job_id, 'geolocation_street' );
		delete_post_meta( $job_id, 'geolocation_street_number' );
		delete_post_meta( $job_id, 'geolocation_zipcode' );
		delete_post_meta( $job_id, 'geolocation_postcode' );
	}

	/**
	 * Saves any returned data to post meta.
	 *
	 * @param  int   $job_id
	 * @param  array $address_data
	 */
	public static function save_location_data( $job_id, $address_data ) {
		if ( ! is_wp_error( $address_data ) && $address_data ) {
			foreach ( $address_data as $key => $value ) {
				if ( $value ) {
					update_post_meta( $job_id, 'geolocation_' . $key, $value );
				}
			}
			update_post_meta( $job_id, 'geolocated', 1 );
		}
	}

	/**
	 * Retrieves the Google Maps API key from the plugin's settings.
	 *
	 * @param  string $key
	 * @return string
	 */
	public function get_google_maps_api_key( $key ) {
		return get_option( 'job_manager_google_maps_api_key' );
	}

	/**
	 * Adds the necessary query arguments for a Google Maps Geocode API request.
	 *
	 * @param  string $geocode_endpoint_url
	 * @param  string $raw_address
	 * @return string|bool
	 */
	public function add_geolocation_endpoint_query_args( $geocode_endpoint_url, $raw_address ) {
		// Add an API key if available.
		$api_key = apply_filters( 'job_manager_geolocation_api_key', '', $raw_address );

		if ( '' !== $api_key ) {
			$geocode_endpoint_url = add_query_arg( 'key', rawurlencode( $api_key ), $geocode_endpoint_url );
		}

		$geocode_endpoint_url = add_query_arg( 'address', rawurlencode( $raw_address ), $geocode_endpoint_url );

		$locale = get_locale();
		if ( $locale ) {
			$geocode_endpoint_url = add_query_arg( 'language', substr( $locale, 0, 2 ), $geocode_endpoint_url );
		}

		$region = apply_filters( 'job_manager_geolocation_region_cctld', '', $raw_address );
		if ( '' !== $region ) {
			$geocode_endpoint_url = add_query_arg( 'region', rawurlencode( $region ), $geocode_endpoint_url );
		}

		return $geocode_endpoint_url;
	}

	/**
	 * Gets Location Data from Google.
	 *
	 * Based on code by Eyal Fitoussi.
	 *
	 * @param string $raw_address
	 * @return array|bool location data.
	 * @throws Exception After geocoding error.
	 */
	public static function get_location_data( $raw_address ) {
		$invalid_chars = [
			' ' => '+',
			',' => '',
			'?' => '',
			'&' => '',
			'=' => '',
			'#' => '',
		];
		$raw_address   = trim( strtolower( str_replace( array_keys( $invalid_chars ), array_values( $invalid_chars ), $raw_address ) ) );

		if ( empty( $raw_address ) ) {
			return false;
		}

		$transient_name              = 'jm_geocode_' . md5( $raw_address );
		$geocoded_address            = get_transient( $transient_name );
		$jm_geocode_over_query_limit = get_transient( 'jm_geocode_over_query_limit' );

		// Query limit reached - don't geocode for a while.
		if ( $jm_geocode_over_query_limit && false === $geocoded_address ) {
			return false;
		}

		$geocode_api_url = apply_filters( 'job_manager_geolocation_endpoint', self::GOOGLE_MAPS_GEOCODE_API_URL, $raw_address );
		if ( false === $geocode_api_url ) {
			return false;
		}

		try {
			if ( false === $geocoded_address || empty( $geocoded_address->results[0] ) ) {
				$result           = wp_remote_get(
					$geocode_api_url,
					[
						'timeout'     => 5,
						'redirection' => 1,
						'httpversion' => '1.1',
						'user-agent'  => 'WordPress/WP-Job-Manager-' . JOB_MANAGER_VERSION . '; ' . get_bloginfo( 'url' ),
						'sslverify'   => false,
					]
				);
				$result           = wp_remote_retrieve_body( $result );
				$geocoded_address = json_decode( $result );

				if ( isset( $geocoded_address->status ) ) {
					if ( 'ZERO_RESULTS' === $geocoded_address->status ) {
						throw new Exception( __( 'No results found', 'wp-job-manager' ) );
					} elseif ( 'OVER_QUERY_LIMIT' === $geocoded_address->status ) {
						set_transient( 'jm_geocode_over_query_limit', 1, HOUR_IN_SECONDS );
						throw new Exception( __( 'Query limit reached', 'wp-job-manager' ) );
					} elseif ( 'OK' === $geocoded_address->status && ! empty( $geocoded_address->results[0] ) ) {
						set_transient( $transient_name, $geocoded_address, DAY_IN_SECONDS * 7 );
					} else {
						throw new Exception( __( 'Geocoding error', 'wp-job-manager' ) );
					}
				} else {
					throw new Exception( __( 'Geocoding error', 'wp-job-manager' ) );
				}
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'error', $e->getMessage() );
		}

		$address                      = [];
		$address['lat']               = sanitize_text_field( $geocoded_address->results[0]->geometry->location->lat );
		$address['long']              = sanitize_text_field( $geocoded_address->results[0]->geometry->location->lng );
		$address['formatted_address'] = sanitize_text_field( $geocoded_address->results[0]->formatted_address );

		if ( ! empty( $geocoded_address->results[0]->address_components ) ) {
			$address_data             = $geocoded_address->results[0]->address_components;
			$address['street_number'] = false;
			$address['street']        = false;
			$address['city']          = false;
			$address['state_short']   = false;
			$address['state_long']    = false;
			$address['postcode']      = false;
			$address['country_short'] = false;
			$address['country_long']  = false;

			foreach ( $address_data as $data ) {
				switch ( $data->types[0] ) {
					case 'street_number':
						$address['street_number'] = sanitize_text_field( $data->long_name );
						break;
					case 'route':
						$address['street'] = sanitize_text_field( $data->long_name );
						break;
					case 'sublocality_level_1':
					case 'locality':
					case 'postal_town':
						$address['city'] = sanitize_text_field( $data->long_name );
						break;
					case 'administrative_area_level_1':
					case 'administrative_area_level_2':
						$address['state_short'] = sanitize_text_field( $data->short_name );
						$address['state_long']  = sanitize_text_field( $data->long_name );
						break;
					case 'postal_code':
						$address['postcode'] = sanitize_text_field( $data->long_name );
						break;
					case 'country':
						$address['country_short'] = sanitize_text_field( $data->short_name );
						$address['country_long']  = sanitize_text_field( $data->long_name );
						break;
				}
			}
		}

		return apply_filters( 'job_manager_geolocation_get_location_data', $address, $geocoded_address );
	}
}

WP_Job_Manager_Geocode::instance();
