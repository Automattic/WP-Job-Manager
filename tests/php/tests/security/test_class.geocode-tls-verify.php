<?php
/**
 * Regression test: the geocoding request must validate the remote TLS certificate.
 *
 * WP_Job_Manager_Geocode::get_location_data() built its outbound HTTPS request to the
 * Google Maps geocoding endpoint with 'sslverify' => false, disabling certificate-chain
 * and hostname validation. A network man-in-the-middle could then present a forged
 * certificate and return crafted geocode JSON, which is persisted to the listing's
 * geolocation post meta (CWE-295). The request must keep certificate verification on.
 *
 * @package wp-job-manager
 */
class Tests_Geocode_TLS_Verify extends WPJM_BaseTest {

	/**
	 * Captured value of the outbound request's `sslverify` argument.
	 *
	 * @var mixed
	 */
	private $captured_sslverify = 'unset';

	public function setUp(): void {
		parent::setUp();
		add_filter( 'job_manager_geolocation_enabled', '__return_true' );
		add_filter( 'http_request_args', [ $this, 'capture_sslverify' ] );
		add_filter( 'pre_http_request', [ $this, 'short_circuit_request' ] );
	}

	public function tearDown(): void {
		remove_filter( 'http_request_args', [ $this, 'capture_sslverify' ] );
		remove_filter( 'pre_http_request', [ $this, 'short_circuit_request' ] );
		parent::tearDown();
	}

	/**
	 * Records the `sslverify` argument WP was given for the outbound request.
	 *
	 * @param array $args HTTP request arguments.
	 *
	 * @return array
	 */
	public function capture_sslverify( $args ) {
		$this->captured_sslverify = array_key_exists( 'sslverify', $args ) ? $args['sslverify'] : 'unset';
		return $args;
	}

	/**
	 * Short-circuits the actual network call; the response body is irrelevant to this test.
	 *
	 * @return array
	 */
	public function short_circuit_request() {
		return [
			'body'     => wp_json_encode( [ 'status' => 'ZERO_RESULTS' ] ),
			'response' => [ 'code' => 200 ],
			'headers'  => [],
		];
	}

	public function test_geocode_request_verifies_tls_certificate() {
		try {
			WP_Job_Manager_Geocode::get_location_data( 'Seattle, WA, USA' );
		} catch ( \Exception $e ) {
			// Response handling (e.g. ZERO_RESULTS) is out of scope; the request args are
			// already captured by the time any exception is thrown.
			unset( $e );
		}

		$this->assertNotSame(
			false,
			$this->captured_sslverify,
			'The geocoding request must not disable TLS certificate verification.'
		);
	}
}
