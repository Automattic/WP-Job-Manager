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
 * @since 1.41.0
 */
class WP_Job_Manager_Helper_Renewals {

	/**
	 * Submit job form instance.
	 *
	 * @var WP_Job_Manager_Form_Submit_Job
	 */
	private $form = null;

	/**
	 * Stores static instance of class.
	 *
	 * @var WP_Job_Manager_Helper_Renewals The single instance of the class.
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 *
	 * @param WP_Job_Manager_Form_Submit_Job $form Form object.
	 */
	public function __construct( WP_Job_Manager_Form_Submit_Job $form ) {
		$this->form = $form;

		if ( self::is_renew_action() ) {
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
	 * @since 1.41.0
	 * @return bool
	 */
	public static function is_renew_action() {
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
	 * @param array $steps Form submit steps.
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
		 * @since 1.41.0
		 *
		 * @param array $steps Form submit steps.
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
				self::renew_job_listing( get_post( $this->form->get_job_id() ) );
			}

			$this->form->next_step();
		}
	}

	/**
	 * Checks if the job listing should be renewed.
	 */
	public function should_renew_job_listing() {
		return 'publish' === get_post_status( $this->form->get_job_id() ) && self::is_renew_action() && self::job_can_be_renewed( $this->form->get_job_id() );
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

	/**
	 * Checks if the WC Paid Listings has the minimum version when installed and activated.
	 *
	 * @since 1.41.0
	 *
	 * @return bool
	 */
	public static function is_wcpl_renew_compatible() {
		return ! class_exists( 'WC_Paid_Listings' ) || version_compare( JOB_MANAGER_WCPL_VERSION, '2.9.9', '>' );
	}

	/**
	 * Checks if the Simple Paid Listings has the minimum version when installed and activated.
	 *
	 * @since 1.41.0
	 *
	 * @return bool
	 */
	public static function is_spl_renew_compatible() {
		return ! class_exists( 'WP_Job_Manager_Simple_Paid_Listings' ) || version_compare( JOB_MANAGER_SPL_VERSION, '1.4.4', '>' );
	}

	/**
	 * Renew a job listing.
	 *
	 * @param WP_Post $job The job to renew.
	 */
	public static function renew_job_listing( $job ) {
		$old_expiry = date_create_immutable_from_format( 'Y-m-d', get_post_meta( $job->ID, '_job_expires', true ) );
		$new_expiry = calculate_job_expiry( $job->ID, false, $old_expiry );

		/**
		 * Filters the expiry date after a renewal.
		 *
		 * @param string $new_expiry The new expiry date (Y-m-d).
		 * @param WP_Post $job       The job that is being renewed.
		 */
		$new_expiry = apply_filters( 'job_manager_renewal_expiry_date', $new_expiry, $job );

		update_post_meta( $job->ID, '_job_expires', $new_expiry );

		/**
		 * Fires when a job listing status is about to be updated.
		 *
		 * @param int  $job_id The job ID.
		 * @param bool $renewing Whether the job is being renewed.
		 */
		$post_status = apply_filters( 'submit_job_post_status', 'publish', $job, true );

		$update_job                  = [];
		$update_job['ID']            = $job->ID;
		$update_job['post_status']   = $post_status;
		$update_job['post_date']     = current_time( 'mysql' );
		$update_job['post_date_gmt'] = current_time( 'mysql', 1 );

		wp_update_post( $update_job );
	}

	/**
	 * Checks if the job expiry can be extended.
	 * This is true if the job is public, the option to extend is set and the job expires within 5 days.
	 *
	 * @since 1.41.0
	 *
	 * @param int|WP_Post $job The job or job ID.
	 *
	 * @return bool
	 */
	public static function job_can_be_renewed( $job ) {
		$job        = get_post( $job );
		$expiration = WP_Job_Manager_Post_Types::instance()->get_job_expiration( $job );

		// If there is no expiration, then renewal is not necessary.
		if ( ! $expiration ) {
			return false;
		} else {
			$expiry = $expiration->getTimestamp();
		}

		$expiring_soon_days = get_option( 'job_manager_renewal_days', 5 );
		$current_time_stamp = current_datetime()->getTimestamp();
		$status             = get_post_status( $job );

		$can_be_renewed = 'publish' === $status && $expiry - $current_time_stamp < $expiring_soon_days * DAY_IN_SECONDS;

		/**
		 * Filters whether a job can be renewed.
		 *
		 * @param boolean $can_be_renewed Whether the job can be renewed.
		 * @param WP_Post $job            The job.
		 */
		return apply_filters( 'job_manager_job_can_be_renewed', $can_be_renewed, $job );
	}
}
