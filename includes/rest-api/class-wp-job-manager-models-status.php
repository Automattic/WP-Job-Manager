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
	public static function declare_fields() {
		$env = self::get_environment();
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
	public static function permissions_check( $request, $action ) {
		if ( in_array( $action, array( 'index', 'show' ), true ) ) {
			return true;
		}
		return current_user_can( 'manage_options' );
	}
}

