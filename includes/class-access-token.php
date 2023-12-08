<?php
/**
 * File containing the class Access_Token.
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An access token which can be used to provide access to a resource.
 */
class Access_Token {
	/**
	 * Token's metadata. They are hashed together with the token.
	 *
	 * @var array
	 */
	private array $metadata;

	/**
	 * Constructor.
	 *
	 * @param array $metadata Metadata to be hashed together with the token.
	 */
	public function __construct( array $metadata ) {
		$this->metadata = $metadata;
		ksort( $this->metadata );
	}

	/**
	 * Creates a new token.
	 *
	 * @param int $expiry The expiry timestamp of the token.
	 *
	 * @return string The token.
	 */
	public function create( int $expiry = 0 ) : string {
		$metadata_json = wp_json_encode( $this->metadata );

		$hash = substr( wp_hash( $expiry . '|' . $metadata_json, 'nonce' ), -18, 16 );

		return $this->encode( $expiry, $hash );
	}

	/**
	 * Verifies that a token is correct.
	 *
	 * @param string $token The token to verify.
	 *
	 * @return bool True if the token is correct.
	 */
	public function verify( string $token ) : bool {
		$decoded_token = $this->decode( $token );

		if ( false === $decoded_token ) {
			return false;
		}

		$expiry = $decoded_token[0];

		if ( '' === $expiry || ( '0' !== $expiry && time() > $expiry ) ) {
			return false;
		}

		return hash_equals( $token, $this->create( (int) $expiry ) );
	}

	/**
	 * Encodes the hash and expiry into a URL-friendly format.
	 *
	 * @param int    $expiry The expiry timestamp.
	 * @param string $hash The hash of the metadata.
	 *
	 * @return string
	 */
	private function encode( int $expiry, string $hash ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- It encodes known values.
		return rtrim( strtr( base64_encode( $expiry . ':' . $hash ), '+/', '-_' ), '=' );
	}

	/**
	 * Decodes a token into the expiry and hash parts.
	 *
	 * @param string $token The token to decode.
	 *
	 * @return false|array A two element array with the expiry and the hash or false on failure.
	 */
	private function decode( string $token ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Output used for comparisons only.
		$decoded_str = base64_decode( str_pad( strtr( $token, '-_', '+/' ), strlen( $token ) % 4, '=', STR_PAD_RIGHT ) );

		if ( false === $decoded_str || 1 !== substr_count( $decoded_str, ':' ) ) {
			return false;
		}

		return explode( ':', $decoded_str );
	}
}
