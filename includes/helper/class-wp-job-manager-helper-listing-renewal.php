<?php
/**
 * File containing the class WP_Job_Manager_Helper_Listing_Renewal.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the renewal of Job Listings.
 *
 * @since $next-version$$
 */
class WP_Job_Manager_Helper_Listing_Renewal {

	/**
	 * Submit job form instance.
	 *
	 * @var WP_Job_Manager_Form_Submit_Job
	 */
	public $form = null;

	/**
	 * Stores static instance of class.
	 *
	 * @access protected
	 * @var WP_Job_Manager_Form_Submit_Job The single instance of the class
	 */
	protected static $instance = null;

	/**
	 * Constructor.
	 *
	 * @param WP_Job_Manager_Form_Submit_Job $form Form object.
	 */
	public function __construct( WP_Job_Manager_Form_Submit_Job $form ) {
		$this->form = $form;

		if ( $this->is_renew_action() ) {
			add_filter( 'submit_job_steps', [ $this, 'remove_edit_steps_for_renewal' ] );
			add_filter( 'submit_job_step_preview_submit_text', [ $this, 'submit_button_text_renewal' ], 15 );
		}
	}

	/**
	 * Returns static instance of class.
	 *
	 * @param WP_Job_Manager_Form_Submit_Job $form Form object.
	 * @return self
	 */
	public static function instance( WP_Job_Manager_Form_Submit_Job $form ) {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self( $form );
		}
		return self::$instance;
	}

	/**
	 * Checks if 'renew' is set as action.
	 *
	 * @since $$next-version$$
	 * @return bool
	 */
	public function is_renew_action() {
		$job_id = isset( $_GET['job_id'] ) ? sanitize_text_field( wp_unslash( $_GET['job_id'] ) ) : '';
		$nonce  = isset( $_GET['nonce'] ) ? wp_unslash( $_GET['nonce'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce should not be modified.
		$action = 'job_manager_renew_job_' . $job_id;
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			return false;
		}
		return isset( $_GET['action'] ) && sanitize_text_field( wp_unslash( $_GET['action'] ) ) === 'renew';
	}

	/**
	 * Handle steps for renewing a listing before expiry, removes edit steps and overrides preview step.
	 *
	 * @access private
	 * @param array $steps
	 * @return array
	 */
	public function remove_edit_steps_for_renewal( $steps ) {
		unset( $steps['submit'], $steps['preview'] );
		$steps['preview'] = [
			'name'     => 'Renew Preview',
			'view'     => [ $this->form, 'preview' ],
			'handler'  => [ $this, 'renew_preview_handler' ],
			'priority' => 20,
		];
		/**
		 * Filter the steps for renewing a listing before expiry.
		 *
		 * @since $$next-version$$
		 *
		 * @param array $steps
		 * @param WP_Job_Manager_Form_Submit_Job $form
		 */
		return apply_filters( 'renew_job_steps', $steps, $this->form );
	}

	/**
	 * Handles the renew-listing form submission.
	 *
	 * @throws Exception On validation error.
	 */
	public function renew_preview_handler() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Input is used safely.
		if ( empty( $_POST ) ) {
			return;
		}

		$this->form->check_preview_form_nonce_field();

		if ( ! empty( $_POST['continue'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Input is used safely.
			if ( $this->should_renew_job_listing() ) {
				job_manager_renew_job_listing( get_post( $this->form->get_job_id() ) );
			}

			$this->form->next_step();
		}
	}

	/**
	 * Checks if the job listing should be renewed.
	 */
	public function should_renew_job_listing() {
		return 'publish' === get_post_status( $this->form->get_job_id() ) && $this->is_renew_action() && job_manager_job_can_be_renewed( $this->form->get_job_id() );
	}

	/**
	 * Filters submit button text for renew-listing step.
	 *
	 * @param int $text The button text.
	 * @return string
	 */
	public function submit_button_text_renewal( $text ) {
		$key = $this->form->get_step_key( $this->form->get_step() );
		if ( 'Renew Preview' === $this->form->get_steps()[ $key ]['name'] ) {
			return __( 'Renew Listing &rarr;', 'wp-job-manager' );
		}
		return $text;
	}
}
