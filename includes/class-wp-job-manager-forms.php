<?php
/**
 * File containing the class WP_Job_Manager_Forms.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for all WP Job Manager forms.
 *
 * @since 1.0.0
 */
class WP_Job_Manager_Forms {

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.26.0
	 */
	private static $instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.26.0
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'load_posted_form' ] );
	}

	/**
	 * If a form was posted, load its class so that it can be processed before display.
	 */
	public function load_posted_form() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used safely.
		$job_manager_form = ! empty( $_REQUEST['job_manager_form'] ) ? sanitize_title( wp_unslash( $_REQUEST['job_manager_form'] ) ) : false;

		if ( ! empty( $job_manager_form ) ) {
			$this->load_form_class( $job_manager_form );
		}
	}

	/**
	 * Load a form's class
	 *
	 * @param  string $form_name
	 * @return bool|WP_Job_Manager_Form Class instance on success, false on failure.
	 */
	private function load_form_class( $form_name ) {
		if ( ! class_exists( 'WP_Job_Manager_Form' ) ) {
			include 'abstracts/abstract-wp-job-manager-form.php';
		}

		// Now try to load the form_name.
		$form_class = 'WP_Job_Manager_Form_' . str_replace( '-', '_', $form_name );
		$form_file  = JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-' . $form_name . '.php';

		if ( class_exists( $form_class ) ) {
			return call_user_func( [ $form_class, 'instance' ] );
		}

		if ( ! file_exists( $form_file ) ) {
			return false;
		}

		if ( ! class_exists( $form_class ) ) {
			include $form_file;
		}

		// Init the form.
		return call_user_func( [ $form_class, 'instance' ] );
	}

	/**
	 * Returns the form content.
	 *
	 * @param string $form_name
	 * @param array  $atts Optional passed attributes.
	 * @return string|null
	 */
	public function get_form( $form_name, $atts = [] ) {
		$form = $this->load_form_class( $form_name );
		if ( $form ) {
			ob_start();
			$form->output( $atts );
			return ob_get_clean();
		}
	}
}
