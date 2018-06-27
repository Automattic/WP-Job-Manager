<?php
/**
 * Declaration of our Job Listings Custom Fields Model
 *
 * @package WPJM/REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_Models_Job_Listings_Custom_Fields
 */
class WP_Job_Manager_Models_Job_Listings_Custom_Fields extends WP_Job_Manager_REST_Model {

	/**
	 * Declare Fields
	 *
	 * @return array
	 * @throws WP_Job_Manager_REST_Exception Exc.
	 */
	public function declare_fields() {
		$env          = $this->get_environment();
		$current_user = wp_get_current_user();

		$declarations = array(
			$env->field( '_job_location', __( 'Leave this blank if the location is not important.', 'wp-job-manager' ) )
				->with_kind( WP_Job_Manager_REST_Field_Declaration::META )
				->with_type( $env->type( 'string' ) ),

			$env->field( '_application', __( 'Application Email or URL', 'wp-job-manager' ) )
				->with_kind( WP_Job_Manager_REST_Field_Declaration::META )
				->with_default( $current_user->user_email )
				->with_type( $env->type( 'string' ) ),

			$env->field( '_company_name', __( 'Company Name', 'wp-job-manager' ) )
				->with_kind( WP_Job_Manager_REST_Field_Declaration::META )
				->with_type( $env->type( 'string' ) ),

			$env->field( '_company_website', __( 'Company Website', 'wp-job-manager' ) )
				->with_kind( WP_Job_Manager_REST_Field_Declaration::META )
				->with_type( $env->type( 'string' ) ),

			$env->field( '_company_tagline', __( 'Company Tagline', 'wp-job-manager' ) )
				->with_kind( WP_Job_Manager_REST_Field_Declaration::META )
				->with_type( $env->type( 'string' ) ),

			$env->field( '_company_twitter', __( 'Company Twitter', 'wp-job-manager' ) )
				->with_kind( WP_Job_Manager_REST_Field_Declaration::META )
				->with_type( $env->type( 'string' ) ),

			$env->field( '_company_video', __( 'Company Video', 'wp-job-manager' ) )
				->with_kind( WP_Job_Manager_REST_Field_Declaration::META )
				->with_type( $env->type( 'string' ) ),

			$env->field( '_filled', __( 'Position Filled', 'wp-job-manager' ) )
				->with_kind( WP_Job_Manager_REST_Field_Declaration::META )
				->with_type( $env->type( 'boolean' ) ),
		);

		// These caps are more related to updating.
		if ( $current_user->has_cap( 'manage_job_listings' ) ) {
			$declarations[] = $env->field( '_featured', __( 'Featured Listing', 'wp-job-manager' ) )
				->with_kind( WP_Job_Manager_REST_Field_Declaration::META )
				->with_type( $env->type( 'boolean' ) );

			$declarations[] = $env->field( '_job_expires', __( 'Listing Expiry Date', 'wp-job-manager' ) )
				->with_type( $env->type( 'string' ) )
				->with_kind( WP_Job_Manager_REST_Field_Declaration::META )
				->with_before_get( 'format_job_expires' );
		}

		if ( $current_user->has_cap( 'edit_others_job_listings' ) ) {
			$declarations[] = $env->field( '_job_author', __( 'Posted by', 'wp-job-manager' ) )
				->with_type( $env->type( 'string' ) )
				->with_kind( WP_Job_Manager_REST_Field_Declaration::META );
		}

		/**
		 * Exposed so extension developers can add their custom fields.
		 *
		 * If the fields map to meta, you can follow the
		 * Example of the above declarations to shape s your own (remember to add any validation or formatting callbacks).
		 * For more constom things, you might want to check out Derived fields.
		 *
		 * @param array                           $declarations The Declarations so far.
		 * @param WP_Job_Manager_REST_Environment $env Environment.
		 *
		 * @return array
		 */
		return (array) apply_filters( 'wpjm_rest_api_job_listings_declare_fields', $declarations, $env );
	}

	/**
	 * Format the date of Job_Expires
	 *
	 * @param mixed $value Value.
	 * @return bool|string
	 */
	public function format_job_expires( $value ) {
		return ! empty( $value ) ? date( 'Y-m-d H:i:s', strtotime( $value ) ) : '';
	}
}
