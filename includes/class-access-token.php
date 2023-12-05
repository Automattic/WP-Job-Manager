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
 * A token that provides access to a private resource.
 *
 * @since $$next-version$$
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
	}

	/**
	 * Creates a new token.
	 *
	 * @return string The token.
	 */
	public function create() : string {
		$tick = wp_nonce_tick();

		return $this->generate_token( $tick );
	}

	/**
	 * Generates a token for a tick.
	 *
	 * @param float $tick
	 *
	 * @return false|string
	 */
	private function generate_token( float $tick ) {
		$metadata_json = wp_json_encode( $this->metadata );

		return substr( wp_hash( $tick . '|' . $metadata_json, 'nonce' ), -12, 10 );
	}

	/**
	 * Verifies that a token is correct.
	 *
	 * @param string $token The token to verify.
	 * @param int    $duration_days Days that the token should be valid.
	 *
	 * @return bool True if the token is correct.
	 */
	public function verify( string $token, int $duration_days ) : bool {
		$duration_ticks = $duration_days * 2;
		$tick           = wp_nonce_tick();

		for ( $i = 0; $i < $duration_ticks; $i++ ) {
			if ( hash_equals( $this->generate_token( $tick - $i ), $token ) ) {
				return true;
			}
		}

		return false;
	}
}


