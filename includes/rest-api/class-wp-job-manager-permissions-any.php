<?php
/**
 * Declaration of permissive Permissions
 *
 * @package WPJM/REST
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_Job_Manager_Permissions_Any
 */
class WP_Job_Manager_Permissions_Any implements WP_Job_Manager_REST_Interfaces_Permissions_Provider {

	/**
	 * Handle Permissions for a REST Controller Action
	 *
	 * @param  WP_REST_Request $request The request.
	 * @param  string          $action  The action (e.g. index, create update etc).
	 * @return bool
	 */
	public function permissions_check( $request, $action ) {
		return true;
	}
}

