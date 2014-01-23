<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * WP_Job_Manager_Geocode
 *
 * Obtains Geolocation data for posted jobs from Google.
 */
class WP_Job_Manager_Geocode {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'job_manager_update_job_data', array( $this, 'update_location_data' ), 20, 2 );
		add_action( 'job_manager_job_location_edited', array( $this, 'change_location_data' ), 20, 2 );
	}

	/**
	 * Update location data - when submitting a job
	 */
	public function update_location_data( $job_id, $values ) {
		if ( apply_filters( 'job_manager_geolocation_enabled', true ) ) {
			$address_data = self::get_location_data( $values['job']['job_location'] );
			self::save_location_data( $job_id, $address_data );
		}
	}

	/**
	 * Change a jobs location data upon editing
	 * @param  int $job_id
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
	 * Checks if a job has location data or not
	 * @param  int  $job_id
	 * @return boolean
	 */
	public static function has_location_data( $job_id ) {
		return get_post_meta( $job_id, 'geolocated', true ) == 1;
	}

	/**
	 * Called manually to generate location data and save to a post
	 * @param  int $job_id
	 * @param  string $location
	 */
	public static function generate_location_data( $job_id, $location ) {
		$address_data = self::get_location_data( $location );
		self::save_location_data( $job_id, $address_data );
	}

	/**
	 * Delete a job's location data
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
		delete_post_meta( $job_id, 'geolocation_zipcode' );
	}

	/**
	 * Save any returned data to post meta
	 * @param  int $job_id
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
	 * Get Location Data from Google
	 *
	 * Based on code by Eyal Fitoussi.
	 * 
	 * @param string $raw_address
	 * @return array location data
	 */
	public static function get_location_data( $raw_address ) {
		$invalid_chars = array( " " => "+", "," => "", "?" => "", "&" => "", "=" => "" , "#" => "" );
		$raw_address   = trim( strtolower( str_replace( array_keys( $invalid_chars ), array_values( $invalid_chars ), $raw_address ) ) );

		if ( empty( $raw_address ) )
			return false;

		$transient_name = 'jm_geo_' . md5( $raw_address );

		try {
			if ( false === ( $result = get_transient( $transient_name ) ) ) {
				$result = wp_remote_get( 
					"http://maps.googleapis.com/maps/api/geocode/xml?address=" . $raw_address . "&sensor=false", 
					array(
						'timeout'     => 20,
					    'redirection' => 5,
					    'httpversion' => '1.1',
					    'user-agent'  => 'WordPress/WP-Job-Manager-' . JOB_MANAGER_VERSION . '; ' . get_bloginfo( 'url' ),
					    'sslverify'   => false,
				    )
				);
				$result = wp_remote_retrieve_body( $result );
				$xml    = new SimpleXMLElement( $result );

				switch ( $xml->status ) {
					case 'ZERO_RESULTS' :
						throw new Exception( __( "No results found", 'wp-job-manager' ) );
					break;
					case 'OVER_QUERY_LIMIT' :
						throw new Exception( __( "Query limit reached", 'wp-job-manager' ) );
					break;
					default :
						set_transient( $transient_name, $result, 24 * HOUR_IN_SECONDS * 365 );
					break;
				}
			} else {
				$xml    = new SimpleXMLElement( $result );
			}
		} catch ( Exception $e ) {
			return new WP_Error( 'error', $e->getMessage() );
		}
		
		$address                      = array();
		$address['lat']               = sanitize_text_field( $xml->result->geometry->location->lat );
		$address['long']              = sanitize_text_field( $xml->result->geometry->location->lng );
		$address['formatted_address'] = sanitize_text_field( $xml->result->formatted_address );
		
		if ( ! empty( $xml->result->address_component ) ) {
			$address_data             = $xml->result->address_component;
			$street_number            = false;
			$address['street']        = false;
			$address['city']          = false;
			$address['state_short']   = false;
			$address['state_long']    = false;
			$address['zipcode']       = false;
			$address['country_short'] = false;
			$address['country_long']  = false;
			
			foreach ( $address_data as $data ) {
				switch ( $data->type ) {
					case 'street_number' :
						$address['street']        = sanitize_text_field( $data->long_name ); 
					break;
					case 'route' :
						$route = sanitize_text_field( $data->long_name );

						if ( ! empty( $address['street'] ) )	
							$address['street'] = $address['street'] . ' ' . $route;
						else
							$address['street'] = $route;
					break;
					case 'locality' :
						$address['city']          = sanitize_text_field( $data->long_name ); 
					break;
					case 'administrative_area_level_1' :
						$address['state_short']   = sanitize_text_field( $data->short_name ); 
						$address['state_long']    = sanitize_text_field( $data->long_name );
					break;
					case 'postal_code' :
						$address['postcode']      = sanitize_text_field( $data->long_name ); 
					break;
					case 'country' :
						$address['country_short'] = sanitize_text_field( $data->short_name ); 
						$address['country_long']  = sanitize_text_field( $data->long_name );
					break;
				}
			}
		}

		return $address;
	}
}

new WP_Job_Manager_Geocode();