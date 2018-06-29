<?php
/**
 * Declaration of Job Types Custom Fields Model
 *
 * @package WPJM/REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_Models_Job_Types_Custom_Fields
 */
class WP_Job_Manager_Models_Job_Types_Custom_Fields extends WP_Job_Manager_REST_Model
	implements WP_Job_Manager_REST_Interfaces_Model {

	/**
	 * Accepted employment types
	 *
	 * @var array
	 */
	private static $accepted_employment_types = array();

	/**
	 * Declare Fields
	 *
	 * @return array
	 * @throws WP_Job_Manager_REST_Exception Thrown during error while processing of request.
	 */
	public function declare_fields() {
		$env                             = $this->get_environment();
		$employment_types                = wpjm_job_listing_employment_type_options();
		self::$accepted_employment_types = array_keys( $employment_types );
		return array(
			$env->field( 'employment_type', esc_html__( 'Employment Type', 'wp-job-manager' ) )
				->with_kind( WP_Job_Manager_REST_Field_Declaration::META )
				->with_type( $env->type( 'string' ) )
				->with_choices( self::$accepted_employment_types ),
		);
	}

	/**
	 * Validate this
	 *
	 * @return bool|WP_Error
	 * @throws WP_Job_Manager_REST_Exception Thrown during error while processing of request.
	 */
	public function validate() {
		$employment_type = $this->get( 'employment_type' );
		if ( ! empty( $employment_type ) && ! in_array( $employment_type, self::$accepted_employment_types, true ) ) {
			return new WP_Error(
				'invalid_employment_type',
				esc_html__( 'Invalid Employment Type', 'wp-job-manager' ),
				array(
					'input'             => $employment_type,
					'acceptable_values' => self::$accepted_employment_types,
					'status'            => 400,
				)
			);
		}
		return parent::validate();
	}
}
