<?php
/**
 * File containing the class WP_Job_Manager_Form_Edit_Job.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'class-wp-job-manager-form-submit-job.php';

/**
 * Handles the editing of Job Listings from the public facing frontend (from within `[job_dashboard]` shortcode).
 *
 * @since 1.0.0
 * @extends WP_Job_Manager_Form_Submit_Job
 */
class WP_Job_Manager_Form_Edit_Job extends WP_Job_Manager_Form_Submit_Job {

	/**
	 * Form name
	 *
	 * @var string
	 */
	public $form_name = 'edit-job';

	/**
	 * Messaged shown on save.
	 *
	 * @var bool|string
	 */
	private $save_message = false;

	/**
	 * Message shown on error.
	 *
	 * @var bool|string
	 */
	private $save_error = false;

	/**
	 * Instance
	 *
	 * @access protected
	 * @var WP_Job_Manager_Form_Edit_Job The single instance of the class
	 */
	protected static $instance = null;

	/**
	 * Main Instance
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'submit_handler' ) );
		add_action( 'submit_job_form_start', array( $this, 'output_submit_form_nonce_field' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Check happens later when possible.
		$this->job_id = ! empty( $_REQUEST['job_id'] ) ? absint( $_REQUEST['job_id'] ) : 0;

		if ( ! job_manager_user_can_edit_job( $this->job_id ) ) {
			$this->job_id = 0;
		}

		if ( ! empty( $this->job_id ) ) {
			$post_status = get_post_status( $this->job_id );
			if (
				( 'publish' === $post_status && ! wpjm_user_can_edit_published_submissions() )
				|| ( 'publish' !== $post_status && ! job_manager_user_can_edit_pending_submissions() )
			) {
				$this->job_id = 0;
			}
		}
	}

	/**
	 * Output function.
	 *
	 * @param array $atts
	 */
	public function output( $atts = array() ) {
		if ( ! empty( $this->save_message ) ) {
			echo '<div class="job-manager-message">' . wp_kses_post( $this->save_message ) . '</div>';
		}
		if ( ! empty( $this->save_error ) ) {
			echo '<div class="job-manager-error">' . wp_kses_post( $this->save_error ) . '</div>';
		}
		$this->submit();
	}

	/**
	 * Submit Step
	 */
	public function submit() {
		$job = get_post( $this->job_id );

		if ( empty( $this->job_id ) ) {
			echo wp_kses_post( wpautop( __( 'Invalid listing', 'wp-job-manager' ) ) );
			return;
		}

		$this->init_fields();

		foreach ( $this->fields as $group_key => $group_fields ) {
			foreach ( $group_fields as $key => $field ) {
				if ( ! isset( $this->fields[ $group_key ][ $key ]['value'] ) ) {
					if ( 'job_title' === $key ) {
						$this->fields[ $group_key ][ $key ]['value'] = $job->post_title;

					} elseif ( 'job_description' === $key ) {
						$this->fields[ $group_key ][ $key ]['value'] = $job->post_content;

					} elseif ( 'company_logo' === $key ) {
						$this->fields[ $group_key ][ $key ]['value'] = has_post_thumbnail( $job->ID ) ? get_post_thumbnail_id( $job->ID ) : get_post_meta( $job->ID, '_' . $key, true );

					} elseif ( ! empty( $field['taxonomy'] ) ) {
						$this->fields[ $group_key ][ $key ]['value'] = wp_get_object_terms( $job->ID, $field['taxonomy'], array( 'fields' => 'ids' ) );

					} else {
						$this->fields[ $group_key ][ $key ]['value'] = get_post_meta( $job->ID, '_' . $key, true );
					}
				}
			}
		}

		$this->fields = apply_filters( 'submit_job_form_fields_get_job_data', $this->fields, $job );

		$this->enqueue_job_form_assets();

		$save_button_text = __( 'Save changes', 'wp-job-manager' );
		if (
			'publish' === get_post_status( $this->job_id )
			&& wpjm_published_submission_edits_require_moderation()
		) {
			$save_button_text = __( 'Submit changes for approval', 'wp-job-manager' );
		}

		$save_button_text = apply_filters( 'update_job_form_submit_button_text', $save_button_text );

		get_job_manager_template(
			'job-submit.php',
			array(
				'form'               => $this->form_name,
				'job_id'             => $this->get_job_id(),
				'action'             => $this->get_action(),
				'job_fields'         => $this->get_fields( 'job' ),
				'company_fields'     => $this->get_fields( 'company' ),
				'step'               => $this->get_step(),
				'submit_button_text' => $save_button_text,
			)
		);
	}

	/**
	 * Submit Step is posted.
	 *
	 * @throws Exception When invalid fields are submitted.
	 */
	public function submit_handler() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Check happens later when possible.
		if ( empty( $_POST['submit_job'] ) ) {
			return;
		}

		$this->check_submit_form_nonce_field();

		try {

			// Get posted values.
			$values = $this->get_posted_fields();

			// Validate required.
			$validation_result = $this->validate_fields( $values );
			if ( is_wp_error( $validation_result ) ) {
				throw new Exception( $validation_result->get_error_message() );
			}

			$save_post_status = '';
			if ( wpjm_published_submission_edits_require_moderation() ) {
				$save_post_status = 'pending';
			}
			$original_post_status = get_post_status( $this->job_id );

			// Update the job.
			$this->save_job( $values['job']['job_title'], $values['job']['job_description'], $save_post_status, $values, false );
			$this->update_job_data( $values );

			// Successful.
			$save_message = __( 'Your changes have been saved.', 'wp-job-manager' );
			$post_status  = get_post_status( $this->job_id );

			update_post_meta( $this->job_id, '_job_edited', time() );

			if ( 'publish' === $post_status ) {
				$save_message = $save_message . ' <a href="' . get_permalink( $this->job_id ) . '">' . __( 'View &rarr;', 'wp-job-manager' ) . '</a>';
			} elseif ( 'publish' === $original_post_status && 'pending' === $post_status ) {
				$save_message = __( 'Your changes have been submitted and your listing will be visible again once approved.', 'wp-job-manager' );

				/**
				 * Resets the job expiration date when a user submits their job listing edit for approval.
				 * Defaults to `false`.
				 *
				 * @since 1.29.0
				 *
				 * @param bool $reset_expiration If true, reset expiration date.
				 */
				if ( apply_filters( 'job_manager_reset_listing_expiration_on_user_edit', false ) ) {
					delete_post_meta( $this->job_id, '_job_expires' );
				}
			}

			/**
			 * Fire action after the user edits a job listing.
			 *
			 * @since 1.30.0
			 *
			 * @param int    $job_id        Job ID.
			 * @param string $save_message  Save message to filter.
			 * @param array  $values        Submitted values for job listing.
			 */
			do_action( 'job_manager_user_edit_job_listing', $this->job_id, $save_message, $values );

			/**
			 * Change the message that appears when a user edits a job listing.
			 *
			 * @since 1.29.0
			 *
			 * @param string $save_message  Save message to filter.
			 * @param int    $job_id        Job ID.
			 * @param array  $values        Submitted values for job listing.
			 */
			$this->save_message = apply_filters( 'job_manager_update_job_listings_message', $save_message, $this->job_id, $values );

		} catch ( Exception $e ) {
			$this->save_error = $e->getMessage();
		}
	}
}
