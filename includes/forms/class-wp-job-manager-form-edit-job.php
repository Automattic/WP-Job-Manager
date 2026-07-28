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
		add_action( 'wp', [ $this, 'submit_handler' ] );
		add_action( 'submit_job_form_start', [ $this, 'output_submit_form_nonce_field' ] );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Check happens later when possible.
		$this->job_id = ! empty( $_REQUEST['job_id'] ) ? absint( $_REQUEST['job_id'] ) : 0;

		if ( ! job_manager_user_can_edit_job( $this->job_id ) ) {
			$this->job_id = 0;
		}

		if ( ! empty( $this->job_id ) ) {
			if ( ! WP_Job_Manager_Post_Types::job_is_editable( $this->job_id ) ) {
				$this->job_id = 0;
			}
		}

		// Reuse the originating `form_id` saved at submission so the edit form
		// keeps the same field set even when the dashboard shortcode doesn't
		// forward one. POST round-trip in `submit_handler()` still wins over
		// this when present, so the user's chosen form id isn't downgraded.
		if ( ! empty( $this->job_id ) ) {
			$stored_form_id = get_post_meta( $this->job_id, '_form_id', true );
			if (
				is_string( $stored_form_id )
				&& strlen( $stored_form_id ) <= 32
				&& preg_match( '/\A[A-Za-z0-9_-]+\z/', $stored_form_id )
			) {
				$this->current_form_id = $stored_form_id;
			}
		}
	}

	/**
	 * Output function.
	 *
	 * @param array $atts
	 */
	public function output( $atts = [] ) {
		if ( ! empty( $this->save_message ) ) {
			echo '<div class="job-manager-message">' . wp_kses_post( $this->save_message ) . '</div>';
		}
		if ( ! empty( $this->save_error ) ) {
			echo '<div class="job-manager-error">' . wp_kses_post( $this->save_error ) . '</div>';
		}
		if ( isset( $atts['form_id'] ) ) {
			$this->current_form_id = sanitize_text_field( $atts['form_id'] );
		}
		$this->submit();
	}

	/**
	 * Submit Step
	 *
	 * @param array $atts Shortcode attributes forwarded from the shortcode handler.
	 */
	public function submit( $atts = [] ) {
		if ( isset( $atts['form_id'] ) ) {
			$this->current_form_id = sanitize_text_field( $atts['form_id'] );
		}

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
						$this->fields[ $group_key ][ $key ]['value'] = wp_get_object_terms( $job->ID, $field['taxonomy'], [ 'fields' => 'ids' ] );

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
			[
				'form'               => $this->form_name,
				'form_id'            => $this->current_form_id,
				'job_id'             => $this->get_job_id(),
				'action'             => $this->get_action(),
				'job_fields'         => $this->get_fields( 'job' ),
				'company_fields'     => $this->get_fields( 'company' ),
				'step'               => $this->get_step(),
				'submit_button_text' => $save_button_text,
			]
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

		// Round-trip the active form id so the field set used to validate matches
		// the one the edit form was rendered with. Only accept a short slug; anything
		// else is discarded to keep the value out of the `submit_job_form_fields` filter.
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce checked below; value is used only to switch field sets.
		if ( ! empty( $_POST['form_id'] ) ) {
			$candidate             = sanitize_text_field( wp_unslash( $_POST['form_id'] ) );
			$this->current_form_id = ( strlen( $candidate ) <= 32 && preg_match( '/\A[A-Za-z0-9_-]+\z/', $candidate ) ) ? $candidate : '';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// The edit form only ever updates an existing, editable listing. A zero job_id means no
		// listing was supplied or the current user is not authorized to edit the requested one
		// (the constructor resets it to 0 via job_manager_user_can_edit_job()). Bail here rather
		// than falling through to save_job(), which would insert a brand-new listing — that path
		// would let an unauthorized (including logged-out) request create a listing, bypassing the
		// account-required submission gate enforced by the submit-job form.
		if ( empty( $this->job_id ) ) {
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

			// Mirror the same update/delete handling used in the submit handler so
			// an edit on the default `[submit_job_form]` page clears a stale
			// `_form_id` from an earlier `[submit_job_form form_id="..."]` save.
			if ( $this->current_form_id ) {
				update_post_meta( $this->job_id, '_form_id', $this->current_form_id );
			} else {
				delete_post_meta( $this->job_id, '_form_id' );
			}

			if ( in_array( $post_status, [ 'future', 'publish' ], true ) ) {
				$save_message = $save_message . ' <a href="' . get_permalink( $this->job_id ) . '">' . __( 'View &rarr;', 'wp-job-manager' ) . '</a>';
			} elseif ( in_array( $original_post_status, [ 'future', 'publish' ], true ) && 'pending' === $post_status ) {
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
