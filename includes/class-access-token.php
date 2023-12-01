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
	 * An expiry timestamp.
	 *
	 * @var int|null
	 */
	private ?int $expiry;

	/**
	 * Token's payload. It is hashed together with the token.
	 *
	 * @var array
	 */
	private array $payload;

	/**
	 * Constructor.
	 *
	 * @param array    $payload A payload to be hashed together with the token.
	 * @param int|null $expiry Expiry timestamp.
	 */
	public function __construct( array $payload, int $expiry = null ) {
		$this->payload = $payload;
		$this->expiry  = $expiry;
	}

	/**
	 * Creates a new token.
	 *
	 * @return string The token.
	 */
	public function create() : string {
		$payload_json = wp_json_encode( $this->payload );
		// Create a random token, rtrim and strtr converts the base64 to a url-friendly format.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Method used safely.
		$token = rtrim( strtr( base64_encode( openssl_random_pseudo_bytes( 18 ) ), '+/', '-_' ), '=' );

		$payload = $this->payload;
		if ( ! empty( $this->expiry ) ) {
			$payload['expiry'] = $this->expiry;
		}

		$token_hash = password_hash( $payload_json . $token, PASSWORD_DEFAULT );

		$this->store_token_data( $payload, $token_hash );

		return $token;
	}

	/**
	 * Verifies that a token is correct.
	 *
	 * @param string $token The token to verify.
	 *
	 * @return bool True if the token is correct.
	 */
	public function verify( string $token ) : bool {
		$token_data = $this->lood_token_data( $this->payload );

		if ( true === $this->check_token_expiry( $token_data['payload'] ) ) {
			return false;
		}

		return password_verify( wp_json_encode( $this->payload ) . $token, $token_data['token_hash'] );
	}

	/**
	 * Whether the token is expired.
	 *
	 * @return bool
	 */
	public function is_expired() : bool {
		$token_data = $this->lood_token_data( $this->payload );

		return $this->check_token_expiry( $token_data['payload'] );
	}

	/**
	 * Checks if a token is expired.
	 *
	 * @param array $payload The token payload.
	 *
	 * @return bool
	 */
	private function check_token_expiry( array $payload ) : bool {
		return ! empty( $payload['expiry'] ) && time() > $payload['expiry'];
	}

	/**
	 * Load the token data from storage.
	 *
	 * @param array $payload The payload of the token.
	 *
	 * @return array $token_data {
	 *
	 * @type string $token_hash The token hash. Used for verification.
	 * @type string $payload    The token payload.
	 * }
	 */
	protected function lood_token_data( array $payload ): array {
		$token_hash = get_user_meta( $payload['user_id'], 'job_manager_alerts_secret_key', true );
		$payload    = get_user_meta( $payload['user_id'], 'job_manager_alerts_token_payload', true );

		return [
			'token_hash' => $token_hash,
			'payload'    => $payload ?: [],
		];
	}

	/**
	 * Store the token data.
	 *
	 * @param array  $payload    The token payload.
	 * @param string $token_hash The token hash that will be used for the verification.
	 *
	 * @return void
	 */
	protected function store_token_data( array $payload, string $token_hash ): void {
		update_user_meta( $payload['user_id'], 'job_manager_alerts_secret_key', $token_hash );
		update_user_meta( $payload['user_id'], 'job_manager_alerts_token_payload', $payload );
	}
}


