<?php
/**
 * WP_Job_Manager_Forms class.
 */
class WP_Job_Manager_Forms {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'load_posted_form' ) );
	}

	/**
	 * If a form was posted, load its class so that it can be processed before display.
	 */
	public function load_posted_form() {
		if ( ! empty( $_POST['job_manager_form'] ) ) {
			$this->load_form_class( sanitize_title( $_POST['job_manager_form'] ) );
		}
	}

	/**
	 * Load a form's class
	 *
	 * @param  string $form_name
	 * @return string class name on success, false on failure
	 */
	private function load_form_class( $form_name ) {
		global $job_manager;

		// Load the form abtract
		if ( ! class_exists( 'WP_Job_Manager_Form' ) )
			include( 'abstracts/abstract-wp-job-manager-form.php' );

		// Now try to load the form_name
		$form_class  = 'WP_Job_Manager_Form_' . str_replace( '-', '_', $form_name );
		$form_file   = JOB_MANAGER_PLUGIN_DIR . '/includes/forms/class-wp-job-manager-form-' . $form_name . '.php';

		if ( class_exists( $form_class ) )
			return $form_class;

		if ( ! file_exists( $form_file ) )
			return false;

		if ( ! class_exists( $form_class ) )
			include $form_file;

		// Init the form
		call_user_func( array( $form_class, "init" ) );

		return $form_class;
	}

	/**
	 * get_form function.
	 *
	 * @access public
	 * @param mixed $form_name
	 * @return string
	 */
	public function get_form( $form_name ) {
		if ( $form = $this->load_form_class( $form_name ) ) {
			ob_start();
			call_user_func( array( $form, "output" ) );
			return ob_get_clean();
		}
	}

}