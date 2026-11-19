<?php
/**
 * File containing the WP_Job_Manager_Recaptcha class.
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Recaptcha class.
 *
 * @since 2.3.0
 */
class WP_Job_Manager_Recaptcha {

	use Singleton;

	/**
	 * Site key.
	 *
	 * @var string
	 */
	private $site_key;

	/**
	 * Secret key.
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * The CAPTCHA provider: 'v2' or 'v3' for reCAPTCHA, or 'turnstile' for Cloudflare Turnstile.
	 *
	 * @var string
	 */
	private $recaptcha_version;

	const RECAPTCHA_SITE_KEY   = 'job_manager_recaptcha_site_key';
	const RECAPTCHA_SECRET_KEY = 'job_manager_recaptcha_secret_key';
	const RECAPTCHA_VERSION    = 'job_manager_recaptcha_version';
	const TURNSTILE_SITE_KEY   = 'job_manager_turnstile_site_key';
	const TURNSTILE_SECRET_KEY = 'job_manager_turnstile_secret_key';

	const TURNSTILE_SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
	const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

	/**
	 * Initialize class for landing pages.
	 *
	 * @since 2.0.0
	 */
	private function __construct() {
		$this->recaptcha_version = get_option( self::RECAPTCHA_VERSION, 'v2' );

		if ( $this->is_turnstile() ) {
			$this->site_key   = get_option( self::TURNSTILE_SITE_KEY );
			$this->secret_key = get_option( self::TURNSTILE_SECRET_KEY );
		} else {
			$this->site_key   = get_option( self::RECAPTCHA_SITE_KEY );
			$this->secret_key = get_option( self::RECAPTCHA_SECRET_KEY );
		}
	}

	/**
	 * Whether Cloudflare Turnstile is the selected CAPTCHA provider.
	 *
	 * @since $$next-version$$
	 *
	 * @return bool
	 */
	public function is_turnstile() {
		return 'turnstile' === $this->recaptcha_version;
	}

	/**
	 * Enables the reCAPTCHA field on the form. To do that, it checks if the provided option is enabled and if it is
	 * it adds the necessary hooks to display and validate the reCAPTCHA field.
	 *
	 * @param string $recaptcha_enabled_option The options name to check if the reCAPTCHA field is enabled.
	 * @param array  $display_hooks The hooks to display the reCAPTCHA field.
	 * @param array  $validate_hooks The hooks to validate the reCAPTCHA field.
	 *
	 * @return void
	 */
	public function maybe_enable_recaptcha( string $recaptcha_enabled_option, array $display_hooks, array $validate_hooks ) {
		if ( $this->use_recaptcha_field( $recaptcha_enabled_option ) ) {
			foreach ( $display_hooks as $display_hook ) {
				add_action( $display_hook, [ $this, 'display_recaptcha_field' ] );
			}

			foreach ( $validate_hooks as $validate_hook ) {
				add_filter( $validate_hook, [ $this, 'validate_recaptcha_field' ] );
			}

			if ( did_action( 'wp_enqueue_scripts' ) ) {
				$this->enqueue_scripts();
			} else {
				add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
			}
		}
	}

	/**
	 * Use reCAPTCHA field on the form?
	 *
	 * @param string $option_name The options name to check if the reCAPTCHA field is enabled.
	 *
	 * @return bool
	 */
	private function use_recaptcha_field( $option_name ) {
		if ( ! $this->is_recaptcha_available() ) {
			return false;
		}

		return 1 === absint( get_option( $option_name ) );
	}

	/**
	 * Enqueue the scripts and add appropriate hooks for the recaptcha to load.
	 *
	 * @access private
	 */
	public function enqueue_scripts() {
		$instance = self::instance();

		if ( $instance->is_turnstile() ) {
			wp_enqueue_script( 'cf-turnstile', self::TURNSTILE_SCRIPT_URL, [], JOB_MANAGER_VERSION, [ 'strategy' => 'defer' ] );
		} elseif ( in_array( $instance->recaptcha_version, [ 'v2', 'v3' ], true ) ) {
			$recaptcha_version = $instance->recaptcha_version;
			$recaptcha_url     = '';

			if ( 'v2' === $recaptcha_version ) {
				$recaptcha_url = 'https://www.google.com/recaptcha/api.js';
			} elseif ( 'v3' === $recaptcha_version ) {
				$recaptcha_url = 'https://www.google.com/recaptcha/api.js?render=' . $instance->site_key;
			}
			wp_enqueue_script( 'recaptcha', $recaptcha_url, [], JOB_MANAGER_VERSION, [ 'strategy' => 'defer' ] );
		}
	}

	/**
	 * Checks whether reCAPTCHA has been set up and is available.
	 *
	 * @access private
	 *
	 * @return bool
	 */
	public function is_recaptcha_available() {
		$is_recaptcha_available = ! empty( $this->site_key ) && ! empty( $this->secret_key );

		/**
		 * Filter whether reCAPTCHA should be available for this form.
		 *
		 * @since 1.30.0
		 *
		 * @param bool $is_recaptcha_available
		 */
		return apply_filters( 'job_manager_is_recaptcha_available', $is_recaptcha_available );
	}

	/**
	 * Display the reCAPTCHA field in the form.
	 *
	 * @access private
	 *
	 * @return void
	 */
	public function display_recaptcha_field() {
		$field             = [];
		$field['label']    = get_option( 'job_manager_recaptcha_label' );
		$field['required'] = true;
		$field['site_key'] = $this->site_key;

		if ( $this->is_turnstile() ) {
			$template = 'form-fields/turnstile-field.php';
		} else {
			$template = 'form-fields/recaptcha-' . ( 'v3' === $this->recaptcha_version ? 'v3-' : '' ) . 'field.php';
		}

		get_job_manager_template(
			$template,
			[
				'key'   => 'recaptcha',
				'field' => $field,
			]
		);
	}

	/**
	 * Validate a reCAPTCHA field.
	 *
	 * @param bool $success
	 *
	 * @access private
	 *
	 * @return bool|\WP_Error
	 */
	public function validate_recaptcha_field( $success ) {
		$recaptcha_field_label = get_option( 'job_manager_recaptcha_label' );

		// translators: %s is the name of the form validation that failed.
		$validation_error = new \WP_Error( 'validation-error', sprintf( esc_html__( '"%s" check failed. Please try again.', 'wp-job-manager' ), $recaptcha_field_label ) );

		$response_field = $this->is_turnstile() ? 'cf-turnstile-response' : 'g-recaptcha-response';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check happens earlier (when possible).
		$input_recaptcha_response = isset( $_POST[ $response_field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $response_field ] ) ) : '';

		if ( empty( $input_recaptcha_response ) ) {
			return $validation_error;
		}

		if ( $this->is_turnstile() ) {
			$response = wp_remote_post(
				self::TURNSTILE_VERIFY_URL,
				[
					'body' => [
						'secret'   => $this->secret_key,
						'response' => $input_recaptcha_response,
						'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
					],
				]
			);

			if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
				return $validation_error;
			}

			$json = json_decode( wp_remote_retrieve_body( $response ) );
			if ( ! is_object( $json ) || empty( $json->success ) ) {
				return $validation_error;
			}
		} elseif ( 'v2' === $this->recaptcha_version ) {
			$default_remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			$response            = wp_remote_get(
				add_query_arg(
					[
						'secret'   => $this->secret_key,
						'response' => $input_recaptcha_response,
						'remoteip' => isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : $default_remote_addr,
					],
					'https://www.google.com/recaptcha/api/siteverify'
				)
			);
			if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
				return $validation_error;
			} else {
				$json = json_decode( $response['body'] );
				if ( ! $json || ! $json->success ) {
					return $validation_error;
				}
			}
		} elseif ( 'v3' === $this->recaptcha_version ) {
			$recaptcha_url  = 'https://www.google.com/recaptcha/api/siteverify';
			$recaptcha_data = [
				'secret'   => $this->secret_key,
				'response' => $input_recaptcha_response,
				'remoteip' => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ),
			];

			$response = wp_remote_post(
				$recaptcha_url,
				[
					'body'    => $recaptcha_data,
					'headers' => [
						'Content-Type' => 'application/x-www-form-urlencoded',
					],
				]
			);

			if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
				return $validation_error;
			} else {
				$response_body = wp_remote_retrieve_body( $response );
				$response_body = json_decode( $response_body );
			}

			/**
			 * Filter the score tolerance for reCAPTCHA v3.
			 *
			 * The score tolerance determines how strict the reCAPTCHA v3 validation is. A higher tolerance allows more leniency in accepting scores. A higher score means more certainty that the user is human.
			 *
			 * @since 2.3.0
			 *
			 * @param float $score_tolerance The score tolerance value. Default is 0.5.
			 */
			$score_tolerance = apply_filters( 'job_manager_recaptcha_v3_score_tolerance', 0.5 );

			if ( ! $response_body->success || $response_body->score < $score_tolerance ) {
				return $validation_error;
			}
		}
		return $success;
	}

	/**
	 * Get the CAPTCHA provider: 'v2' or 'v3' for reCAPTCHA, or 'turnstile' for Cloudflare Turnstile.
	 *
	 * @return string
	 */
	public function get_recaptcha_version() {
		return $this->recaptcha_version;
	}
}
