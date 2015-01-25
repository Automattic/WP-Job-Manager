<?php

include_once( 'class-wp-job-manager-form-submit-job.php' );

/**
 * WP_Job_Manager_Form_Edit_Job class.
 */
class WP_Job_Manager_Form_Edit_Job extends WP_Job_Manager_Form_Submit_Job {

	public static $form_name = 'edit-job';

	/**
	 * Constructor
	 */
	public static function init() {
		self::$job_id = ! empty( $_REQUEST['job_id'] ) ? absint( $_REQUEST[ 'job_id' ] ) : 0;

		if  ( ! job_manager_user_can_edit_job( self::$job_id ) ) {
			self::$job_id = 0;
		}
	}

	/**
	 * output function.
	 */
	public static function output( $atts = array() ) {
		self::submit_handler();
		self::submit();
	}

	/**
	 * Submit Step
	 */
	public static function submit() {
		$job = get_post( self::$job_id );

		if ( empty( self::$job_id  ) || ( $job->post_status !== 'publish' && ! job_manager_user_can_edit_pending_submissions() ) ) {
			echo wpautop( __( 'Invalid listing', 'wp-job-manager' ) );
			return;
		}

		self::init_fields();

		foreach ( self::$fields as $group_key => $group_fields ) {
			foreach ( $group_fields as $key => $field ) {
				if ( ! isset( self::$fields[ $group_key ][ $key ]['value'] ) ) {
					if ( 'job_title' === $key ) {
						self::$fields[ $group_key ][ $key ]['value'] = $job->post_title;

					} elseif ( 'job_description' === $key ) {
						self::$fields[ $group_key ][ $key ]['value'] = $job->post_content;

					} elseif ( ! empty( $field['taxonomy'] ) ) {
						self::$fields[ $group_key ][ $key ]['value'] = wp_get_object_terms( $job->ID, $field['taxonomy'], array( 'fields' => 'ids' ) );

					} else {
						self::$fields[ $group_key ][ $key ]['value'] = get_post_meta( $job->ID, '_' . $key, true );
					}
				}
			}
		}

		self::$fields = apply_filters( 'submit_job_form_fields_get_job_data', self::$fields, $job );

		wp_enqueue_script( 'wp-job-manager-job-submission' );

		get_job_manager_template( 'job-submit.php', array(
			'form'               => self::$form_name,
			'job_id'             => self::get_job_id(),
			'action'             => self::get_action(),
			'job_fields'         => self::get_fields( 'job' ),
			'company_fields'     => self::get_fields( 'company' ),
			'step'               => self::get_step(),
			'submit_button_text' => __( 'Save changes', 'wp-job-manager' )
			) );
	}

	/**
	 * Submit Step is posted
	 */
	public static function submit_handler() {
		if ( empty( $_POST['submit_job'] ) ) {
			return;
		}

		try {

			// Get posted values
			$values = self::get_posted_fields();

			// Validate required
			if ( is_wp_error( ( $return = self::validate_fields( $values ) ) ) ) {
				throw new Exception( $return->get_error_message() );
			}

			// Update the job
			self::save_job( $values['job']['job_title'], $values['job']['job_description'], '', $values, false );
			self::update_job_data( $values );

			// Successful
			echo '<div class="job-manager-message">' . __( 'Your changes have been saved.', 'wp-job-manager' ), ' <a href="' . get_permalink( self::$job_id ) . '">' . __( 'View &rarr;', 'wp-job-manager' ) . '</a>' . '</div>';

		} catch ( Exception $e ) {
			echo '<div class="job-manager-error">' . $e->getMessage() . '</div>';
			return;
		}
	}
}