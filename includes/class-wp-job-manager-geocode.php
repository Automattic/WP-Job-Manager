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
	}

	/**
	 * Update location data
	 */
	public function update_location_data( $job_id, $values ) {
		$address_data = self::get_location_data( $values['job']['job_location'] );

		if ( ! is_wp_error( $address_data ) && $address_data ) {
			foreach ( $address_data as $key => $value ) {
				if ( $value ) {
					update_post_meta( $job_id, 'geolocation_' . $key, $value );
				}
			}
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

		if ( false === ( $result = get_transient( $transient_name ) ) ) {
			$result = wp_remote_get( "http://maps.googleapis.com/maps/api/geocode/xml?address=" . $raw_address . "&sensor=false" );
			$result = wp_remote_retrieve_body( $result );
			$xml    = new SimpleXMLElement( $result );

			switch ( $xml->status ) {
				case 'ZERO_RESULTS' :
					return false;
				break;
				case 'OVER_QUERY_LIMIT' :
					return new WP_Error( 'error', __( "Query limit reached", 'job_manager' ) );
				break;
				default :
					set_transient( $transient_name, $result, 24 * HOUR_IN_SECONDS * 365 );
				break;
			}
		} else {
			$xml    = new SimpleXMLElement( $result );
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