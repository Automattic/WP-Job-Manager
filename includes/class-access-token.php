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
	 * Token's metadata. They are hashed together with the token.
	 *
	 * @var array
	 */
	private array $metadata;

	/**
	 * Constructor.
	 *
	 * @param array    $metadata Metadata to be hashed together with the token.
	 * @param int|null $expiry Expiry timestamp.
	 */
	public function __construct( array $metadata, int $expiry = null ) {
		$this->metadata = $metadata;
		$this->expiry   = $expiry;
	}

	/**
	 * Creates a new token.
	 *
	 * @return string The token.
	 */
	public function create() : string {
		// Create a random token, rtrim and strtr converts the base64 to a url-friendly format.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Method used safely.
		$token = rtrim( strtr( base64_encode( openssl_random_pseudo_bytes( 18 ) ), '+/', '-_' ), '=' );

		$metadata_json = wp_json_encode( $this->metadata );
		$token_hash    = password_hash( $metadata_json . $token, PASSWORD_DEFAULT );

		$this->store_token_data( $this->metadata, $token_hash, $this->expiry );

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
		$stored_data = $this->load_token_data( $this->metadata );

		if ( true === $this->check_token_expiry( $stored_data['expiry'] ) ) {
			return false;
		}

		return password_verify( wp_json_encode( $this->metadata ) . $token, $stored_data['token_hash'] );
	}

	/**
	 * Whether the token is expired.
	 *
	 * @return bool
	 */
	public function is_expired() : bool {
		$stored_data = $this->load_token_data( $this->metadata );

		return $this->check_token_expiry( $stored_data['expiry'] );
	}

	/**
	 * Checks if a token is expired.
	 *
	 * @param int $expiry The token expiry.
	 *
	 * @return bool
	 */
	private function check_token_expiry( int $expiry ) : bool {
		return ! empty( $expiry ) && time() > $expiry;
	}

	/**
	 * Load the token data from storage.
	 *
	 * @param array $metadata The metadata of the token.
	 *
	 * @return array $stored_data {
	 *
	 * @type string $token_hash The token hash. Used for verification.
	 * @type string $metadata The stored token metadata.
	 * @type int    $expiry The expiry timestamp.
	 * }
	 */
	protected function load_token_data( array $metadata ): array {
		$token_hash      = get_user_meta( $metadata['user_id'], 'job_manager_alerts_secret_key', true );
		$stored_metadata = get_user_meta( $metadata['user_id'], 'job_manager_alerts_token_metadata', true );
		$expiry          = (int) get_user_meta( $metadata['user_id'], 'job_manager_alerts_token_expiry', true );

		return [
			'token_hash' => $token_hash,
			'metadata'   => $stored_metadata ?: [],
			'expiry'     => $expiry,
		];
	}

	/**
	 * Store the token data.
	 *
	 * @param array    $metadata   The token metadata.
	 * @param string   $token_hash The token hash that will be used for the verification.
	 * @param int|null $expiry The token expiry.
	 *
	 * @return void
	 */
	protected function store_token_data( array $metadata, string $token_hash, ?int $expiry ): void {
		update_user_meta( $metadata['user_id'], 'job_manager_alerts_secret_key', $token_hash );
		update_user_meta( $metadata['user_id'], 'job_manager_alerts_token_metadata', $metadata );
		update_user_meta( $metadata['user_id'], 'job_manager_alerts_token_expiry', $expiry );
	}
}


