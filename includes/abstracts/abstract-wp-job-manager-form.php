<?php
/**
 * File containing the class WP_Job_Manager_Form.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parent abstract class for form classes.
 *
 * @abstract
 * @since 1.0.0
 */
abstract class WP_Job_Manager_Form {

	/**
	 * Form fields.
	 *
	 * @access protected
	 * @var array
	 */
	protected $fields = array();

	/**
	 * Form action.
	 *
	 * @access protected
	 * @var string
	 */
	protected $action = '';

	/**
	 * Form errors.
	 *
	 * @access protected
	 * @var array
	 */
	protected $errors = array();

	/**
	 * Form notices.
	 *
	 * @access protected
	 * @var array
	 */
	protected $messages = array();

	/**
	 * Form steps.
	 *
	 * @access protected
	 * @var array
	 */
	protected $steps = array();

	/**
	 * Current form step.
	 *
	 * @access protected
	 * @var int
	 */
	protected $step = 0;

	/**
	 * Form name.
	 *
	 * @access protected
	 * @var string
	 */
	public $form_name = '';

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, 'Unable to clone ' . __CLASS__, '1.0.0' );
	}

	/**
	 * Unserializes instances of this class is forbidden.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, 'Unable to wake up ' . __CLASS__, '1.0.0' );
	}

	/**
	 * Processes the form result and can also change view if step is complete.
	 */
	public function process() {

		// reset cookie.
		if (
			isset( $_GET['new'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used safely.
			isset( $_COOKIE['wp-job-manager-submitting-job-id'] ) &&
			isset( $_COOKIE['wp-job-manager-submitting-job-key'] ) &&
			get_post_meta( sanitize_text_field( wp_unslash( $_COOKIE['wp-job-manager-submitting-job-id'] ) ), '_submitting_key', true ) === $_COOKIE['wp-job-manager-submitting-job-key']
		) {
			delete_post_meta( sanitize_text_field( wp_unslash( $_COOKIE['wp-job-manager-submitting-job-id'] ) ), '_submitting_key' );
			setcookie( 'wp-job-manager-submitting-job-id', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, false );
			setcookie( 'wp-job-manager-submitting-job-key', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, false );
			wp_safe_redirect( remove_query_arg( array( 'new', 'key' ) ) );
			exit;
		}

		$step_key = $this->get_step_key( $this->step );

		if ( $step_key && is_callable( $this->steps[ $step_key ]['handler'] ) ) {
			call_user_func( $this->steps[ $step_key ]['handler'] );
		}

		$next_step_key = $this->get_step_key( $this->step );

		// If the next step has a handler to call before going to the view, run it now.
		if (
			$next_step_key
			&& $step_key !== $next_step_key
			&& isset( $this->steps[ $next_step_key ]['before'] )
			&& is_callable( $this->steps[ $next_step_key ]['before'] )
		) {
			call_user_func( $this->steps[ $next_step_key ]['before'] );
		}

		// if the step changed, but the next step has no 'view', call the next handler in sequence.
		if ( $next_step_key && $step_key !== $next_step_key && ! is_callable( $this->steps[ $next_step_key ]['view'] ) ) {
			$this->process();
		}
	}

	/**
	 * Calls the view handler if set, otherwise call the next handler.
	 *
	 * @param array $atts Attributes to use in the view handler.
	 */
	public function output( $atts = array() ) {
		$this->enqueue_scripts();
		$step_key = $this->get_step_key( $this->step );
		$this->show_errors();
		$this->show_messages();

		if ( $step_key && is_callable( $this->steps[ $step_key ]['view'] ) ) {
			call_user_func( $this->steps[ $step_key ]['view'], $atts );
		}
	}

	/**
	 * Adds an error.
	 *
	 * @param string $error The error message.
	 */
	public function add_error( $error ) {
		$this->errors[] = $error;
	}

	/**
	 * Displays errors.
	 */
	public function show_errors() {
		foreach ( $this->errors as $error ) {
			echo '<div class="job-manager-error">' . wp_kses_post( $error ) . '</div>';
		}
	}

	/**
	 * Adds an notice.
	 *
	 * @param string $message The notice message.
	 */
	public function add_message( $message ) {
		$this->messages[] = $message;
	}

	/**
	 * Displays notice messages.
	 */
	public function show_messages() {
		foreach ( $this->messages as $message ) {
			echo '<div class="job-manager-info">' . wp_kses_post( $message ) . '</div>';
		}
	}

	/**
	 * Gets the action (URL for forms to post to).
	 * As of 1.22.2 this defaults to the current page permalink.
	 *
	 * @return string
	 */
	public function get_action() {
		$default_action = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		return esc_url_raw( $this->action ? $this->action : $default_action );
	}

	/**
	 * Gets form name.
	 *
	 * @since 1.24.0
	 * @return string
	 */
	public function get_form_name() {
		return $this->form_name;
	}

	/**
	 * Gets steps from outside of the class.
	 *
	 * @since 1.24.0
	 */
	public function get_steps() {
		return $this->steps;
	}

	/**
	 * Gets step from outside of the class.
	 */
	public function get_step() {
		return $this->step;
	}

	/**
	 * Gets step key from outside of the class.
	 *
	 * @since 1.24.0
	 * @param string|int $step
	 * @return string
	 */
	public function get_step_key( $step = '' ) {
		if ( ! $step ) {
			$step = $this->step;
		}
		$keys = array_keys( $this->steps );
		return isset( $keys[ $step ] ) ? $keys[ $step ] : '';
	}

	/**
	 * Sets step from outside of the class.
	 *
	 * @since 1.24.0
	 * @param int $step
	 */
	public function set_step( $step ) {
		$this->step = absint( $step );
	}

	/**
	 * Increases step from outside of the class.
	 */
	public function next_step() {
		$this->step ++;
	}

	/**
	 * Decreases step from outside of the class.
	 */
	public function previous_step() {
		$this->step --;
	}

	/**
	 * Gets fields for form.
	 *
	 * @param string $key
	 * @return array
	 */
	public function get_fields( $key ) {
		if ( empty( $this->fields[ $key ] ) ) {
			return array();
		}

		$fields = $this->fields[ $key ];

		uasort( $fields, array( $this, 'sort_by_priority' ) );

		return $fields;
	}

	/**
	 * Sorts array by priority value.
	 *
	 * @param array $a
	 * @param array $b
	 * @return int
	 */
	protected function sort_by_priority( $a, $b ) {
		if ( floatval( $a['priority'] ) === floatval( $b['priority'] ) ) {
			return 0;
		}
		return ( floatval( $a['priority'] ) < floatval( $b['priority'] ) ) ? -1 : 1;
	}

	/**
	 * Initializes form fields.
	 */
	protected function init_fields() {
		$this->fields = array();
	}

	/**
	 * Enqueue the scripts for the form.
	 */
	public function enqueue_scripts() {
		if ( $this->use_recaptcha_field() ) {
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion
			wp_enqueue_script( 'recaptcha', 'https://www.google.com/recaptcha/api.js', array(), false, false );
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
	 * Show reCAPTCHA field on the form.
	 *
	 * @return bool
	 */
	public function use_recaptcha_field() {
		return false;
	}

	/**
	 * Output the reCAPTCHA field.
	 */
	public function display_recaptcha_field() {
		$field             = array();
		$field['label']    = get_option( 'job_manager_recaptcha_label' );
		$field['required'] = true;
		$field['site_key'] = get_option( 'job_manager_recaptcha_site_key' );
		get_job_manager_template(
			'form-fields/recaptcha-field.php',
			array(
				'key'   => 'recaptcha',
				'field' => $field,
			)
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
				array(
					'secret'   => get_option( 'job_manager_recaptcha_secret_key' ),
					'response' => $input_recaptcha_response,
					'remoteip' => isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : $default_remote_addr,
				),
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

	/**
	 * Gets post data for fields.
	 *
	 * @return array of data.
	 */
	protected function get_posted_fields() {
		$this->init_fields();

		$values = array();

		foreach ( $this->fields as $group_key => $group_fields ) {
			foreach ( $group_fields as $key => $field ) {
				// Get the value.
				$field_type = str_replace( '-', '_', $field['type'] );
				$handler    = apply_filters( "job_manager_get_posted_{$field_type}_field", false );

				if ( $handler ) {
					$values[ $group_key ][ $key ] = call_user_func( $handler, $key, $field );
				} elseif ( method_exists( $this, "get_posted_{$field_type}_field" ) ) {
					$values[ $group_key ][ $key ] = call_user_func( array( $this, "get_posted_{$field_type}_field" ), $key, $field );
				} else {
					$values[ $group_key ][ $key ] = $this->get_posted_field( $key, $field );
				}

				// Set fields value.
				$this->fields[ $group_key ][ $key ]['value'] = $values[ $group_key ][ $key ];
			}
		}

		/**
		 * Alter values for posted fields.
		 *
		 * Before submitting or editing a job, alter the posted values before they get stored into the database.
		 *
		 * @since 1.32.0
		 *
		 * @param array  $values  The values that have been submitted.
		 * @param array  $fields  The form fields.
		 */
		return apply_filters( 'job_manager_get_posted_fields', $values, $this->fields );
	}

	/**
	 * Navigates through an array and sanitizes the field.
	 *
	 * @since 1.22.0
	 * @since 1.29.1 Added the $sanitizer argument
	 *
	 * @param array|string    $value      The array or string to be sanitized.
	 * @param string|callable $sanitizer  The sanitization method to use. Built in: `url`, `email`, `url_or_email`, or
	 *                                      default (text). Custom single argument callable allowed.
	 * @return array|string   $value      The sanitized array (or string from the callback).
	 */
	protected function sanitize_posted_field( $value, $sanitizer = null ) {
		// Sanitize value.
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $val ) {
				$value[ $key ] = $this->sanitize_posted_field( $val, $sanitizer );
			}

			return $value;
		}

		$value = trim( $value );

		if ( 'url' === $sanitizer ) {
			return esc_url_raw( $value );
		} elseif ( 'email' === $sanitizer ) {
			return sanitize_email( $value );
		} elseif ( 'url_or_email' === $sanitizer ) {
			if ( null !== wp_parse_url( $value, PHP_URL_HOST ) ) {
				// Sanitize as URL.
				return esc_url_raw( $value );
			}

			// Sanitize as email.
			return sanitize_email( $value );
		} elseif ( is_callable( $sanitizer ) ) {
			return call_user_func( $sanitizer, $value );
		}

		// Use standard text sanitizer.
		return sanitize_text_field( wp_unslash( $value ) );
	}

	/**
	 * Gets the value of a posted field.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return string|array
	 */
	protected function get_posted_field( $key, $field ) {
		// Allow custom sanitizers with standard text fields.
		if ( ! isset( $field['sanitizer'] ) ) {
			$field['sanitizer'] = null;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification -- WP_Job_Manager_Form::sanitize_posted_field handles the sanitization based on the type of data passed; nonce check happens elsewhere.
		return isset( $_POST[ $key ] ) ? $this->sanitize_posted_field( wp_unslash( $_POST[ $key ] ), $field['sanitizer'] ) : '';
	}

	/**
	 * Gets the value of a posted multiselect field.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return array
	 */
	protected function get_posted_multiselect_field( $key, $field ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check happens earlier.
		return isset( $_POST[ $key ] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST[ $key ] ) ) : array();
	}

	/**
	 * Gets the value of a posted file field.
	 *
	 * @param  string $key
	 * @param  array  $field
	 *
	 * @return string|array
	 * @throws Exception When the upload fails.
	 */
	protected function get_posted_file_field( $key, $field ) {
		$file = $this->upload_file( $key, $field );

		if ( ! $file ) {
			$file = $this->get_posted_field( 'current_' . $key, $field );
		} elseif ( is_array( $file ) ) {
			$file = array_filter( array_merge( $file, (array) $this->get_posted_field( 'current_' . $key, $field ) ) );
		}

		return $file;
	}

	/**
	 * Gets the value of a posted textarea field.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return string
	 */
	protected function get_posted_textarea_field( $key, $field ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check happens earlier.
		return isset( $_POST[ $key ] ) ? trim( wp_kses_post( wp_unslash( $_POST[ $key ] ) ) ) : '';
	}

	/**
	 * Gets the value of a posted textarea field.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return string
	 */
	protected function get_posted_wp_editor_field( $key, $field ) {
		return $this->get_posted_textarea_field( $key, $field );
	}

	/**
	 * Gets posted terms for the taxonomy.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return array
	 */
	protected function get_posted_term_checklist_field( $key, $field ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check happens earlier.
		if ( isset( $_POST['tax_input'] ) && isset( $_POST['tax_input'][ $field['taxonomy'] ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check happens earlier.
			return array_map( 'absint', $_POST['tax_input'][ $field['taxonomy'] ] );
		} else {
			return array();
		}
	}

	/**
	 * Gets posted terms for the taxonomy.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return array
	 */
	protected function get_posted_term_multiselect_field( $key, $field ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check happens earlier.
		return isset( $_POST[ $key ] ) ? array_map( 'absint', $_POST[ $key ] ) : array();
	}

	/**
	 * Gets posted terms for the taxonomy.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return int
	 */
	protected function get_posted_term_select_field( $key, $field ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check happens earlier.
		return ! empty( $_POST[ $key ] ) && $_POST[ $key ] > 0 ? absint( $_POST[ $key ] ) : '';
	}

	/**
	 * Handles the uploading of files.
	 *
	 * @param string $field_key
	 * @param array  $field
	 * @throws Exception When file upload failed.
	 * @return  string|array
	 */
	protected function upload_file( $field_key, $field ) {
		if ( isset( $_FILES[ $field_key ] ) && ! empty( $_FILES[ $field_key ] ) && ! empty( $_FILES[ $field_key ]['name'] ) ) {
			if ( ! empty( $field['allowed_mime_types'] ) ) {
				$allowed_mime_types = $field['allowed_mime_types'];
			} else {
				$allowed_mime_types = job_manager_get_allowed_mime_types();
			}

			$file_urls       = array();
			$files_to_upload = job_manager_prepare_uploaded_files( $_FILES[ $field_key ] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- see https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards/issues/1720.

			foreach ( $files_to_upload as $file_to_upload ) {
				$uploaded_file = job_manager_upload_file(
					$file_to_upload,
					array(
						'file_key'           => $field_key,
						'allowed_mime_types' => $allowed_mime_types,
					)
				);

				if ( is_wp_error( $uploaded_file ) ) {
					throw new Exception( $uploaded_file->get_error_message() );
				} else {
					$file_urls[] = $uploaded_file->url;
				}
			}

			if ( ! empty( $field['multiple'] ) ) {
				return $file_urls;
			} else {
				return current( $file_urls );
			}
		}
	}
}
