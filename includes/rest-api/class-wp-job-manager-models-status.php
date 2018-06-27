<?php
/**
 * Declaration of our Status Model
 *
 * @package WPJM/REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_Models_Status
 */
class WP_Job_Manager_Models_Status extends WP_Job_Manager_REST_Model
	implements WP_Job_Manager_REST_Interfaces_Permissions_Provider {


	/**
	 * Declare our fields
	 *
	 * @return array
	 * @throws WP_Job_Manager_REST_Exception Exc.
	 */
	public function declare_fields() {
		$env = $this->get_environment();
		return array(
			$env->field( 'run_page_setup', 'Should we run page setup' )
				->with_type( $env->type( 'boolean' ) ),
		);
	}

	/**
	 * Handle Permissions for a REST Controller Action
	 *
	 * @param  WP_REST_Request $request The request.
	 * @param  string          $action  The action (e.g. index, create update etc).
	 * @return bool
	 */
	public function permissions_check( $request, $action ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( in_array( $action, array( 'index', 'show' ), true ) ) {
			return current_user_can( 'manage_job_listings' );
		}
		return current_user_can( 'manage_options' );
	}
}

