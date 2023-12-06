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
		ksort( $this->metadata );
	}

	/**
	 * Creates a new token.
	 *
	 * @return string The token.
	 */
	public function create() : string {
		$tick = $this->token_tick();

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

		return substr( wp_hash( $tick . '|' . $metadata_json, 'nonce' ), -18, 16 );
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
		$tick = $this->token_tick();

		for ( $i = 0; $i < $duration_days; $i++ ) {
			if ( hash_equals( $this->generate_token( $tick - $i ), $token ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The tick controls how often the token is going to be rotated. Copied from wp_nonce_tick to avoid the nonce_life
	 * filter.
	 *
	 * @return false|float
	 */
	private function token_tick() {
		return ceil( time() / DAY_IN_SECONDS );
	}
}
