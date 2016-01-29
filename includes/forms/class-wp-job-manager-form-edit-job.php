<?php

include_once( 'class-wp-job-manager-form-submit-job.php' );

/**
 * WP_Job_Manager_Form_Edit_Job class.
 */
class WP_Job_Manager_Form_Edit_Job extends WP_Job_Manager_Form_Submit_Job {

	public $form_name           = 'edit-job';

	/** @var WP_Job_Manager_Form_Edit_Job The single instance of the class */
	protected static $_instance = null;

	/**
	 * Main Instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->job_id = ! empty( $_REQUEST['job_id'] ) ? absint( $_REQUEST[ 'job_id' ] ) : 0;

		if  ( ! job_manager_user_can_edit_job( $this->job_id ) ) {
			$this->job_id = 0;
		}
	}

	/**
	 * output function.
	 */
	public function output( $atts = array() ) {
		$this->submit_handler();
		$this->submit();
	}

	/**
	 * Submit Step
	 */
	public function submit() {
		$job = get_post( $this->job_id );

		if ( empty( $this->job_id  ) || ( $job->post_status !== 'publish' && ! job_manager_user_can_edit_pending_submissions() ) ) {
			echo wpautop( __( 'Invalid listing', 'wp-job-manager' ) );
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

		wp_enqueue_script( 'wp-job-manager-job-submission' );

		get_job_manager_template( 'job-submit.php', array(
			'form'               => $this->form_name,
			'job_id'             => $this->get_job_id(),
			'action'             => $this->get_action(),
			'job_fields'         => $this->get_fields( 'job' ),
			'company_fields'     => $this->get_fields( 'company' ),
			'step'               => $this->get_step(),
			'submit_button_text' => __( 'Save changes', 'wp-job-manager' )
			) );
	}

	/**
	 * Submit Step is posted
	 */
	public function submit_handler() {
		if ( empty( $_POST['submit_job'] ) ) {
			return;
		}

		try {

			// Get posted values
			$values = $this->get_posted_fields();

			// Validate required
			if ( is_wp_error( ( $return = $this->validate_fields( $values ) ) ) ) {
				throw new Exception( $return->get_error_message() );
			}

			// Update the job
			$this->save_job( $values['job']['job_title'], $values['job']['job_description'], '', $values, false );
			$this->update_job_data( $values );

			// Successful
			switch ( get_post_status( $this->job_id ) ) {
				case 'publish' :
					echo '<div class="job-manager-message">' . __( 'Your changes have been saved.', 'wp-job-manager' ) . ' <a href="' . get_permalink( $this->job_id ) . '">' . __( 'View &rarr;', 'wp-job-manager' ) . '</a>' . '</div>';
				break;
				default :
					echo '<div class="job-manager-message">' . __( 'Your changes have been saved.', 'wp-job-manager' ) . '</div>';
				break;
			}

		} catch ( Exception $e ) {
			echo '<div class="job-manager-error">' . $e->getMessage() . '</div>';
			return;
		}
	}
}
