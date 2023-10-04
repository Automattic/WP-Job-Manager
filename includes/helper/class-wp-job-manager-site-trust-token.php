<?php
/**
 * File containing the class WP_Job_Manager_Site_Trust_Token.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper functions used for creating and validating tokens used for verification with WPJobManager.com.
 *
 * @package wp-job-manager
 * @since   1.42.0
 */
class WP_Job_Manager_Site_Trust_Token {
	/**
	 * The meta key used to store the tokens.
	 */
	const META_KEY = '_wpjm_site_trust_tokens';

	/**
	 * The accepted object types to be associated with the token.
	 */
	const ACCEPTED_OBJECT_TYPES = [ 'post', 'user' ];

	/**
	 * The expiration time for the token, in seconds.
	 */
	const EXPIRATION_TIME = MINUTE_IN_SECONDS;

	/**
	 * The singleton instance of the class.
	 *
	 * @var ?self
	 */
	private static $instance = null;

	/**
	 * WP_Job_Manager_Site_Trust_Token constructor.
	 */
	private function __construct() {}

	/**
	 * Returns the singleton instance of the class.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Generates the site token associated with an object type and object id
	 *
	 * @param string $object_type Type of the object associated with the token. Accepts 'post' or 'user'.
	 * @param int    $object_id The ID of the object to associate the token with.
	 * @return string|WP_Error The token generated, or a WP_Error if the token could not be generated/persisted
	 */
	public function generate( $object_type, $object_id ) {
		if ( ! in_array( $object_type, self::ACCEPTED_OBJECT_TYPES, true ) ) {
			return new WP_Error( 'wpjobmanager-site-trust-invalid-object-type', __( 'Invalid object type to associate with token', 'wp-job-manager' ) );
		}
		$token = $this->generate_new_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$encoded = $this->encode( $token );
		$result  = add_metadata( $object_type, $object_id, self::META_KEY, $encoded );
		if ( ! $result ) {
			return new WP_Error( 'wpjobmanager-site-trust-token-not-saved', __( 'Token could not be persisted', 'wp-job-manager' ) );
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
			$hash = random_bytes( 48 );
			//phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			$base64 = base64_encode( $hash );
			return strtr( $base64, '+/=', '._-' );
		} catch ( Exception $e ) {
			return new WP_Error( 'wpjobmanager-site-trust-token-not-generated', __( 'Token could not be generated', 'wp-job-manager' ) );
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

	/**
	 * Validate if a token is valid or not, and might remove it if it is valid or expired.
	 *
	 * @param string $object_type Type of object metadata is for. Accepts 'post' or 'user'.
	 * @param int    $object_id  The ID of the object to associate the token with.
	 * @param string $token The token to validate.
	 * @return bool True if the token is valid, false otherwise.
	 */
	public function validate( $object_type, $object_id, $token ) {
		if ( ! in_array( $object_type, self::ACCEPTED_OBJECT_TYPES, true ) ) {
			return false;
		}
		$metadatas = get_metadata( $object_type, $object_id, self::META_KEY );
		if ( false === $metadatas ) {
			return false;
		}
		$found = false;
		foreach ( $metadatas as $metadata ) {
			if ( ! $this->is_valid_format( $metadata ) ) {
				// If the metadata structure isn't valid, just ignore it.
				continue;
			}
			if ( $this->is_expired( $metadata ) ) {
				// If the token is expired, remove it.
				delete_metadata( $object_type, $object_id, self::META_KEY, $metadata );
				continue;
			}
			if ( $this->is_valid_token( $metadata, $token ) ) {
				delete_metadata( $object_type, $object_id, self::META_KEY, $metadata );
				$found = true;
			}
		}
		return $found;
	}


	/**
	 * Checks if a value persisted in the database has the correct format for us.
	 *
	 * @param array $value The value persisted in the database.
	 * @return bool True if the token is valid, false otherwise.
	 */
	private function is_valid_format( $value ) {
		return is_array( $value ) &&
			array_key_exists( 'token', $value ) &&
			array_key_exists( 'ts', $value ) &&
			is_string( $value['token'] ) &&
			is_int( $value['ts'] );
	}

	/**
	 * Checks if a token is expired or not.
	 *
	 * @param array $value The value persisted in the database.
	 * @return bool True if the token is expired, false otherwise.
	 */
	private function is_expired( $value ) {
		return $value['ts'] + self::EXPIRATION_TIME < time();
	}

	/**
	 * Checks if a token is valid or not.
	 *
	 * @param array  $value The value persisted in the database.
	 * @param string $token The token to analyse.
	 * @return bool True if the token is valid, false otherwise.
	 */
	private function is_valid_token( $value, $token ) {
		return wp_check_password( $token, $value['token'] );
	}
}
