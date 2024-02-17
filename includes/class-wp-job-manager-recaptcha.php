<?php
/**
 * File containing the WP_Job_Manager_Recaptcha class.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notices_Conditions_Checker class.
 *
 * @since 1.40.0
 * @internal
 */
class WP_Job_Manager_Recaptcha {

	/**
	 * Initialize class for landing pages.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {
		if ( $this->use_recaptcha_field() ) {
			add_action( 'submit_job_form_end', [ $this, 'display_recaptcha_field' ] );
			add_filter( 'submit_job_form_validate_fields', [ $this, 'validate_recaptcha_field' ] );
			add_filter( 'submit_draft_job_form_validate_fields', [ $this, 'validate_recaptcha_field' ] );
		}
	}

	/**
	 * Use reCAPTCHA field on the form?
	 *
	 * @return bool
	 */
	public function use_recaptcha_field() {
		if ( ! $this->is_recaptcha_available() ) {
			return false;
		}
		return 1 === absint( get_option( 'job_manager_enable_recaptcha_job_submission' ) );
	}

	/**
	 * Enqueue the scripts for the form.
	 */
	public function enqueue_scripts() {
		if ( $this->use_recaptcha_field() ) {
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion
			wp_enqueue_script( 'recaptcha', 'https://www.google.com/recaptcha/api.js', [], false, false );
		}
	}

	/**
	 * Checks whether reCAPTCHA has been set up and is available.
	 *
	 * @return bool
	 */
	public function is_recaptcha_available() {
		$site_key               = get_option( 'job_manager_recaptcha_site_key' );
		$secret_key             = get_option( 'job_manager_recaptcha_secret_key' );
		$is_recaptcha_available = ! empty( $site_key ) && ! empty( $secret_key );

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
	 * Dispaly the reCAPTCHA field in the form.
	 *
	 * @return void
	 */
	public function display_recaptcha_field() {
		$field             = [];
		$field['label']    = get_option( 'job_manager_recaptcha_label' );
		$field['required'] = true;
		$field['site_key'] = get_option( 'job_manager_recaptcha_site_key' );
		get_job_manager_template(
			'form-fields/recaptcha-field.php',
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
	 * @return bool|WP_Error
	 */
	public function validate_recaptcha_field( $success ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check happens earlier (when possible).
		$input_recaptcha_response = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';

		$recaptcha_field_label = get_option( 'job_manager_recaptcha_label' );
		if ( empty( $input_recaptcha_response ) ) {
			// translators: Placeholder is for the label of the reCAPTCHA field.
			return new WP_Error( 'validation-error', sprintf( esc_html__( '"%s" check failed. Please try again.', 'wp-job-manager' ), $recaptcha_field_label ) );
		}

		$default_remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$response            = wp_remote_get(
			add_query_arg(
				[
					'secret'   => get_option( 'job_manager_recaptcha_secret_key' ),
					'response' => $input_recaptcha_response,
					'remoteip' => isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : $default_remote_addr,
				],
				'https://www.google.com/recaptcha/api/siteverify'
			)
		);

		// translators: %s is the name of the form validation that failed.
		$validation_error = new WP_Error( 'validation-error', sprintf( esc_html__( '"%s" check failed. Please try again.', 'wp-job-manager' ), $recaptcha_field_label ) );

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			return $validation_error;
		}

		$json = json_decode( $response['body'] );
		if ( ! $json || ! $json->success ) {
			return $validation_error;
		}

		return $success;
	}

}
