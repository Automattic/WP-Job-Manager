<?php

use WP_Job_Manager\WP_Job_Manager_Recaptcha;

/**
 * @group recaptcha
 */
class WP_Test_WP_Job_Manager_Recaptcha extends WPJM_BaseTest {

	/**
	 * Response body returned by the mocked HTTP request. A WP_Error simulates a transport failure.
	 *
	 * @var array|WP_Error
	 */
	private $mock_response = [ 'success' => true ];

	/**
	 * Captured HTTP requests as [ 'url' => string, 'args' => array ].
	 *
	 * @var array
	 */
	private $requests = [];

	/**
	 * Original REMOTE_ADDR, restored in tearDown.
	 *
	 * @var string|null
	 */
	private $original_remote_addr;

	public function setUp(): void {
		parent::setUp();
		$this->original_remote_addr = $_SERVER['REMOTE_ADDR'] ?? null;
		$_SERVER['REMOTE_ADDR']     = '203.0.113.7';
		update_option( 'job_manager_recaptcha_label', 'Are you human?' );
		$this->requests      = [];
		$this->mock_response = [ 'success' => true ];
		add_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10, 3 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', [ $this, 'mock_http_request' ], 10 );
		unset( $_POST['cf-turnstile-response'], $_POST['g-recaptcha-response'] );
		if ( null === $this->original_remote_addr ) {
			unset( $_SERVER['REMOTE_ADDR'] );
		} else {
			$_SERVER['REMOTE_ADDR'] = $this->original_remote_addr;
		}
		$this->reset_instance();
		parent::tearDown();
	}

	public function mock_http_request( $preempt, $args, $url ) {
		$this->requests[] = [
			'url'  => $url,
			'args' => $args,
		];

		if ( is_wp_error( $this->mock_response ) ) {
			return $this->mock_response;
		}

		return [
			'headers'  => [],
			'body'     => wp_json_encode( $this->mock_response ),
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
		];
	}

	/**
	 * @covers WP_Job_Manager\WP_Job_Manager_Recaptcha::__construct
	 * @covers WP_Job_Manager\WP_Job_Manager_Recaptcha::is_recaptcha_available
	 */
	public function test_turnstile_uses_turnstile_keys() {
		update_option( 'job_manager_turnstile_site_key', 'ts-site' );
		update_option( 'job_manager_turnstile_secret_key', 'ts-secret' );
		update_option( 'job_manager_recaptcha_site_key', '' );
		update_option( 'job_manager_recaptcha_secret_key', '' );

		$this->assertTrue( $this->get_instance( 'turnstile' )->is_recaptcha_available() );
		$this->assertFalse( $this->get_instance( 'v2' )->is_recaptcha_available() );
		$this->assertFalse( $this->get_instance( 'v3' )->is_recaptcha_available() );
	}

	/**
	 * @covers WP_Job_Manager\WP_Job_Manager_Recaptcha::__construct
	 * @covers WP_Job_Manager\WP_Job_Manager_Recaptcha::is_recaptcha_available
	 */
	public function test_recaptcha_ignores_turnstile_keys() {
		update_option( 'job_manager_turnstile_site_key', '' );
		update_option( 'job_manager_turnstile_secret_key', '' );
		update_option( 'job_manager_recaptcha_site_key', 'rc-site' );
		update_option( 'job_manager_recaptcha_secret_key', 'rc-secret' );

		$this->assertTrue( $this->get_instance( 'v2' )->is_recaptcha_available() );
		$this->assertFalse( $this->get_instance( 'turnstile' )->is_recaptcha_available() );
	}

	/**
	 * @covers WP_Job_Manager\WP_Job_Manager_Recaptcha::get_recaptcha_version
	 * @covers WP_Job_Manager\WP_Job_Manager_Recaptcha::is_turnstile
	 */
	public function test_get_recaptcha_version_returns_turnstile() {
		$instance = $this->get_turnstile_instance();

		$this->assertSame( 'turnstile', $instance->get_recaptcha_version() );
		$this->assertTrue( $instance->is_turnstile() );
		$this->assertFalse( $this->get_instance( 'v2' )->is_turnstile() );
	}

	/**
	 * @covers WP_Job_Manager\WP_Job_Manager_Recaptcha::validate_recaptcha_field
	 */
	public function test_turnstile_validation_passes_on_success() {
		$instance                      = $this->get_turnstile_instance();
		$_POST['cf-turnstile-response'] = 'token-123';
		$this->mock_response            = [ 'success' => true ];

		$this->assertTrue( $instance->validate_recaptcha_field( true ) );

		$this->assertCount( 1, $this->requests );
		$request = $this->requests[0];
		$this->assertSame( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', $request['url'] );
		$this->assertSame( 'POST', $request['args']['method'] );
		$this->assertSame( 'ts-secret', $request['args']['body']['secret'] );
		$this->assertSame( 'token-123', $request['args']['body']['response'] );
		$this->assertSame( '203.0.113.7', $request['args']['body']['remoteip'] );
	}

	/**
	 * @covers WP_Job_Manager\WP_Job_Manager_Recaptcha::validate_recaptcha_field
	 */
	public function test_turnstile_validation_fails_on_unsuccessful_response() {
		$instance                      = $this->get_turnstile_instance();
		$_POST['cf-turnstile-response'] = 'token-123';
		$this->mock_response            = [
			'success'     => false,
			'error-codes' => [ 'invalid-input-response' ],
		];

		$this->assertValidationError( $instance->validate_recaptcha_field( true ) );
	}

	/**
	 * @covers WP_Job_Manager\WP_Job_Manager_Recaptcha::validate_recaptcha_field
	 */
	public function test_turnstile_validation_fails_on_http_error() {
		$instance                      = $this->get_turnstile_instance();
		$_POST['cf-turnstile-response'] = 'token-123';
		$this->mock_response            = new WP_Error( 'http_request_failed', 'Timed out' );

		$this->assertValidationError( $instance->validate_recaptcha_field( true ) );
	}

	/**
	 * @covers WP_Job_Manager\WP_Job_Manager_Recaptcha::validate_recaptcha_field
	 */
	public function test_turnstile_validation_fails_on_malformed_response() {
		$instance                      = $this->get_turnstile_instance();
		$_POST['cf-turnstile-response'] = 'token-123';
		$this->mock_response            = 'not-json-object';

		$this->assertValidationError( $instance->validate_recaptcha_field( true ) );
	}

	/**
	 * @covers WP_Job_Manager\WP_Job_Manager_Recaptcha::validate_recaptcha_field
	 */
	public function test_turnstile_validation_fails_without_token() {
		$instance = $this->get_turnstile_instance();

		$this->assertValidationError( $instance->validate_recaptcha_field( true ) );
		$this->assertCount( 0, $this->requests, 'No verification request should be made without a token' );
	}

	/**
	 * @covers WP_Job_Manager\WP_Job_Manager_Recaptcha::validate_recaptcha_field
	 */
	public function test_turnstile_validation_ignores_recaptcha_token() {
		$instance                     = $this->get_turnstile_instance();
		$_POST['g-recaptcha-response'] = 'token-123';

		$this->assertValidationError( $instance->validate_recaptcha_field( true ) );
		$this->assertCount( 0, $this->requests );
	}

	/**
	 * @covers WP_Job_Manager\WP_Job_Manager_Recaptcha::validate_recaptcha_field
	 */
	public function test_recaptcha_v2_validation_unchanged() {
		update_option( 'job_manager_recaptcha_site_key', 'rc-site' );
		update_option( 'job_manager_recaptcha_secret_key', 'rc-secret' );
		$instance                     = $this->get_instance( 'v2' );
		$_POST['g-recaptcha-response'] = 'token-123';
		$this->mock_response           = [ 'success' => true ];

		$this->assertTrue( $instance->validate_recaptcha_field( true ) );

		$this->assertCount( 1, $this->requests );
		$request = $this->requests[0];
		$this->assertSame( 'GET', $request['args']['method'] );
		$this->assertSame( 'www.google.com', wp_parse_url( $request['url'], PHP_URL_HOST ) );
		$this->assertStringContainsString( 'secret=rc-secret', $request['url'] );
		$this->assertStringContainsString( 'response=token-123', $request['url'] );
	}

	/**
	 * @covers WP_Job_Manager\WP_Job_Manager_Recaptcha::display_recaptcha_field
	 */
	public function test_turnstile_display_renders_widget() {
		$instance = $this->get_turnstile_instance();

		ob_start();
		$instance->display_recaptcha_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'class="cf-turnstile"', $output );
		$this->assertStringContainsString( 'data-sitekey="ts-site"', $output );
		$this->assertStringContainsString( 'Are you human?', $output );
		$this->assertStringNotContainsString( 'g-recaptcha', $output );
	}

	/**
	 * @covers WP_Job_Manager\WP_Job_Manager_Recaptcha::display_recaptcha_field
	 */
	public function test_recaptcha_v2_display_unchanged() {
		update_option( 'job_manager_recaptcha_site_key', 'rc-site' );
		update_option( 'job_manager_recaptcha_secret_key', 'rc-secret' );
		$instance = $this->get_instance( 'v2' );

		ob_start();
		$instance->display_recaptcha_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'class="g-recaptcha"', $output );
		$this->assertStringContainsString( 'data-sitekey="rc-site"', $output );
		$this->assertStringNotContainsString( 'cf-turnstile', $output );
	}

	/**
	 * @covers WP_Job_Manager\WP_Job_Manager_Recaptcha::enqueue_scripts
	 */
	public function test_turnstile_enqueues_script() {
		$instance = $this->get_turnstile_instance();

		$instance->enqueue_scripts();

		$this->assertTrue( wp_script_is( 'cf-turnstile', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'recaptcha', 'enqueued' ) );
		$this->assertSame( 'https://challenges.cloudflare.com/turnstile/v0/api.js', wp_scripts()->registered['cf-turnstile']->src );

		wp_dequeue_script( 'cf-turnstile' );
		wp_deregister_script( 'cf-turnstile' );
	}

	/**
	 * @covers WP_Job_Manager\WP_Job_Manager_Recaptcha::enqueue_scripts
	 */
	public function test_recaptcha_v2_enqueues_script() {
		update_option( 'job_manager_recaptcha_site_key', 'rc-site' );
		update_option( 'job_manager_recaptcha_secret_key', 'rc-secret' );
		$instance = $this->get_instance( 'v2' );

		$instance->enqueue_scripts();

		$this->assertTrue( wp_script_is( 'recaptcha', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'cf-turnstile', 'enqueued' ) );

		wp_dequeue_script( 'recaptcha' );
		wp_deregister_script( 'recaptcha' );
	}

	/**
	 * @covers WP_Job_Manager\WP_Job_Manager_Recaptcha::maybe_enable_recaptcha
	 */
	public function test_maybe_enable_recaptcha_hooks_turnstile_into_form() {
		update_option( 'job_manager_enable_recaptcha_job_submission', '1' );
		$instance = $this->get_turnstile_instance();

		$instance->maybe_enable_recaptcha( 'job_manager_enable_recaptcha_job_submission', [ 'wpjm_test_display' ], [ 'wpjm_test_validate' ] );

		$this->assertSame( 10, has_action( 'wpjm_test_display', [ $instance, 'display_recaptcha_field' ] ) );
		$this->assertSame( 10, has_filter( 'wpjm_test_validate', [ $instance, 'validate_recaptcha_field' ] ) );

		remove_action( 'wpjm_test_display', [ $instance, 'display_recaptcha_field' ] );
		remove_filter( 'wpjm_test_validate', [ $instance, 'validate_recaptcha_field' ] );
		remove_action( 'wp_enqueue_scripts', [ $instance, 'enqueue_scripts' ] );
		wp_dequeue_script( 'cf-turnstile' );
		wp_deregister_script( 'cf-turnstile' );
	}

	private function assertValidationError( $result ) {
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'validation-error', $result->get_error_code() );
		$this->assertStringContainsString( 'Are you human?', $result->get_error_message() );
	}

	private function get_turnstile_instance() {
		update_option( 'job_manager_turnstile_site_key', 'ts-site' );
		update_option( 'job_manager_turnstile_secret_key', 'ts-secret' );

		return $this->get_instance( 'turnstile' );
	}

	/**
	 * Build a fresh instance for the given provider. The singleton has no reset method so the
	 * cached instance is cleared through reflection.
	 *
	 * @param string $version 'v2', 'v3' or 'turnstile'.
	 *
	 * @return WP_Job_Manager_Recaptcha
	 */
	private function get_instance( $version ) {
		update_option( 'job_manager_recaptcha_version', $version );
		$this->reset_instance();

		return WP_Job_Manager_Recaptcha::instance();
	}

	private function reset_instance() {
		$property = new ReflectionProperty( WP_Job_Manager_Recaptcha::class, 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}
}
