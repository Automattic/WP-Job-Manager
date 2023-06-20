<?php
/**
 * File containing the class WP_Job_Manager_Com_Auth_Token.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper functions used for creating and validating tokens used for authenticating with WPJobManager.com.
 *
 * @package wp-job-manager
 * @since   $$next-version$$
 */
class WP_Job_Manager_Com_Auth_Token {
	const META_KEY = 'wpjmcom_site_auth_token';

	const ACCEPTED_OBJECT_TYPES = [ 'post', 'user ' ];

	const EXPIRATION_TIME = MINUTE_IN_SECONDS;

	/**
	 * Generates the site token associated with an object type and object id
	 *
	 * @param string $object_type Type of object metadata is for. Accepts 'post' or 'user'.
	 * @param int    $object_id The ID of the object to associate the token with.
	 * @return string|WP_Error The token generated, or a WP_Error if the token could not be generated/persisted
	 */
	public function generate( $object_type, $object_id ) {
		if ( ! in_array( $object_type, self::ACCEPTED_OBJECT_TYPES, true ) ) {
			return new WP_Error( 'wpjobmanager-com-invalid-type', __( 'Invalid object type', 'wp-job-manager' ) );
		}
		$token = $this->generate_new_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$encoded = $this->encode( $token );
		$result  = add_metadata( $object_type, $object_id, self::META_KEY, $encoded );
		if ( ! $result ) {
			return new WP_Error( 'wpjobmanager-com-token-not-saved', __( 'Token could not be persisted', 'wp-job-manager' ) );
		}
		return $token;
	}
	/**
	 * Generates a new random token and return it
	 *
	 * @return string|WP_Error The random token generated, or a WP_Error if the token could not be generated.
	 */
	private function generate_new_token() {
		try {
			$hash = random_bytes( 32 );
			return bin2hex( $hash );
		} catch ( Exception $e ) {
			return new WP_Error( 'wpjobmanager-com-token-not-generated', __( 'Token could not be generated', 'wp-job-manager' ) );
		}
	}

	/**
	 * Prepare the token to be persisted in the database
	 *
	 * @param string $token The token to encode.
	 * @return array The value to persist in the database.
	 */
	private function encode( $token ) {
		return [
			'token' => wp_hash_password( $token ),
			'ts'    => time(),
		];
	}

}
